"""Collects the separate updates of a Telegram album into one list."""

from __future__ import annotations

import asyncio
from collections.abc import Awaitable, Callable
from typing import Any

from aiogram import BaseMiddleware
from aiogram.types import Message


class AlbumMiddleware(BaseMiddleware):
    """Telegram delivers an album as N updates sharing a media_group_id.

    The first update for a group becomes the leader: it waits `delay` seconds
    for its siblings, then invokes the handler once with the whole album.
    Later arrivals return immediately without invoking the handler.
    """

    def __init__(self, delay: float = 2.0) -> None:
        self.delay = delay
        self._albums: dict[str, list[Message]] = {}
        self._lock = asyncio.Lock()

    async def __call__(
        self,
        handler: Callable[[Message, dict[str, Any]], Awaitable[Any]],
        event: Message,
        data: dict[str, Any],
    ) -> Any:
        if event.media_group_id is None:
            data["album"] = [event]
            return await handler(event, data)

        group_id = event.media_group_id
        async with self._lock:
            group = self._albums.setdefault(group_id, [])
            group.append(event)
            is_leader = len(group) == 1

        if not is_leader:
            return None

        await asyncio.sleep(self.delay)
        async with self._lock:
            album = self._albums.pop(group_id, [event])

        album.sort(key=lambda message: message.message_id)
        data["album"] = album
        return await handler(album[0], data)
