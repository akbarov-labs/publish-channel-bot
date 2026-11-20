from __future__ import annotations

import os
import tempfile

from cryptography.fernet import Fernet

# Settings and the database engine are built at import time, so the
# environment has to be complete before anything from app/ is imported.
_TMPDIR = tempfile.mkdtemp(prefix="publish-bot-tests-")
os.environ.setdefault("BOT_TOKEN", "12345:test-token")
os.environ.setdefault("SECRET_KEY", Fernet.generate_key().decode())
os.environ.setdefault("PUBLIC_BASE_URL", "https://bot.test")
# The import path calls session_scope() on its own, so the fixture and the
# application must share one engine rather than each opening their own.
os.environ.setdefault("DATABASE_URL", f"sqlite+aiosqlite:///{_TMPDIR}/test.db")
# No reason to pace forwards in tests.
os.environ.setdefault("IMPORT_RATE_PER_SECOND", "1000")

import pytest_asyncio  # noqa: E402

from app.db.models import Base  # noqa: E402
from app.db.session import SessionLocal, engine  # noqa: E402


@pytest_asyncio.fixture
async def session():
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
        await conn.run_sync(Base.metadata.create_all)

    async with SessionLocal() as db:
        yield db


@pytest_asyncio.fixture(autouse=True)
async def offline_site(monkeypatch):
    """No test reaches a real blog; tests that inspect calls patch their own."""
    from app.services import sync
    from tests.factories import StubSiteClient

    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda _site: client)
    return client
