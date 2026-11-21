"""Premium as the blog keeps it: who has it, and recording what they paid.

The bot sells Premium as a monthly Telegram Stars subscription. Like
page requests, these calls are made for the platform with
BOT_INTERNAL_SECRET, not with an author's own token.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

import httpx
from aiogram.types import SuccessfulPayment

TIMEOUT = httpx.Timeout(10.0, connect=5.0)
# Telegram gives a pre-checkout query 10 seconds for an answer, all of it.
PRE_CHECKOUT_TIMEOUT = httpx.Timeout(5.0, connect=2.0)

# Telegram's only subscription period: 30 days.
SUBSCRIPTION_PERIOD = 30 * 24 * 60 * 60

PAYLOAD_PREFIX = "premium:"


class PremiumError(RuntimeError):
    """The blog refused or could not be reached."""


@dataclass(slots=True)
class PremiumStatus:
    author: str
    premium: bool
    premium_until: datetime | None
    blocked: bool

    @classmethod
    def from_json(cls, data: dict) -> "PremiumStatus":
        until = data.get("premium_until")
        return cls(
            author=str(data.get("author") or ""),
            premium=bool(data.get("premium")),
            premium_until=datetime.fromisoformat(until) if until else None,
            blocked=bool(data.get("blocked")),
        )


def invoice_payload(telegram_user_id: int) -> str:
    """Names who the subscription is for. Telegram hands it back on the
    pre-checkout query and on every payment, at most 128 bytes."""
    return f"{PAYLOAD_PREFIX}{telegram_user_id}"


def payload_user_id(payload: str | None) -> int | None:
    """The Telegram user id in a payload from invoice_payload, else None."""
    if not payload or not payload.startswith(PAYLOAD_PREFIX):
        return None
    rest = payload[len(PAYLOAD_PREFIX):]
    return int(rest) if rest.isdigit() else None


def payment_payload(telegram_user_id: int, payment: SuccessfulPayment) -> dict[str, object]:
    return {
        "telegram_user_id": telegram_user_id,
        "telegram_payment_charge_id": payment.telegram_payment_charge_id,
        "currency": payment.currency,
        "total_amount": payment.total_amount,
        "invoice_payload": payment.invoice_payload,
        "subscription_expiration_date": payment.subscription_expiration_date,
        "is_recurring": bool(payment.is_recurring),
        "is_first_recurring": bool(payment.is_first_recurring),
    }


async def _post(
    path: str,
    payload: dict[str, object],
    *,
    base_url: str,
    secret: str,
    timeout: httpx.Timeout,
    transport: httpx.AsyncBaseTransport | None,
) -> PremiumStatus | None:
    """None when no author has this Telegram account (the blog's 404)."""
    url = f"{base_url.rstrip('/')}/internal/premium/{path}"
    headers = {"Authorization": f"Bearer {secret}", "User-Agent": "publish-channel-bot/0.1"}
    async with httpx.AsyncClient(timeout=timeout, transport=transport) as client:
        try:
            response = await client.post(url, json=payload, headers=headers)
        except httpx.HTTPError as exc:
            raise PremiumError(f"POST {url} failed: {exc}") from exc
    try:
        data = response.json()
    except ValueError:
        data = {}

    if response.status_code in (200, 201) and data.get("author"):
        return PremiumStatus.from_json(data)
    # A 404 that says so means "not an author"; a bare 404 means the blog has
    # these calls switched off, which is an error like any other.
    if response.status_code == 404 and "author" in str(data.get("message", "")).lower():
        return None
    raise PremiumError(f"POST {url} returned {response.status_code}: {response.text[:300]}")


async def premium_status(
    telegram_user_id: int,
    *,
    base_url: str,
    secret: str,
    timeout: httpx.Timeout = TIMEOUT,
    transport: httpx.AsyncBaseTransport | None = None,
) -> PremiumStatus | None:
    return await _post(
        "status",
        {"telegram_user_id": telegram_user_id},
        base_url=base_url,
        secret=secret,
        timeout=timeout,
        transport=transport,
    )


async def record_payment(
    telegram_user_id: int,
    payment: SuccessfulPayment,
    *,
    base_url: str,
    secret: str,
    transport: httpx.AsyncBaseTransport | None = None,
) -> PremiumStatus | None:
    """Records a payment, the first or a renewal. The blog counts a charge id
    once, so sending the same payment again is safe."""
    return await _post(
        "payments",
        payment_payload(telegram_user_id, payment),
        base_url=base_url,
        secret=secret,
        timeout=TIMEOUT,
        transport=transport,
    )
