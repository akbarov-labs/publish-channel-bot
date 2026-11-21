# publish-channel-bot

Two-way sync between a Telegram channel and an author's blog, for many authors at once.

- **Telegram → blog**: a channel post (or album) becomes a published article.
- **Blog → Telegram**: a newly published article becomes a channel message with an excerpt and a link back.

Both directions can be enabled independently per channel.

## How a post is kept from bouncing

With both directions on, a naive implementation loops forever: a Telegram post
creates an article, the blog's webhook fires, the article is posted back to the
channel, and so on.

Three guards prevent it:

1. Every synced post gets one `post_links` row recording `tg_message_id`,
   `site_post_id` and which side it came from. Either direction skips anything
   already in that table.
2. Outgoing blog calls carry `"source": "telegram"`, and the blog echoes it back
   in its webhook. Payloads marked `telegram` are dropped on arrival.
3. An in-process TTL cache of just-sent message ids closes the sub-second gap
   between sending a channel message and committing its row.

Guard 1 is the durable one and is covered by `tests/test_loop_guard.py`.

## The blog API contract

The bot calls these on the author's blog, authenticated with
`Authorization: Bearer <token>`, under the API base URL the author supplies.

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/ping` | Verify base URL + token during `/connect`. Any `2xx` JSON body. |
| `POST` | `/posts` | Create a post. Must return `{"id": "...", "url": "..."}`. |
| `PATCH` | `/posts/{id}` | Apply an edited channel post. |
| `POST` | `/media` | Multipart `file` upload. Must return `{"url": "..."}`. |
| `PUT` | `/webhook` | Optional. `{"url", "secret"}` for the outgoing webhook below, sent after `/connect`. Without it the author pastes both by hand. |

`POST /posts` body:

```json
{
  "source": "telegram",
  "external_id": "tg:-1001234567890:451",
  "title": "First line of the post",
  "html": "<p>Body…</p>",
  "media": [{"type": "photo", "url": "https://cdn.blog/…jpg"}],
  "published_at": "2026-09-19T10:00:00+00:00"
}
```

`external_id` is stable per channel message — treat it as an idempotency key.

### The blog's outgoing webhook

On publish, the blog posts to the URL the bot hands out at the end of `/connect`:

```
POST {PUBLIC_BASE_URL}/webhooks/site/{webhook_secret}
X-Signature: sha256=<hmac_sha256(webhook_secret, raw_request_body)>

{
  "event": "post.published",
  "post": {
    "id": "123",
    "title": "…",
    "url": "https://blog.example/posts/slug",
    "html": "<p>…</p>",
    "cover_image": "https://cdn.blog/cover.jpg",
    "source": "web"
  }
}
```

`source` must be `"telegram"` for posts the bot itself created — that is guard 2.
Unsigned or wrongly signed requests are rejected with `401`.

## Importing a channel's existing posts

`/import` turns a channel that already has years of posts into a blog, each
post keeping the date it was published.

**The Bot API cannot read history.** There is no `getChatHistory`; a bot sees
only what is sent after it joins. What it *can* do is forward any message id
from a chat where it is an admin, and a forwarded copy carries
`forward_origin`, which holds both the original publication date and the
original message id. So the import:

1. Posts a silent throwaway message to learn the channel's newest message id,
   then deletes it. That id is the upper bound of the walk.
2. Forwards each id in turn into the author's own chat with the bot, reads the
   copy, and deletes it again.
3. Publishes each post with `published_at` taken from `forward_origin.date` and
   `external_id` built from the *original* message id, never the copy's.

Ids that fail to forward - deleted posts, service messages, ids that never
existed - are counted as skipped. Albums are regrouped from consecutive ids
sharing a timestamp, because forwarding a single album member strips its
`media_group_id`.

Progress is reported in one continuously edited message. The cursor is stored
on the `import_jobs` row, so `/cancel_import` and a later `/import` resume
rather than start over, and the `post_links` rows make a second pass a no-op.

| Command | |
| --- | --- |
| `/import` | Start (also works when sent in the channel) |
| `/import_status` | Progress for every linked channel |
| `/cancel_import` | Stop; already-imported posts stay |

Two limits worth knowing:

- **Content-protected channels cannot be imported.** Forwarding is blocked by
  Telegram, and forwarding is the whole mechanism. The bot detects this via
  `has_protected_content` and says so instead of failing halfway.
- Forwarding is paced at `IMPORT_RATE_PER_SECOND` (default 8/s) and backs off
  on flood control, so a large archive takes a while. Media upload to the blog,
  not forwarding, is usually the slower half.

## Author setup flow

1. DM the bot `/connect`. Beside the bundled blog (`BLOG_INTERNAL_URL` and
   `BOT_INTERNAL_SECRET` set) the bot asks the blog to connect the user by
   their Telegram id; an author not known that way pastes the token from their
   admin Profile. Otherwise it asks for the API base URL, then the token.
2. The bot verifies with `GET /ping`, stores the token encrypted (Fernet), and
   hands the blog its webhook URL and signing secret (`PUT /webhook`). A blog
   without that route gets them shown to paste by hand.
3. Add the bot to the channel as an admin with *Post messages*. The bot binds
   the channel to that author's blog and switches syncing on. A channel added
   before `/connect` waits, and is switched on when its owner connects.
4. `/deactivate` in the channel pauses, `/activate` resumes, `/status` reports.
5. Send `/import` to pull in everything already posted in the channel.

## Premium

`/premium` sells authors a monthly Telegram Stars subscription when
`PREMIUM_PRICE_STARS` is set. The blog keeps who has it
(`/api/internal/premium/*`, with `BOT_INTERNAL_SECRET`).

1. **`/premium`** asks the blog whether this Telegram account is an author.
   An author without Premium gets a button with a `createInvoiceLink`
   (`XTR`, `subscription_period` 30 days, payload `premium:<user id>`).
   An author who already has it, paid or gifted, sees until when, and no
   button, so nobody pays twice.
2. **Pre-checkout**: Telegram asks before it charges. The payload must
   name the payer, and the blog must say they are an author who is not
   blocked. The blog gets 5 seconds of Telegram's 10.
3. **`successful_payment`** arrives for the first payment and every
   renewal. The bot passes it to the blog, which moves `premium_until` to
   the end of the paid period. The bot tries 4 times, then sends the charge
   id to `ADMIN_CHAT_ID` so the admin can grant Premium by hand. Someone who
   stopped being an author in between is refunded with `refundStarPayment`.
4. **Cancelling** happens in Telegram (Settings → My Stars) and needs
   nothing from the bot: no renewal arrives, and `premium_until` passes.

`/paysupport <problem>` passes the message to `ADMIN_CHAT_ID`, and `/terms`
shows the terms. Telegram requires both of bots that take payments. Texts
follow the user's Telegram language: uz, ru, or en otherwise.

## Running it

```bash
cp .env.example .env          # fill in BOT_TOKEN and SECRET_KEY
docker compose up --build
```

Generate `SECRET_KEY` with:

```bash
docker compose run --rm bot python -c \
  "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
```

The service is a single container either way: with `TELEGRAM_MODE=polling`
(the default, for local work) long polling runs as a background task inside the
web app, so the blog webhook endpoint is live too. Set `TELEGRAM_MODE=webhook`
and a public `PUBLIC_BASE_URL` in production.

Tests:

```bash
docker compose run --rm bot sh -c "pip install -q pytest pytest-asyncio && python -m pytest -q"
```

## Known Telegram limits

- **Deleted channel posts are not reported to bots.** Deletions do not propagate;
  only creates and edits do.
- Bots can download files up to **20 MB**; larger media is skipped with a log line.
- Albums arrive as separate updates and are buffered for
  `ALBUM_DEBOUNCE_SECONDS` (default 2s) before publishing as one post.
- Channel text caps at 4096 characters and photo captions at 1024, so blog→Telegram
  always sends an excerpt plus a link rather than the full article.
