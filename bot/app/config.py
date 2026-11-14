import re
from functools import lru_cache

from pydantic import model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

# What Telegram accepts as a webhook secret_token, at a length worth having.
_TELEGRAM_SECRET = re.compile(r"[A-Za-z0-9_-]{16,256}")


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    bot_token: str
    secret_key: str
    database_url: str = "sqlite+aiosqlite:///./bot.db"
    public_base_url: str = "http://localhost:8000"
    telegram_mode: str = "polling"
    telegram_webhook_secret: str = "change-me"

    # How long to wait for the rest of an album before publishing it as one post.
    album_debounce_seconds: float = 2.0
    # Characters of article body to push into the channel before linking out.
    channel_excerpt_chars: int = 700

    # Forwards per second while importing history. Telegram tolerates far
    # more, but an import is bulk work with no reason to run near the limit.
    import_rate_per_second: float = 8.0
    # Refresh the progress message every N message ids.
    import_progress_every: int = 25

    @property
    def telegram_webhook_path(self) -> str:
        return "/telegram/webhook"

    @model_validator(mode="after")
    def _webhook_mode_is_safe(self) -> "Settings":
        """Refuse to start a public webhook with placeholder settings.

        Telegram signs every webhook call with this secret; left at its
        default, anyone could post forged updates to the bot.
        """
        if self.telegram_mode != "webhook":
            return self
        secret = self.telegram_webhook_secret
        if secret in {"change-me", "replace-me"} or not _TELEGRAM_SECRET.fullmatch(secret):
            raise ValueError(
                "TELEGRAM_WEBHOOK_SECRET must be set for webhook mode: 16-256 characters "
                "of A-Z, a-z, 0-9, _ or - (generate one with: openssl rand -hex 32)"
            )
        if not self.public_base_url.startswith("https://"):
            raise ValueError("PUBLIC_BASE_URL must be an https:// address in webhook mode")
        return self


@lru_cache
def get_settings() -> Settings:
    return Settings()
