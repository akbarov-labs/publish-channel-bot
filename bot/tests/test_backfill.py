"""Importing a channel's back catalogue, with the original dates intact."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from typing import Any

import pytest
from aiogram.exceptions import TelegramBadRequest
from aiogram.methods import ForwardMessage

from app.db.models import ImportJob
from app.services import backfill, sync
from app.services.backfill import HistoricalMessage, _same_album, probe_latest_message_id, run_import
from tests.factories import StubMessage, StubOrigin, StubPhoto, StubSiteClient, make_channel

D1 = datetime(2023, 4, 1, 9, 0, tzinfo=timezone.utc)
D2 = datetime(2024, 6, 15, 18, 30, tzinfo=timezone.utc)
D3 = datetime(2025, 11, 2, 7, 45, tzinfo=timezone.utc)


@dataclass
class StubTelegram:
    """A channel whose history can only be reached by forwarding."""

    history: dict[int, StubMessage]
    forwarded: list[int] = field(default_factory=list)
    deleted: list[tuple[int, int]] = field(default_factory=list)
    sent: list[dict[str, Any]] = field(default_factory=list)
    protected: bool = False
    _next_staging_id: int = 9000

    async def forward_message(
        self, chat_id: int, from_chat_id: int, message_id: int, **kwargs: Any
    ) -> StubMessage:
        original = self.history.get(message_id)
        if original is None:
            raise TelegramBadRequest(
                method=ForwardMessage(chat_id=chat_id, from_chat_id=from_chat_id, message_id=message_id),
                message="message to forward not found",
            )
        self.forwarded.append(message_id)
        self._next_staging_id += 1
        # A forwarded copy has a new id in the staging chat; the original
        # identity and date survive only in forward_origin.
        return StubMessage(
            message_id=self._next_staging_id,
            text=original.text,
            caption=original.caption,
            html=original.html,
            photo=original.photo,
            date=datetime.now(timezone.utc),
            media_group_id=None,
            forward_origin=StubOrigin(date=original.date, message_id=message_id),
        )

    async def delete_message(self, chat_id: int, message_id: int) -> None:
        self.deleted.append((chat_id, message_id))

    async def send_message(self, chat_id: int, text: str, **kwargs: Any) -> StubMessage:
        self.sent.append({"chat_id": chat_id, "text": text})
        self._next_staging_id += 1
        return StubMessage(message_id=self._next_staging_id, text=text)

    async def edit_message_text(self, **kwargs: Any) -> None:
        return None

    async def download(self, file_id: str, destination: Any) -> None:
        destination.write(b"fake-bytes")


def _history() -> dict[int, StubMessage]:
    """Id 2 was deleted; ids 3 and 4 are an album posted in one go."""
    return {
        1: StubMessage(1, text="The oldest post", date=D1),
        3: StubMessage(3, caption="Trip photos", photo=[StubPhoto("a", "ua")], date=D2),
        4: StubMessage(4, photo=[StubPhoto("b", "ub")], date=D2),
        5: StubMessage(5, text="The newest post", date=D3),
    }


async def _seed(session, monkeypatch, *, cursor: int = 1, last: int = 5):
    channel = await make_channel(session)
    job = ImportJob(
        channel_id=channel.id,
        staging_chat_id=42,
        status="pending",
        first_message_id=1,
        last_message_id=last,
        cursor=cursor,
    )
    session.add(job)
    await session.commit()

    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda site: client)
    sync.recently_sent._seen.clear()
    return channel, job, client


async def test_old_posts_keep_their_real_publication_date(session, monkeypatch):
    _, job, client = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    dates = [created["published_at"] for created in client.created]
    assert dates == [D1.isoformat(), D2.isoformat(), D3.isoformat()], (
        "each post must carry the date it was published in the channel, "
        "not the date it was imported"
    )


async def test_posts_are_identified_by_their_original_message_id(session, monkeypatch):
    channel, job, client = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    chat = channel.telegram_chat_id
    assert [created["external_id"] for created in client.created] == [
        f"tg:{chat}:1",
        f"tg:{chat}:3",
        f"tg:{chat}:5",
    ], "the id of the forwarded copy must never leak into the external id"


async def test_an_album_in_the_history_becomes_one_carousel(session, monkeypatch):
    _, job, client = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    assert len(client.created) == 3, "ids 3 and 4 are one post, not two"
    gallery = client.created[1]
    assert gallery["post_type"] == "gallery"
    assert len(gallery["media"]) == 2


async def test_deleted_ids_are_skipped_rather_than_failing(session, monkeypatch):
    _, job, _ = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    await session.refresh(job)
    assert job.status == "done"
    assert job.imported == 3
    assert job.skipped == 1, "id 2 no longer exists in the channel"
    assert job.failed == 0


async def test_the_forwarded_copies_are_cleaned_up(session, monkeypatch):
    _, job, _ = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    # Every message that was forwarded is deleted again from the staging chat.
    deleted_in_staging = [message_id for chat_id, message_id in bot.deleted if chat_id == 42]
    assert len(deleted_in_staging) == len(bot.forwarded)


async def test_an_interrupted_import_resumes_from_its_cursor(session, monkeypatch):
    _, job, client = await _seed(session, monkeypatch, cursor=5)
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    assert len(client.created) == 1
    assert client.created[0]["title"] == "The newest post"
    assert bot.forwarded == [5], "already-imported ids must not be fetched again"


async def test_cancelling_stops_the_import(session, monkeypatch):
    _, job, client = await _seed(session, monkeypatch)
    job.status = "cancelled"
    await session.commit()
    bot = StubTelegram(history=_history())

    await run_import(bot, job.id)

    assert client.created == []
    assert bot.forwarded == []


async def test_reimporting_does_not_duplicate_posts(session, monkeypatch):
    channel, job, client = await _seed(session, monkeypatch)
    bot = StubTelegram(history=_history())
    await run_import(bot, job.id)

    second = ImportJob(
        channel_id=channel.id, staging_chat_id=42, status="pending",
        first_message_id=1, last_message_id=5, cursor=1,
    )
    session.add(second)
    await session.commit()
    sync.recently_sent._seen.clear()

    await run_import(bot, second.id)

    assert len(client.created) == 3, "the PostLink rows must stop a second pass republishing"


async def test_the_probe_message_is_not_published_as_a_post(session, monkeypatch):
    bot = StubTelegram(history={})
    sync.recently_sent._seen.clear()

    latest = await probe_latest_message_id(bot, -1001)

    assert sync.recently_sent.contains(-1001, latest), (
        "the bot must not treat its own probe as a channel post"
    )
    assert (-1001, latest) in bot.deleted


def test_consecutive_media_at_the_same_instant_is_one_album():
    first = HistoricalMessage(3, D2, StubMessage(90, photo=[StubPhoto()]))
    second = HistoricalMessage(4, D2, StubMessage(91, photo=[StubPhoto()]))
    assert _same_album(first, second)


def test_a_gap_in_ids_breaks_the_album():
    first = HistoricalMessage(3, D2, StubMessage(90, photo=[StubPhoto()]))
    second = HistoricalMessage(5, D2, StubMessage(91, photo=[StubPhoto()]))
    assert not _same_album(first, second)


def test_media_posted_at_different_times_is_not_an_album():
    first = HistoricalMessage(3, D2, StubMessage(90, photo=[StubPhoto()]))
    second = HistoricalMessage(4, D2 + timedelta(minutes=5), StubMessage(91, photo=[StubPhoto()]))
    assert not _same_album(first, second)


def test_text_posts_are_never_grouped():
    first = HistoricalMessage(3, D2, StubMessage(90, text="one"))
    second = HistoricalMessage(4, D2, StubMessage(91, text="two"))
    assert not _same_album(first, second)
