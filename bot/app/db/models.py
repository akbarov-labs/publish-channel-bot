from datetime import datetime

from sqlalchemy import (
    BigInteger,
    Boolean,
    DateTime,
    ForeignKey,
    String,
    Text,
    UniqueConstraint,
    func,
)
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship


class Base(DeclarativeBase):
    pass


class Author(Base):
    """One blogger using the service."""

    __tablename__ = "authors"

    id: Mapped[int] = mapped_column(primary_key=True)
    telegram_user_id: Mapped[int] = mapped_column(BigInteger, unique=True, index=True)
    username: Mapped[str | None] = mapped_column(String(64))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    sites: Mapped[list["Site"]] = relationship(back_populates="author")


class Site(Base):
    """An author's blog. api_token is stored encrypted at rest."""

    __tablename__ = "sites"

    id: Mapped[int] = mapped_column(primary_key=True)
    author_id: Mapped[int] = mapped_column(ForeignKey("authors.id", ondelete="CASCADE"), index=True)
    api_base: Mapped[str] = mapped_column(String(512))
    api_token_encrypted: Mapped[bytes] = mapped_column()
    # Shared secret the site signs its outgoing webhooks with (HMAC-SHA256).
    webhook_secret: Mapped[str] = mapped_column(String(64), unique=True, index=True)
    # telegra.ph account used to mirror articles for Instant View.
    telegraph_token_encrypted: Mapped[bytes | None] = mapped_column(nullable=True)
    verified_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    author: Mapped[Author] = relationship(back_populates="sites")
    channels: Mapped[list["Channel"]] = relationship(back_populates="site")


class Channel(Base):
    """A Telegram channel bound to a site."""

    __tablename__ = "channels"

    id: Mapped[int] = mapped_column(primary_key=True)
    site_id: Mapped[int] = mapped_column(ForeignKey("sites.id", ondelete="CASCADE"), index=True)
    telegram_chat_id: Mapped[int] = mapped_column(BigInteger, unique=True, index=True)
    title: Mapped[str | None] = mapped_column(String(255))
    active: Mapped[bool] = mapped_column(Boolean, default=False)
    sync_telegram_to_site: Mapped[bool] = mapped_column(Boolean, default=True)
    sync_site_to_telegram: Mapped[bool] = mapped_column(Boolean, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    site: Mapped[Site] = relationship(back_populates="channels")


class PostLink(Base):
    """
    The echo-loop guard. Every synced post gets exactly one row here, so a post
    that originated on one side is never re-published back to its own origin.
    """

    __tablename__ = "post_links"
    __table_args__ = (
        UniqueConstraint("channel_id", "tg_message_id", name="uq_link_tg"),
        UniqueConstraint("channel_id", "site_post_id", name="uq_link_site"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    channel_id: Mapped[int] = mapped_column(ForeignKey("channels.id", ondelete="CASCADE"), index=True)
    tg_message_id: Mapped[int | None] = mapped_column(BigInteger)
    site_post_id: Mapped[str | None] = mapped_column(String(128))
    # "telegram" or "site" - which side the content was authored on.
    origin: Mapped[str] = mapped_column(String(16))
    content_hash: Mapped[str | None] = mapped_column(String(64))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now()
    )


class WaitingChannel(Base):
    """A channel the bot was made admin of before its owner connected a blog.

    Bound to the blog, and switched on, as soon as that owner connects.
    """

    __tablename__ = "waiting_channels"

    telegram_chat_id: Mapped[int] = mapped_column(BigInteger, primary_key=True)
    telegram_user_id: Mapped[int] = mapped_column(BigInteger, index=True)
    title: Mapped[str | None] = mapped_column(String(255))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())


class PendingConnection(Base):
    """Scratch state for the /connect conversation in DM."""

    __tablename__ = "pending_connections"

    telegram_user_id: Mapped[int] = mapped_column(BigInteger, primary_key=True)
    step: Mapped[str] = mapped_column(String(32))
    api_base: Mapped[str | None] = mapped_column(Text)


class ImportJob(Base):
    """A run that walks a channel's history and turns it into blog posts.

    The Bot API cannot read history, so the import forwards each message id to
    the author's own chat, reads the copy, and deletes it again. The cursor is
    persisted so a restart resumes instead of starting over.
    """

    __tablename__ = "import_jobs"

    id: Mapped[int] = mapped_column(primary_key=True)
    channel_id: Mapped[int] = mapped_column(ForeignKey("channels.id", ondelete="CASCADE"), index=True)
    # Chat the messages are forwarded through - the author's private chat.
    staging_chat_id: Mapped[int] = mapped_column(BigInteger)

    # pending | running | done | failed | cancelled
    status: Mapped[str] = mapped_column(String(16), default="pending", index=True)
    first_message_id: Mapped[int] = mapped_column(default=1)
    last_message_id: Mapped[int] = mapped_column(default=1)
    cursor: Mapped[int] = mapped_column(default=1)

    imported: Mapped[int] = mapped_column(default=0)
    skipped: Mapped[int] = mapped_column(default=0)
    failed: Mapped[int] = mapped_column(default=0)
    error: Mapped[str | None] = mapped_column(Text)

    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    finished_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))

    @property
    def total(self) -> int:
        return max(self.last_message_id - self.first_message_id + 1, 1)

    @property
    def progress_percent(self) -> int:
        done = max(self.cursor - self.first_message_id, 0)
        return min(int(done / self.total * 100), 100)
