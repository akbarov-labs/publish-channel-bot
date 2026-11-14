import Link from "next/link";
import { ArrowLeft, ArrowRight } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

export default function Pagination({
  page,
  lastPage,
  locale,
  basePath = "/",
}: {
  page: number;
  lastPage: number;
  locale: Locale;
  basePath?: string;
}) {
  if (lastPage <= 1) return null;

  const t = dict(locale);

  const href = (target: number) => (target === 1 ? basePath : `${basePath}?page=${target}`);
  const button =
    "inline-flex h-10 items-center gap-2 rounded-full border border-line px-4 text-[0.9375rem] font-medium transition-colors duration-150 hover:border-ink";

  return (
    <nav aria-label={t.pages} className="mt-6 flex items-center justify-between gap-4 border-t border-line pt-8">
      {page > 1 ? (
        <Link href={href(page - 1)} className={button} rel="prev">
          <ArrowLeft className="size-4" />
          {t.newer}
        </Link>
      ) : (
        <span />
      )}
      <span className="text-sm text-muted tabular-nums">
        {t.pageOf(page, lastPage)}
      </span>
      {page < lastPage ? (
        <Link href={href(page + 1)} className={button} rel="next">
          {t.older}
          <ArrowRight className="size-4" />
        </Link>
      ) : (
        <span />
      )}
    </nav>
  );
}

/** ?page=N from the URL, clamped to something the API will accept. */
export function pageFrom(value: string | string[] | undefined): number {
  const page = Number(Array.isArray(value) ? value[0] : value);
  return Number.isInteger(page) && page > 0 ? page : 1;
}
