"""The echo-loop guard is the one thing that must never regress."""

from __future__ import annotations

from app.db.models import PostLink
from app.services import sync
from app.services.sync import channel_post_to_site, site_post_to_channel
from tests.factories import StubBot, StubMessage, make_channel

POST = {"id": "p-1", "title": "Hello", "html": "<p>body</p>", "url": "https://blog.test/p/1"}


async def test_site_post_reaches_the_channel_once(session):
    channel = await make_channel(session)
    bot = StubBot()

    first = await site_post_to_channel(session, bot, channel, POST)
    second = await site_post_to_channel(session, bot, channel, POST)

    assert first is not None and first.origin == "site"
    assert second is None, "the same blog post must not be published twice"
    assert len(bot.sent) == 1
    assert "https://blog.test/p/1" in bot.sent[0]["text"]


async def test_bot_does_not_republish_its_own_channel_message(session):
    """site -> telegram -> site would be the echo loop."""
    channel = await make_channel(session)
    bot = StubBot()
    link = await site_post_to_channel(session, bot, channel, POST)

    echoed = StubMessage(message_id=link.tg_message_id, text="Hello")
    result = await channel_post_to_site(session, bot, channel, [echoed])

    assert result is None


async def test_guard_survives_a_cold_in_process_cache(session):
    """A second worker has an empty cache and must rely on the PostLink row."""
    channel = await make_channel(session)
    bot = StubBot()
    link = await site_post_to_channel(session, bot, channel, POST)

    sync.recently_sent._seen.clear()

    echoed = StubMessage(message_id=link.tg_message_id, text="Hello")
    assert await channel_post_to_site(session, bot, channel, [echoed]) is None


async def test_telegram_sourced_post_is_not_echoed_back(session):
    channel = await make_channel(session)
    bot = StubBot()

    result = await site_post_to_channel(session, bot, channel, {**POST, "source": "telegram"})

    assert result is None
    assert bot.sent == []


async def test_inactive_channel_syncs_nothing(session):
    channel = await make_channel(session, active=False)
    bot = StubBot()

    assert await site_post_to_channel(session, bot, channel, POST) is None
    assert await channel_post_to_site(session, bot, channel, [StubMessage(1, text="hi")]) is None


async def test_one_direction_can_be_paused(session):
    channel = await make_channel(session)
    channel.sync_site_to_telegram = False
    bot = StubBot()

    assert await site_post_to_channel(session, bot, channel, POST) is None


async def test_already_synced_telegram_post_is_skipped(session):
    channel = await make_channel(session)
    session.add(
        PostLink(channel_id=channel.id, tg_message_id=77, site_post_id="p-9", origin="telegram")
    )
    await session.flush()
    sync.recently_sent._seen.clear()

    assert await channel_post_to_site(session, StubBot(), channel, [StubMessage(77, text="x")]) is None
