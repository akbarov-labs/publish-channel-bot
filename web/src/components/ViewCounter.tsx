"use client";

import { useEffect } from "react";

/**
 * Counts this visit once per browser session. A beacon, so it never holds up
 * the page; the blog caps repeats from one address on top of this.
 */
export default function ViewCounter({ slug }: { slug: string }) {
  useEffect(() => {
    const key = `viewed:${slug}`;
    try {
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, "1");
    } catch {
      // Storage blocked (private mode): count anyway.
    }
    const url = `/posts/${encodeURIComponent(slug)}/view`;
    if (!navigator.sendBeacon?.(url)) {
      fetch(url, { method: "POST", keepalive: true }).catch(() => {});
    }
  }, [slug]);

  return null;
}
