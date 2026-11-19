---
name: Channels
description: A multi-author reading site where each Telegram channel becomes its author's own page.
colors:
  bg: "#ffffff"
  surface: "#f5f5f2"
  ink: "#1d1d1b"
  ink-soft: "#3d3d3a"
  muted: "#6b6b66"
  line: "#e7e7e3"
  accent: "#1a7f37"
  on-accent: "#ffffff"
  bg-dark: "#121211"
  surface-dark: "#1c1c1a"
  ink-dark: "#ecece8"
  ink-soft-dark: "#cfcfca"
  muted-dark: "#9c9c96"
  line-dark: "#2a2a27"
  accent-dark: "#4cc36a"
  on-accent-dark: "#0b1a0f"
typography:
  display:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.25rem, 6vw, 3.5rem)"
    fontWeight: 700
    lineHeight: 1.08
    letterSpacing: "-0.021em"
  headline:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2rem, 5vw, 2.75rem)"
    fontWeight: 700
    lineHeight: 1.14
    letterSpacing: "-0.021em"
  title-featured:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.625rem, 4vw, 2.125rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.021em"
  title:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.125rem, 2.5vw, 1.3125rem)"
    fontWeight: 700
    lineHeight: 1.375
    letterSpacing: "-0.021em"
  prose:
    fontFamily: "Literata Okina, Literata, ui-serif, Georgia, serif"
    fontSize: "clamp(1.125rem, 2.5vw, 1.25rem)"
    fontWeight: 400
    lineHeight: 1.75
  lede:
    fontFamily: "Literata Okina, Literata, ui-serif, Georgia, serif"
    fontSize: "clamp(1.0625rem, 2.2vw, 1.25rem)"
    fontWeight: 400
    lineHeight: 1.625
  body:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.625
  label:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 500
    lineHeight: 1.25
  meta:
    fontFamily: "Golos Text, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.25
    fontFeature: "tnum"
rounded:
  thumb: "4px"
  media: "6px"
  code-block: "8px"
  pill: "9999px"
spacing:
  gutter: "16px"
  gutter-wide: "24px"
  bar-height: "56px"
  row-y: "28px"
  row-gap: "20px"
  row-gap-wide: "40px"
  section: "48px"
  section-wide: "64px"
  measure-post: "680px"
  measure-author: "720px"
  media-wide: "960px"
  container: "1120px"
components:
  button-follow:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 12px"
    height: "32px"
  button-follow-lg:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    padding: "0 16px"
    height: "40px"
  button-outline:
    backgroundColor: "{colors.bg}"
    textColor: "{colors.ink}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0 12px"
    height: "32px"
  button-outline-lg:
    backgroundColor: "{colors.bg}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    padding: "0 16px"
    height: "40px"
  button-ink:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.bg}"
    typography: "{typography.body}"
    rounded: "{rounded.pill}"
    padding: "0 16px"
    height: "40px"
  author-bar:
    backgroundColor: "{colors.bg}"
    textColor: "{colors.ink}"
    height: "{spacing.bar-height}"
    padding: "0 16px"
  post-row:
    backgroundColor: "{colors.bg}"
    textColor: "{colors.ink}"
    typography: "{typography.title}"
    padding: "28px 0"
  post-row-thumb:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.thumb}"
    width: "160px"
    height: "108px"
  gallery-thumb:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.thumb}"
    size: "64px"
  avatar:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.pill}"
---

# Design System: Channels

## Overview

**Creative North Star: "The Author's Front Page"**

A reading site built to the Substack and Medium bar and executed straight: white paper (or a true near-black at night), near-black ink, one green that only ever means "follow" or "go". The author's name, face and writing lead every screen; the platform shows itself only in a footer line. Nothing is a card. Posts are rows of type separated by hairlines, the reading body is a 20px serif at a 680px measure, and everything that is not reading (titles, bars, meta, buttons) speaks in one neutral sans.

Density is calm and editorial: generous vertical rhythm between feed rows, slim 56px bars, centred profile blocks, and media that is allowed to be wider than the text column (960px) without ever going full-bleed on desktop. A two-line note, a 20-photo album and a 5,000-word article all sit in the same feed grammar and each looks intended. Cyrillic, Uzbek Latin (with the U+02BB okina) and English share one voice; every face is chosen and patched so the three scripts look equally designed.

The world refuses the generic content template: no same-size thumbnail cards, no gradient heroes, no decorative colour. Light is the default and dark follows the reader's system setting, with every token redefined rather than inverted.

**Key Characteristics:**
- White and true-dark pages, near-black ink, a single green accent.
- Serif for reading, neutral sans for everything else.
- Hairline dividers between rows; no cards, no surface shadows.
- Round avatars as the author's mark; pill buttons only for small actions.
- Media may run wider than the text measure; the measure itself never exceeds 680px.
- One primary call to action per region: follow the author's Telegram channel.

## Colors

A near-monochrome paper-and-ink palette with one green, redefined token for token in dark mode.

### Primary
- **Telegram-Follow Green** (accent / accent-dark): the only chromatic colour in the system. Fills the Follow pill, colours the author's @handle link on the author home, the focus ring, the caret, link hover inside prose, and (at 22% mix) the text selection. In dark mode it brightens so it still reads on near-black, and its text flips to a deep green-black (on-accent-dark).

### Neutral
- **Paper White** (bg) / **Night Paper** (bg-dark): the page. Also the bar background, so sticky bars read as part of the page, not a layer above it.
- **Warm Off-White** (surface) / **Lifted Night** (surface-dark): placeholder ground behind images and avatars while they load, the gallery track, inline code and code blocks.
- **Near-Black Ink** (ink) / **Bone Ink** (ink-dark): titles, body copy, the blockquote rule, the active gallery-thumbnail ring, the ink pill and the hover border of outline pills.
- **Soft Ink** (ink-soft): serif ledes, author bios, featured excerpts and blockquote text: reading text that steps back one shade.
- **Warm Grey** (muted): meta lines (date, read time, photo count), feed excerpts, footers, list markers, figure captions, pagination counter.
- **Hairline** (line): every divider, the bottom border of bars, outline-pill borders, table rules and the prose link underline base.

### Named Rules
**The One Green Rule.** Green means "follow" or "go" and nothing else. It never fills a surface, a badge or a heading, and an author home carries exactly one green pill (the bar's Follow); the channel handle in the profile block is a green text link, not a second pill.

**The Redefine-Don't-Invert Rule.** Dark mode re-declares every token under `prefers-color-scheme: dark` with its own tuned value; nothing is produced by filters or opacity flips, and the browser theme colour follows the same two page values.

## Typography

**Display Font:** Golos Text (with ui-sans-serif, system-ui)
**Body Font:** Literata, optical-size axis on, normal and italic (with ui-serif, Georgia), preceded by Literata Okina
**Label/Mono Font:** Golos Text for labels; ui-monospace, SFMono-Regular, Menlo for code

**Character:** A sturdy, neutral grotesque that handles Cyrillic as well as Latin, paired with a warm book serif with real italics. The sans carries structure and the platform's voice; the serif carries the author's.

### Hierarchy
- **Display** (700, 2.25rem to 3.5rem, 1.08, -0.021em): the root directory's one heading, capped at 18ch.
- **Headline** (700, 2rem to 2.75rem, 1.14): the post title; the author's name on their home uses the same voice at 2rem to 2.625rem.
- **Title Featured** (700, 1.625rem to 2.125rem, 1.2): the newest post on an author home.
- **Title** (700, 1.125rem to 1.3125rem, snug): feed-row titles, neighbour links, section-level titles inside the page.
- **Prose** (Literata 400, 1.125rem / 1.72 on phones, 1.25rem / 1.75 from 640px): the article body, at a 680px measure. Headings inside prose switch to the sans at 700 (1.55em, 1.3em, 1.1em).
- **Lede** (Literata 400, 1.0625rem to 1.25rem, relaxed, ink-soft): the root intro, author bios, featured excerpts.
- **Body** (Golos 400, 0.9375rem): UI copy, feed excerpts (muted), section labels such as "Latest posts" and "Authors" (at 600, sentence case).
- **Label** (Golos 500, 0.8125rem): pill text, bylines in feed rows.
- **Meta** (Golos 400, 0.8125rem, tabular figures, muted): date · type · count lines, separated by a centred dot.

### Named Rules
**The Serif-Reads, Sans-Speaks Rule.** Literata is for text a reader reads as the author's: the prose body, bios, ledes and the featured excerpt. Everything structural (titles, headings inside prose, bars, meta, buttons, captions, tables) is Golos. Feed-row excerpts stay sans and muted so the list scans.

**The Balanced Title Rule.** Every title is 700 weight, -0.021em tracking, `text-wrap: balance` and `overflow-wrap: anywhere`, because Telegram titles are a post's first line and can be long, chatty or one unbroken URL.

**The Okina Rule.** Literata ships U+02BB with zero advance width, so a local face (Literata Okina) that maps only U+02BB to Literata's own turned comma sits first in the serif stack, loaded by unicode-range and never preloaded. Any face added to the system must cover Latin, Latin Extended and Cyrillic, and must render oʻ and gʻ with a real advance before it ships.

## Layout

Single-column reading with fixed measures, centred, over a 16px phone gutter (24px from 640px). Bars and footers run to a 1120px container. The post page holds its header and body to 680px and lets the cover, album or video widen to 960px (edge to edge on phones, rounded inset from 640px). The author home is a 720px column: a centred profile block (96px avatar, name, serif bio, post count and handle), then a featured post, then hairline-divided rows. The root directory is a 1120px two-column grid from 1024px (feed plus a sticky 300px authors aside with a 64px column gap); on phones the authors become a horizontally swiped strip of 60px avatars above the feed.

Vertical rhythm: feed rows 28px top and bottom, 20px (phone) or 40px text-to-thumbnail gap; page openings 48px on phones and 64px from 640px; author block and post-to-footer separations 64 to 80px. Breakpoints are 640px (type steps up, gutters widen, media rounds) and 1024px (root grid). Motion is short and functional: 150ms ease-out for colour, border and filter changes, 200ms for spoiler reveal, a 500ms 1.5% scale on the featured cover's hover; everything collapses under `prefers-reduced-motion`.

## Elevation & Depth

Flat. Depth is carried by hairlines, the warm surface tone behind media, and the page colour itself; content never floats. Sticky bars are page-coloured with a hairline bottom border, not shadowed.

### Shadow Vocabulary
- **Floating control** (`box-shadow: 0 2px 10px rgb(0 0 0 / 0.18)`): only the gallery's previous and next buttons, which sit over arbitrary photographs and need separation from them.

### Named Rules
**The Hairline Rule.** Rows, sections, bars and footers are separated by a 1px line in the line colour and by space, never by cards, fills or shadows.

**The Over-a-Photo Exception.** The only things allowed to float are controls and badges laid over imagery: the gallery arrows (page-colour circle at 95% with the floating-control shadow) and the media badges and counter (black at 65% with white text).

## Shapes

Round where it is a person or an action, nearly square where it is content. Avatars and every button are full circles or pills. Images are softened, not rounded into tiles: 4px on feed thumbnails, gallery thumbnails, inline code and prose images; 6px on the cover, album frame, video and featured image (from 640px only; on phones they run square to the screen edge); 8px on code blocks. Borders are always 1px hairlines, apart from the 3px blockquote rule and the 2px ink ring of the active gallery thumbnail.

## Components

### Buttons
Pills, small and quiet; they never compete with a title.
- **Shape:** full pill (9999px), 32px tall for in-line actions, 40px for page-level actions.
- **Follow (primary):** green fill, on-accent text, Telegram mark at 16px (18px at 40px) with a 6px gap; text "Follow" in bars, "Follow on Telegram" in the post's author block. Hover brightens to 110%; press scales to 0.98.
- **Outline (secondary):** page-colour ground, 1px hairline border, ink text, 500 weight; hover turns the border ink. Used for Share, All posts, Newer and Older pagination.
- **Ink (page action):** ink fill with page-colour text, 40px; hover drops to 85% opacity. Used for the single exit action on a 404.
- **Focus:** every interactive element shows a 2px green outline at 3px offset with a 4px radius.

### Navigation
- **Author bar:** sticky, 56px, page-coloured with a hairline bottom. Left: 30px avatar and the author's name (semibold, -0.01em); right: the Follow pill. It appears on every page of an author's space, including their 404.
- **Site bar:** 56px, hairline bottom, the site name alone in bold sans (1.0625rem, -0.02em). Used on the root directory and the root 404.
- **Pagination:** a hairline-topped row with outline pills at either end and a muted, tabular "Page n of m" between.
- **Neighbour links:** a two-column hairline-bounded strip; a muted "Newer" or "Older" with a 16px arrow above a two-line balanced title that underlines on hover.

### Post Row (signature)
The feed's only unit. Text left, an optional thumbnail right (80px square on phones, 160 by 108px from 640px, 4px radius, surface ground). Optional author byline (20px avatar plus name, label size) above a balanced title, a muted two- or three-line excerpt, then the meta line. The whole row is one link target; hovering underlines the title. Gallery and video thumbnails carry a small black-at-65% pill badge with a stroke icon and photo count.

### Featured Post
The newest post on an author home: a 16:9 cover at 6px radius, a Title Featured heading, a three-line serif excerpt in soft ink, and the meta line. Hover underlines the title at 2px and nudges the cover to 101.5%.

### Gallery (signature)
A Telegram album in its original order. The frame takes the first photo's shape, clamped between 4:5 and 16:9 and to 78% of the viewport height, on the surface ground. Phones swipe a native scroll-snap track; from 640px, 44px floating arrow buttons appear and the arrow keys move between photos. A black-at-65% "n / m" counter sits top right. Below, a strip of 56px (64px from 640px) thumbnails at 4px radius: the active one carries a 2px ink ring offset 2px from the page; inactive ones stay at full opacity but are dimmed to 88% brightness and 55% saturation, returning to full colour on hover.

### Avatar
Always a circle on the surface ground. Without a photo it shows initials in semibold dark ink (#2b2b28, 38% of the avatar size) on one of six muted grounds chosen stably from the username: sand #e9dccb, mist #d3e1ea, sage #d9e6d2, mauve #e7d6e2, straw #e8e2c6, lavender #d8dbe9. Sizes in use: 20, 30, 40, 44, 60, 64 and 96px.

### Author Invitation (root domain)
The root domain's pitch to channel owners, shown only when the bot's username is configured. While there are fewer than three authors it opens the page in place of the reader hero: a Display heading at 16ch and a serif lede on the left, and on the right (below, on phones) the **address specimen**: a muted "Your page's address" label over `yourchannel.<root host>` at 1.625 to 2rem bold, with the name in ink, the root host in muted, and the system's green caret blinking between them (steady under reduced motion). Then come one Follow-style green pill, "Request a page" (it opens the bot), and a muted note saying that authors are added by hand. After the feed, a "What you get" definition list (hairline rows, a 240px semibold term and soft-ink text up to 880px) and a "How it works" three-column ordered list (muted tabular step number, semibold title, soft-ink text, hairline above each) close with the same pill. Once the directory is full, all of this shrinks to a hairline-topped strip above the footer (a title, one line of text and the pill), and the site bar gains a quiet muted "For authors" link to it. It makes no claims about authors, numbers or testimonials.

### Prose
The article body, rendered from author HTML. Links inherit the text colour with a 1px underline at 45% ink, 0.2em offset, turning green on hover. Images and video centre at up to the measure with a 4px radius; captions are small muted sans. Lists use muted markers. Horizontal rules are three spaced middle dots. Tables are sans, tabular, hairline-ruled. Telegram spoilers render as an ink bar that reveals on hover or tap.

**The Blockquote Rule.** A quotation in prose is set off by a 3px solid ink rule on its left, 1.1em of inner space, italic Literata in soft ink, and 1.6em of margin above and below. It is the prose's own quoting device and belongs to quoted text inside the article body.

**The Full-Colour Thumbnail Rule.** Inactive gallery thumbnails are never faded with opacity; they are dimmed and desaturated so every photo stays legible and the active ring is the only state marker.

## Do's and Don'ts

### Do:
- **Do** give every author space the author bar, with their avatar and name on the left and one Follow pill on the right.
- **Do** separate feed items with 1px hairlines and 28px of vertical space, and keep every post type (note, album, video, long read) in the same Post Row grammar.
- **Do** set reading text in Literata at 1.125rem (1.25rem from 640px) on a 680px measure, and everything structural in Golos Text.
- **Do** let covers, albums and video widen to 960px while the text stays at 680px.
- **Do** carry dates, read times and counts in the muted meta line with tabular figures and centred-dot separators.
- **Do** redefine every colour token for dark mode and check both themes.
- **Do** test new type against Cyrillic, Uzbek Latin with oʻ and gʻ, and English in the same view.

### Don't:
- **Don't** put posts in cards, tiles or shadowed boxes, or show a grid of same-size thumbnail cards.
- **Don't** use the green accent for decoration, headings, badges or fills beyond the Follow pill, links, focus and selection.
- **Don't** add a second green pill to a region that already has one; the author home's channel handle is a text link.
- **Don't** cast shadows on content; the floating-control shadow belongs only to controls laid over photos.
- **Don't** fade inactive gallery thumbnails with opacity.
- **Don't** let the platform's name or branding appear in an author's bar or profile block; it lives in the footer line.
- **Don't** invent author names, follower counts, view counts or testimonials on shipped pages.
