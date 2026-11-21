// Turns a post body into what the reader sees: headings get anchors for the
// table of contents, and [^1] footnote markers become linked notes. Both work
// on the HTML the blog stores, whether it came from the admin or a channel.

import { stripTags } from "@/lib/text";

export type Heading = { id: string; text: string; level: number };

function anchor(text: string, taken: Set<string>): string {
  const base =
    text
      .toLowerCase()
      .normalize("NFKC")
      .replace(/[^\p{L}\p{N}]+/gu, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 60) || "section";
  let id = base;
  for (let n = 2; taken.has(id); n++) id = `${base}-${n}`;
  taken.add(id);
  return id;
}

/** Gives h2-h4 an id (keeping one the author set) and lists them. */
export function withHeadingAnchors(html: string): { html: string; headings: Heading[] } {
  const taken = new Set<string>();
  const headings: Heading[] = [];

  const out = html.replace(/<h([2-4])(\s[^>]*)?>([\s\S]*?)<\/h\1>/gi, (_match, level: string, attrs = "", inner: string) => {
    const text = stripTags(inner);
    if (!text) return _match;
    const existing = /\sid=["']([^"']+)["']/i.exec(attrs)?.[1];
    const id = existing ?? anchor(text, taken);
    headings.push({ id, text, level: Number(level) });
    const attributes = existing ? attrs : `${attrs} id="${id}"`;
    return `<h${level}${attributes}>${inner}</h${level}>`;
  });

  return { html: out, headings };
}

const escapeAttr = (value: string) => value.replace(/[^\p{L}\p{N}_-]/gu, "_");

/**
 * Markdown-style footnotes: "[^1]" in the text, and a paragraph starting
 * with "[^1]:" holding the note. Markers without a note are left alone.
 */
export function withFootnotes(html: string, backLabel: string): string {
  const notes = new Map<string, string>();

  // Notes are whole paragraphs; take them out of the body.
  let body = html.replace(/<p>\s*\[\^([^\]\s]{1,20})\]:\s*([\s\S]*?)<\/p>/gi, (_match, label: string, text: string) => {
    notes.set(label, text.trim());
    return "";
  });
  if (notes.size === 0) return html;

  const order: string[] = [];
  body = body.replace(/\[\^([^\]\s]{1,20})\](?!:)/g, (match, label: string) => {
    if (!notes.has(label)) return match;
    let number = order.indexOf(label) + 1;
    const first = number === 0;
    if (first) {
      order.push(label);
      number = order.length;
    }
    const id = escapeAttr(label);
    const refId = first ? ` id="fnref-${id}"` : "";
    return `<sup class="footnote-ref"${refId}><a href="#fn-${id}">${number}</a></sup>`;
  });
  if (order.length === 0) return html;

  const items = order
    .map((label) => {
      const id = escapeAttr(label);
      return `<li id="fn-${id}">${notes.get(label)} <a href="#fnref-${id}" class="footnote-back" aria-label="${backLabel}">↩</a></li>`;
    })
    .join("");

  return `${body}<section class="footnotes" role="doc-endnotes"><hr /><ol>${items}</ol></section>`;
}
