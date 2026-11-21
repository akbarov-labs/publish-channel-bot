"use client";

import { useEffect, useRef, useState } from "react";
import type { Heading } from "@/lib/article";

/**
 * The table of contents of a long post. Open from 640px, folded on phones so
 * the writing comes first. Jumps scroll smoothly (unless the reader asked for
 * less motion), and the section in view is marked as the reader goes.
 */
export default function Contents({ headings, label }: { headings: Heading[]; label: string }) {
  const ref = useRef<HTMLDetailsElement>(null);
  const [active, setActive] = useState<string | null>(null);
  const top = Math.min(...headings.map((heading) => heading.level));

  useEffect(() => {
    if (ref.current && window.matchMedia("(min-width: 640px)").matches) ref.current.open = true;
  }, []);

  useEffect(() => {
    const targets = headings
      .map((heading) => document.getElementById(heading.id))
      .filter((element): element is HTMLElement => element !== null);
    if (targets.length === 0) return;

    // The section in view is the last heading that has passed the upper third.
    const observer = new IntersectionObserver(
      () => {
        const line = window.innerHeight * 0.33;
        let current: string | null = null;
        for (const target of targets) {
          if (target.getBoundingClientRect().top <= line) current = target.id;
        }
        setActive(current);
      },
      { rootMargin: "0px 0px -66% 0px", threshold: [0, 1] },
    );
    targets.forEach((target) => observer.observe(target));
    return () => observer.disconnect();
  }, [headings]);

  const jump = (event: React.MouseEvent<HTMLAnchorElement>, id: string) => {
    const target = document.getElementById(id);
    if (!target) return;
    event.preventDefault();
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    target.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
    history.replaceState(null, "", `#${id}`);
    target.focus({ preventScroll: true });
  };

  return (
    <nav aria-label={label} className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
      <details ref={ref} className="contents-box group border-y border-line">
        <summary className="flex cursor-pointer list-none items-center justify-between py-3.5 text-[0.9375rem] font-semibold [&::-webkit-details-marker]:hidden">
          {label}
          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className="size-4 text-muted transition-transform duration-200 ease-out group-open:rotate-180"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="m6 9 6 6 6-6" />
          </svg>
        </summary>
        <ol className="space-y-2 pb-4 text-[0.9375rem] leading-snug">
          {headings.map((heading) => (
            <li key={heading.id} style={{ paddingLeft: `${(heading.level - top) * 1}rem` }}>
              <a
                href={`#${heading.id}`}
                onClick={(event) => jump(event, heading.id)}
                aria-current={active === heading.id ? "location" : undefined}
                className="contents-link"
              >
                {heading.text}
              </a>
            </li>
          ))}
        </ol>
      </details>
    </nav>
  );
}
