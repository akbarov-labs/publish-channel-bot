# Telegram channel ↔ blog

A blogging setup where a Telegram channel and a website stay in step, in both
directions, for many authors at once.

| Service | Stack | Purpose |
| --- | --- | --- |
| [`bot/`](bot/) | Python 3.12, aiogram 3, FastAPI | Talks to Telegram, calls the blog API |
| [`blog/`](blog/) | Laravel 13, Filament 5 | Blog API and the author's admin panel |
| [`web/`](web/) | Next.js 16, Tailwind 4 | Reader-facing site |

Each service has its own database and its own container. The bot and the blog
talk only over HTTP, so either can be deployed and migrated on its own.

## What it does

**Telegram → blog.** A channel post becomes a published article. An album of
photos becomes a **gallery** post, rendered as a carousel on the site with the
album's order preserved. If the post contains a **telegra.ph** link, the bot
fetches that page and stores the full article instead of the 4096-character
message.

**Existing channels.** `/import` walks the channel's back catalogue and turns
it into a blog, every post keeping the date it was first published. The Bot API
cannot read history, so the bot forwards each message to itself to read it —
forwarded copies carry the original date and message id. Deleted ids are
skipped, albums are regrouped, and the run resumes if interrupted. Details and
limits are in [bot/README.md](bot/README.md).

**Blog → Telegram.** Publishing in the admin announces the post in the channel
with an excerpt and a link. If the author ran `/telegraph`, the bot also mirrors
the article to telegra.ph and puts that link first, so readers get **Instant
View** and can read the whole piece without leaving Telegram.

Edits to a channel post are mirrored onto the blog. Deletions are not — the
Telegram Bot API does not report them.

### Keeping posts from bouncing

Both directions running at once is a loop waiting to happen. Three guards stop
it, described in full in [bot/README.md](bot/README.md):

1. A `post_links` row per synced post, recording both ids and which side
   authored it. Either direction skips what is already there.
2. `source: "telegram"` travels with the post and is echoed back by the blog;
   payloads marked `telegram` are dropped on arrival.
3. An in-process TTL cache covering the moment between sending a message and
   committing its row.

Both services have tests for this — `bot/tests/test_loop_guard.py` and
`blog/tests/Feature/BotApiTest.php`.

## Running it

```bash
cp bot/.env.example bot/.env      # set BOT_TOKEN and SECRET_KEY
cp blog/.env.example blog/.env    # set TELEGRAM_API_TOKEN

docker compose up --build
```

| | |
| --- | --- |
| Reader site | http://localhost:3000 |
| Admin panel | http://localhost:8001/admin |
| Blog API | http://localhost:8001/api |
| Bot webhooks | http://localhost:8000 |

Create the first admin user:

```bash
docker compose exec blog php artisan make:filament-user
```

Generate the bot's encryption key:

```bash
docker compose run --rm bot python -c \
  "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
```

## Connecting an author

1. DM the bot `/connect`.
2. Send the blog's API base URL — `https://yourblog.com/api/telegram` — then the
   value of `TELEGRAM_API_TOKEN` from the blog's `.env`.
3. The bot verifies with `GET /ping` and replies with a webhook URL and signing
   secret. Put those in the blog's `TELEGRAM_WEBHOOK_URL` and
   `TELEGRAM_WEBHOOK_SECRET`.
4. Add the bot to the channel as an admin with *Post messages*.
5. Send `/activate` in the channel.
6. `/import` to turn the posts already in the channel into blog posts.
7. Optional: `/telegraph` in the DM to turn on Instant View.

`/status` reports what is connected; `/deactivate` pauses a channel.

## Tests

```bash
docker compose run --rm bot sh -c "pip install -q pytest pytest-asyncio && python -m pytest -q"
docker compose run --rm blog php artisan test
docker compose run --rm web npm run build
```

## Still to do

- Authors currently paste the webhook secret into the blog's `.env` by hand.
  A settings page in Filament would let them save it from the admin.
- No pagination controls on the reader site yet; the API already paginates.
- `blog/CLAUDE.md` asks for [Laravel Boost](https://github.com/laravel/boost) to
  be installed as a dev dependency. It was not installed — it adds a package
  that was not asked for.
