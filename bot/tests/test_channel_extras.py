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
