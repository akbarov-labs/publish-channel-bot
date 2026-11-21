"use client";

import { useEffect, useRef, useState } from "react";
import { Check, LinkIcon, Telegram } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

/**
 * Readers mostly arrive from Telegram and pass links on. Phones get the native
 * share sheet; elsewhere a small menu offers Telegram or copying the link.
 */
export default function ShareButton({ url, title, locale }: { url: string; title: string; locale: Locale }) {
  const t = dict(locale);
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const wrap = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: PointerEvent) => {
      if (!wrap.current?.contains(event.target as Node)) setOpen(false);
    };
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const share = async () => {
    if (navigator.share && window.matchMedia("(pointer: coarse)").matches) {
      try {
        await navigator.share({ url, title });
      } catch {
        // The reader closed the share sheet.
      }
      return;
    }
    setOpen((value) => !value);
  };

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => {
        setCopied(false);
        setOpen(false);
      }, 1400);
    } catch {
      window.prompt(t.copyThisLink, url);
    }
  };

  const item =
    "flex h-10 w-full items-center gap-2.5 rounded-[4px] px-3 text-left text-[0.9375rem] transition-colors hover:bg-surface";

  return (
    <div ref={wrap} className="relative">
      <button
        type="button"
        onClick={share}
        aria-expanded={open}
        aria-haspopup="menu"
        className="inline-flex h-8 items-center rounded-full border border-line px-3 text-[0.8125rem] font-medium transition-[border-color,transform] duration-150 ease-out hover:border-ink active:scale-[0.97]"
      >
        {t.share}
      </button>
      {open && (
        <div role="menu" className="share-menu absolute right-0 top-10 z-20 w-56 rounded-md border border-line bg-bg p-1">
          <a
            role="menuitem"
            href={`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`}
            target="_blank"
            rel="noopener noreferrer"
            onClick={() => setOpen(false)}
            className={item}
          >
            <Telegram className="size-4 text-muted" />
            {t.shareToTelegram}
          </a>
          <button role="menuitem" type="button" onClick={copy} className={item} aria-live="polite">
            {copied ? <Check className="size-4 text-ink" /> : <LinkIcon className="size-4 text-muted" />}
            {copied ? t.linkCopied : t.copyLink}
          </button>
        </div>
      )}
    </div>
  );
}
