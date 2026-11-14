import type { PostSummary } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";

export function stripTags(html: string): string {
  return html
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/\s+/g, " ")
    .trim();
}

export function readingMinutes(html: string | null): number {
  if (!html) return 1;
  const words = stripTags(html).split(" ").filter(Boolean).length;
  return Math.max(1, Math.round(words / 200));
}

/**
 * Best guess at the language of a piece of text, for the lang attribute: it
 * picks the right hyphenation and quotes, and helps screen readers.
 */
export function guessLang(text: string): "uz" | "ru" | "en" {
  if (/[ўқғҳЎҚҒҲ]/.test(text)) return "uz";
  if (/[Ѐ-ӿ]/.test(text)) return "ru";
  if (/[oOgG][ʻ‘'’`]/.test(text)) return "uz";
  return "en";
}

/**
 * Channel posts use their first line as the title, and the body still starts
 * with that line. Drop it from the body so the title is not printed twice.
 */
export function withoutLeadingTitle(html: string, title: string): string {
  const match = html.match(/^\s*<p>([\s\S]*?)(<br\s*\/?>|<\/p>)/i);
  if (!match || stripTags(match[1]) !== stripTags(title)) return html;

  const rest = html.slice(match[0].length);
  return match[2].toLowerCase().startsWith("<br") ? `<p>${rest.replace(/^\s*/, "")}` : rest;
}

/** The excerpt minus a repeated title, or null when nothing is left. */
export function cleanExcerpt(post: Pick<PostSummary, "excerpt" | "title">): string | null {
  if (!post.excerpt) return null;
  const excerpt = post.excerpt.trim();
  const title = post.title.trim();
  // The API ends each block with a full stop, so the title leaves one behind.
  const rest = excerpt.startsWith(title) ? excerpt.slice(title.length).replace(/^[.:;!?…\s]+/, "") : excerpt;
  return rest.length > 0 ? rest : null;
}

export function photoCount(post: Pick<PostSummary, "media">): number {
  return post.media.filter((item) => item.type === "photo").length;
}

/** What kind of post this is, in plain words, or null for an ordinary article. */
export function typeLabel(post: Pick<PostSummary, "post_type" | "media">, t: Dictionary): string | null {
  if (post.post_type === "gallery") return t.photos(photoCount(post));
  if (post.post_type === "video") return t.video;
  return null;
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => Array.from(word)[0]?.toUpperCase() ?? "")
    .join("");
}

/** A cover for listings: the post's cover, else its first photo. */
export function coverOf(post: Pick<PostSummary, "cover_image" | "media">): string | null {
  return post.cover_image ?? post.media.find((item) => item.type === "photo")?.url ?? null;
}
