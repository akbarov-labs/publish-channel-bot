"""Commands for importing a channel's existing posts into the blog."""

from __future__ import annotations

import logging

from aiogram import Bot, F, Router
from aiogram.exceptions import TelegramAPIError
from aiogram.filters import Command
from aiogram.types import Message

from app.db import repo
from app.db.models import Channel
from app.db.session import session_scope
from app.services import backfill

log = logging.getLogger(__name__)
router = Router(name="importing")

NO_CHANNEL = (
    "No channel is linked yet.\n\n"
    "Add me to your channel as an admin with <i>Post messages</i>, "
    "then come back and run /import."
)

PROTECTED = (
    "This channel has <b>content protection</b> turned on, which blocks "
    "forwarding — and forwarding is the only way a bot can read past posts.\n\n"
    "Turn protection off in the channel settings and run /import again, or "
    "export the channel from Telegram Desktop and we can load the file instead."
)


async def _start_for_channel(
    bot: Bot, channel_id: int, chat_id: int, staging_chat_id: int
) -> str:
    """Probe the channel's newest message id and queue the import."""
    try:
        chat = await bot.get_chat(chat_id)
    except TelegramAPIError as exc:
        return f"I cannot read that channel any more: <code>{exc}</code>"

    if getattr(chat, "has_protected_content", False):
        return PROTECTED

    try:
        latest_id = await backfill.probe_latest_message_id(bot, chat_id)
    except TelegramAPIError as exc:
        return (
            "I could not post in the channel, so I cannot tell how far back its "
            f"history goes: <code>{exc}</code>\n\nGive me <i>Post messages</i> rights and try again."
        )

    async with session_scope() as session:
        channel = await session.get(Channel, channel_id)
        job = await backfill.create_job(session, channel, staging_chat_id, latest_id)
        job_id = job.id

    backfill.start(bot, job_id)
    log.info("import job %s started for channel %s (%s messages)", job_id, chat_id, latest_id)

    return (
        f"Starting the import of <b>{latest_id}</b> message slots.\n\n"
        "Posts keep their original publication date. You will see messages "
        "appear and disappear here while I read them — that is how a bot gets "
        "at channel history, and the copies are deleted as I go.\n\n"
        "/import_status for progress, /cancel_import to stop."
    )


@router.message(Command("import"), F.chat.type == "private")
async def on_import(message: Message, bot: Bot) -> None:
    async with session_scope() as session:
        channels = await repo.channels_for_user(session, message.from_user.id)
        if not channels:
            await message.answer(NO_CHANNEL)
            return
        if len(channels) > 1:
            names = "\n".join(f"• {channel.title or channel.telegram_chat_id}" for channel in channels)
            await message.answer(
                f"You have several channels:\n{names}\n\n"
                "Send <code>/import</code> in the channel you want to import."
            )
            return

        channel = channels[0]
        if not channel.active:
            await message.answer("Syncing is paused in that channel. Send /activate here to turn it back on.")
            return
        running = await repo.unfinished_import(session, channel.id)
        if running is not None:
            await message.answer("An import is already running. /import_status for progress.")
            return
        channel_id, chat_id = channel.id, channel.telegram_chat_id

    note = await _start_for_channel(bot, channel_id, chat_id, message.from_user.id)
    await message.answer(note)


@router.message(Command("import_status"), F.chat.type == "private")
async def on_import_status(message: Message) -> None:
    async with session_scope() as session:
        channels = await repo.channels_for_user(session, message.from_user.id)
        if not channels:
            await message.answer(NO_CHANNEL)
            return

        lines: list[str] = []
        for channel in channels:
            job = await repo.latest_import(session, channel.id)
            name = channel.title or str(channel.telegram_chat_id)
            if job is None:
                lines.append(f"<b>{name}</b> — never imported. Run /import.")
                continue
            lines.append(
                f"<b>{name}</b> — {job.status}, {job.progress_percent}%\n"
                f"  published {job.imported}, skipped {job.skipped}, failed {job.failed}"
            )
    await message.answer("\n".join(lines))


@router.message(Command("cancel_import"), F.chat.type == "private")
async def on_cancel_import(message: Message) -> None:
    cancelled = 0
    async with session_scope() as session:
        for channel in await repo.channels_for_user(session, message.from_user.id):
            job = await repo.unfinished_import(session, channel.id)
            if job is not None:
                job.status = "cancelled"
                cancelled += 1

    if cancelled:
        await message.answer(
            "Stopping. Posts already imported stay on the blog — running /import "
            "again resumes from where this one stopped."
        )
    else:
        await message.answer("Nothing is importing right now.")


@router.channel_post(F.text == "/import")
async def on_channel_import(message: Message, bot: Bot) -> None:
    """Same thing, started from inside the channel."""
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None:
            return
        if not channel.active:
            await bot.send_message(message.chat.id, "Syncing is paused here. Send /activate first.")
            return
        author = await repo.author_for_channel(session, channel)
        if author is None:
            return
        if await repo.unfinished_import(session, channel.id) is not None:
            await bot.send_message(message.chat.id, "An import is already running.")
            return
        channel_id, chat_id, staging = channel.id, channel.telegram_chat_id, author.telegram_user_id

    note = await _start_for_channel(bot, channel_id, chat_id, staging)
    try:
        await bot.send_message(staging, note)
    except TelegramAPIError:
        # The author has never opened a chat with the bot, so say it in-channel.
        await bot.send_message(chat_id, "Open a chat with me and send /start so I can report progress.")
