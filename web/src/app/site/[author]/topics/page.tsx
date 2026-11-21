import type { Metadata } from "next";
import { notFound } from "next/navigation";
import TopicList from "@/components/TopicList";
import { getAuthor, getAuthorTopics } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";

export async function generateMetadata({ params }: PageProps<"/site/[author]/topics">): Promise<Metadata> {
  const author = await getAuthor((await params).author);
  return author ? { title: dict(localeOf(author.locale)).topics } : {};
}

export default async function TopicsPage({ params }: PageProps<"/site/[author]/topics">) {
  const { author: username } = await params;
  const [author, topics] = await Promise.all([getAuthor(username), getAuthorTopics(username)]);
  if (!author || topics.length === 0) notFound();
  const t = dict(localeOf(author.locale));

  return (
    <main className="mx-auto max-w-[720px] px-4">
      <h1 className="title pb-8 pt-12 text-[2rem] leading-tight sm:pt-16 sm:text-[2.5rem]">{t.topics}</h1>
      <TopicList topics={topics} label={t.topics} counts className="border-t border-line pt-8" />
    </main>
  );
}
