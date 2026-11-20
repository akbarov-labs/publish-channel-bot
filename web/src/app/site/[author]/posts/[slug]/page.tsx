import type { Metadata } from "next";
import { notFound } from "next/navigation";
import PostView from "@/components/PostView";
import { getAuthor, getPost } from "@/lib/api";
import { authorUrl } from "@/lib/hosts";
import { dict, localeOf } from "@/lib/i18n";
import { cleanExcerpt } from "@/lib/text";

type Props = PageProps<"/site/[author]/posts/[slug]">;

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { author, slug } = await params;
  const post = await getPost(author, slug);
  if (!post) return { title: dict(localeOf((await getAuthor(author))?.locale)).notFoundTitle };

  const description = cleanExcerpt(post) ?? undefined;
  const url = authorUrl(post.author.username, `/posts/${post.slug}`);

  return {
    title: post.title,
    description,
    // Metadata merges shallowly, so the author's feed link is repeated here.
    alternates: {
      canonical: url,
      types: { "application/rss+xml": [{ url: "/feed.xml", title: post.author.name }] },
    },
    openGraph: {
      type: "article",
      url,
      title: post.title,
      description,
      images: post.cover_image ? [post.cover_image] : undefined,
      publishedTime: post.published_at ?? undefined,
      authors: [post.author.name],
    },
    twitter: { card: post.cover_image ? "summary_large_image" : "summary" },
  };
}

export default async function PostPage({ params }: Props) {
  const { author: username, slug } = await params;
  const post = await getPost(username, slug);
  if (!post) notFound();

  return <PostView post={post} />;
}
