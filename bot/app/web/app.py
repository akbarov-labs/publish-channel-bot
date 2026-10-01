"""One ASGI app serving both webhook ends: Telegram in, and the blog in.

In polling mode the Telegram side runs as a background task instead, so the
service is a single container either way.
"""

from __future__ import annotations

import asyncio
import json
import logging
from contextlib import asynccontextmanager
from typing import Any

from aiogram.types import Update
from fastapi import BackgroundTasks, FastAPI, Header, HTTPException, Request, Response

from app.bot.factory import create_bot, create_dispatcher
from app.config import get_settings
from app.db import repo
from app.db.session import init_models, session_scope
from app.services.crypto import verify_signature
from app.services.site_client import SiteError
from app.services.sync import site_post_to_channel

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger(__name__)

settings = get_settings()
bot = create_bot()
dispatcher = create_dispatcher()

PUBLISH_EVENTS = {"post.published", "post.updated", "published"}


@asynccontextmanager
async def lifespan(_: FastAPI):
    await init_models()
    polling_task: asyncio.Task[Any] | None = None

    if settings.telegram_mode == "webhook":
        url = f"{settings.public_base_url.rstrip('/')}{settings.telegram_webhook_path}"
        await bot.set_webhook(
            url,
            secret_token=settings.telegram_webhook_secret,
            allowed_updates=dispatcher.resolve_used_update_types(),
            drop_pending_updates=True,
        )
        log.info("telegram webhook set to %s", url)
    else:
        await bot.delete_webhook(drop_pending_updates=True)
        polling_task = asyncio.create_task(
            dispatcher.start_polling(
                bot, allowed_updates=dispatcher.resolve_used_update_types()
            )
        )
        log.info("telegram long polling started")

    try:
        yield
    finally:
        if polling_task is not None:
            await dispatcher.stop_polling()
            polling_task.cancel()
        await bot.session.close()


app = FastAPI(title="publish-channel-bot", lifespan=lifespan)


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/telegram/webhook")
async def telegram_webhook(
    request: Request,
    background: BackgroundTasks,
    x_telegram_bot_api_secret_token: str | None = Header(default=None),
) -> Response:
    if x_telegram_bot_api_secret_token != settings.telegram_webhook_secret:
        raise HTTPException(status_code=403, detail="bad secret token")
    update = Update.model_validate(await request.json(), context={"bot": bot})
    # Answer Telegram immediately; an album handler waits a couple of seconds.
    background.add_task(_feed_update, update)
    return Response(status_code=202)


async def _feed_update(update: Update) -> None:
    try:
        await dispatcher.feed_update(bot, update)
    except Exception:  # noqa: BLE001 - one bad update must not kill the worker
        log.exception("failed to process update %s", update.update_id)


@app.post("/webhooks/site/{secret}")
async def site_webhook(
    secret: str,
    request: Request,
    x_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    raw = await request.body()

    async with session_scope() as session:
        site = await repo.get_site_by_webhook_secret(session, secret)
        if site is None:
            raise HTTPException(status_code=404, detail="unknown webhook")
        if not verify_signature(site.webhook_secret, raw, x_signature):
            raise HTTPException(status_code=401, detail="bad signature")

        try:
            payload = json.loads(raw)
        except ValueError as exc:
            raise HTTPException(status_code=400, detail="body is not JSON") from exc

        event = str(payload.get("event", "post.published"))
        if event not in PUBLISH_EVENTS:
            return {"status": "ignored", "reason": f"event {event} is not a publish event"}

        post = payload.get("post") or payload
        delivered: list[int] = []
        for channel in site.channels:
            if not channel.active or not channel.sync_site_to_telegram:
                continue
            try:
                link = await site_post_to_channel(session, bot, channel, post, site=site)
            except (SiteError, ValueError) as exc:
                log.error("site post -> channel %s failed: %s", channel.telegram_chat_id, exc)
                raise HTTPException(status_code=502, detail=str(exc)) from exc
            if link is not None:
                delivered.append(channel.telegram_chat_id)

    return {"status": "ok", "delivered_to": delivered}
