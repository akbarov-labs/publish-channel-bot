"use client";

import { useRef, useState } from "react";
import { ExternalLink } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

/**
 * Comments live in the channel's discussion group. Telegram's widget shows
 * them here, but it loads a script from telegram.org, so it waits until the
 * reader asks for it.
 */
export default function Comments({ post, locale }: { post: string; locale: Locale }) {
  const t = dict(locale);
  const box = useRef<HTMLDivElement>(null);
  const [shown, setShown] = useState(false);

  const show = () => {
    if (shown || !box.current) return;
    setShown(true);
    const script = document.createElement("script");
    script.async = true;
    script.src = "https://telegram.org/js/telegram-widget.js?22";
    script.dataset.telegramDiscussion = post;
    script.dataset.commentsLimit = "10";
    script.dataset.color = "1a7f37";
    if (window.matchMedia("(prefers-color-scheme: dark)").matches) script.dataset.dark = "1";
    box.current.appendChild(script);
  };

  return (
    <section aria-labelledby="comments" className="mt-12">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="comments" className="text-[0.9375rem] font-semibold">
          {t.comments}
        </h2>
        <a
          href={`https://t.me/${post}`}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1.5 text-[0.8125rem] text-muted underline decoration-line underline-offset-4 hover:text-ink"
        >
          {t.discussInTelegram}
          <ExternalLink className="size-3.5" />
        </a>
      </div>
      {!shown && (
        <button
          type="button"
          onClick={show}
          className="mt-4 inline-flex h-10 items-center rounded-full border border-line px-4 text-[0.9375rem] font-medium transition-colors hover:border-ink"
        >
          {t.showComments}
        </button>
      )}
      <div ref={box} className="mt-4 min-h-0" />
    </section>
  );
}
