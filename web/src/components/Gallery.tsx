"use client";

import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight } from "@/components/Icons";
import type { PostMedium } from "@/lib/api";

/**
 * A Telegram album in its original order. The track is a native scroll-snap
 * strip, so phones swipe it with no script at all; the script only keeps the
 * counter, arrows and thumbnails in step with wherever the reader scrolled.
 */
export default function Gallery({ items, label }: { items: PostMedium[]; label: string }) {
  const trackRef = useRef<HTMLDivElement>(null);
  const thumbsRef = useRef<HTMLDivElement>(null);
  const [index, setIndex] = useState(0);
  // The frame takes the first photo's shape, from portrait 4:5 to wide 16:9,
  // so a typical album shows without letterbox bands.
  const [ratio, setRatio] = useState(4 / 3);
  const count = items.length;

  const measure = (image: HTMLImageElement | null) => {
    if (!image?.naturalWidth || !image.naturalHeight) return;
    setRatio(Math.min(16 / 9, Math.max(4 / 5, image.naturalWidth / image.naturalHeight)));
  };

  // A cached first photo can finish loading before hydration, when onLoad has
  // no listener yet; read its size directly in that case.
  useEffect(() => {
    const first = trackRef.current?.querySelector("img");
    if (first?.complete) measure(first);
  }, []);

  useEffect(() => {
    const track = trackRef.current;
    if (!track) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) setIndex(Number((entry.target as HTMLElement).dataset.index));
        }
      },
      { root: track, threshold: 0.6 },
    );
    for (const slide of Array.from(track.children)) observer.observe(slide);
    return () => observer.disconnect();
  }, [count]);

  // Keep the active thumbnail in view without scrolling the page itself.
  useEffect(() => {
    const strip = thumbsRef.current;
    const thumb = strip?.children[index] as HTMLElement | undefined;
    if (!strip || !thumb) return;
    const left = thumb.offsetLeft - strip.clientWidth / 2 + thumb.clientWidth / 2;
    strip.scrollTo({ left, behavior: "smooth" });
  }, [index]);

  const go = (target: number) => {
    const track = trackRef.current;
    if (!track) return;
    const next = Math.max(0, Math.min(count - 1, target));
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    track.scrollTo({ left: next * track.clientWidth, behavior: reduce ? "auto" : "smooth" });
  };

  const onKeyDown = (event: React.KeyboardEvent) => {
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      go(index - 1);
    } else if (event.key === "ArrowRight") {
      event.preventDefault();
      go(index + 1);
    }
  };

  if (count === 0) return null;

  const arrow =
    "absolute top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-bg/95 text-ink shadow-[0_2px_10px_rgb(0_0_0/0.18)] transition-[opacity,transform] duration-150 ease-out hover:scale-105 disabled:pointer-events-none disabled:opacity-0 sm:flex";

  return (
    <section aria-roledescription="carousel" aria-label={label} onKeyDown={onKeyDown} className="relative">
      <div className="relative">
        <div
          ref={trackRef}
          tabIndex={0}
          aria-live="polite"
          className="no-scrollbar flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain bg-surface sm:rounded-md"
        >
          {items.map((item, position) => (
            <div
              key={item.id ?? position}
              data-index={position}
              role="group"
              aria-roledescription="slide"
              aria-label={`${position + 1} of ${count}`}
              style={{ aspectRatio: ratio }}
              className="flex max-h-[78vh] w-full shrink-0 snap-center items-center justify-center"
            >
              {item.type === "video" ? (
                <video src={item.url} controls playsInline preload="metadata" className="h-full w-full object-contain" />
              ) : (
                // Album photos live on arbitrary hosts; a plain <img> avoids an allowlist.
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={item.url}
                  alt={`Photo ${position + 1} of ${count}`}
                  loading={position < 2 ? "eager" : "lazy"}
                  decoding="async"
                  draggable={false}
                  onLoad={position === 0 ? (event) => measure(event.currentTarget) : undefined}
                  className="h-full w-full object-contain"
                />
              )}
            </div>
          ))}
        </div>

        {count > 1 && (
          <>
            <button type="button" onClick={() => go(index - 1)} disabled={index === 0} aria-label="Previous photo" className={`${arrow} left-3`}>
              <ChevronLeft />
            </button>
            <button type="button" onClick={() => go(index + 1)} disabled={index === count - 1} aria-label="Next photo" className={`${arrow} right-3`}>
              <ChevronRight />
            </button>
            <span className="pointer-events-none absolute right-3 top-3 rounded-full bg-black/65 px-2.5 py-1 text-xs font-medium text-white tabular-nums">
              {index + 1} / {count}
            </span>
          </>
        )}
      </div>

      {count > 1 && (
        <div ref={thumbsRef} className="no-scrollbar mt-2 flex gap-2 overflow-x-auto px-4 py-1.5 sm:px-1">
          {items.map((item, position) => (
            <button
              key={item.id ?? position}
              type="button"
              onClick={() => go(position)}
              aria-label={`Show photo ${position + 1}`}
              aria-current={position === index ? "true" : undefined}
              className={`relative size-14 shrink-0 overflow-hidden rounded-[4px] bg-surface transition-opacity duration-150 sm:size-16 ${
                position === index ? "opacity-100 ring-2 ring-ink ring-offset-2 ring-offset-bg" : "opacity-55 hover:opacity-90"
              }`}
            >
              {item.type === "video" ? (
                <span className="flex h-full w-full items-center justify-center text-xs text-muted">Video</span>
              ) : (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={item.url} alt="" loading="lazy" decoding="async" className="h-full w-full object-cover" />
              )}
            </button>
          ))}
        </div>
      )}
    </section>
  );
}
