"""Importing a channel's existing history into the blog.

The Bot API has no method for reading past messages: a bot sees only what is
sent after it joins. What it *can* do is forward any message id from a chat
where it is an admin, and a forwarded copy carries ``forward_origin`` — which
holds the message's real publication date and its id in the source channel.

So the import walks the id range, forwards each message to the author's own
chat, reads the copy, publishes it with its original date, and deletes the
copy again. Ids that no longer exist (deleted posts, service messages) simply
fail to forward and are counted as skipped.
"""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass
from datetime import datetime, timezone

from aiogram import Bot
from aiogram.exceptions import TelegramAPIError, TelegramBadRequest, TelegramRetryAfter
from aiogram.types import Message
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import get_settings
from app.db.models import Channel, ImportJob
from app.db.session import session_scope
from app.services.site_client import SiteError
from app.services.sync import channel_post_to_site, recently_sent

log = logging.getLogger(__name__)

# Telegram albums hold at most 10 items.
MAX_ALBUM_SIZE = 10

# Import tasks currently in flight, keyed by job id.
_running: dict[int, asyncio.Task] = {}


@dataclass(slots=True)
class HistoricalMessage:
    """A forwarded copy, paired with the identity of the original post."""

    original_id: int
    date: datetime
    copy: Message


class ImportError_(RuntimeError):
    """The import could not start."""


def _is_media(message: Message) -> bool:
    return bool(message.photo or message.video or message.document or message.animation)


def _same_album(first: HistoricalMessage, second: HistoricalMessage) -> bool:
    """Album members keep consecutive ids and share a publication timestamp.

    Forwarding one member of an album strips its media_group_id, so when that
    is missing the id/date pair is what is left to group on.
    """
    if first.copy.media_group_id and second.copy.media_group_id:
        return first.copy.media_group_id == second.copy.media_group_id
    return (
        second.original_id == first.original_id + 1
        and first.date == second.date
        and _is_media(first.copy)
        and _is_media(second.copy)
    )


async def probe_latest_message_id(bot: Bot, chat_id: int) -> int:
    """Find the channel's newest message id by posting a throwaway message.

    Telegram hands back the id it was assigned, which is the current high water
    mark. The message is silent and is deleted immediately.
    """
    probe = await bot.send_message(chat_id, "⏳", disable_notification=True)
    # Registered before deletion so the bot does not treat its own probe as a
    # channel post and publish it.
    recently_sent.add(chat_id, probe.message_id)
    try:
        await bot.delete_message(chat_id, probe.message_id)
    except TelegramAPIError as exc:
        log.warning("could not delete the probe message in %s: %s", chat_id, exc)
    return probe.message_id


async def _forward_one(
    bot: Bot, staging_chat_id: int, channel_chat_id: int, message_id: int
) -> HistoricalMessage | None:
    """Forward one historical message, or return None if it is not there."""
    for attempt in range(3):
        try:
            copy = await bot.forward_message(
                chat_id=staging_chat_id,
                from_chat_id=channel_chat_id,
                message_id=message_id,
                disable_notification=True,
            )
        except TelegramRetryAfter as exc:
            log.info("flood control: waiting %ss", exc.retry_after)
            await asyncio.sleep(exc.retry_after + 1)
            continue
        except TelegramBadRequest:
            # Deleted, never existed, or a service message - nothing to import.
            return None
        except TelegramAPIError as exc:
            log.warning("forwarding %s failed on attempt %s: %s", message_id, attempt + 1, exc)
            await asyncio.sleep(2 * (attempt + 1))
            continue

        origin = copy.forward_origin
        date = getattr(origin, "date", None) or copy.date or datetime.now(timezone.utc)
        original_id = getattr(origin, "message_id", None) or message_id
        return HistoricalMessage(original_id=original_id, date=date, copy=copy)

    return None


async def _discard(bot: Bot, staging_chat_id: int, items: list[HistoricalMessage]) -> None:
    """Clean the forwarded copies out of the author's chat."""
    for item in items:
        try:
            await bot.delete_message(staging_chat_id, item.copy.message_id)
        except TelegramAPIError:
            pass


async def create_job(session: AsyncSession, channel: Channel, staging_chat_id: int, latest_id: int) -> ImportJob:
    job = ImportJob(
        channel_id=channel.id,
        staging_chat_id=staging_chat_id,
        status="pending",
        first_message_id=1,
        last_message_id=latest_id,
        cursor=1,
    )
    session.add(job)
    await session.flush()
    return job


async def run_import(bot: Bot, job_id: int) -> None:
    """Walk the channel's history and publish every post it finds."""
    settings = get_settings()
    delay = 1.0 / max(settings.import_rate_per_second, 0.1)
    buffer: list[HistoricalMessage] = []
    progress_message_id: int | None = None

    async with session_scope() as session:
        job = await session.get(ImportJob, job_id)
        if job is None or job.status not in {"pending", "running"}:
            return
        job.status = "running"
        channel_id, staging_chat_id = job.channel_id, job.staging_chat_id
        first, last, cursor = job.first_message_id, job.last_message_id, job.cursor
        channel = await session.get(Channel, channel_id)
        if channel is None:
            return
        channel_chat_id = channel.telegram_chat_id

    async def flush() -> None:
        nonlocal buffer
        if not buffer:
            return
        group, buffer = buffer, []
        try:
            async with session_scope() as inner:
                channel = await inner.get(Channel, channel_id)
                link = await channel_post_to_site(
                    inner,
                    bot,
                    channel,
                    [item.copy for item in group],
                    origin_message_id=group[0].original_id,
                    origin_date=group[0].date,
                )
                imported = link is not None
            await _bump(job_id, imported=imported)
        except (SiteError, ValueError) as exc:
            log.error("importing message %s failed: %s", group[0].original_id, exc)
            await _bump(job_id, failed=True)
        finally:
            await _discard(bot, staging_chat_id, group)

    try:
        message_id = cursor
        processed = 0
        while message_id <= last:
            if processed % 10 == 0 and await _is_cancelled(job_id):
                await flush()
                log.info("import %s cancelled at %s", job_id, message_id)
                return

            item = await _forward_one(bot, staging_chat_id, channel_chat_id, message_id)

            if item is None:
                await flush()
                await _bump(job_id, skipped=True, cursor=message_id + 1)
            else:
                if buffer and not _same_album(buffer[-1], item):
                    await flush()
                buffer.append(item)
                if len(buffer) >= MAX_ALBUM_SIZE:
                    await flush()
                await _bump(job_id, cursor=message_id + 1)

            if (message_id - first) % settings.import_progress_every == 0:
                progress_message_id = await _report(bot, job_id, staging_chat_id, progress_message_id)

            await asyncio.sleep(delay)
            message_id += 1
            processed += 1

        await flush()
        await _finish(job_id, "done")
        await _report(bot, job_id, staging_chat_id, progress_message_id, final=True)
    except Exception as exc:  # noqa: BLE001 - record why the import stopped
        log.exception("import %s failed", job_id)
        await _finish(job_id, "failed", error=str(exc))
    finally:
        _running.pop(job_id, None)


async def _bump(
    job_id: int,
    *,
    imported: bool = False,
    skipped: bool = False,
    failed: bool = False,
    cursor: int | None = None,
) -> None:
    async with session_scope() as session:
        job = await session.get(ImportJob, job_id)
        if job is None:
            return
        if imported:
            job.imported += 1
        if skipped:
            job.skipped += 1
        if failed:
            job.failed += 1
        if cursor is not None:
            job.cursor = cursor


async def _is_cancelled(job_id: int) -> bool:
    async with session_scope() as session:
        job = await session.get(ImportJob, job_id)
        return job is None or job.status == "cancelled"


async def _finish(job_id: int, status: str, error: str | None = None) -> None:
    async with session_scope() as session:
        job = await session.get(ImportJob, job_id)
        if job is None or job.status == "cancelled":
            return
        job.status = status
        job.error = error
        job.finished_at = datetime.now(timezone.utc)


async def _report(
    bot: Bot, job_id: int, chat_id: int, message_id: int | None, final: bool = False
) -> int | None:
    """Keep a single progress message up to date rather than sending many."""
    async with session_scope() as session:
        job = await session.get(ImportJob, job_id)
        if job is None:
            return message_id
        if final:
            text = (
                f"✅ Import finished.\n\n"
                f"Published: <b>{job.imported}</b>\n"
                f"Skipped (deleted or not a post): {job.skipped}\n"
                f"Failed: {job.failed}"
            )
        else:
            text = (
                f"⏳ Importing channel history — {job.progress_percent}%\n"
                f"Published so far: <b>{job.imported}</b>\n"
                f"At message {job.cursor} of {job.last_message_id}\n\n"
                "Send /cancel_import to stop."
            )

    try:
        if message_id is None:
            sent = await bot.send_message(chat_id, text)
            return sent.message_id
        await bot.edit_message_text(chat_id=chat_id, message_id=message_id, text=text)
    except TelegramAPIError:
        pass
    return message_id


def start(bot: Bot, job_id: int) -> None:
    """Run an import in the background of the bot process."""
    if job_id in _running:
        return
    _running[job_id] = asyncio.create_task(run_import(bot, job_id))


def is_running(job_id: int) -> bool:
    return job_id in _running
