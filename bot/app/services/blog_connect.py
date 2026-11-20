"""Asks the platform's own blog to connect a Telegram user, for /connect.

An author made from a page request is known to the blog by their Telegram
id, so they are connected without typing anything. Anyone else is told
where to fetch a token, and the address the bot should use with it.
"""

from __future__ import annotations

from dataclasses import dataclass

import httpx

TIMEOUT = httpx.Timeout(10.0, connect=5.0)


class BlogConnectError(RuntimeError):
    """The blog refused or could not be reached."""


@dataclass(slots=True)
class Connected:
    api_base: str
    token: str
    page_url: str


@dataclass(slots=True)
class NeedsToken:
    """No author has this Telegram account; they paste a token by hand."""

    api_base: str
    admin_url: str


async def connect_by_telegram(
    telegram_user_id: int,
    *,
    webhook_url: str,
    webhook_secret: str,
    base_url: str,
    secret: str,
    transport: httpx.AsyncBaseTransport | None = None,
) -> Connected | NeedsToken:
    url = f"{base_url.rstrip('/')}/internal/connect"
    payload = {
        "telegram_user_id": telegram_user_id,
        "webhook_url": webhook_url,
        "webhook_secret": webhook_secret,
    }
    headers = {"Authorization": f"Bearer {secret}", "User-Agent": "publish-channel-bot/0.1"}
    async with httpx.AsyncClient(timeout=TIMEOUT, transport=transport) as client:
        try:
            response = await client.post(url, json=payload, headers=headers)
        except httpx.HTTPError as exc:
            raise BlogConnectError(f"POST {url} failed: {exc}") from exc
    try:
        data = response.json()
    except ValueError:
        data = {}

    if response.status_code == 200 and data.get("token") and data.get("api_base"):
        return Connected(api_base=data["api_base"], token=data["token"], page_url=data.get("url", ""))
    # 404 with directions means "not an author"; a bare 404 means the blog has
    # this switched off, which is an error like any other.
    if response.status_code == 404 and data.get("api_base") and data.get("admin_url"):
        return NeedsToken(api_base=data["api_base"], admin_url=data["admin_url"])
    raise BlogConnectError(f"POST {url} returned {response.status_code}: {response.text[:300]}")
