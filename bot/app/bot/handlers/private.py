"""DM onboarding: an author connects their blog, then adds the bot to a channel."""

from __future__ import annotations

import logging
from datetime import datetime, timezone

from aiogram import F, Router
from aiogram.filters import Command, CommandStart
from aiogram.types import Message

from app.config import get_settings
from app.db import repo
from app.db.models import PendingConnection, Site
from app.db.session import session_scope
from app.services.crypto import encrypt, new_webhook_secret
from app.services.site_client import SiteClient, SiteError
from app.services.telegraph import TelegraphClient, TelegraphError

log = logging.getLogger(__name__)
router = Router(name="private")
router.message.filter(F.chat.type == "private")

WELCOME = (
    "<b>Blog ↔ Channel sync</b>\n\n"
    "I keep a Telegram channel and your blog in step, in both directions.\n\n"
    "<b>Setup</b>\n"
    "1. /connect — link your blog's API\n"
    "2. Add me to your channel as an admin with <i>Post messages</i>\n"
    "3. Send <code>/activate</code> in the channel\n"
    "4. /import — turn the posts already in your channel into a blog, "
    "each keeping the date it was first published\n\n"
    "Optional: /telegraph turns on Instant View for published articles.\n\n"
    "Then /status shows what is connected."
)


@router.message(CommandStart())
async def on_start(message: Message) -> None:
    async with session_scope() as session:
        await repo.get_or_create_author(
            session, message.from_user.id, message.from_user.username
        )
    await message.answer(WELCOME)


@router.message(Command("cancel"))
async def on_cancel(message: Message) -> None:
    async with session_scope() as session:
        await repo.clear_pending(session, message.from_user.id)
    await message.answer("Cancelled.")


@router.message(Command("connect"))
async def on_connect(message: Message) -> None:
    async with session_scope() as session:
        await repo.get_or_create_author(
            session, message.from_user.id, message.from_user.username
        )
        await session.merge(
            PendingConnection(telegram_user_id=message.from_user.id, step="api_base", api_base=None)
        )
    await message.answer(
        "Send your blog's API base URL, for example:\n"
        "<code>https://myblog.com/api/telegram</code>\n\n"
        "It must expose <code>GET /ping</code>, <code>POST /posts</code>, "
        "<code>PATCH /posts/{id}</code> and <code>POST /media</code>.\n"
        "/cancel to stop."
    )


@router.message(Command("status"))
async def on_status(message: Message) -> None:
    async with session_scope() as session:
        author = await repo.get_author(session, message.from_user.id)
        if author is None or not author.sites:
            await message.answer("No blog connected yet. Send /connect to start.")
            return
        lines: list[str] = []
        for site in author.sites:
            verified = "verified" if site.verified_at else "unverified"
            lines.append(f"<b>{site.api_base}</b> ({verified})")
            if not site.channels:
                lines.append("  └ no channel linked yet")
            for channel in site.channels:
                state = "active" if channel.active else "inactive"
                arrows = []
                if channel.sync_telegram_to_site:
                    arrows.append("TG→blog")
                if channel.sync_site_to_telegram:
                    arrows.append("blog→TG")
                lines.append(
                    f"  └ {channel.title or channel.telegram_chat_id} — {state}, {' + '.join(arrows) or 'paused'}"
                )
    await message.answer("\n".join(lines))


@router.message(Command("telegraph"))
async def on_telegraph(message: Message) -> None:
    """Create a telegra.ph account so published articles get Instant View."""
    async with session_scope() as session:
        site = await repo.latest_site_for_user(session, message.from_user.id)
        if site is None:
            await message.answer("Connect your blog first with /connect.")
            return
        if site.telegraph_token_encrypted:
            await message.answer("Instant View is already enabled for this blog.")
            return
        author_name = message.from_user.full_name or "Blog"
        try:
            token = await TelegraphClient().create_account(
                short_name=author_name, author_name=author_name
            )
        except TelegraphError as exc:
            log.warning("telegra.ph account creation failed: %s", exc)
            await message.answer(f"telegra.ph refused the request:\n<code>{exc}</code>")
            return
        site.telegraph_token_encrypted = encrypt(token)

    await message.answer(
        "✅ Instant View enabled.\n\n"
        "Published articles now get a telegra.ph mirror, so readers can open the "
        "full piece inside Telegram. The link to your own blog is still included."
    )


@router.message(F.text & ~F.text.startswith("/"))
async def on_connect_input(message: Message) -> None:
    """Handles the two free-text steps of /connect."""
    settings = get_settings()
    value = message.text.strip()

    async with session_scope() as session:
        pending = await repo.get_pending(session, message.from_user.id)
        if pending is None:
            await message.answer("Nothing to do. Try /connect or /status.")
            return

        if pending.step == "api_base":
            if not value.startswith(("http://", "https://")):
                await message.answer("That does not look like a URL. Send the full https:// address.")
                return
            pending.api_base = value.rstrip("/")
            pending.step = "api_token"
            await message.answer(
                "Now send the API token the bot should authenticate with.\n"
                "<i>Delete your message afterwards — I store it encrypted.</i>"
            )
            return

        if pending.step != "api_token":
            return

        api_base = pending.api_base or ""
        try:
            await SiteClient(api_base, value).ping()
        except SiteError as exc:
            log.warning("site verification failed for %s: %s", api_base, exc)
            await message.answer(
                f"Could not verify the blog:\n<code>{exc}</code>\n\n"
                "Send the token again, or /cancel."
            )
            return

        author = await repo.get_or_create_author(
            session, message.from_user.id, message.from_user.username
        )
        secret = new_webhook_secret()
        site = Site(
            author_id=author.id,
            api_base=api_base,
            api_token_encrypted=encrypt(value),
            webhook_secret=secret,
            verified_at=datetime.now(timezone.utc),
        )
        session.add(site)
        await repo.clear_pending(session, message.from_user.id)

    webhook_url = f"{settings.public_base_url.rstrip('/')}/webhooks/site/{secret}"
    await message.answer(
        "✅ Blog connected.\n\n"
        "Point your blog's publish webhook at:\n"
        f"<code>{webhook_url}</code>\n\n"
        "Sign the raw request body with HMAC-SHA256 using that same secret and send it as "
        "<code>X-Signature</code>.\n\n"
        "Next: add me to your channel as an admin, then send <code>/activate</code> there."
    )
