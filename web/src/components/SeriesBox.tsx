import Link from "next/link";
import type { SeriesSummary } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";

function position(series: SeriesSummary, current: string) {
  return series.parts.findIndex((part) => part.slug === current);
}

/** Above the post: one quiet line saying which series and which part. */
export function SeriesLine({ series, current, t }: { series: SeriesSummary; current: string; t: Dictionary }) {
  const index = position(series, current);
  if (index < 0) return null;

  return (
    <p className="mt-5 text-[0.8125rem] text-muted tabular-nums">
      <Link href={`/series/${series.slug}`} className="font-medium text-ink-soft underline decoration-line underline-offset-4 hover:text-ink hover:decoration-ink">
        {series.title}
      </Link>
      <span aria-hidden="true"> · </span>
      {t.partOf(index + 1, series.parts.length)}
    </p>
  );
}

/** After the post: every part, the current one marked, between hairlines. */
export function SeriesList({ series, current, t }: { series: SeriesSummary; current: string; t: Dictionary }) {
  const index = position(series, current);
  if (index < 0) return null;

  return (
    <section aria-labelledby="series-parts" className="border-t border-line pt-6">
      <h2 id="series-parts" className="text-[0.9375rem] font-semibold">
        <Link href={`/series/${series.slug}`} className="hover:underline hover:underline-offset-4">
          {series.title}
        </Link>
      </h2>
      <p className="mt-0.5 text-[0.8125rem] text-muted tabular-nums">
        {t.series} · {t.partOf(index + 1, series.parts.length)}
      </p>
      <ol className="mt-4 space-y-2 text-[0.9375rem] leading-snug">
        {series.parts.map((part, i) => (
          <li key={part.slug} className="flex gap-3">
            <span className="w-5 shrink-0 text-right text-muted tabular-nums">{i + 1}</span>
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
    </section>
  );
}
