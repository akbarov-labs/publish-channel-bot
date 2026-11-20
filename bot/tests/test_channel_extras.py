"""Channel events beyond new posts: pins, reactions, audio, polls."""

from __future__ import annotations

import pytest

from app.db.models import PostLink
from app.services import sync
from tests.factories import StubSiteClient, make_channel


@pytest.fixture
def site(monkeypatch):
    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda _site: client)
    return client


async def test_a_pin_in_the_channel_pins_the_post_on_the_site(session, site):
    channel = await make_channel(session)
    session.add(PostLink(channel_id=channel.id, tg_message_id=77, site_post_id="p-9", origin="telegram"))
    await session.flush()

    assert await sync.channel_pin_to_site(session, channel, 77) is True
    assert site.created == [{"pin": "p-9"}]


async def test_a_pin_of_an_unknown_message_is_ignored(session, site):
    channel = await make_channel(session)

    assert await sync.channel_pin_to_site(session, channel, 12345) is False
    assert site.created == []


async def test_reaction_counts_reach_the_site(session, site):
    channel = await make_channel(session)
    session.add(PostLink(channel_id=channel.id, tg_message_id=5, site_post_id="p-3", origin="telegram"))
    await session.flush()

    counts = [{"emoji": "🔥", "count": 4}]
    assert await sync.channel_reactions_to_site(session, channel, 5, counts) is True
    assert site.created == [{"reactions": "p-3", "counts": counts}]


def test_reaction_counts_keep_emoji_and_stars_and_drop_custom_emoji():
    from datetime import datetime

    from aiogram.types import (
        Chat,
        MessageReactionCountUpdated,
        ReactionCount,
        ReactionTypeCustomEmoji,
        ReactionTypeEmoji,
        ReactionTypePaid,
    )

    from app.bot.handlers.channel import reaction_counts

    event = MessageReactionCountUpdated(
        chat=Chat(id=-1001, type="channel"),
        message_id=5,
        date=datetime.now(),
        reactions=[
            ReactionCount(type=ReactionTypeEmoji(emoji="👍"), total_count=3),
            ReactionCount(type=ReactionTypeCustomEmoji(custom_emoji_id="123"), total_count=2),
            ReactionCount(type=ReactionTypePaid(), total_count=7),
        ],
    )

    assert reaction_counts(event) == [{"emoji": "👍", "count": 3}, {"emoji": "⭐", "count": 7}]


async def test_an_announced_admin_post_tells_the_site_its_message(session, site):
    from tests.factories import StubBot

    channel = await make_channel(session)
    bot = StubBot()

    link = await sync.site_post_to_channel(
        session, bot, channel, {"id": "p-1", "title": "Hi", "html": "<p>x</p>", "url": "https://blog.test/p/1"}
    )

    assert {"channel_message": "p-1", "message_id": link.tg_message_id} in site.created


async def test_a_voice_note_becomes_an_audio_post(session, site):
    from types import SimpleNamespace

    from tests.factories import StubBot, StubMessage

    channel = await make_channel(session)
    voice = SimpleNamespace(file_id="v1", file_unique_id="uv1", file_size=2048, mime_type="audio/ogg")
    message = StubMessage(message_id=40, caption="Morning thoughts", html="Morning thoughts", voice=voice)

    await sync.channel_post_to_site(session, StubBot(), channel, [message])

    created = site.created[0]
    assert created["post_type"] == "audio"
    assert created["title"] == "Morning thoughts"
    assert created["media"] == [{"type": "audio", "url": "https://cdn.blog.test/uv1.ogg"}]


async def test_a_track_without_caption_is_titled_by_the_track(session, site):
    from types import SimpleNamespace

    from tests.factories import StubBot, StubMessage

    channel = await make_channel(session)
    audio = SimpleNamespace(
        file_id="a1", file_unique_id="ua1", file_size=4096, file_name="song.mp3",
        mime_type="audio/mpeg", title="Lola", performer="Sevara",
    )

    await sync.channel_post_to_site(session, StubBot(), channel, [StubMessage(message_id=41, audio=audio)])

    assert site.created[0]["title"] == "Sevara — Lola"
    assert site.created[0]["media"][0]["url"].endswith("song.mp3")


async def test_a_poll_becomes_a_poll_post(session, site):
    from types import SimpleNamespace

    from tests.factories import StubBot, StubMessage

    channel = await make_channel(session)
    poll = SimpleNamespace(
        question="Tea or coffee?",
        options=[SimpleNamespace(text="Tea", voter_count=7), SimpleNamespace(text="Coffee", voter_count=3)],
        total_voter_count=10,
        is_closed=False,
        allows_multiple_answers=False,
        type="regular",
    )

    await sync.channel_post_to_site(session, StubBot(), channel, [StubMessage(message_id=42, poll=poll)])

    created = site.created[0]
    assert created["post_type"] == "poll"
    assert created["title"] == "Tea or coffee?"
    assert created["poll"]["options"] == [{"text": "Tea", "votes": 7}, {"text": "Coffee", "votes": 3}]
    assert created["poll"]["total_votes"] == 10
