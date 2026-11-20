import Link from "next/link";
import type { SeriesSummary } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";

/** "Part 2 of 5" and the list of parts, on a post that belongs to a series. */
export default function SeriesBox({ series, current, t }: { series: SeriesSummary; current: string; t: Dictionary }) {
  const index = series.parts.findIndex((part) => part.slug === current);
  if (index < 0) return null;

  return (
    <aside className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
      <div className="rounded-md bg-surface px-5 py-4">
        <p className="text-[0.8125rem] text-muted">
          {t.series} ·{" "}
          <span className="tabular-nums">{t.partOf(index + 1, series.parts.length)}</span>
        </p>
        <p className="title mt-1 text-[1.0625rem] leading-snug">
          <Link href={`/series/${series.slug}`} className="hover:underline hover:underline-offset-4">
            {series.title}
          </Link>
        </p>
        <ol className="mt-3 space-y-1.5 text-[0.9375rem] leading-snug">
          {series.parts.map((part, i) => (
            <li key={part.slug} className="flex gap-2.5">
              <span className="w-5 shrink-0 text-right text-muted tabular-nums">{i + 1}.</span>
              {part.slug === current ? (
                <span aria-current="page" className="font-semibold">
                  {part.title}
                </span>
              ) : (
                <Link href={`/posts/${part.slug}`} className="text-ink-soft hover:text-ink hover:underline hover:underline-offset-4">
                  {part.title}
                </Link>
              )}
            </li>
          ))}
        </ol>
      </div>
    </aside>
  );
}
