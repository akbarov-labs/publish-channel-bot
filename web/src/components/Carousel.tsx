"use client";

import { useCallback, useEffect, useState } from "react";
import type { PostMedium } from "@/lib/api";

export default function Carousel({ items }: { items: PostMedium[] }) {
  const [index, setIndex] = useState(0);
  const count = items.length;

  const go = useCallback(
    (delta: number) => setIndex((current) => (current + delta + count) % count),
    [count],
  );

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "ArrowLeft") go(-1);
      if (event.key === "ArrowRight") go(1);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [go]);

  if (count === 0) return null;

  const current = items[index];

  return (
    <figure className="my-8">
      <div className="relative overflow-hidden rounded-xl bg-black/5 dark:bg-white/5">
        {current.type === "video" ? (
          <video src={current.url} controls className="h-auto w-full" />
        ) : (
          // Sources are arbitrary blog/CDN hosts, so plain <img> avoids having
          // to allowlist every domain in next.config.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={current.url}
            alt={`Image ${index + 1} of ${count}`}
            className="h-auto w-full object-contain"
          />
        )}

        {count > 1 && (
          <>
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Previous image"
              className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 px-3 py-2 text-white transition hover:bg-black/70"
            >
              ‹
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Next image"
              className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 px-3 py-2 text-white transition hover:bg-black/70"
            >
              ›
            </button>
          </>
        )}
      </div>

      {count > 1 && (
        <div className="mt-3 flex items-center justify-center gap-2">
          {items.map((item, position) => (
            <button
              key={item.id ?? position}
              type="button"
              onClick={() => setIndex(position)}
              aria-label={`Go to image ${position + 1}`}
              aria-current={position === index}
              className={`h-2 rounded-full transition-all ${
                position === index
                  ? "w-6 bg-foreground"
                  : "w-2 bg-foreground/30 hover:bg-foreground/50"
              }`}
            />
          ))}
        </div>
      )}

      <figcaption className="mt-2 text-center text-sm text-foreground/60">
        {index + 1} / {count}
      </figcaption>
    </figure>
  );
}
