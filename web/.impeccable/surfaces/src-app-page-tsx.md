---
version: 1
slug: "src-app-page-tsx"
primary_target: "src/app/page.tsx"
related_targets: ["src/app/posts/[slug]/page.tsx"]
---

# Reader site (root directory, author home, post page)

Scope: every reader-facing route in web/. Visitor mode: Read. Audience arrives
from Telegram links on phones; secondary visitors browse authors on the root
domain. Constraints: Cyrillic + Uzbek Latin (U+02BB) + English; arbitrary image
hosts; posts may be a two-line note, an album, or a long telegra.ph article.

## Direction contract

THESIS: A multi-author reading site at Substack/Medium craft. The author's name and the writing lead; the platform recedes. Refuses: a generic template where every post is a same-size card with a thumbnail.

OWN-WORLD: White (and true dark) page, near-black ink, one green accent for links and actions. Serif body at 20px for reading, neutral sans for UI and headings. Hairline dividers between feed rows, no cards. Round avatars. Pill buttons only for small actions.

STORY: The reader understands whose writing this is, reads the post comfortably, and then sees what to read next from the same author. On the root domain, the visitor scans authors and recent posts and picks one.

FIRST VIEWPORT: Author home: a slim top bar (author avatar + name left, Telegram channel button right), then a centred profile block (large avatar, name at display size, bio, post count, channel link), then the feed. Post page: title at 40-48px, author byline with avatar and date, cover or album, then the body at a 680px measure.

FORM: Canon, chosen by the user over two hands (seed 28afe81a, re-roll 1); quality bar Substack and Medium. Signature interaction: a gallery that swipes with scroll-snap on phones and keyboard arrows on desktop, with a thumbnail strip.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
