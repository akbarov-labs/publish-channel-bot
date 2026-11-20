"""HTTP client for the author's blog.

This is the bot -> site half of the contract; app/web/routes_site.py is the
site -> bot half. Both halves are documented in README.md.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import httpx

TIMEOUT = httpx.Timeout(20.0, connect=10.0)


class SiteError(RuntimeError):
    """The blog rejected the request or was unreachable."""


@dataclass(slots=True)
class CreatedPost:
    id: str
    url: str


class SiteClient:
    def __init__(self, api_base: str, api_token: str) -> None:
        self.api_base = api_base.rstrip("/")
        self._headers = {
            "Authorization": f"Bearer {api_token}",
            "User-Agent": "publish-channel-bot/0.1",
        }

    async def _request(self, method: str, path: str, **kwargs: Any) -> dict[str, Any]:
        url = f"{self.api_base}{path}"
        async with httpx.AsyncClient(timeout=TIMEOUT) as client:
            try:
                response = await client.request(method, url, headers=self._headers, **kwargs)
            except httpx.HTTPError as exc:
                raise SiteError(f"{method} {url} failed: {exc}") from exc
        if response.status_code >= 400:
            raise SiteError(f"{method} {url} returned {response.status_code}: {response.text[:300]}")
        try:
            return response.json()
        except ValueError as exc:
            raise SiteError(f"{method} {url} returned non-JSON body") from exc

    async def ping(self) -> dict[str, Any]:
        """Used during /connect to prove the base URL and token are valid."""
        return await self._request("GET", "/ping")

    async def set_webhook(self, url: str, secret: str) -> None:
        """Tells the blog where to announce posts published in its admin."""
        await self._request("PUT", "/webhook", json={"url": url, "secret": secret})

    async def create_post(
        self,
        *,
        external_id: str,
        title: str,
        html: str,
        media: list[dict[str, str]] | None = None,
        published_at: str | None = None,
        post_type: str = "article",
        telegraph_url: str | None = None,
        poll: dict[str, Any] | None = None,
    ) -> CreatedPost:
        payload = {
            "source": "telegram",
            "external_id": external_id,
            "title": title,
            "html": html,
            "media": media or [],
            "published_at": published_at,
            "post_type": post_type,
            "telegraph_url": telegraph_url,
        }
        if poll is not None:
            payload["poll"] = poll
        data = await self._request("POST", "/posts", json=payload)
        if "id" not in data or "url" not in data:
            raise SiteError("POST /posts must return {\"id\": ..., \"url\": ...}")
        return CreatedPost(id=str(data["id"]), url=str(data["url"]))

    async def update_post(self, post_id: str, *, title: str, html: str) -> None:
        await self._request(
            "PATCH", f"/posts/{post_id}", json={"source": "telegram", "title": title, "html": html}
        )

    async def pin_post(self, post_id: str) -> None:
        """The channel pinned this post's message; the site pins it too."""
        await self._request("POST", f"/posts/{post_id}/pin")

    async def set_channel_message(self, post_id: str, message_id: int) -> None:
        """Where an admin post was announced, so the site can link to it."""
        await self._request("PUT", f"/posts/{post_id}/channel-message", json={"message_id": message_id})

    async def set_reactions(self, post_id: str, reactions: list[dict[str, Any]]) -> None:
        """The channel's reaction counts for a post, replacing the last ones."""
        await self._request("PUT", f"/posts/{post_id}/reactions", json={"reactions": reactions})

    async def upload_media(self, filename: str, content: bytes, content_type: str) -> str:
        data = await self._request(
            "POST", "/media", files={"file": (filename, content, content_type)}
        )
        if "url" not in data:
            raise SiteError("POST /media must return {\"url\": ...}")
        return str(data["url"])
