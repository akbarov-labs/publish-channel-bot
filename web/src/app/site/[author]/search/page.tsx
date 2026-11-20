import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Search } from "@/components/Icons";
import Pagination, { pageFrom } from "@/components/Pagination";
import { PostRow } from "@/components/PostRow";
import { getAuthor, searchPosts } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";

type Props = PageProps<"/site/[author]/search">;

function queryFrom(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value ?? "").trim().slice(0, 100);
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const author = await getAuthor((await params).author);
  // Result pages are endless and thin; keep them out of search engines.
  return author ? { title: dict(localeOf(author.locale)).search, robots: { index: false } } : {};
}

export default async function SearchPage({ params, searchParams }: Props) {
  const { author: username } = await params;
  const query = await searchParams;
  const q = queryFrom(query.q);
  const page = pageFrom(query.page);
  const [author, results] = await Promise.all([
    getAuthor(username),
    q.length >= 2 ? searchPosts(username, q, page) : Promise.resolve(null),
  ]);
  if (!author) notFound();

  const locale = localeOf(author.locale);
  const t = dict(locale);

  return (
    <main className="mx-auto max-w-[720px] px-4">
      <h1 className="title pb-6 pt-12 text-[2rem] leading-tight sm:pt-16">{t.search}</h1>
      <form action="/search" role="search" className="flex gap-2">
        <label htmlFor="q" className="sr-only">
          {t.searchLabel}
        </label>
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-muted" />
          <input
            id="q"
            name="q"
            type="search"
            defaultValue={q}
            placeholder={t.searchPlaceholder}
            minLength={2}
            maxLength={100}
            autoFocus={!q}
            className="h-11 w-full rounded-full border border-line bg-bg pl-10 pr-4 text-base outline-none transition-colors placeholder:text-muted focus:border-ink"
          />
        </div>
        <button
          type="submit"
          className="inline-flex h-11 items-center rounded-full bg-ink px-5 text-[0.9375rem] font-medium text-bg transition-opacity hover:opacity-85"
        >
          {t.searchSubmit}
        </button>
      </form>

      {!results ? (
        <p className="mt-4 text-sm text-muted">{t.searchHint}</p>
      ) : results.total === 0 ? (
        <p className="mt-10 border-t border-line pt-10 text-muted" role="status">
          {t.searchNothing(q)}
        </p>
      ) : (
        <>
          <p className="mt-8 text-sm text-muted tabular-nums" role="status">
            {t.searchResults(results.total, q)}
          </p>
          <div className="mt-3 divide-y divide-line border-t border-line">
            {results.data.map((post) => (
              <PostRow key={post.id} post={post} href={`/posts/${post.slug}`} locale={locale} />
            ))}
          </div>
          <Pagination
            page={results.current_page}
            lastPage={results.last_page}
            locale={locale}
            basePath={`/search?q=${encodeURIComponent(q)}`}
          />
        </>
      )}
    </main>
  );
}
