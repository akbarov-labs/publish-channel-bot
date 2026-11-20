import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import Pagination, { pageFrom } from "@/components/Pagination";
import PostList from "@/components/PostList";
import { topicHref } from "@/components/TopicList";
import { getAuthor, getAuthorTopics, getTopicPosts } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";
import { templateOf } from "@/lib/templates";

type Props = PageProps<"/site/[author]/topics/[topic]">;

/** Route params may arrive encoded or not, depending on the characters. */
function slugFrom(raw: string): string {
  try {
    return decodeURIComponent(raw);
  } catch {
    return raw;
  }
}

async function load(username: string, slug: string, page: number) {
  const [author, posts, topics] = await Promise.all([
    getAuthor(username),
    getTopicPosts(username, slug, page),
    getAuthorTopics(username),
  ]);
  const topic = topics.find((item) => item.slug === slug);
  return { author, posts, topic };
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { author: username, topic: raw } = await params;
  const { topic } = await load(username, slugFrom(raw), 1);
  return topic ? { title: `#${topic.name}`, alternates: { canonical: topicHref(topic) } } : {};
}

export default async function TopicPage({ params, searchParams }: Props) {
  const { author: username, topic: raw } = await params;
  const slug = slugFrom(raw);
  const page = pageFrom((await searchParams).page);
  const { author, posts, topic } = await load(username, slug, page);
  if (!author || !posts || !topic) notFound();

  const locale = localeOf(author.locale);
  const t = dict(locale);

  return (
    <main className="mx-auto max-w-[720px] px-4">
      <header className="pb-8 pt-12 sm:pt-16">
        <p className="text-sm text-muted">
          <Link href="/topics" className="hover:text-ink hover:underline hover:underline-offset-4">
            {t.topics}
          </Link>
        </p>
        <h1 className="title mt-2 text-[2rem] leading-tight sm:text-[2.5rem]">
          <span className="text-muted">#</span>
          {topic.name}
        </h1>
        <p className="mt-2 text-sm text-muted tabular-nums">{t.topicPosts(posts.total)}</p>
      </header>
      <PostList posts={posts.data} locale={locale} template={templateOf(author.template)} />
      <Pagination page={posts.current_page} lastPage={posts.last_page} locale={locale} basePath={topicHref(topic)} />
    </main>
  );
}
