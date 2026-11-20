"""/connect without copying anything, and channels that switch on by themselves."""

from __future__ import annotations

import json
from types import SimpleNamespace

import httpx
import pytest
from aiogram.types import User
from sqlalchemy import select

from app.bot.handlers import channel as channel_handlers
from app.bot.handlers import private
from app.config import Settings
from app.db.models import Channel, PendingConnection, Site, WaitingChannel
from app.db.session import SessionLocal
from app.services.blog_connect import (
    BlogConnectError,
    Connected,
    NeedsToken,
    connect_by_telegram,
)
from app.services.crypto import decrypt
from app.services.site_client import SiteError

ALI = User(id=42, is_bot=False, first_name="Ali", username="ali_writes", language_code="uz")
PUBLIC_API = "https://admin.posts.test/api/telegram"
PLATFORM = {
    "bot_token": "1:x",
    "secret_key": "k",
    "public_base_url": "https://bot.posts.test",
    "blog_internal_url": "http://pcb-blog/api",
    "bot_internal_secret": "s",
}


class FakeBot:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str]] = []

    async def send_message(self, chat_id: int, text: str) -> None:
        self.sent.append((chat_id, text))


class FakeMessage:
    def __init__(self, text: str = "/connect") -> None:
        self.from_user = ALI
        self.text = text
        self.answers: list[str] = []

    async def answer(self, text: str, **kwargs) -> None:
        self.answers.append(text)


def use_platform(monkeypatch, **overrides):
    settings = Settings(**{**PLATFORM, **overrides})
    monkeypatch.setattr(private, "get_settings", lambda: settings)


def blog_answers(monkeypatch, result):
    calls: list[dict] = []

    async def fake(telegram_user_id, **kwargs):
        calls.append({"telegram_user_id": telegram_user_id, **kwargs})
        if isinstance(result, Exception):
            raise result
        return result

    monkeypatch.setattr(private, "connect_by_telegram", fake)
    return calls


async def sites() -> list[Site]:
    async with SessionLocal() as db:
        return list((await db.execute(select(Site))).scalars())


async def channels() -> list[Channel]:
    async with SessionLocal() as db:
        return list((await db.execute(select(Channel))).scalars())


# --- An author the blog knows by Telegram id ---------------------------------


async def test_a_known_author_is_connected_without_typing_anything(session, monkeypatch):
    use_platform(monkeypatch)
    calls = blog_answers(monkeypatch, Connected(api_base=PUBLIC_API, token="tok", page_url="https://ali.posts.test"))
    message = FakeMessage()

    await private.on_connect(message)

    [call] = calls
    secret = call["webhook_secret"]
    assert call["telegram_user_id"] == 42
    assert call["webhook_url"] == f"https://bot.posts.test/webhooks/site/{secret}"
    assert call["base_url"] == "http://pcb-blog/api"

    [site] = await sites()
    assert site.api_base == PUBLIC_API
    assert decrypt(site.api_token_encrypted) == "tok"
    assert site.webhook_secret == secret
    assert "✅ Connected" in message.answers[0]
    assert "https://ali.posts.test" in message.answers[0]
    assert "add me to your channel" in message.answers[0]


async def test_a_channel_added_before_connecting_starts_syncing_on_connect(session, monkeypatch):
    session.add(WaitingChannel(telegram_chat_id=-100777, telegram_user_id=42, title="Ali's notes"))
    await session.commit()
    use_platform(monkeypatch)
    blog_answers(monkeypatch, Connected(api_base=PUBLIC_API, token="tok", page_url=""))
    message = FakeMessage()

    await private.on_connect(message)

    [channel] = await channels()
    assert channel.telegram_chat_id == -100777 and channel.active
    async with SessionLocal() as db:
        assert (await db.execute(select(WaitingChannel))).first() is None
    assert "Syncing is on in <b>Ali&#x27;s notes</b>" in message.answers[0]


async def test_reconnecting_keeps_the_channels_of_the_earlier_connection(session, monkeypatch):
    use_platform(monkeypatch)
    blog_answers(monkeypatch, Connected(api_base=PUBLIC_API, token="old", page_url=""))
    await private.on_connect(FakeMessage())
    [first] = await sites()
    async with SessionLocal() as db:
        db.add(Channel(site_id=first.id, telegram_chat_id=-100777, active=True))
        await db.commit()

    blog_answers(monkeypatch, Connected(api_base=PUBLIC_API, token="new", page_url=""))
    await private.on_connect(FakeMessage())

    [site] = await sites()
    assert site.id == first.id
    assert decrypt(site.api_token_encrypted) == "new"
    assert (await channels())[0].site_id == site.id


# --- Anyone else pastes a token, and nothing more ----------------------------


class FakeSiteClient:
    webhooks: list[tuple[str, str]] = []
    refuse_webhook = False

    def __init__(self, api_base: str, token: str) -> None:
        self.api_base, self.token = api_base, token

    async def ping(self) -> dict:
        return {"ok": True, "url": "https://me.posts.test"}

    async def set_webhook(self, url: str, secret: str) -> None:
        if self.refuse_webhook:
            raise SiteError("PUT /webhook returned 404")
        self.webhooks.append((url, secret))


@pytest.fixture
def site_client(monkeypatch):
    FakeSiteClient.webhooks = []
    FakeSiteClient.refuse_webhook = False
    monkeypatch.setattr(private, "SiteClient", FakeSiteClient)
    return FakeSiteClient


async def test_an_unknown_user_is_only_asked_for_the_token(session, monkeypatch, site_client):
    use_platform(monkeypatch)
    blog_answers(monkeypatch, NeedsToken(api_base=PUBLIC_API, admin_url="https://admin.posts.test/admin/profile"))
    message = FakeMessage()

    await private.on_connect(message)

    assert "https://admin.posts.test/admin/profile" in message.answers[0]
    assert "Generate bot token" in message.answers[0]
    assert "myblog.com" not in message.answers[0]
    async with SessionLocal() as db:
        pending = await db.get(PendingConnection, 42)
    assert pending.step == "api_token" and pending.api_base == PUBLIC_API

    reply = FakeMessage("pasted-token")
    await private.on_connect_input(reply)

    [site] = await sites()
    assert site.api_base == PUBLIC_API
    assert site_client.webhooks == [(f"https://bot.posts.test/webhooks/site/{site.webhook_secret}", site.webhook_secret)]
    assert reply.answers == [private.connected_text("https://me.posts.test", [])]


async def test_a_blog_that_cannot_take_the_webhook_gets_it_shown(session, monkeypatch, site_client):
    use_platform(monkeypatch, blog_internal_url=None, bot_internal_secret=None)
    site_client.refuse_webhook = True

    first = FakeMessage()
    await private.on_connect(first)
    assert "myblog.com" in first.answers[0]  # a blog of its own: the address is needed

    await private.on_connect_input(FakeMessage("https://own.blog/api/telegram"))
    reply = FakeMessage("pasted-token")
    await private.on_connect_input(reply)

    [site] = await sites()
    assert f"/webhooks/site/{site.webhook_secret}" in reply.answers[0]
    assert "add me to your channel" in reply.answers[1]


async def test_an_unreachable_blog_falls_back_to_asking_for_the_address(session, monkeypatch):
    use_platform(monkeypatch)
    blog_answers(monkeypatch, BlogConnectError("down"))
    message = FakeMessage()

    await private.on_connect(message)

    assert "API base URL" in message.answers[0]


# --- Channels ---------------------------------------------------------------


def membership(status: str, chat_id: int = -100777, title: str = "Ali's notes"):
    return SimpleNamespace(
        new_chat_member=SimpleNamespace(status=status),
        chat=SimpleNamespace(id=chat_id, title=title),
        from_user=ALI,
    )


async def connected_site() -> Site:
    async with SessionLocal() as db:
        db.add(Site(author_id=1, api_base=PUBLIC_API, api_token_encrypted=b"x", webhook_secret="w" * 20))
        from app.db.models import Author

        db.add(Author(id=1, telegram_user_id=42))
        await db.commit()
    return (await sites())[0]


async def test_making_the_bot_admin_switches_syncing_on(session):
    await connected_site()
    bot = FakeBot()

    await channel_handlers.on_bot_membership_change(membership("administrator"), bot)

    [channel] = await channels()
    assert channel.active
    assert "Syncing is on" in bot.sent[0][1]


async def test_a_channel_added_before_connecting_waits(session):
    bot = FakeBot()

    await channel_handlers.on_bot_membership_change(membership("administrator"), bot)

    assert await channels() == []
    async with SessionLocal() as db:
        waiting = await db.get(WaitingChannel, -100777)
    assert waiting.telegram_user_id == 42
    assert "/connect" in bot.sent[0][1]

    # Removed again before connecting: nothing left to switch on later.
    await channel_handlers.on_bot_membership_change(membership("left"), bot)
    async with SessionLocal() as db:
        assert await db.get(WaitingChannel, -100777) is None


async def test_activate_sent_in_the_dm_switches_the_channels_on(session):
    site = await connected_site()
    async with SessionLocal() as db:
        db.add(Channel(site_id=site.id, telegram_chat_id=-100777, title="Notes", active=False))
        await db.commit()
    message = FakeMessage("/activate")

    await private.on_activate_in_dm(message)

    assert (await channels())[0].active
    assert "<b>Notes</b>" in message.answers[0]


# --- The call to the blog ----------------------------------------------------


async def call_with(response: httpx.Response):
    seen = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["url"] = str(request.url)
        seen["auth"] = request.headers["authorization"]
        seen["body"] = json.loads(request.content)
        return response

    result = await connect_by_telegram(
        42, webhook_url="https://bot/w/abc", webhook_secret="abc", base_url="http://pcb-blog/api",
        secret="s", transport=httpx.MockTransport(handler),
    )
    return result, seen


async def test_the_blog_connects_a_known_author():
    result, seen = await call_with(
        httpx.Response(200, json={"token": "t", "api_base": PUBLIC_API, "url": "https://ali.posts.test"})
    )
    assert result == Connected(api_base=PUBLIC_API, token="t", page_url="https://ali.posts.test")
    assert seen["url"] == "http://pcb-blog/api/internal/connect"
    assert seen["auth"] == "Bearer s"
    assert seen["body"] == {"telegram_user_id": 42, "webhook_url": "https://bot/w/abc", "webhook_secret": "abc"}


async def test_the_blog_points_an_unknown_user_at_their_profile():
    result, _ = await call_with(
        httpx.Response(404, json={"message": "no", "api_base": PUBLIC_API, "admin_url": "https://a/admin/profile"})
    )
    assert result == NeedsToken(api_base=PUBLIC_API, admin_url="https://a/admin/profile")


async def test_a_blog_with_connecting_switched_off_is_an_error():
    with pytest.raises(BlogConnectError):
        await call_with(httpx.Response(404, text="Not Found"))
