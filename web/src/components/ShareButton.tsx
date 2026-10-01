"use client";

import { useState } from "react";

/**
 * Readers mostly arrive from Telegram and pass links on. Phones get the native
 * share sheet; elsewhere the link is copied.
 */
export default function ShareButton({ url, title }: { url: string; title: string }) {
  const [copied, setCopied] = useState(false);

  const share = async () => {
    if (navigator.share && window.matchMedia("(pointer: coarse)").matches) {
      try {
        await navigator.share({ url, title });
      } catch {
        // The reader closed the share sheet.
      }
      return;
    }

    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      window.prompt("Copy this link", url);
    }
  };

  return (
    <button
      type="button"
      onClick={share}
      aria-live="polite"
      className="inline-flex h-8 items-center rounded-full border border-line px-3 text-[0.8125rem] font-medium transition-colors hover:border-ink"
    >
      {copied ? "Link copied" : "Share"}
    </button>
  );
}
