import type { Heading } from "@/lib/article";

/** The table of contents of a long post: shown once it has three headings. */
export default function Contents({ headings, label }: { headings: Heading[]; label: string }) {
  if (headings.length < 3) return null;
  const top = Math.min(...headings.map((heading) => heading.level));

  return (
    <nav aria-label={label} className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
      <details open className="group rounded-md border border-line px-5 py-4">
        <summary className="cursor-pointer list-none text-[0.8125rem] font-semibold uppercase tracking-[0.06em] text-muted marker:hidden">
          {label}
        </summary>
        <ol className="mt-3 space-y-2 text-[0.9375rem] leading-snug">
          {headings.map((heading) => (
            <li key={heading.id} style={{ paddingLeft: `${(heading.level - top) * 1}rem` }}>
              <a href={`#${heading.id}`} className="text-ink-soft hover:text-ink hover:underline hover:underline-offset-4">
                {heading.text}
              </a>
            </li>
          ))}
        </ol>
      </details>
    </nav>
  );
}
