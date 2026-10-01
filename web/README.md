# Reader site

The public face of the blog, in Next.js 16 and Tailwind 4. One deployment
serves every author:

| Host | Shows |
| --- | --- |
| `example.com` | The directory: all authors and the latest posts |
| `<username>.example.com` | That author's page |
| `<username>.example.com/posts/<slug>` | One post |

`src/proxy.ts` reads the Host header and rewrites author subdomains to the
internal routes under `src/app/site/[author]`. Visiting `/site/...` on the root
host redirects to the real subdomain, so no page exists twice.

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `BLOG_API_URL` | `http://localhost:8001/api` | The Laravel blog API |
| `ROOT_URL` | `http://localhost:3000` | The root host; authors live one label below it |
| `SITE_NAME` | `Channels` | The platform's name on the root domain and in footers |
| `SITE_TIME_ZONE` | `UTC` | Time zone for post dates |

## Running it

```bash
npm install
BLOG_API_URL=http://localhost:8001/api npm run dev
```

Open http://localhost:3000 for the directory, and
`http://<username>.localhost:3000` for an author. Browsers resolve
`*.localhost` to your machine, so no hosts-file edits are needed.

Seed demo authors in the blog with
`php artisan db:seed --class=DemoContentSeeder` to see every post type.

## Design

`PRODUCT.md` records who the site is for. `DESIGN.md` records the visual
system. The look is a plain reading site, Substack/Medium style:

- Golos Text for the interface and headings, Literata for article bodies. Both
  cover Cyrillic and Uzbek Latin.
- One green accent.
- Light and dark modes, following the reader's system setting.
