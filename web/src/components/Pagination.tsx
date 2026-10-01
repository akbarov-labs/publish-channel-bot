import Link from "next/link";
import { ArrowLeft, ArrowRight } from "@/components/Icons";

export default function Pagination({ page, lastPage, basePath = "/" }: { page: number; lastPage: number; basePath?: string }) {
  if (lastPage <= 1) return null;

  const href = (target: number) => (target === 1 ? basePath : `${basePath}?page=${target}`);
  const button =
    "inline-flex h-10 items-center gap-2 rounded-full border border-line px-4 text-[0.9375rem] font-medium transition-colors duration-150 hover:border-ink";

  return (
    <nav aria-label="Pages" className="mt-6 flex items-center justify-between gap-4 border-t border-line pt-8">
      {page > 1 ? (
        <Link href={href(page - 1)} className={button} rel="prev">
          <ArrowLeft className="size-4" />
          Newer
        </Link>
      ) : (
        <span />
      )}
      <span className="text-sm text-muted tabular-nums">
        Page {page} of {lastPage}
      </span>
      {page < lastPage ? (
        <Link href={href(page + 1)} className={button} rel="next">
          Older
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
