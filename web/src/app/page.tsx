import Link from "next/link";
import Avatar from "@/components/Avatar";
import Pagination, { pageFrom } from "@/components/Pagination";
import { PostRow } from "@/components/PostRow";
import { getAuthors, getLatestPosts, type Author } from "@/lib/api";
import { authorUrl } from "@/lib/hosts";
import { SITE_NAME } from "@/lib/site";
import { formatDate } from "@/lib/text";

// Rendered per request so the image can be built without the blog API being
// reachable. The 60s fetch cache in lib/api still absorbs the traffic.
export const dynamic = "force-dynamic";

export const metadata = {
  title: { absolute: SITE_NAME },
};

function AuthorListItem({ author }: { author: Author }) {
  return (
    <li>
      <a href={authorUrl(author.username)} className="group flex gap-3 py-3">
        <Avatar name={author.name} username={author.username} src={author.avatar_url} size={40} />
        <span className="min-w-0 flex-1">
          <span className="block truncate text-[0.9375rem] font-semibold group-hover:underline group-hover:underline-offset-4">
            {author.name}
          </span>
          {author.bio && <span className="mt-0.5 line-clamp-2 block text-[0.8125rem] leading-snug text-muted">{author.bio}</span>}
          <span className="mt-1 block text-xs text-muted tabular-nums">
            {author.posts_count === 1 ? "1 post" : `${author.posts_count ?? 0} posts`}
            {author.latest_post_at && <> · last {formatDate(author.latest_post_at)}</>}
          </span>
        </span>
      </a>
    </li>
  );
}

export default async function Home({ searchParams }: PageProps<"/">) {
  const page = pageFrom((await searchParams).page);
  const [authors, posts] = await Promise.all([getAuthors(), getLatestPosts(page)]);

  return (
    <>
      <header className="border-b border-line">
        <div className="mx-auto flex h-14 max-w-[1120px] items-center px-4 sm:px-6">
          <Link href="/" className="text-[1.0625rem] font-bold tracking-[-0.02em]">
            {SITE_NAME}
          </Link>
        </div>
      </header>

      <main className="mx-auto w-full max-w-[1120px] flex-1 px-4 sm:px-6">
        {page === 1 && (
          <section className="border-b border-line py-12 sm:py-16">
            <h1 className="title max-w-[18ch] text-[2.25rem] leading-[1.08] sm:text-[3.5rem]">
              Writing from Telegram channels
            </h1>
            <p className="mt-5 max-w-[56ch] font-serif text-[1.0625rem] leading-relaxed text-ink-soft sm:text-xl">
              Every author here writes in their own channel. Posts, albums and long reads arrive on their page on their
              own, with the dates they were first published.
            </p>
          </section>
        )}

        {authors.length === 0 && posts.data.length === 0 ? (
          <section className="py-20 text-center">
            <h2 className="title text-2xl">Nothing published yet</h2>
            <p className="mx-auto mt-3 max-w-[46ch] text-muted">
              Connect a Telegram channel to the bot and its posts appear here, each author on their own page.
            </p>
          </section>
        ) : (
          <div className="grid gap-x-16 lg:grid-cols-[minmax(0,1fr)_300px]">
            {/* On phones the authors come first, as a strip you can swipe. */}
            {authors.length > 0 && (
              <section aria-labelledby="authors-strip" className="border-b border-line py-6 lg:hidden">
                <h2 id="authors-strip" className="text-[0.9375rem] font-semibold">
                  Authors
                </h2>
                <ul className="no-scrollbar -mx-4 mt-4 flex gap-5 overflow-x-auto px-4">
                  {authors.map((author) => (
                    <li key={author.username} className="w-[72px] shrink-0">
                      <a href={authorUrl(author.username)} className="flex flex-col items-center gap-2 text-center">
                        <Avatar name={author.name} username={author.username} src={author.avatar_url} size={60} />
                        <span className="line-clamp-2 text-xs font-medium leading-tight">{author.name}</span>
                      </a>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <section aria-labelledby="latest" className="pt-8">
              <h2 id="latest" className="text-[0.9375rem] font-semibold">
                {page === 1 ? "Latest posts" : `Latest posts, page ${page}`}
              </h2>
              <div className="divide-y divide-line">
                {posts.data.map((post) => (
                  <PostRow
                    key={post.id}
                    post={post}
                    href={post.author ? authorUrl(post.author.username, `/posts/${post.slug}`) : post.url}
                    authorHref={post.author ? authorUrl(post.author.username) : undefined}
                    showAuthor
                  />
                ))}
              </div>
              <Pagination page={posts.current_page} lastPage={posts.last_page} />
            </section>

            {authors.length > 0 && (
              <aside aria-labelledby="authors" className="hidden pt-8 lg:block">
                <div className="sticky top-8">
                  <h2 id="authors" className="text-[0.9375rem] font-semibold">
                    Authors
                  </h2>
                  <ul className="mt-2 divide-y divide-line">
                    {authors.map((author) => (
                      <AuthorListItem key={author.username} author={author} />
                    ))}
                  </ul>
                </div>
              </aside>
            )}
          </div>
        )}
      </main>

      <footer className="mt-16 border-t border-line">
        <div className="mx-auto max-w-[1120px] px-4 py-8 text-sm text-muted sm:px-6">
          {SITE_NAME} · Every page here is written in a Telegram channel.
        </div>
      </footer>
    </>
  );
}
