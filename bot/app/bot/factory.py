from __future__ import annotations

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode

from app.bot.handlers import channel, importing, private
from app.bot.middlewares.album import AlbumMiddleware
from app.config import get_settings


def create_bot() -> Bot:
    settings = get_settings()
    return Bot(
        token=settings.bot_token,
        default=DefaultBotProperties(parse_mode=ParseMode.HTML),
    )


def create_dispatcher() -> Dispatcher:
    settings = get_settings()
    dispatcher = Dispatcher()
    album = AlbumMiddleware(delay=settings.album_debounce_seconds)
    channel.router.channel_post.middleware(album)
    # importing first: its /import channel_post filter must win over the
    # catch-all channel post handler.
    dispatcher.include_router(importing.router)
    dispatcher.include_router(private.router)
    dispatcher.include_router(channel.router)
    return dispatcher
