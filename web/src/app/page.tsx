import Link from "next/link";
import { fetchPosts, formatDate, type Post } from "@/lib/api";

// Rendered per request so the image can be built without the blog API being
// reachable. The 60s fetch cache in lib/api still absorbs the traffic.
export const dynamic = "force-dynamic";


export const metadata = {
  title: "Blog",
  description: "Posts from the channel and the blog, kept in sync.",
};

function TypeBadge({ post }: { post: Post }) {
  if (post.post_type === "article") return null;
  const label = post.post_type === "gallery" ? `${post.media.length} photos` : "Video";
  return (
    <span className="rounded-full bg-foreground/10 px-2 py-0.5 text-xs font-medium">
      {label}
    </span>
  );
}

export default async function Home() {
  const { data: posts } = await fetchPosts();

  return (
    <main className="mx-auto w-full max-w-3xl px-6 py-16">
      <h1 className="text-4xl font-bold tracking-tight">Blog</h1>

      {posts.length === 0 ? (
        <p className="mt-10 text-foreground/60">
          Nothing published yet. Post in the connected Telegram channel, or write
          something in the admin.
        </p>
      ) : (
        <ul className="mt-12 space-y-10">
          {posts.map((post) => (
            <li key={post.id}>
              <article className="group flex gap-5">
                {post.cover_image && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={post.cover_image}
                    alt=""
                    className="hidden h-24 w-32 shrink-0 rounded-lg object-cover sm:block"
                  />
                )}
                <div>
                  <div className="flex items-center gap-3 text-sm text-foreground/60">
                    <time dateTime={post.published_at ?? undefined}>
                      {formatDate(post.published_at)}
                    </time>
                    <TypeBadge post={post} />
                  </div>
                  <h2 className="mt-1 text-xl font-semibold">
                    <Link href={`/posts/${post.slug}`} className="hover:underline">
                      {post.title}
                    </Link>
                  </h2>
                  {post.excerpt && (
                    <p className="mt-2 text-foreground/70">{post.excerpt}</p>
                  )}
                </div>
              </article>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
