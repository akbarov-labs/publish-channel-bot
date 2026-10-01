"""Channel side: activation, and mirroring channel posts onto the blog."""

from __future__ import annotations

import logging

from aiogram import Bot, F, Router
from aiogram.types import ChatMemberUpdated, Message

from app.db import repo
from app.db.models import Channel
from app.db.session import session_scope
from app.services.site_client import SiteError
from app.services.sync import channel_edit_to_site, channel_post_to_site

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
        log.info("bot lost admin in channel %s, sync paused", event.chat.id)
        return

    actor = event.from_user
    async with session_scope() as session:
        channel = await repo.get_channel(session, event.chat.id)
        if channel is not None:
            channel.title = event.chat.title
            return

        site = await repo.latest_site_for_user(session, actor.id) if actor else None
        if site is None:
            await _notify(bot, actor.id if actor else None,
                          "I was added to a channel, but you have not connected a blog yet. "
                          "Send /connect here first, then send /activate in the channel.")
            return

        session.add(
            Channel(
                site_id=site.id,
                telegram_chat_id=event.chat.id,
                title=event.chat.title,
                active=False,
            )
        )

    await _notify(
        bot,
        actor.id if actor else None,
        f"Linked <b>{event.chat.title}</b> to your blog.\n"
        "Send <code>/activate</code> in the channel to start syncing.",
    )


@router.channel_post(F.text.in_({"/activate", "/deactivate", "/status"}))
async def on_channel_command(message: Message, bot: Bot) -> None:
    command = (message.text or "").strip()
    async with session_scope() as session:
        channel = await repo.get_channel(session, message.chat.id)
        if channel is None:
            log.info("command %s in unlinked channel %s", command, message.chat.id)
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
