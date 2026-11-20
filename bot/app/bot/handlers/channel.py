"""Channel side: activation, and mirroring channel posts onto the blog."""

from __future__ import annotations

import html
import logging

from aiogram import Bot, F, Router
from aiogram.types import ChatMemberUpdated, Message

from app.db import repo
from app.db.models import WaitingChannel
from app.db.session import session_scope
from app.services.site_client import SiteError
from app.services.sync import channel_edit_to_site, channel_pin_to_site, channel_post_to_site

log = logging.getLogger(__name__)
router = Router(name="channel")

ADMIN_STATUSES = {"administrator", "creator"}


@router.my_chat_member(F.chat.type == "channel")
async def on_bot_membership_change(event: ChatMemberUpdated, bot: Bot) -> None:
    """Bind the channel to the blog of whoever added the bot."""
    status = event.new_chat_member.status
    if status not in ADMIN_STATUSES:
        async with session_scope() as session:
            channel = await repo.get_channel(session, event.chat.id)
            if channel is not None:
                channel.active = False
            waiting = await session.get(WaitingChannel, event.chat.id)
            if waiting is not None:
                await session.delete(waiting)
        log.info("bot lost admin in channel %s, sync paused", event.chat.id)
        return

    actor = event.from_user
    title = html.escape(event.chat.title or str(event.chat.id))
    async with session_scope() as session:
        site = await repo.latest_site_for_user(session, actor.id) if actor else None
        if site is None:
            existing = await repo.get_channel(session, event.chat.id)
            if existing is not None:
                # Still bound to whoever connected it; just back on.
                existing.active = True
                existing.title = event.chat.title
                return
            if actor is not None:
                await session.merge(
                    WaitingChannel(
                        telegram_chat_id=event.chat.id,
                        telegram_user_id=actor.id,
                        title=event.chat.title,
                    )
                )
            await _notify(
                bot,
                actor.id if actor else None,
                f"I'm in <b>{title}</b>. Send /connect here to link your blog, "
                "and syncing starts right after.",
            )
            return

        await repo.bind_channel(session, site, event.chat.id, event.chat.title)

    await _notify(
        bot,
        actor.id if actor else None,
        f"✅ Syncing is on in <b>{title}</b>: posts there appear on your page, and posts "
        "you publish in the admin appear in the channel.\n\n"
        "/import brings in the posts already in the channel.",
    )


@router.channel_post(F.text.in_({"/activate", "/deactivate", "/status"}))
async def on_channel_command(message: Message, bot: Bot) -> None:
    command = (message.text or "").strip()
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None:
            # Channel posts don't say who sent them, so the owner is unknown.
            log.info("command %s in unlinked channel %s", command, message.chat.id)
            await bot.send_message(
                message.chat.id,
                "I don't know whose channel this is yet. Remove me from the admins and "
                "add me again, and I'll write to you.",
            )
            return
        if command == "/activate":
            channel.active = True
            channel.title = message.chat.title
            note = "✅ Sync is on — posts here become blog posts, and new blog posts appear here."
        elif command == "/deactivate":
            channel.active = False
            note = "⏸ Sync paused."
        else:
            note = f"Sync is {'on' if channel.active else 'paused'} for {channel.site.api_base}."
    await bot.send_message(message.chat.id, note)


@router.channel_post(F.pinned_message)
async def on_channel_pin(message: Message) -> None:
    """A pinned message in the channel pins its post on the site."""
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None or message.pinned_message is None:
            return
        try:
            await channel_pin_to_site(session, channel, message.pinned_message.message_id)
        except SiteError as exc:
            log.error("channel %s pin -> site failed: %s", message.chat.id, exc)


@router.channel_post()
async def on_channel_post(message: Message, bot: Bot, album: list[Message] | None = None) -> None:
    messages = album or [message]
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None:
            return
        try:
            await channel_post_to_site(session, bot, channel, messages)
        except SiteError as exc:
            log.error("channel %s -> site failed: %s", message.chat.id, exc)


@router.edited_channel_post()
async def on_channel_post_edited(message: Message) -> None:
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None:
            return
        try:
            await channel_edit_to_site(session, channel, message)
        except SiteError as exc:
            log.error("channel %s edit -> site failed: %s", message.chat.id, exc)


async def _notify(bot: Bot, user_id: int | None, text: str) -> None:
    """Best effort DM; the user may not have started the bot."""
    if user_id is None:
        return
    try:
        await bot.send_message(user_id, text)
    except Exception as exc:  # noqa: BLE001 - a blocked DM must not break the update
        log.info("could not DM %s: %s", user_id, exc)
