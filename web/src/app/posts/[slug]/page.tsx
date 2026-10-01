import Link from "next/link";
import { notFound } from "next/navigation";
import Carousel from "@/components/Carousel";
import { fetchPost, formatDate } from "@/lib/api";

// Rendered per request so the image can be built without the blog API being
// reachable. The 60s fetch cache in lib/api still absorbs the traffic.
export const dynamic = "force-dynamic";


type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props) {
  const { slug } = await params;
  const post = await fetchPost(slug);

  if (!post) return { title: "Not found" };

  return {
    title: post.title,
    description: post.excerpt ?? undefined,
    openGraph: {
      title: post.title,
      description: post.excerpt ?? undefined,
      images: post.cover_image ? [post.cover_image] : undefined,
      type: "article",
    },
  };
}

export default async function PostPage({ params }: Props) {
  const { slug } = await params;
  const post = await fetchPost(slug);

  if (!post) notFound();

  const gallery = post.media.filter((item) => item.type !== "document");

  return (
    <main className="mx-auto w-full max-w-3xl px-6 py-16">
      <Link href="/" className="text-sm text-foreground/60 hover:underline">
        ← All posts
      </Link>

      <h1 className="mt-6 text-4xl font-bold tracking-tight">{post.title}</h1>
      <p className="mt-2 text-sm text-foreground/60">
        <time dateTime={post.published_at ?? undefined}>
          {formatDate(post.published_at)}
        </time>
      </p>

      {post.post_type === "gallery" && gallery.length > 0 && (
        <Carousel items={gallery} />
      )}

      {post.html && (
        // The body is written by the blog's own author, in the admin or in
        // their channel, so it is trusted content.
        <div
          className="prose mt-8 max-w-none [&_a]:underline [&_h3]:mt-8 [&_h3]:text-2xl [&_h3]:font-semibold [&_img]:rounded-lg [&_p]:my-4"
          dangerouslySetInnerHTML={{ __html: post.html }}
        />
      )}

      {post.telegraph_url && (
        <p className="mt-10 text-sm text-foreground/60">
          Also published on{" "}
          <a href={post.telegraph_url} className="underline" rel="noreferrer">
            telegra.ph
          </a>
        </p>
      )}
    </main>
  );
}
