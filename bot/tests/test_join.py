from aiogram.types import User

from app.bot.handlers.private import JOIN_REPLY, join_notice, join_reply
from app.config import Settings

BASE = {"bot_token": "1:x", "secret_key": "k"}


def test_reply_follows_the_users_telegram_language():
    assert join_reply("ru") == JOIN_REPLY["ru"]
    assert join_reply("uz") == JOIN_REPLY["uz"]
    assert join_reply("en-GB") == JOIN_REPLY["en"]


def test_reply_falls_back_to_english():
    assert join_reply(None) == JOIN_REPLY["en"]
    assert join_reply("de") == JOIN_REPLY["en"]


def test_notice_links_the_user_and_escapes_their_name():
    user = User(id=42, is_bot=False, first_name="<Ali>", last_name="& Co", username="ali", language_code="uz")
    notice = join_notice(user)
    assert '<a href="tg://user?id=42">&lt;Ali&gt; &amp; Co</a>' in notice
    assert "(@ali)" in notice
    assert "<code>42</code>" in notice


def test_notice_without_a_username():
    user = User(id=7, is_bot=False, first_name="Vali")
    assert "(@" not in join_notice(user)


def test_blank_admin_chat_id_means_unset():
    assert Settings(**BASE, admin_chat_id="").admin_chat_id is None
    assert Settings(**BASE, admin_chat_id="-1001234").admin_chat_id == -1001234


# --- Recording requests on the blog -----------------------------------------

import json  # noqa: E402

import httpx  # noqa: E402
import pytest  # noqa: E402

from app.bot.handlers import private  # noqa: E402
from app.services.page_requests import (  # noqa: E402
    PageRequestError,
    page_request_payload,
    record_page_request,
)

ALI = User(id=42, is_bot=False, first_name="Ali", last_name="Valiyev", username="ali_writes", language_code="ru")


def test_payload_carries_who_asked():
    assert page_request_payload(ALI) == {
        "telegram_user_id": 42,
        "name": "Ali Valiyev",
        "telegram_username": "ali_writes",
        "language": "ru",
    }


async def test_request_is_posted_with_the_internal_secret():
    seen = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["url"] = str(request.url)
        seen["auth"] = request.headers["authorization"]
        seen["body"] = json.loads(request.content)
        return httpx.Response(201, json={"id": 1})

    await record_page_request(
        ALI, base_url="http://pcb-blog/api/", secret="s3cret", transport=httpx.MockTransport(handler)
    )
    assert seen["url"] == "http://pcb-blog/api/internal/page-requests"
    assert seen["auth"] == "Bearer s3cret"
    assert seen["body"]["telegram_user_id"] == 42


async def test_a_refusal_or_an_unreachable_blog_is_an_error():
    refused = httpx.MockTransport(lambda request: httpx.Response(401, json={"message": "Unauthenticated."}))
    with pytest.raises(PageRequestError, match="401"):
        await record_page_request(ALI, base_url="http://pcb-blog/api", secret="x", transport=refused)

    def down(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectError("no route", request=request)

    with pytest.raises(PageRequestError, match="failed"):
        await record_page_request(ALI, base_url="http://pcb-blog/api", secret="x", transport=httpx.MockTransport(down))


def test_recording_needs_both_the_url_and_the_secret():
    assert not Settings(**BASE).records_page_requests
    assert not Settings(**BASE, blog_internal_url="http://pcb-blog/api", bot_internal_secret="").records_page_requests
    assert Settings(**BASE, blog_internal_url="http://pcb-blog/api", bot_internal_secret="s").records_page_requests


class FakeBot:
    def __init__(self, fails: bool = False) -> None:
        self.fails = fails
        self.sent: list[tuple[int, str]] = []

    async def send_message(self, chat_id: int, text: str) -> None:
        if self.fails:
            from aiogram.exceptions import TelegramForbiddenError
            from aiogram.methods import SendMessage

            raise TelegramForbiddenError(method=SendMessage(chat_id=chat_id, text=text), message="bot was blocked")
        self.sent.append((chat_id, text))


class FakeMessage:
    def __init__(self, bot: FakeBot) -> None:
        self.from_user = ALI
        self.bot = bot
        self.answers: list[str] = []

    async def answer(self, text: str, **kwargs) -> None:
        self.answers.append(text)


@pytest.fixture
def recorded(monkeypatch):
    calls: list[dict] = []

    async def fake_record(user, *, base_url, secret):
        calls.append({"user": user.id, "base_url": base_url, "secret": secret})

    monkeypatch.setattr(private, "record_page_request", fake_record)
    return calls


def use_settings(monkeypatch, **overrides):
    monkeypatch.setattr(private, "get_settings", lambda: Settings(**BASE, **overrides))


async def test_join_goes_to_the_admin_and_to_the_blog(session, monkeypatch, recorded):
    use_settings(monkeypatch, admin_chat_id=7, blog_internal_url="http://pcb-blog/api", bot_internal_secret="s")
    message = FakeMessage(FakeBot())

    await private.on_join_request(message)

    assert message.bot.sent and message.bot.sent[0][0] == 7
    assert recorded == [{"user": 42, "base_url": "http://pcb-blog/api", "secret": "s"}]
    assert message.answers == [JOIN_REPLY["ru"]]


async def test_join_still_counts_when_only_the_blog_gets_it(session, monkeypatch, recorded):
    use_settings(monkeypatch, admin_chat_id=7, blog_internal_url="http://pcb-blog/api", bot_internal_secret="s")
    message = FakeMessage(FakeBot(fails=True))

    await private.on_join_request(message)

    assert len(recorded) == 1
    assert message.answers == [JOIN_REPLY["ru"]]


async def test_join_with_nowhere_to_go_gets_the_welcome(session, monkeypatch, recorded):
    use_settings(monkeypatch)
    message = FakeMessage(FakeBot())

    await private.on_join_request(message)

    assert recorded == []
    assert message.answers == [private.WELCOME]
