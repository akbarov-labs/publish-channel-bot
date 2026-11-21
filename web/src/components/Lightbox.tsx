"use client";

import { useCallback, useRef, useState } from "react";
import { Close } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

/**
 * A photo, full screen. A native <dialog>: Escape closes it, focus is held
 * inside while it is open and returns afterwards; a tap anywhere closes it.
 * Pinch-zoom stays available on phones.
 */
export function useLightbox(locale: Locale) {
  const ref = useRef<HTMLDialogElement>(null);
  const [image, setImage] = useState<{ src: string; alt: string } | null>(null);
  const t = dict(locale);

  const open = useCallback((src: string, alt = "") => {
    setImage({ src, alt });
    requestAnimationFrame(() => ref.current?.showModal());
  }, []);

  const close = () => ref.current?.close();

  const element = (
    <dialog
      ref={ref}
      onClick={close}
      onClose={() => setImage(null)}
      aria-label={image?.alt || t.openImage}
      className="lightbox"
    >
      {image && (
        <>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={image.src} alt={image.alt} className="lightbox-image" />
          <button
            type="button"
            onClick={close}
            aria-label={t.closeImage}
            autoFocus
            className="absolute right-3 top-3 inline-flex size-11 items-center justify-center rounded-full bg-bg/95 text-ink shadow-[0_2px_10px_rgb(0_0_0/0.18)] transition-transform duration-150 ease-out hover:scale-105"
          >
            <Close />
          </button>
        </>
      )}
    </dialog>
  );

  return { open, element };
}

/** An image that opens full screen when tapped: the post's cover. */
export function ZoomableImage({
  src,
  alt,
  className,
  locale,
}: {
  src: string;
  alt: string;
  className?: string;
  locale: Locale;
}) {
  const { open, element } = useLightbox(locale);

  return (
    <>
      <button
        type="button"
        onClick={() => open(src, alt)}
        aria-label={dict(locale).openImage}
        className="block w-full cursor-zoom-in"
      >
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={src} alt={alt} fetchPriority="high" data-fade="" suppressHydrationWarning className={className} />
      </button>
      {element}
    </>
  );
}
