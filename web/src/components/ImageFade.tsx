"use client";

import { useEffect } from "react";

/**
 * Lets images fade in as they arrive instead of popping onto a blank square.
 * It switches on only after the page has hydrated: images that were already
 * there are marked loaded first, so nothing visible ever disappears, and
 * everything that loads later (thumbnails further down, pages opened from a
 * link) fades in. Only images marked data-fade take part; they carry
 * suppressHydrationWarning, because streaming can hydrate a part of the page
 * after this has already marked its images, and that difference is intended.
 */
export default function ImageFade() {
  useEffect(() => {
    const mark = (image: Element) => image.setAttribute("data-loaded", "");
    const onEvent = (event: Event) => {
      const target = event.target as Element | null;
      if (target?.tagName === "IMG" && target.hasAttribute("data-fade")) mark(target);
    };

    document.addEventListener("load", onEvent, true);
    document.addEventListener("error", onEvent, true);
    document.querySelectorAll<HTMLImageElement>("img[data-fade]").forEach((image) => {
      if (image.complete) mark(image);
    });
    document.documentElement.setAttribute("data-image-fade", "");

    return () => {
      document.removeEventListener("load", onEvent, true);
      document.removeEventListener("error", onEvent, true);
      document.documentElement.removeAttribute("data-image-fade");
    };
  }, []);

  return null;
}
