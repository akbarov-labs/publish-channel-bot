// RSS 2.0 and sitemap XML, written by hand: both formats are small and fixed,
// and a library would cost more than these few lines.

import type { PostSummary } from "@/lib/api";
import { cleanExcerpt } from "@/lib/text";

export function escapeXml(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

/** HTML inside CDATA; a literal "]]>" in the body would end the section early. */
function cdata(html: string): string {
  return `<![CDATA[${html.replaceAll("]]>", "]]]]><![CDATA[>")}]]>`;
}

type FeedItem = PostSummary & { html?: string | null; authorName?: string };

export type Feed = {
  title: string;
  description: string;
  /** The page the feed belongs to. */
  link: string;
  /** The feed's own address. */
  self: string;
  language?: string;
  image?: string | null;
  items: FeedItem[];
};

function item(post: FeedItem): string {
  const description = cleanExcerpt(post);
  const cover = post.cover_image ? `<p><img src="${escapeXml(post.cover_image)}" alt="" /></p>` : "";
  const body = post.html ? cover + post.html : null;

  return [
    "<item>",
    `<title>${escapeXml(post.title)}</title>`,
    `<link>${escapeXml(post.url)}</link>`,
    `<guid isPermaLink="true">${escapeXml(post.url)}</guid>`,
    post.published_at ? `<pubDate>${new Date(post.published_at).toUTCString()}</pubDate>` : "",
    post.authorName ? `<dc:creator>${escapeXml(post.authorName)}</dc:creator>` : "",
    description ? `<description>${escapeXml(description)}</description>` : "",
    body ? `<content:encoded>${cdata(body)}</content:encoded>` : "",
    post.cover_image ? `<enclosure url="${escapeXml(post.cover_image)}" type="${imageType(post.cover_image)}" length="0" />` : "",
    "</item>",
  ]
    .filter(Boolean)
    .join("");
}

function imageType(url: string): string {
  const extension = new URL(url, "http://x").pathname.split(".").pop()?.toLowerCase();
  if (extension === "png") return "image/png";
  if (extension === "webp") return "image/webp";
  if (extension === "gif") return "image/gif";
  return "image/jpeg";
}

export function rss(feed: Feed): Response {
  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">',
    "<channel>",
    `<title>${escapeXml(feed.title)}</title>`,
    `<link>${escapeXml(feed.link)}</link>`,
    `<description>${escapeXml(feed.description)}</description>`,
    `<atom:link href="${escapeXml(feed.self)}" rel="self" type="application/rss+xml" />`,
    feed.language ? `<language>${escapeXml(feed.language)}</language>` : "",
    feed.items[0]?.published_at ? `<lastBuildDate>${new Date(feed.items[0].published_at).toUTCString()}</lastBuildDate>` : "",
    feed.image
      ? `<image><url>${escapeXml(feed.image)}</url><title>${escapeXml(feed.title)}</title><link>${escapeXml(feed.link)}</link></image>`
      : "",
    ...feed.items.map(item),
    "</channel>",
    "</rss>",
  ]
    .filter(Boolean)
    .join("\n");

  return xmlResponse(xml, "application/rss+xml");
}

export function sitemap(urls: { loc: string; lastmod?: string | null }[]): Response {
  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls.map(
      ({ loc, lastmod }) =>
        `<url><loc>${escapeXml(loc)}</loc>${lastmod ? `<lastmod>${escapeXml(lastmod)}</lastmod>` : ""}</url>`,
    ),
    "</urlset>",
  ].join("\n");

  return xmlResponse(xml, "application/xml");
}

function xmlResponse(xml: string, type: string): Response {
  return new Response(xml, {
    headers: {
      "Content-Type": `${type}; charset=utf-8`,
      // Feed readers and crawlers poll; a few minutes of caching keeps them off the API.
      "Cache-Control": "public, max-age=300, stale-while-revalidate=600",
    },
  });
}
