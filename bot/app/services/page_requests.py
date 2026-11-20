"""Records "Request a page" presses on the blog, for its admin's list.

Unlike SiteClient, which acts for one author with that author's token, this
call is made for the platform as a whole, with BOT_INTERNAL_SECRET.
"""

from __future__ import annotations

import httpx
from aiogram.types import User

TIMEOUT = httpx.Timeout(10.0, connect=5.0)


class PageRequestError(RuntimeError):
    """The blog refused the request or could not be reached."""


def page_request_payload(user: User) -> dict[str, object]:
    return {
        "telegram_user_id": user.id,
        "name": user.full_name,
        "telegram_username": user.username,
        "language": user.language_code,
    }


async def record_page_request(
    user: User,
    *,
    base_url: str,
    secret: str,
    transport: httpx.AsyncBaseTransport | None = None,
) -> None:
    url = f"{base_url.rstrip('/')}/internal/page-requests"
    headers = {"Authorization": f"Bearer {secret}", "User-Agent": "publish-channel-bot/0.1"}
    async with httpx.AsyncClient(timeout=TIMEOUT, transport=transport) as client:
        try:
            response = await client.post(url, json=page_request_payload(user), headers=headers)
        except httpx.HTTPError as exc:
            raise PageRequestError(f"POST {url} failed: {exc}") from exc
    if response.status_code >= 400:
        raise PageRequestError(f"POST {url} returned {response.status_code}: {response.text[:300]}")
