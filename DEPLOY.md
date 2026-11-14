# Deploying to Contabo

Same model as every other app on the server (`docs/add-app.md` in the
`akbarov-labs/reads` repo): every push to `main` runs the tests, builds three
images on GitHub (`ghcr.io/akbarov-labs/publish-channel-bot/{bot,blog,web}`,
tagged with the commit), and deploys them to **staging**. **Production**
gets that same tag only when you press **Actions → Promote to production →
Run workflow**. (The repository is private, and GitHub's free plan has no
Required reviewers for it, so that button is the approval step.)

CI logs in as `deployer`, which can only run
`deploy-app publish-channel-bot <tag>` (or `publish-channel-bot-staging`).
The compose file, the `.env` and the hook live on the server, owned by root.

| | Production | Staging |
|---|---|---|
| Reader site | `https://DOMAIN` | `https://publish-channel-bot.staging.sarvarlogs.uz` (login `staging`) |
| An author | `https://<author>.DOMAIN` | `https://<author>.publish-channel-bot.staging.sarvarlogs.uz` |
| Admin + API | `https://admin.DOMAIN` | `https://admin.publish-channel-bot.staging.sarvarlogs.uz` |
| Bot webhooks | `https://bot.DOMAIN` | `https://bot.publish-channel-bot.staging.sarvarlogs.uz` |
| Telegram bot | the real one | a test bot of its own |
| Folder | `/opt/apps/publish-channel-bot` | `/opt/apps/publish-channel-bot-staging` |
| Backups | nightly + before each deploy | none |

`DOMAIN` is the one address you choose; the stack derives the rest. The
folders and their `.env` are root's: prefix the commands on this page with
`sudo`, and run them from the app's folder.

**Footprint:** two small Postgres containers, the bot (~80 MB), the blog
(nginx + PHP-FPM + queue worker, ~150–250 MB), the Next.js site
(~100 MB), and a backup container. Memory limits are in the compose file.

## How author addresses get certificates

Every author has a subdomain, and nobody knows them in advance, so the
edge Caddy cannot get certificates up front. It uses **on-demand TLS**: the
first visit to `aziza.DOMAIN` makes Caddy ask
`http://pcb-tls-ask/api/tls/allowed?domain=aziza.DOMAIN`, and only if the
blog answers 200 (that author exists) does it fetch a certificate from
Let's Encrypt. That takes a second or two once per author, then it's cached
and renewed like any other.

The ask URL is a single setting for the whole server, so it lives in the
edge Caddy's own Caddyfile (step 2 below), not in this project's labels: if a
label carried it, the setting would vanish whenever this stack was down and
leave the proxy with an invalid config. `pcb-tls-ask` is a name both the
production and the staging blog answer to on the `edge` network; each one
answers for its own `DOMAIN` and passes the other's hosts to the other
(`TLS_ASK_PEER_URL`), so it doesn't matter which one Caddy reaches.

Without the ask check anyone could make the server request certificates for
random subdomains and burn the domain's Let's Encrypt limit (50 new
certificates per registered domain per week). With it, only real authors
count, and an author is one certificate.

## One-time setup

### 1. DNS

At the DNS host of your domain (ahost.uz for `sarvarlogs.uz`), all `A`
records to `169.58.243.86`:

| Record | Why |
|---|---|
| `DOMAIN` | the directory |
| `*.DOMAIN` | every author, plus `admin.` and `bot.` |
| `publish-channel-bot.staging` in `sarvarlogs.uz` | staging's directory (there is no `*.staging` wildcard; other staging sites have records of their own) |
| `*.publish-channel-bot.staging` in `sarvarlogs.uz` | staging's authors, `admin.`, `bot.` |

### 2. The edge Caddy's ask URL (once for the server)

On the server, in the edge proxy's folder (`~/edge`, see
`infra/edge-caddy/README.md` in online-shop-telegram-bot), add this block
just before the closing `}` of the global block in `Caddyfile`. Add only
this block: leave `email`, `servers`, the Cloudflare `trusted_proxies` and
`client_ip_headers` lines as they are.

```caddyfile
    # Author subdomains of publish-channel-bot (see its DEPLOY.md). Caddy asks
    # the blog before issuing a certificate, so random subdomains are refused.
    on_demand_tls {
        ask http://pcb-tls-ask/api/tls/allowed
    }
```

Validate the edited copy first, then swap it in and recreate the proxy
(`sarvar` is not in the `docker` group, so these need `sudo`). Every site on
the box goes through it, so read the log afterwards:

```bash
sudo docker run --rm -e ACME_EMAIL=test@example.com -v "$PWD/Caddyfile.new:/etc/caddy/Caddyfile:ro" \
  --entrypoint caddy lucaslorentz/caddy-docker-proxy:2.9-alpine validate --config /etc/caddy/Caddyfile --adapter caddyfile
cp Caddyfile Caddyfile.bak-before-pcb && cp Caddyfile.new Caddyfile
sudo docker compose up -d --force-recreate && sudo docker logs edge-caddy-1 --tail=50
```

Rollback: `cp Caddyfile.bak-before-pcb Caddyfile && sudo docker compose up -d --force-recreate`.

Until this app's blog runs, the ask URL simply doesn't answer, which only
means no author certificates yet; every other site is unaffected.

### 3. Two bots from @BotFather

The real one for production, and a test bot for staging. Staging must never
run with production's token: it would take the real bot's webhook over.

### 4. Production's `.env`

`add-app` copies production's `.env` from a folder you name. Make it in
your home folder first:

```bash
mkdir -p ~/publish-channel-bot && cd ~/publish-channel-bot
nano .env        # paste .env.production.example from the repo, fill it in
chmod 600 .env
```

Every value and how to generate it is in `.env.production.example`. No
quotes around values.

### 5. Server tools

Mac, from this repo (`prepare-app` lives in the Reads repo):

```bash
~/dev/sites/Reads/deploy/prepare-app publish-channel-bot ~/dev/telegram-bots/publish-channel-bot
```

It makes two CI keys and sends the compose files, the keys and
`deploy/hooks/pre-up` to the server. Then on the server:

```bash
sudo add-app --images "bot blog web" --up-flags "--remove-orphans" \
  publish-channel-bot ghcr.io/akbarov-labs/publish-channel-bot /home/sarvar/publish-channel-bot
```

- `--images`: three images under one name.
- `--up-flags "--remove-orphans"` (no `--force-recreate`): a deploy
  recreates only the containers whose image changed, so the databases keep
  running.
- It warns that no running container uses the image: expected for a new app.
- It asks for staging's password twice (user `staging`).

### 6. Staging's `.env`

`add-app` made it from production's, with `DOMAIN` switched to
`publish-channel-bot.staging.sarvarlogs.uz` and `BOT_TOKEN` emptied. Set:

```bash
sudo nano /opt/apps/publish-channel-bot-staging/.env
```

```
BOT_TOKEN=<the test bot's token>
APP_KEY=<new: echo "base64:$(openssl rand -base64 32)">
BOT_SECRET_KEY=<new Fernet key>
TELEGRAM_WEBHOOK_SECRET=<new: openssl rand -hex 32>
```

### 7. GitHub

The repository is private, so it can't see the org's variables. In
**Settings → Secrets and variables → Actions → Variables** add
`CONTABO_HOST` (`169.58.243.86`), `CONTABO_PORT` (`22`), `CONTABO_USER`
(`deployer`) and `CONTABO_KNOWN_HOSTS` (copy it from the org's Variables page).

**Settings → Environments:**

- `staging`: secret `CONTABO_SSH_KEY` = `pbcopy < ~/.ssh/ci_publish_channel_bot_staging`,
  variable `SITE_URL` = `https://publish-channel-bot.staging.sarvarlogs.uz`,
  deployment branches: `main` only.
- `production`: secret `CONTABO_SSH_KEY` = `pbcopy < ~/.ssh/ci_publish_channel_bot`,
  variable `SITE_URL` = `https://DOMAIN`, deployment branches: `main` only.

Then delete the two keys from the Mac: `rm ~/.ssh/ci_publish_channel_bot*`.

### 8. First deploy

Merge to `main`. When **Build and deploy** is green, open staging and check
the list under "After every deploy" below. Then **Actions → Promote to
production → Run workflow**.

### 9. The first author

The admin has no accounts until you make one:

```bash
cd /opt/apps/publish-channel-bot
sudo docker compose -f docker-compose.prod.yml exec -u www-data blog php artisan make:filament-user
```

Sign in at `https://admin.DOMAIN`, open **Profile**, pick a username (the
subdomain) and the site's language, then connect the channel as described in
the README ("Connecting an author"). The blog API base URL the bot asks for
is `https://admin.DOMAIN/api/telegram`.

### 10. Off-site backups

Add to `/etc/offsite-backup/sources` (`sudo nano`), then
`sudo offsite-backup check`:

```
dumps  publish-channel-bot  vol:publish-channel-bot_db_backups
files  publish-channel-bot  vol:publish-channel-bot_blog_storage/app/public
```

## After every deploy

```bash
cd /opt/apps/publish-channel-bot
sudo docker compose -f docker-compose.prod.yml ps        # all Up, bot/blog/web (healthy)
docker logs edge-caddy-1 --tail=30                         # config loaded, no errors
curl -sI https://DOMAIN | head -1                          # 200
curl -sI https://admin.DOMAIN/admin/login | head -1        # 200
curl -s  https://bot.DOMAIN/health                         # {"status":"ok"}
curl -sI https://<an author>.DOMAIN | head -1              # 200 (first time: a second slower)
```

Then publish a test post in a connected channel and see it on the author's
page, and publish one in the admin and see it in the channel.

## Everyday use

Push to `main` → tests → staging updates → look at it → **Promote to
production**. Don't like staging? Don't promote; push a fix.

**Rollback:** **Promote to production** with an older commit's SHA. Images
are kept for the last three deploys. Migrations are not rolled back, so a
rollback across a migration needs the database restore below.

## Changing `.env` on the server

Recreate, don't restart (a container keeps the environment it was created
with), from the images already running:

```bash
cd /opt/apps/publish-channel-bot
TAG=$(sudo docker inspect publish-channel-bot-blog-1 --format '{{.Config.Image}}' | cut -d: -f2)
sudo IMAGE_TAG=$TAG docker compose -f docker-compose.prod.yml up -d --force-recreate --pull never bot blog web backup
```

## Changing server files

CI never ships `docker-compose.prod.yml`, `docker-compose.staging.yml` or
`deploy/hooks/pre-up`. After changing one, copy it by hand:

```bash
scp docker-compose.prod.yml sarvar@169.58.243.86:/tmp/
ssh -t sarvar@169.58.243.86 'sudo install -m 644 -o root -g root /tmp/docker-compose.prod.yml /opt/apps/publish-channel-bot/'
# the hook: install -m 755 ... /opt/apps/publish-channel-bot/hooks/pre-up (and -staging)
```

## Backups and restore

The `backup` service dumps both databases (`pg_dump -Fc`) at 03:40 UTC, on
every start, and before every production deploy (`pre-up` hook), into the
`db_backups` volume, keeping `BACKUP_RETENTION_DAYS` (14) days. The
server's `offsite-backup` job copies the dumps and the uploaded media off the
box at night (step 10).

```bash
cd /opt/apps/publish-channel-bot
sudo docker compose -f docker-compose.prod.yml logs --tail 20 backup   # "[backup] ... ok:"
sudo docker compose -f docker-compose.prod.yml exec backup ls -la /backups
sudo docker compose -f docker-compose.prod.yml exec backup /usr/local/bin/backup.sh   # one now
```

**Restore** a database (here the blog's; the bot's is the same with `bot`):

```bash
cd /opt/apps/publish-channel-bot
sudo docker compose -f docker-compose.prod.yml stop bot blog web
sudo docker compose -f docker-compose.prod.yml exec backup sh -c \
  'PGPASSWORD=$BLOG_DB_PASSWORD pg_restore -h blog-db -U blog -d blog --clean --if-exists /backups/blog-<UTC time>.dump'
sudo docker compose -f docker-compose.prod.yml start bot blog web
```

The blog's migrations run on start and bring an older dump up to date.
Everything after the dump's time is gone, and posts made in the channel
meanwhile stay in Telegram; `/import` brings them back.

From off-site, when the server is lost:
`sudo offsite-backup fetch dumps/publish-channel-bot/<file> "$(sudo docker volume inspect -f '{{.Mountpoint}}' publish-channel-bot_db_backups)"`,
and the media with `files/publish-channel-bot` into
`publish-channel-bot_blog_storage`'s `app/public`.

## Troubleshooting

**An author's address shows a TLS error.** Ask what Caddy asks:

```bash
docker exec edge-caddy-1 wget -qSO- "http://pcb-tls-ask/api/tls/allowed?domain=<author>.DOMAIN" 2>&1 | head -1
```

`200` and still failing: `docker logs edge-caddy-1 | grep -i <author>`
(Let's Encrypt's answer is there; the usual cause is DNS without
`*.DOMAIN`). `404`: no author has that username, or `DOMAIN` differs from the
host. `bad address`: step 2 is missing, or the blog isn't running.

**Every blog request 500s with "Unsupported cipher".** `APP_KEY` is quoted
or malformed. It must be `base64:` plus 44 characters, no quotes.

**The bot doesn't react in a channel.** `sudo docker compose -f
docker-compose.prod.yml logs --tail 50 bot`. At start it logs `telegram
webhook set to https://bot.DOMAIN/telegram/webhook`. A refusal to start
names the setting it needs (`TELEGRAM_WEBHOOK_SECRET`, `PUBLIC_BASE_URL`).

**A post published in the admin doesn't reach the channel.** The queue
worker runs inside the blog container: `sudo docker compose -f
docker-compose.prod.yml logs blog | grep -i queue`, and the author's Profile
must hold the webhook URL and secret the bot gave at `/connect`.
