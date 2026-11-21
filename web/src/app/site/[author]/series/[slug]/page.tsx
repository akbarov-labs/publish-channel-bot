import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowRight } from "@/components/Icons";
import { PostRow } from "@/components/PostRow";
import { getAuthor, getSeries } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";
import { guessLang } from "@/lib/text";

type Props = PageProps<"/site/[author]/series/[slug]">;

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { author, slug } = await params;
  const series = await getSeries(author, slug);
  return series ? { title: series.title, description: series.description ?? undefined } : {};
}

export default async function SeriesPage({ params }: Props) {
  const { author: username, slug } = await params;
  const [author, series] = await Promise.all([getAuthor(username), getSeries(username, slug)]);
  if (!author || !series) notFound();

  const locale = localeOf(author.locale);
  const t = dict(locale);

  return (
    <main className="mx-auto max-w-[720px] px-4">
      <header className="pb-8 pt-12 sm:pt-16" lang={guessLang(`${series.title} ${series.description ?? ""}`)}>
        <h1 className="title text-[2rem] leading-tight sm:text-[2.5rem]">{series.title}</h1>
        <p className="mt-2 text-[0.8125rem] text-muted tabular-nums" lang={locale}>
          {t.series} · {t.seriesParts(series.posts.length)}
        </p>
        {series.description && (
          <p className="mt-4 max-w-[56ch] font-serif text-[1.0625rem] leading-relaxed text-ink-soft">{series.description}</p>
        )}
        <Link
          href={`/posts/${series.posts[0].slug}`}
          lang={locale}
          className="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-ink px-4 text-[0.9375rem] font-medium text-bg transition-[opacity,transform] duration-150 ease-out hover:opacity-85 active:scale-[0.98]"
        >
          {t.startReading}
          <ArrowRight className="size-4" />
        </Link>
      </header>
      <h2 className="sr-only">{t.seriesParts(series.posts.length)}</h2>
      <ol className="divide-y divide-line border-t border-line">
        {series.posts.map((post, index) => (
          <li key={post.id} className="flex gap-4 sm:gap-6">
            <span className="title pt-7 text-[1.125rem] text-muted tabular-nums sm:text-[1.3125rem]">{index + 1}</span>
            <div className="min-w-0 flex-1">
              <PostRow post={post} href={`/posts/${post.slug}`} locale={locale} />
            </div>
          </li>
        ))}
      </ol>
    </main>
  );
}
