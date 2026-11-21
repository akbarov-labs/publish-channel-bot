import type { Metadata } from "next";
import { notFound } from "next/navigation";
import PostView from "@/components/PostView";
import { getPreview } from "@/lib/api";

type Props = PageProps<"/site/[author]/preview/[token]">;

// Anyone with the link may look, but nothing should index or cache it.
export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { author, token } = await params;
  const post = await getPreview(author, token);
  return { title: post?.title, robots: { index: false, follow: false } };
}

export default async function PreviewPage({ params }: Props) {
  const { author, token } = await params;
  const post = await getPreview(author, token);
  if (!post) notFound();

  return <PostView post={post} preview />;
}
