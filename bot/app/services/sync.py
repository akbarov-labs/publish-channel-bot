"""The two sync directions, plus the echo-loop guard that keeps them apart."""

from __future__ import annotations

import logging
import time
from datetime import datetime
from io import BytesIO
from typing import Any, Iterable

from aiogram import Bot
from aiogram.types import Message
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import get_settings
from app.db.models import Channel, PostLink, Site
from app.services.crypto import content_hash, decrypt
from app.services.renderer import build_channel_post, derive_title, telegram_html_to_article
from app.services.site_client import SiteClient, SiteError
from app.services.telegraph import (
    TelegraphClient,
    TelegraphError,
    fetch_article,
    find_telegraph_url,
)

log = logging.getLogger(__name__)

# Bots can only download files up to 20 MB through the Bot API.
MAX_DOWNLOAD_BYTES = 20 * 1024 * 1024
# Telegram truncates photo captions at 1024 characters.
CAPTION_BUDGET = 700


class _RecentlySent:
    """In-process fast guard against the bot reacting to its own channel posts.

    The PostLink row is the durable guard; this only closes the sub-second race
    between sending a message and committing its row. With more than one worker
    process, each has its own copy - which is why the DB check still runs.
    """

    def __init__(self, ttl_seconds: float = 120.0) -> None:
        self._ttl = ttl_seconds
        self._seen: dict[tuple[int, int], float] = {}

    def add(self, chat_id: int, message_id: int) -> None:
        self._seen[(chat_id, message_id)] = time.monotonic()

    def contains(self, chat_id: int, message_id: int) -> bool:
        self._prune()
        return (chat_id, message_id) in self._seen

    def _prune(self) -> None:
        cutoff = time.monotonic() - self._ttl
        for key, stamp in list(self._seen.items()):
            if stamp < cutoff:
                del self._seen[key]


recently_sent = _RecentlySent()


def site_client_for(site: Site) -> SiteClient:
    return SiteClient(site.api_base, decrypt(site.api_token_encrypted))


async def link_for_message(session: AsyncSession, channel_id: int, tg_message_id: int) -> PostLink | None:
    result = await session.execute(
        select(PostLink).where(
            PostLink.channel_id == channel_id, PostLink.tg_message_id == tg_message_id
        )
    )
    return result.scalar_one_or_none()


async def link_for_post(session: AsyncSession, channel_id: int, site_post_id: str) -> PostLink | None:
    result = await session.execute(
        select(PostLink).where(
            PostLink.channel_id == channel_id, PostLink.site_post_id == site_post_id
        )
    )
    return result.scalar_one_or_none()


def _plain_text(messages: Iterable[Message]) -> str:
    for message in messages:
        if message.text or message.caption:
            return message.text or message.caption or ""
    return ""


def _post_type(messages: list[Message]) -> str:
    """An album of two or more photos becomes a carousel post on the blog."""
    if any(message.poll for message in messages):
        return "poll"
    photos = [message for message in messages if message.photo]
    if len(photos) >= 2:
        return "gallery"
    if any(message.video for message in messages):
        return "video"
    if any(message.audio or message.voice for message in messages):
        return "audio"
    return "article"


def _poll(messages: Iterable[Message]) -> dict[str, Any] | None:
    """A channel poll as the site shows it. Counts are a snapshot: the Bot API
    only reports later votes on polls the bot sent itself."""
    for message in messages:
        poll = message.poll
        if poll:
            return {
                "question": poll.question,
                "options": [{"text": option.text, "votes": option.voter_count} for option in poll.options],
                "total_votes": poll.total_voter_count,
                "closed": bool(poll.is_closed),
                "multiple": bool(poll.allows_multiple_answers),
                "quiz": poll.type == "quiz",
            }
    return None


def _audio_title(messages: Iterable[Message]) -> str | None:
    """A track's own name, for an audio post with no caption."""
    for message in messages:
        audio = message.audio
        if audio and (audio.title or audio.file_name):
            name = audio.title or audio.file_name
            return f"{audio.performer} — {name}" if audio.performer else name
    return None


def _message_html(messages: Iterable[Message]) -> str:
    """Telegram entities -> HTML. In an album only one message carries the text."""
    for message in messages:
        if message.text or message.caption:
            return message.html_text
    return ""


async def _mirror_to_telegraph(
    site: Site, title: str, body_html: str, canonical_url: str
) -> str | None:
    """Publish a telegra.ph copy so channel readers get Instant View.

    Best effort: a telegra.ph outage must not hold up the channel post.
    """
    if site is None or not site.telegraph_token_encrypted:
        return None
    try:
        client = TelegraphClient(decrypt(site.telegraph_token_encrypted))
        return await client.create_page(
            title=title, html=body_html, author_url=canonical_url or None
        )
    except TelegraphError as exc:
        log.warning("telegra.ph mirror failed for %r: %s", title, exc)
        return None


async def _upload_media(bot: Bot, client: SiteClient, messages: Iterable[Message]) -> list[dict[str, str]]:
    uploaded: list[dict[str, str]] = []
    for message in messages:
        if message.photo:
            photo = message.photo[-1]
            spec = ("photo", photo.file_id, photo.file_size, f"{photo.file_unique_id}.jpg", "image/jpeg")
        elif message.video:
            video = message.video
            spec = (
                "video",
                video.file_id,
                video.file_size,
                video.file_name or f"{video.file_unique_id}.mp4",
                video.mime_type or "video/mp4",
            )
        elif message.audio:
            audio = message.audio
            spec = (
                "audio",
                audio.file_id,
                audio.file_size,
                audio.file_name or f"{audio.file_unique_id}.mp3",
                audio.mime_type or "audio/mpeg",
            )
        elif message.voice:
            voice = message.voice
            spec = (
                "audio",
                voice.file_id,
                voice.file_size,
                f"{voice.file_unique_id}.ogg",
                voice.mime_type or "audio/ogg",
            )
        elif message.document:
            doc = message.document
            spec = (
                "document",
                doc.file_id,
                doc.file_size,
                doc.file_name or doc.file_unique_id,
                doc.mime_type or "application/octet-stream",
            )
        else:
            continue

        kind, file_id, size, filename, mime = spec
        if size and size > MAX_DOWNLOAD_BYTES:
            log.warning("skipping %s: %s bytes exceeds the Bot API download limit", filename, size)
            continue
        buffer = BytesIO()
        await bot.download(file_id, destination=buffer)
        url = await client.upload_media(filename, buffer.getvalue(), mime)
        uploaded.append({"type": kind, "url": url})
    return uploaded


async def channel_post_to_site(
    session: AsyncSession,
    bot: Bot,
    channel: Channel,
    messages: list[Message],
    origin_message_id: int | None = None,
    origin_date: datetime | None = None,
) -> PostLink | None:
    """Telegram channel post (or album) -> a new post on the author's blog.

    During a history import the messages handed in are forwarded copies, so
    ``origin_message_id`` and ``origin_date`` carry the identity and the real
    publication date of the post as it stands in the channel.
    """
    primary = messages[0]
    message_id = origin_message_id if origin_message_id is not None else primary.message_id
    published_at = origin_date or primary.date

    if not channel.active or not channel.sync_telegram_to_site:
        return None
    if recently_sent.contains(channel.telegram_chat_id, message_id):
        return None
    if await link_for_message(session, channel.id, message_id):
        return None  # already synced, or we published it ourselves

    html = _message_html(messages)
    poll = _poll(messages)
    if not html and not poll and not any(m.photo or m.video or m.document or m.audio or m.voice for m in messages):
        return None  # nothing publishable (service message, sticker...)

    site = await session.get(Site, channel.site_id)
    client = site_client_for(site)
    media = await _upload_media(bot, client, messages)
    fallback = (poll or {}).get("question") or _audio_title(messages) or channel.title or "Channel post"
    title = derive_title(html, fallback=fallback)
    body = telegram_html_to_article(html)

    # A telegra.ph link carries the real article; the message is just a teaser.
    telegraph_url = find_telegraph_url(_plain_text(messages))
    if telegraph_url:
        try:
            page_title, page_html = await fetch_article(telegraph_url)
            title, body = page_title, page_html
            log.info("pulled full article from %s", telegraph_url)
        except TelegraphError as exc:
            log.warning("could not read %s, keeping the message text: %s", telegraph_url, exc)

    created = await client.create_post(
        external_id=f"tg:{channel.telegram_chat_id}:{message_id}",
        title=title,
        html=body,
        media=media,
        published_at=published_at.isoformat() if published_at else None,
        post_type=_post_type(messages),
        telegraph_url=telegraph_url,
        poll=poll,
    )
    link = PostLink(
        channel_id=channel.id,
        tg_message_id=message_id,
        site_post_id=created.id,
        origin="telegram",
        content_hash=content_hash(title, body),
    )
    session.add(link)
    await session.flush()
    log.info("channel %s post %s -> site post %s", channel.telegram_chat_id, message_id, created.id)
    return link


async def channel_edit_to_site(session: AsyncSession, channel: Channel, message: Message) -> bool:
    """Mirror an edited channel post onto the blog."""
    link = await link_for_message(session, channel.id, message.message_id)
    if not link or not link.site_post_id or link.origin != "telegram":
        return False
    html = _message_html([message])
    title = derive_title(html, fallback=channel.title or "Channel post")
    body = telegram_html_to_article(html)
    digest = content_hash(title, body)
    if digest == link.content_hash:
        return False
    site = await session.get(Site, channel.site_id)
    await site_client_for(site).update_post(link.site_post_id, title=title, html=body)
    link.content_hash = digest
    await session.flush()
    return True


async def channel_pin_to_site(session: AsyncSession, channel: Channel, pinned_message_id: int) -> bool:
    """A message pinned in the channel pins its post on the author's page.

    Telegram reports pins but not unpins, so the site keeps the last pin until
    another post is pinned, here or in the admin.
    """
    if not channel.active:
        return False
    link = await link_for_message(session, channel.id, pinned_message_id)
    if not link or not link.site_post_id:
        return False
    site = await session.get(Site, channel.site_id)
    await site_client_for(site).pin_post(link.site_post_id)
    return True


async def channel_reactions_to_site(
    session: AsyncSession, channel: Channel, message_id: int, reactions: list[dict[str, Any]]
) -> bool:
    """The reaction counts on a channel post, mirrored onto its page."""
    if not channel.active:
        return False
    link = await link_for_message(session, channel.id, message_id)
    if not link or not link.site_post_id:
        return False
    site = await session.get(Site, channel.site_id)
    await site_client_for(site).set_reactions(link.site_post_id, reactions)
    return True


async def site_post_to_channel(
    session: AsyncSession,
    bot: Bot,
    channel: Channel,
    post: dict[str, Any],
    site: Site | None = None,
) -> PostLink | None:
    """A post published on the blog -> a message in the Telegram channel."""
    if not channel.active or not channel.sync_site_to_telegram:
        return None
    if str(post.get("source", "")).lower() == "telegram":
        return None  # the blog is echoing back something that came from Telegram
    site_post_id = str(post.get("id") or "")
    if not site_post_id:
        raise ValueError("webhook payload is missing post.id")
    if await link_for_post(session, channel.id, site_post_id):
        return None  # already mirrored

    settings = get_settings()
    title = str(post.get("title") or "Untitled")
    url = str(post.get("url") or "")
    body_html = str(post.get("html") or post.get("excerpt") or "")
    cover = post.get("cover_image")

    budget = CAPTION_BUDGET if cover else settings.channel_excerpt_chars
    effective_site = site or await session.get(Site, channel.site_id)
    instant_view_url = await _mirror_to_telegraph(effective_site, title, body_html, url)
    text = build_channel_post(
        title, body_html, url, budget=budget, instant_view_url=instant_view_url
    )

    if cover:
        sent = await bot.send_photo(channel.telegram_chat_id, photo=str(cover), caption=text)
    else:
        sent = await bot.send_message(
            channel.telegram_chat_id, text, link_preview_options={"is_disabled": not (instant_view_url or url)}
        )

    recently_sent.add(channel.telegram_chat_id, sent.message_id)

    # The site links to the post in the channel and shows its comments there.
    # Best effort: the announcement is out either way.
    try:
        await site_client_for(effective_site).set_channel_message(site_post_id, sent.message_id)
    except SiteError as exc:
        log.warning("could not tell the site where post %s was announced: %s", site_post_id, exc)

    link = PostLink(
        channel_id=channel.id,
        tg_message_id=sent.message_id,
        site_post_id=site_post_id,
        origin="site",
        content_hash=content_hash(title, body_html),
    )
    session.add(link)
    await session.flush()
    log.info("site post %s -> channel %s message %s", site_post_id, channel.telegram_chat_id, sent.message_id)
    return link


__all__ = [
    "SiteError",
    "channel_edit_to_site",
    "channel_pin_to_site",
    "channel_reactions_to_site",
    "channel_post_to_site",
    "link_for_message",
    "link_for_post",
    "recently_sent",
    "site_client_for",
    "site_post_to_channel",
]
