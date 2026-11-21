# Telegram channel ↔ blog

A blogging setup where a Telegram channel and a website stay in step, in both
directions, for many authors at once. Every author gets their own space on the
reader site at `<username>.<your domain>`; the root domain lists them all.

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

### What a post can do

| Feature | Where it comes from | Notes |
| --- | --- | --- |
| **Scheduled posts** | "Publish at" a future time in the admin | Hidden until then; `posts:announce-due` (the blog's scheduler process, every minute) announces it in the channel. |
| **Pinned post** | Pin in the admin, or pin the message in the channel | One per author; leads the front page. Telegram reports pins, not unpins. |
| **Topics** | Hashtags in a post (`#travel`), or the admin's Topics field | Pages at `/topics/<name>`, chips on posts, the busiest on the front page. |
| **Search** | The search button in the author's bar | `/search?q=`: every word, in titles and text. |
| **Series** | Series in the admin, then a post's Series field | "Part 2 of 5" on each part; `/series/<slug>`. |
| **Contents and footnotes** | The body | Contents once a post has three headings; `[^1]` markers with `[^1]: note` paragraphs become linked notes. |
| **Views** | Counted on the site | Once per reader per visit, capped per address, kept per day. The Bot API cannot read a channel post's own views. |
| **Reactions** | The channel's reaction totals (`message_reaction_count`) | Emoji and paid stars; custom emoji are left out. |
| **Comments** | The channel's discussion group | Telegram's widget, loaded only when the reader asks; public channels only. |
| **Audio, voice notes, files** | Channel posts, or the admin's Media section | A player per track; files listed for download. |
| **Polls** | Channel polls | Question, options and the votes at arrival (the Bot API reports later votes only on the bot's own polls), with a link to vote. |
| **Draft previews** | Preview on the post's edit page | A secret link showing the post as readers will; never indexed. "New preview link" retires the old one. |
| **Stats** | The admin dashboard | Views over 30 days against the 30 before, a daily chart, the most read posts. |
| **RSS and sitemap** | Every author's space | `/feed.xml` (full posts) and `/sitemap.xml`; the root domain has a feed of everyone's latest. |
| **Site design** | Profile → Site design | Classic, Magazine (cards, pictures first) or Notebook (serif, words first). |

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
cp blog/.env.example blog/.env    # set APP_KEY and FRONTEND_URL

docker compose up --build
```

| | |
| --- | --- |
| Reader site (directory) | http://localhost:3000 |
| An author's space | http://&lt;username&gt;.localhost:3000 |
| Admin panel | http://localhost:8001/admin |
| Blog API | http://localhost:8001/api |
| Bot webhooks | http://localhost:8000 |

Create an author (every user of the admin is an author):

```bash
docker compose exec blog php artisan make:filament-user
```

The author then opens **Profile** in the admin to pick a username (their
subdomain), add a bio, avatar and channel link, and generate a bot token.

Browsers resolve `*.localhost` to your machine, so author spaces work locally
without DNS. In production every address derives from one `DOMAIN`: authors
at `<username>.DOMAIN` with certificates issued on first visit, the admin at
`admin.DOMAIN`, the bot at `bot.DOMAIN` ([DEPLOY.md](DEPLOY.md)).

Demo authors and posts in Russian, Uzbek and English, for local work only:

```bash
docker compose exec blog php artisan db:seed --class=DemoContentSeeder
```

Generate the bot's encryption key:

```bash
docker compose run --rm bot python -c \
  "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
```

## Connecting an author

1. DM the bot `/connect`.
   - An author made from **Page requests** is connected straight away: the
     bot asks the blog over the stack's network (`POST /api/internal/connect`,
     needs `BOT_INTERNAL_SECRET`), which issues their bot token and stores the
     bot's webhook.
   - Anyone else is sent to their admin **Profile** to press **Generate bot
     token** and paste it. The bot then sets its webhook on the blog itself
     (`PUT /api/telegram/webhook`).
   - Without `BOT_INTERNAL_SECRET`, the bot asks for the API base URL
     (`https://admin.DOMAIN/api/telegram`) and then the token, as before.
2. Add the bot to the channel as an admin with *Post messages*. Syncing starts
   by itself. A channel the bot was added to before `/connect` starts syncing
   as soon as its owner connects.
3. `/import` to turn the posts already in the channel into blog posts.
4. Optional: `/telegraph` in the DM to turn on Instant View.

`/status` reports what is connected; `/deactivate` pauses a channel.

## Managing authors

The admin account (the oldest one, `is_admin`) has **Authors**: every account
with its page, channel, post counts and last post. An author's page lists all
their posts, drafts included, read-only. From the list or the page:

- **Set new password**, for an author who forgot theirs (the admin has no
  "forgot password" email). The form suggests one to copy and send them; their
  other sessions are signed out.
- **Edit**: name, email, username (their subdomain) and site language.
- **Block**: they are signed out and cannot sign in, their bot gets 403s so
  channel posts stop arriving, their scheduled posts are not announced, and
  their page, posts and feeds leave the reader site. Nothing is deleted;
  **Unblock** brings it all back. The admin account itself cannot be blocked.
- **Grant Premium**: N more days, counted from the end of the Premium they
  already have. Select several authors in the list to grant it to all of them
  at once (a launch trial, say); blocked ones are skipped. The note shows only
  in their **Premium payments**.

## Premium

An author has Premium while `users.premium_until` is in the future, plus
three grace days, so a renewal that arrives late does not switch anything off.
Every period they got, whether paid or granted, is a row in `payments`.
Telegram's charge id is unique there, so the same payment reported twice is
counted once.

The bot sells Premium as a monthly Telegram Stars subscription and calls the
blog with `BOT_INTERNAL_SECRET`:

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/api/internal/premium/status` | `{telegram_user_id}` → `{author, premium, premium_until, blocked}`, or 404 for someone who is not an author. Asked on `/premium` and before Telegram charges. |
| `POST` | `/api/internal/premium/payments` | The fields of Telegram's `SuccessfulPayment` plus `telegram_user_id`. `premium_until` moves to `subscription_expiration_date` and never back. Returns 201 for a new payment, 200 for one already recorded, and 404 for someone who is not an author (the bot refunds it). |

Cancelling needs no call: no renewal arrives, and `premium_until` passes. The
reader API's author has `premium: true/false`; when it ends is not public.

**Own domains.** With `CUSTOM_DOMAINS_ENABLED`, a Premium author sets a
domain in Profile and presses **Check DNS**. The blog checks the domain
resolves to the reader site's address (a CNAME) or `CUSTOM_DOMAIN_IP` (an A
record). After that, `siteUrl()` and every post link use the domain. The
reader site's proxy asks `GET /api/public/hosts/{host}` which author a host
is for, and keeps the answer for a minute. It serves an own domain while it
is live, sends the subdomain there with a 302, and sends the domain back to
the subdomain once Premium ends. Setup is in DEPLOY.md, "Authors' own
domains".

## Tests

The images leave tests out, so they are mounted in:

```bash
docker compose run --rm -v "$PWD/bot/tests:/srv/tests:ro" -v "$PWD/bot/pyproject.toml:/srv/pyproject.toml:ro" \
  -u root bot sh -c "pip install -q pytest pytest-asyncio && python -m pytest -q"
docker run --rm -v "$PWD/blog/tests:/srv/tests:ro" -v "$PWD/blog/.env.example:/srv/.env:ro" \
  -e APP_KEY=base64:$(openssl rand -base64 32) --entrypoint php publish-channel-bot-blog artisan test
docker compose run --rm web npm run build
```

Run the blog's with plain `docker run`, not `docker compose run`: compose
injects `blog/.env`, whose `QUEUE_CONNECTION` overrides the tests' `sync`.
CI runs all three on every push and pull request, and also migrates a real
Postgres, since the tests use SQLite.

## Deploying

To the Contabo server, through staging, the same way as the other apps
there: [DEPLOY.md](DEPLOY.md).

## Notes

- The reader site's interface speaks Uzbek, Russian or English; each author
  picks theirs on the admin **Profile**, and `DEFAULT_LOCALE` sets the root
  directory's. Post content keeps its own language (`lang` is guessed per
  post for hyphenation and screen readers).
- `TELEGRAM_API_TOKEN` in the blog's `.env` still works and acts for the first
  author, so single-author installs keep syncing after the upgrade. New authors
  should use per-author tokens from their profile.
- `blog/CLAUDE.md` asks for [Laravel Boost](https://github.com/laravel/boost) to
  be installed as a dev dependency. It was not installed — it adds a package
  that was not asked for.
