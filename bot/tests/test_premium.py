import json
from datetime import datetime, timezone

import httpx
import pytest
from aiogram.types import SuccessfulPayment, User
from pydantic import ValidationError

from app.bot.handlers import premium
from app.config import Settings
from app.services.premium import (
    SUBSCRIPTION_PERIOD,
    PremiumError,
    PremiumStatus,
    invoice_payload,
    payload_user_id,
    payment_payload,
    premium_status,
    record_payment,
)

BASE = {"bot_token": "1:x", "secret_key": "k"}
SELLING = {**BASE, "blog_internal_url": "http://pcb-blog/api", "bot_internal_secret": "s", "premium_price_stars": 250}

ALI = User(id=42, is_bot=False, first_name="Ali", username="ali_writes", language_code="ru")
UNTIL = datetime(2026, 12, 21, 10, 0, tzinfo=timezone.utc)


def paid(**overrides) -> SuccessfulPayment:
    return SuccessfulPayment(
        **{
            "currency": "XTR",
            "total_amount": 250,
            "invoice_payload": "premium:42",
            "telegram_payment_charge_id": "stxCharge1",
            "provider_payment_charge_id": "",
            "subscription_expiration_date": int(UNTIL.timestamp()),
            "is_recurring": True,
            "is_first_recurring": True,
            **overrides,
        }
    )


def status(**overrides) -> PremiumStatus:
    return PremiumStatus(
        **{"author": "ali", "premium": False, "premium_until": None, "blocked": False, **overrides}
    )


# --- Settings -------------------------------------------------------------------


def test_premium_is_sold_only_with_a_price_and_the_blog():
    assert not Settings(**BASE).sells_premium
    assert not Settings(**BASE, premium_price_stars=250).sells_premium
    assert not Settings(**{**SELLING, "premium_price_stars": 0}).sells_premium
    assert Settings(**SELLING).sells_premium


def test_a_blank_price_is_off_and_telegrams_cap_is_kept():
    assert Settings(**BASE, premium_price_stars="").premium_price_stars == 0
    with pytest.raises(ValidationError):
        Settings(**BASE, premium_price_stars=10001)
    with pytest.raises(ValidationError):
        Settings(**BASE, premium_price_stars=-1)


# --- The payload ------------------------------------------------------------------


def test_the_payload_names_whom_the_subscription_is_for():
    assert invoice_payload(42) == "premium:42"
    assert payload_user_id("premium:42") == 42
    assert payload_user_id("premium:") is None
    assert payload_user_id("premium:4x2") is None
    assert payload_user_id("donation:42") is None
    assert payload_user_id(None) is None


def test_a_payment_goes_to_the_blog_as_telegram_reported_it():
    assert payment_payload(42, paid()) == {
        "telegram_user_id": 42,
        "telegram_payment_charge_id": "stxCharge1",
        "currency": "XTR",
        "total_amount": 250,
        "invoice_payload": "premium:42",
        "subscription_expiration_date": int(UNTIL.timestamp()),
        "is_recurring": True,
        "is_first_recurring": True,
    }


# --- Talking to the blog -------------------------------------------------------------


def blog(status_code: int, body: object):
    seen: dict = {}

    def handler(request: httpx.Request) -> httpx.Response:
        seen["url"] = str(request.url)
        seen["auth"] = request.headers["authorization"]
        seen["body"] = json.loads(request.content)
        return httpx.Response(status_code, json=body)

    return httpx.MockTransport(handler), seen


async def test_status_is_asked_with_the_internal_secret():
    transport, seen = blog(
        200, {"author": "ali", "premium": True, "premium_until": "2026-12-21T10:00:00+00:00", "blocked": False}
    )

    result = await premium_status(42, base_url="http://pcb-blog/api/", secret="s3cret", transport=transport)

    assert seen == {"url": "http://pcb-blog/api/internal/premium/status", "auth": "Bearer s3cret", "body": {"telegram_user_id": 42}}
    assert result == PremiumStatus(author="ali", premium=True, premium_until=UNTIL, blocked=False)


async def test_someone_who_is_not_an_author_has_no_status():
    transport, _ = blog(404, {"message": "No author has this Telegram account."})
    assert await premium_status(42, base_url="http://pcb-blog/api", secret="s", transport=transport) is None


async def test_a_bare_404_a_refusal_or_an_unreachable_blog_is_an_error():
    for code, body in ((404, {"message": "Not Found"}), (401, {"message": "Unauthenticated."}), (500, {})):
        transport, _ = blog(code, body)
        with pytest.raises(PremiumError, match=str(code)):
            await premium_status(42, base_url="http://pcb-blog/api", secret="s", transport=transport)

    def down(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectError("no route", request=request)

    with pytest.raises(PremiumError, match="failed"):
        await premium_status(42, base_url="http://pcb-blog/api", secret="s", transport=httpx.MockTransport(down))


async def test_a_payment_is_recorded_and_both_new_and_repeated_answers_count():
    for code in (201, 200):
        transport, seen = blog(
            code, {"author": "ali", "premium": True, "premium_until": "2026-12-21T10:00:00+00:00", "blocked": False}
        )
        result = await record_payment(42, paid(), base_url="http://pcb-blog/api", secret="s", transport=transport)
        assert seen["url"] == "http://pcb-blog/api/internal/premium/payments"
        assert seen["body"]["telegram_payment_charge_id"] == "stxCharge1"
        assert result.premium_until == UNTIL


# --- The handlers ---------------------------------------------------------------------


class FakeBot:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str]] = []
        self.invoices: list[dict] = []
        self.refunds: list[dict] = []

    async def send_message(self, chat_id: int, text: str, **kwargs) -> None:
        self.sent.append((chat_id, text))

    async def create_invoice_link(self, **kwargs) -> str:
        self.invoices.append(kwargs)
        return "https://t.me/$premium-link"

    async def refund_star_payment(self, **kwargs) -> bool:
        self.refunds.append(kwargs)
        return True


class FakeMessage:
    def __init__(self, text: str = "", successful_payment: SuccessfulPayment | None = None) -> None:
        self.from_user = ALI
        self.bot = FakeBot()
        self.text = text
        self.successful_payment = successful_payment
        self.answers: list[str] = []
        self.markups: list = []

    async def answer(self, text: str, reply_markup=None, **kwargs) -> None:
        self.answers.append(text)
        self.markups.append(reply_markup)


class FakeQuery:
    def __init__(self, payload: str = "premium:42", currency: str = "XTR") -> None:
        self.from_user = ALI
        self.invoice_payload = payload
        self.currency = currency
        self.answered: dict | None = None

    async def answer(self, ok: bool, error_message: str | None = None) -> None:
        self.answered = {"ok": ok, "error_message": error_message}


def use_settings(monkeypatch, **settings):
    monkeypatch.setattr(premium, "get_settings", lambda: Settings(**settings))


def blog_says(monkeypatch, answer: PremiumStatus | None | Exception):
    calls: list[dict] = []

    async def fake_status(telegram_user_id, **kwargs):
        calls.append({"telegram_user_id": telegram_user_id, **kwargs})
        if isinstance(answer, Exception):
            raise answer
        return answer

    monkeypatch.setattr(premium, "premium_status", fake_status)
    return calls


async def test_premium_says_so_when_it_is_not_on_sale(monkeypatch):
    use_settings(monkeypatch, **BASE)
    message = FakeMessage("/premium")

    await premium.on_premium(message)

    assert message.answers == [premium.TEXTS["not_on_sale"]["ru"]]
    assert message.bot.invoices == []


async def test_premium_offers_a_monthly_stars_subscription(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    blog_says(monkeypatch, status())
    message = FakeMessage("/premium")

    await premium.on_premium(message)

    invoice = message.bot.invoices[0]
    assert invoice["currency"] == "XTR"
    assert invoice["subscription_period"] == SUBSCRIPTION_PERIOD == 2592000
    assert invoice["payload"] == "premium:42"
    assert [price.amount for price in invoice["prices"]] == [250]
    assert len(invoice["description"]) <= 255

    assert "250 ⭐" in message.answers[0]
    button = message.markups[0].inline_keyboard[0][0]
    assert button.url == "https://t.me/$premium-link"
    assert "250" in button.text


@pytest.mark.parametrize(
    ("answer", "key"),
    [
        (None, "not_author"),
        (status(blocked=True), "blocked"),
        (PremiumError("down"), "unavailable"),
    ],
)
async def test_premium_turns_away_who_cannot_buy_it(monkeypatch, answer, key):
    use_settings(monkeypatch, **SELLING)
    blog_says(monkeypatch, answer)
    message = FakeMessage("/premium")

    await premium.on_premium(message)

    assert message.answers == [premium.TEXTS[key]["ru"]]
    assert message.bot.invoices == []


async def test_an_author_who_has_premium_sees_until_when_and_no_button(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    blog_says(monkeypatch, status(premium=True, premium_until=UNTIL))
    message = FakeMessage("/premium")

    await premium.on_premium(message)

    assert "21.12.2026" in message.answers[0]
    assert message.markups == [None]
    assert message.bot.invoices == []


async def test_texts_follow_the_users_language_and_fall_back_to_english():
    assert premium.language_of(User(id=1, is_bot=False, first_name="A", language_code="uz")) == "uz"
    assert premium.language_of(User(id=1, is_bot=False, first_name="A", language_code="en-GB")) == "en"
    assert premium.language_of(User(id=1, is_bot=False, first_name="A", language_code="de")) == "en"
    assert premium.language_of(None) == "en"
    # Every text exists in every language, with the same placeholders.
    for key, versions in premium.TEXTS.items():
        assert set(versions) == {"uz", "ru", "en"}, key
        placeholders = {version.count("{") for version in versions.values()}
        assert len(placeholders) == 1, key


# --- Pre-checkout ----------------------------------------------------------------------


async def test_pre_checkout_lets_an_author_pay_and_asks_the_blog_quickly(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    calls = blog_says(monkeypatch, status())
    query = FakeQuery()

    await premium.on_pre_checkout(query)

    assert query.answered == {"ok": True, "error_message": None}
    assert calls[0]["timeout"] is premium.PRE_CHECKOUT_TIMEOUT


@pytest.mark.parametrize(
    ("payload", "currency", "answer", "key"),
    [
        ("premium:7", "XTR", status(), "invalid"),  # a link made for someone else
        ("premium:42", "USD", status(), "invalid"),
        ("premium:42", "XTR", None, "not_author"),
        ("premium:42", "XTR", status(blocked=True), "blocked"),
        ("premium:42", "XTR", PremiumError("down"), "unavailable"),
    ],
)
async def test_pre_checkout_refuses_with_a_reason(monkeypatch, payload, currency, answer, key):
    use_settings(monkeypatch, **SELLING)
    blog_says(monkeypatch, answer)
    query = FakeQuery(payload, currency)

    await premium.on_pre_checkout(query)

    assert query.answered == {"ok": False, "error_message": premium.TEXTS[key]["ru"]}


async def test_pre_checkout_refuses_when_premium_is_off_sale(monkeypatch):
    use_settings(monkeypatch, **BASE)
    query = FakeQuery()

    await premium.on_pre_checkout(query)

    assert query.answered["ok"] is False


# --- After the payment -----------------------------------------------------------------


def blog_records(monkeypatch, *answers):
    calls: list[int] = []
    pending = list(answers)

    async def fake_record(telegram_user_id, payment, **kwargs):
        calls.append(telegram_user_id)
        answer = pending.pop(0)
        if isinstance(answer, Exception):
            raise answer
        return answer

    monkeypatch.setattr(premium, "record_payment", fake_record)
    monkeypatch.setattr(premium, "RECORD_RETRY_DELAYS", (0, 0, 0))
    return calls


async def test_a_first_payment_is_recorded_and_thanked_for(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    calls = blog_records(monkeypatch, status(premium=True, premium_until=UNTIL))
    message = FakeMessage(successful_payment=paid())

    await premium.on_successful_payment(message)

    assert calls == [42]
    assert message.answers == [premium.TEXTS["paid"]["ru"].format(date="21.12.2026")]


async def test_a_renewal_says_renewed(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    blog_records(monkeypatch, status(premium=True, premium_until=UNTIL))
    message = FakeMessage(successful_payment=paid(is_first_recurring=False, telegram_payment_charge_id="stxCharge2"))

    await premium.on_successful_payment(message)

    assert message.answers == [premium.TEXTS["renewed"]["ru"].format(date="21.12.2026")]


async def test_a_blog_that_is_down_for_a_moment_is_tried_again(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    calls = blog_records(monkeypatch, PremiumError("down"), status(premium=True, premium_until=UNTIL))
    message = FakeMessage(successful_payment=paid())

    await premium.on_successful_payment(message)

    assert calls == [42, 42]
    assert message.answers[0].startswith("✅")


async def test_a_payment_the_blog_never_takes_goes_to_the_admin(monkeypatch):
    use_settings(monkeypatch, **SELLING, admin_chat_id=7)
    calls = blog_records(monkeypatch, *[PremiumError("down")] * 4)
    message = FakeMessage(successful_payment=paid())

    await premium.on_successful_payment(message)

    assert len(calls) == 4
    assert message.answers == [premium.TEXTS["delayed"]["ru"]]
    chat_id, notice = message.bot.sent[0]
    assert chat_id == 7
    assert "stxCharge1" in notice
    assert "tg://user?id=42" in notice
    assert "21.12.2026" in notice


async def test_a_payment_from_someone_who_is_not_an_author_is_refunded(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    blog_records(monkeypatch, None)
    message = FakeMessage(successful_payment=paid())

    await premium.on_successful_payment(message)

    assert message.bot.refunds == [{"user_id": 42, "telegram_payment_charge_id": "stxCharge1"}]
    assert message.answers == [premium.TEXTS["refunded"]["ru"]]


async def test_a_payment_that_is_not_for_premium_is_left_alone(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    calls = blog_records(monkeypatch)
    message = FakeMessage(successful_payment=paid(invoice_payload="something-else"))

    await premium.on_successful_payment(message)

    assert calls == []
    assert message.answers == []


# --- /paysupport and /terms -------------------------------------------------------------


async def test_paysupport_without_a_problem_explains_how(monkeypatch):
    use_settings(monkeypatch, **SELLING, admin_chat_id=7)
    message = FakeMessage("/paysupport")

    await premium.on_paysupport(message)

    assert message.answers == [premium.TEXTS["support_how"]["ru"]]
    assert message.bot.sent == []


async def test_paysupport_passes_the_problem_to_the_admin(monkeypatch):
    use_settings(monkeypatch, **SELLING, admin_chat_id=7)
    message = FakeMessage("/paysupport I paid but <nothing> happened")

    await premium.on_paysupport(message)

    chat_id, notice = message.bot.sent[0]
    assert chat_id == 7
    assert "I paid but &lt;nothing&gt; happened" in notice
    assert "tg://user?id=42" in notice
    assert message.answers == [premium.TEXTS["support_sent"]["ru"]]


async def test_paysupport_without_an_admin_chat_does_not_pretend(monkeypatch):
    use_settings(monkeypatch, **SELLING)
    message = FakeMessage("/paysupport help")

    await premium.on_paysupport(message)

    assert message.answers == [premium.TEXTS["support_unavailable"]["ru"]]


async def test_terms_are_in_the_users_language():
    message = FakeMessage("/terms")

    await premium.on_terms(message)

    assert message.answers == [premium.TEXTS["terms"]["ru"]]


def test_the_dispatcher_listens_for_pre_checkout_queries():
    from app.bot.factory import create_dispatcher

    assert "pre_checkout_query" in create_dispatcher().resolve_used_update_types()
