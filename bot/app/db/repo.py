"""Query helpers. Relationships are eager-loaded because lazy loads raise under asyncio."""

from __future__ import annotations

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.db.models import Author, Channel, ImportJob, PendingConnection, Site


async def get_or_create_author(
    session: AsyncSession, telegram_user_id: int, username: str | None
) -> Author:
    result = await session.execute(
        select(Author).where(Author.telegram_user_id == telegram_user_id)
    )
    author = result.scalar_one_or_none()
    if author is None:
        author = Author(telegram_user_id=telegram_user_id, username=username)
        session.add(author)
        await session.flush()
    elif username and author.username != username:
        author.username = username
    return author


async def get_author(session: AsyncSession, telegram_user_id: int) -> Author | None:
    result = await session.execute(
        select(Author)
        .where(Author.telegram_user_id == telegram_user_id)
        .options(selectinload(Author.sites).selectinload(Site.channels))
    )
    return result.scalar_one_or_none()


async def latest_site_for_user(session: AsyncSession, telegram_user_id: int) -> Site | None:
    result = await session.execute(
        select(Site)
        .join(Author, Author.id == Site.author_id)
        .where(Author.telegram_user_id == telegram_user_id)
        .order_by(Site.created_at.desc())
        .limit(1)
    )
    return result.scalar_one_or_none()


async def get_channel(session: AsyncSession, telegram_chat_id: int) -> Channel | None:
    result = await session.execute(
        select(Channel)
        .where(Channel.telegram_chat_id == telegram_chat_id)
        .options(selectinload(Channel.site))
    )
    return result.scalar_one_or_none()


async def get_site_by_webhook_secret(session: AsyncSession, secret: str) -> Site | None:
    result = await session.execute(
        select(Site).where(Site.webhook_secret == secret).options(selectinload(Site.channels))
    )
    return result.scalar_one_or_none()


async def get_pending(session: AsyncSession, telegram_user_id: int) -> PendingConnection | None:
    return await session.get(PendingConnection, telegram_user_id)


async def clear_pending(session: AsyncSession, telegram_user_id: int) -> None:
    pending = await session.get(PendingConnection, telegram_user_id)
    if pending is not None:
        await session.delete(pending)


async def channels_for_user(session: AsyncSession, telegram_user_id: int) -> list[Channel]:
    result = await session.execute(
        select(Channel)
        .join(Site, Site.id == Channel.site_id)
        .join(Author, Author.id == Site.author_id)
        .where(Author.telegram_user_id == telegram_user_id)
        .order_by(Channel.created_at)
    )
    return list(result.scalars().all())


async def author_for_channel(session: AsyncSession, channel: Channel) -> Author | None:
    result = await session.execute(
        select(Author)
        .join(Site, Site.author_id == Author.id)
        .where(Site.id == channel.site_id)
    )
    return result.scalar_one_or_none()


async def unfinished_import(session: AsyncSession, channel_id: int) -> ImportJob | None:
    result = await session.execute(
        select(ImportJob)
        .where(ImportJob.channel_id == channel_id, ImportJob.status.in_(("pending", "running")))
        .order_by(ImportJob.created_at.desc())
        .limit(1)
    )
    return result.scalar_one_or_none()


async def latest_import(session: AsyncSession, channel_id: int) -> ImportJob | None:
    result = await session.execute(
        select(ImportJob)
        .where(ImportJob.channel_id == channel_id)
        .order_by(ImportJob.created_at.desc())
        .limit(1)
    )
    return result.scalar_one_or_none()
