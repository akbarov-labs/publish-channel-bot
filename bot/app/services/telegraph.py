"""telegra.ph support — Telegram's own publishing tool.

Two uses:

* **Inbound** — a channel post that is just a telegra.ph link carries a full
  article behind it. Fetching the page lifts the 4096-character message cap.
* **Outbound** — mirroring a blog article to telegra.ph gives channel readers
  Instant View, so the whole piece renders natively inside Telegram.

Content is a DOM-ish JSON tree, not HTML, so both directions need a conversion.
"""

from __future__ import annotations

import re
from html import escape
from html.parser import HTMLParser
from typing import Any
from urllib.parse import urlparse

import httpx

API_BASE = "https://api.telegra.ph"
TIMEOUT = httpx.Timeout(20.0, connect=10.0)

TELEGRAPH_HOSTS = {"telegra.ph", "graph.org", "www.telegra.ph", "www.graph.org"}

# The exact tag set telegra.ph accepts; anything else is rejected by createPage.
ALLOWED_TAGS = {
    "a", "aside", "b", "blockquote", "br", "code", "em", "figcaption", "figure",
    "h3", "h4", "hr", "i", "iframe", "img", "li", "ol", "p", "pre", "s",
    "strong", "u", "ul", "video",
}
VOID_TAGS = {"br", "hr", "img", "video", "iframe"}
# Headings above h3 do not exist on telegra.ph.
TAG_ALIASES = {"h1": "h3", "h2": "h3", "h5": "h4", "h6": "h4", "div": "p", "section": "p", "del": "s", "ins": "u"}

LINK_RE = re.compile(r"https?://(?:www\.)?(?:telegra\.ph|graph\.org)/[^\s<>\"']+", re.IGNORECASE)


class TelegraphError(RuntimeError):
    pass


def find_telegraph_url(text: str) -> str | None:
    """Return the first telegra.ph URL in a message, if any."""
    match = LINK_RE.search(text or "")
    return match.group(0).rstrip(".,);") if match else None


def page_path(url: str) -> str:
    parsed = urlparse(url)
    if parsed.netloc.lower() not in TELEGRAPH_HOSTS:
        raise TelegraphError(f"{url} is not a telegra.ph page")
    return parsed.path.lstrip("/")


def nodes_to_html(nodes: Any) -> str:
    """telegra.ph content tree -> HTML for storage on the blog."""
    if isinstance(nodes, str):
        return escape(nodes)
    if isinstance(nodes, list):
        return "".join(nodes_to_html(node) for node in nodes)
    if not isinstance(nodes, dict):
        return ""

    tag = str(nodes.get("tag", ""))
    attrs = nodes.get("attrs") or {}
    inner = nodes_to_html(nodes.get("children") or [])

    rendered_attrs = "".join(
        f' {name}="{escape(str(value), quote=True)}"'
        for name, value in attrs.items()
        if value is not None
    )
    if tag in VOID_TAGS:
        return f"<{tag}{rendered_attrs}>"
    if not tag:
        return inner
    return f"<{tag}{rendered_attrs}>{inner}</{tag}>"


class _NodeBuilder(HTMLParser):
    """HTML -> telegra.ph content tree."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.root: list[Any] = []
        self.stack: list[dict[str, Any]] = []

    def _append(self, node: Any) -> None:
        if self.stack:
            self.stack[-1].setdefault("children", []).append(node)
        else:
            self.root.append(node)

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag = TAG_ALIASES.get(tag, tag)
        if tag not in ALLOWED_TAGS:
            return
        node: dict[str, Any] = {"tag": tag}
        kept = {
            name: value
            for name, value in attrs
            if name in {"href", "src"} and value
        }
        if kept:
            node["attrs"] = kept
        if tag in VOID_TAGS:
            self._append(node)
            return
        self._append(node)
        self.stack.append(node)

    def handle_endtag(self, tag: str) -> None:
        tag = TAG_ALIASES.get(tag, tag)
        if tag in VOID_TAGS or tag not in ALLOWED_TAGS:
            return
        for index in range(len(self.stack) - 1, -1, -1):
            if self.stack[index]["tag"] == tag:
                del self.stack[index:]
                return

    def handle_data(self, data: str) -> None:
        if data.strip():
            self._append(data)

    def result(self) -> list[Any]:
        # telegra.ph rejects bare strings at the top level, so wrap them.
        return [node if isinstance(node, dict) else {"tag": "p", "children": [node]} for node in self.root]


def html_to_nodes(html: str) -> list[Any]:
    builder = _NodeBuilder()
    builder.feed(html or "")
    builder.close()
    return builder.result() or [{"tag": "p", "children": [""]}]


class TelegraphClient:
    def __init__(self, access_token: str | None = None) -> None:
        self.access_token = access_token

    async def _call(self, method: str, payload: dict[str, Any]) -> dict[str, Any]:
        async with httpx.AsyncClient(timeout=TIMEOUT) as client:
            try:
                response = await client.post(f"{API_BASE}/{method}", json=payload)
            except httpx.HTTPError as exc:
                raise TelegraphError(f"{method} failed: {exc}") from exc
        data = response.json()
        if not data.get("ok"):
            raise TelegraphError(f"{method} rejected: {data.get('error')}")
        return data["result"]

    async def create_account(self, short_name: str, author_name: str) -> str:
        result = await self._call(
            "createAccount", {"short_name": short_name[:32], "author_name": author_name[:128]}
        )
        self.access_token = str(result["access_token"])
        return self.access_token

    async def get_page(self, path: str) -> dict[str, Any]:
        """Fetch a page including its content tree."""
        return await self._call("getPage", {"path": path, "return_content": True})

    async def create_page(
        self, *, title: str, html: str, author_name: str | None = None, author_url: str | None = None
    ) -> str:
        if not self.access_token:
            raise TelegraphError("an access token is required to publish")
        payload: dict[str, Any] = {
            "access_token": self.access_token,
            "title": title[:256] or "Untitled",
            "content": html_to_nodes(html),
        }
        if author_name:
            payload["author_name"] = author_name[:128]
        if author_url:
            payload["author_url"] = author_url
        result = await self._call("createPage", payload)
        return str(result["url"])


async def fetch_article(url: str) -> tuple[str, str]:
    """telegra.ph URL -> (title, article HTML)."""
    result = await TelegraphClient().get_page(page_path(url))
    return str(result.get("title") or "Untitled"), nodes_to_html(result.get("content") or [])
