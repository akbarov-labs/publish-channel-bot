import pytest
from pydantic import ValidationError

from app.config import Settings

BASE = {"bot_token": "1:x", "secret_key": "k"}


def test_polling_mode_keeps_the_local_defaults():
    settings = Settings(**BASE, telegram_mode="polling")
    assert settings.telegram_webhook_secret == "change-me"


@pytest.mark.parametrize("secret", ["change-me", "replace-me", "short", "has spaces in it!!"])
def test_webhook_mode_refuses_a_placeholder_or_invalid_secret(secret):
    with pytest.raises(ValidationError, match="TELEGRAM_WEBHOOK_SECRET"):
        Settings(
            **BASE,
            telegram_mode="webhook",
            telegram_webhook_secret=secret,
            public_base_url="https://bot.example.uz",
        )


def test_webhook_mode_needs_an_https_address():
    with pytest.raises(ValidationError, match="PUBLIC_BASE_URL"):
        Settings(
            **BASE,
            telegram_mode="webhook",
            telegram_webhook_secret="a" * 32,
            public_base_url="http://bot.example.uz",
        )


def test_webhook_mode_with_real_settings_starts():
    settings = Settings(
        **BASE,
        telegram_mode="webhook",
        telegram_webhook_secret="0123456789abcdef" * 4,
        public_base_url="https://bot.example.uz",
    )
    assert settings.telegram_mode == "webhook"
