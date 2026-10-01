# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Readers of Telegram channels. Most arrive from a link inside Telegram — the
announcement the bot posts when an article is published — so the first visit
usually happens on a phone, in the in-app browser, mid-scroll through other
channels. A smaller group lands on the root domain looking for writers to read.

Authors are the other audience: people who already run a Telegram channel and
want it to exist as a proper website without maintaining one. They judge the
site by whether their channel looks like it deserves the address.

## Product Purpose

One reader-facing site for many authors. Each author's Telegram channel is
mirrored into their own space at `<username>.<root domain>`; the root domain
lists the authors and their latest posts. Success: a reader who came for one
post stays for the next, and an author is proud to put the subdomain in their
channel description.

## Positioning

The channel writes the site. Posts, photo albums and full telegra.ph articles
arrive from Telegram on their own, keep their original publish dates, and edits
follow. Nothing on the site is authored for the site — it is the channel, given
a permanent, linkable, searchable home.

## Operating Context

- Content comes from the sync bot (`bot/`) via the Laravel blog API (`blog/`),
  or is written in the Filament admin.
- Post types: `article` (text, sometimes a full telegra.ph article), `gallery`
  (a Telegram album, order preserved, shown as a carousel), `video`.
- Short channel posts and long articles sit side by side in one feed. Titles of
  Telegram posts are the post's first line, so they can be long or chatty.
- Images come from arbitrary hosts (the blog's storage, CDNs), so the site uses
  plain `<img>` rather than an allowlisted image optimizer.
- Many posts also exist on telegra.ph for Instant View; the site links to it.

## Capabilities and Constraints

- Stack: Next.js 16 (App Router), React 19, Tailwind 4, standalone output in
  Docker. Laravel 13 + Filament 5 backend.
- Authors are addressed by subdomain: `<username>.<root domain>`. Post URLs are
  `<username>.<root domain>/posts/<slug>`. Slugs are unique per author.
- Languages: Russian (Cyrillic), Uzbek (Latin, including oʻ and gʻ, U+02BB),
  and English, often mixed on one page and sometimes within one author's feed.
  Every face must cover Cyrillic and U+02BB.
- Deletions in Telegram do not propagate; edits do.
- Undecided: per-author theming beyond name, avatar, bio and channel link.

## Brand Commitments

- Standing preference (chosen over two dealt direction hands, 2026-10-01): the
  category standard for a multi-author reading site, executed straight. The
  craft bar is Substack and Medium. No novelty world, no smuggled quirk.

## Evidence on Hand

No real author content, avatars or logos exist in the repository. Do not invent
author names, follower counts, view counts or testimonials on shipped pages;
demonstration content belongs only in local seed data and is labelled as such.

## Product Principles

1. The writing leads. Chrome is never louder than a post.
2. Every post type — a two-line note, a 20-photo album, a 5,000-word article —
   looks intended, not like a fallback.
3. Each author's space feels like theirs; the platform stays in the background.
4. Arrival from Telegram on a phone is the primary scene; it must be fast and
   legible there first.
5. Three scripts, one voice: Cyrillic and Latin text must look equally designed.

## Accessibility & Inclusion

WCAG AA contrast, keyboard-operable carousel, `lang` attributes, reduced-motion
respected. Readers on low-end Android phones and slow mobile networks are a
real share of the audience.
