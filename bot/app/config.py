from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


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


@lru_cache
def get_settings() -> Settings:
    return Settings()
