from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from app.db.models import Author, Channel, Site
from app.services.crypto import encrypt


async def make_channel(session, *, chat_id: int = -1001, active: bool = True) -> Channel:
    author = Author(telegram_user_id=42, username="blogger")
    session.add(author)
    await session.flush()
    site = Site(
        author_id=author.id,
        api_base="https://blog.test/api",
        api_token_encrypted=encrypt("token"),
        webhook_secret="secret-123",
    )
    session.add(site)
    await session.flush()
    channel = Channel(site_id=site.id, telegram_chat_id=chat_id, title="My channel", active=active)
    session.add(channel)
    await session.flush()
    return channel


@dataclass
class SentMessage:
    message_id: int


@dataclass
class StubBot:
    """Records outgoing calls instead of talking to Telegram."""

    sent: list[dict[str, Any]] = field(default_factory=list)
    next_message_id: int = 500

    async def send_message(self, chat_id: int, text: str, **kwargs: Any) -> SentMessage:
        self.sent.append({"kind": "message", "chat_id": chat_id, "text": text, **kwargs})
        self.next_message_id += 1
        return SentMessage(self.next_message_id)

    async def send_photo(self, chat_id: int, photo: str, caption: str, **kwargs: Any) -> SentMessage:
        self.sent.append({"kind": "photo", "chat_id": chat_id, "photo": photo, "text": caption})
        self.next_message_id += 1
        return SentMessage(self.next_message_id)

    async def download(self, file_id: str, destination: Any) -> None:
        destination.write(b"fake-bytes")


@dataclass
class StubPhoto:
    file_id: str = "file-1"
    file_unique_id: str = "uniq-1"
    file_size: int = 1024


@dataclass
class StubSiteClient:
    """Stands in for the author's blog."""

    created: list[dict[str, Any]] = field(default_factory=list)
    uploaded: list[str] = field(default_factory=list)

    async def upload_media(self, filename: str, content: bytes, mime: str) -> str:
        self.uploaded.append(filename)
        return f"https://cdn.blog.test/{filename}"

    async def create_post(self, **kwargs: Any) -> Any:
        self.created.append(kwargs)
        from app.services.site_client import CreatedPost

        return CreatedPost(id=f"p-{len(self.created)}", url="https://blog.test/p/1")

    async def update_post(self, post_id: str, **kwargs: Any) -> None:
        self.created.append({"update": post_id, **kwargs})

    async def pin_post(self, post_id: str) -> None:
        self.created.append({"pin": post_id})

    async def set_channel_message(self, post_id: str, message_id: int) -> None:
        self.created.append({"channel_message": post_id, "message_id": message_id})

    async def set_reactions(self, post_id: str, reactions: list[dict[str, Any]]) -> None:
        self.created.append({"reactions": post_id, "counts": reactions})


@dataclass
class StubOrigin:
    """Stands in for MessageOriginChannel on a forwarded message."""

    date: Any
    message_id: int


@dataclass
class StubMessage:
    """Only the attributes the sync and import paths read."""

    message_id: int
    text: str | None = None
    caption: str | None = None
    html: str = ""
    photo: list[Any] = field(default_factory=list)
    video: Any = None
    document: Any = None
    animation: Any = None
    date: Any = None
    media_group_id: str | None = None
    forward_origin: Any = None

    @property
    def html_text(self) -> str:
        return self.html or (self.text or "")
