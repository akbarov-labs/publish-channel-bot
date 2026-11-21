"use client";

import { useEffect, useRef, useState } from "react";
import { useLightbox } from "@/components/Lightbox";
import { dict, type Locale } from "@/lib/i18n";

const LINK_SVG =
  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>';
const CHECK_SVG =
  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';

type Note = { html: string; top: number; left: number };

/**
 * The post body. It arrives as the author's HTML (from the admin or the
 * channel), so the reading helpers are added after it renders:
 * - photos open full screen when tapped;
 * - each heading gets a button that copies a link to that section;
 * - footnote numbers open their note in place instead of jumping away.
 * Without script it is the same page, with plain anchors that still work.
 */
export default function Prose({ html, locale }: { html: string; locale: Locale }) {
  const ref = useRef<HTMLDivElement>(null);
  const noteRef = useRef<HTMLDivElement>(null);
  const [note, setNote] = useState<Note | null>(null);
  const [announce, setAnnounce] = useState("");
  const { open, element } = useLightbox(locale);
  const t = dict(locale);

  useEffect(() => {
    const root = ref.current;
    if (!root) return;
    const cleanups: (() => void)[] = [];

    for (const image of Array.from(root.querySelectorAll("img"))) {
      if (image.closest("a")) continue;
      image.classList.add("zoomable");
      image.tabIndex = 0;
      image.setAttribute("role", "button");
      image.setAttribute("aria-label", image.alt || t.openImage);
      const show = () => open(image.currentSrc || image.src, image.alt);
      const onKey = (event: KeyboardEvent) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          show();
        }
      };
      image.addEventListener("click", show);
      image.addEventListener("keydown", onKey);
      cleanups.push(() => {
        image.removeEventListener("click", show);
        image.removeEventListener("keydown", onKey);
      });
    }

    for (const heading of Array.from(root.querySelectorAll<HTMLElement>("h2[id], h3[id], h4[id]"))) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "heading-link";
      button.setAttribute("aria-label", t.linkToSection);
      button.innerHTML = LINK_SVG;
      const copy = async () => {
        const url = `${location.origin}${location.pathname}#${heading.id}`;
        history.replaceState(null, "", `#${heading.id}`);
        try {
          await navigator.clipboard.writeText(url);
          button.innerHTML = CHECK_SVG;
          button.dataset.copied = "";
          setAnnounce(t.linkCopied);
          setTimeout(() => {
            button.innerHTML = LINK_SVG;
            delete button.dataset.copied;
            setAnnounce("");
          }, 1600);
        } catch {
          window.prompt(t.copyThisLink, url);
        }
      };
      button.addEventListener("click", copy);
      heading.append(button);
      cleanups.push(() => button.remove());
    }

    const supportsPopover = typeof HTMLElement !== "undefined" && "showPopover" in HTMLElement.prototype;
    if (supportsPopover) {
      for (const link of Array.from(root.querySelectorAll<HTMLAnchorElement>(".footnote-ref a"))) {
        const onClick = (event: MouseEvent) => {
          const target = root.querySelector<HTMLElement>(link.hash);
          if (!target) return;
          event.preventDefault();
          const body = target.cloneNode(true) as HTMLElement;
          body.querySelector(".footnote-back")?.remove();
          const rect = link.getBoundingClientRect();
          const width = Math.min(300, window.innerWidth - 32);
          const left = Math.min(Math.max(16, rect.left + rect.width / 2 - width / 2), window.innerWidth - width - 16);
          const below = rect.bottom + 10;
          setNote({ html: body.innerHTML, top: below, left });
        };
        link.addEventListener("click", onClick);
        cleanups.push(() => link.removeEventListener("click", onClick));
      }
    }

    return () => cleanups.forEach((cleanup) => cleanup());
  }, [html, open, t.copyThisLink, t.linkCopied, t.linkToSection, t.openImage]);

  // Open the note once it has rendered, and keep it inside the screen.
  useEffect(() => {
    const popover = noteRef.current;
    if (!note || !popover) return;
    popover.showPopover();
    const rect = popover.getBoundingClientRect();
    if (rect.bottom > window.innerHeight - 12) {
      popover.style.top = `${Math.max(12, note.top - rect.height - 40)}px`;
    }
  }, [note]);

  return (
    <>
      {/* The body is written by the author, in the admin or in their channel, so it is trusted content. */}
      <div ref={ref} className="prose-post" dangerouslySetInnerHTML={{ __html: html }} />
      <div
        ref={noteRef}
        popover="auto"
        role="note"
        aria-label={t.note}
        onToggle={(event) => {
          if ((event as unknown as ToggleEvent).newState === "closed") setNote(null);
        }}
        style={note ? { top: note.top, left: note.left } : undefined}
        className="footnote-pop"
        dangerouslySetInnerHTML={{ __html: note?.html ?? "" }}
      />
      <span aria-live="polite" className="sr-only">
        {announce}
      </span>
      {element}
    </>
  );
}
