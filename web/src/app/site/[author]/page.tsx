import { notFound } from "next/navigation";
import Avatar from "@/components/Avatar";
import Pagination, { pageFrom } from "@/components/Pagination";
import { FeaturedPost, PostRow } from "@/components/PostRow";
import { getAuthor, getAuthorPosts } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";
import { guessLang } from "@/lib/text";

function channelHandle(url: string): string {
  const match = url.match(/t\.me\/([^/?#]+)/);
  return match ? `@${match[1]}` : url.replace(/^https?:\/\//, "");
}

export default async function AuthorHome({ params, searchParams }: PageProps<"/site/[author]">) {
  const { author: username } = await params;
  const page = pageFrom((await searchParams).page);
  const [author, posts] = await Promise.all([getAuthor(username), getAuthorPosts(username, page)]);
  if (!author || !posts) notFound();
  const locale = localeOf(author.locale);
  const t = dict(locale);

  const [first, ...rest] = posts.data;
  const featured = page === 1 ? first : undefined;
  const rows = featured ? rest : posts.data;

  return (
    <main>
      {page === 1 && (
        <section className="mx-auto max-w-[720px] px-4 pb-10 pt-12 text-center sm:pt-16">
          <div className="flex justify-center">
            <Avatar name={author.name} username={author.username} src={author.avatar_url} size={96} />
          </div>
          <h1 className="title mt-5 text-[2rem] leading-tight sm:text-[2.625rem]">{author.name}</h1>
          {author.bio && (
            <p lang={guessLang(author.bio)} className="mx-auto mt-3 max-w-[50ch] text-balance font-serif text-[1.0625rem] leading-relaxed text-ink-soft sm:text-lg">
              {author.bio}
            </p>
          )}
          <p className="mt-4 flex flex-wrap items-center justify-center gap-x-2 text-sm text-muted tabular-nums">
            <span>{t.posts(author.posts_count ?? posts.total)}</span>
            {author.telegram_url && (
              <>
                <span aria-hidden="true">·</span>
                <a href={author.telegram_url} className="font-medium text-accent underline-offset-4 hover:underline">
                  {channelHandle(author.telegram_url)}
                </a>
              </>
            )}
          </p>
        </section>
      )}

      <div className="mx-auto max-w-[720px] px-4">
        {posts.data.length === 0 ? (
          <div className="border-t border-line py-16 text-center">
            <h2 className="title text-xl">{t.noPostsTitle}</h2>
            <p className="mx-auto mt-2 max-w-[40ch] text-muted">
              {t.noPostsText(author.name)}
            </p>
          </div>
        ) : (
          <>
            {featured && (
              <div className="border-t border-line pt-8">
                <FeaturedPost post={featured} href={`/posts/${featured.slug}`} locale={locale} />
              </div>
            )}
            {page > 1 && <h1 className="title pt-10 text-xl">{t.olderPostsPage(page)}</h1>}
            <div className="divide-y divide-line border-t border-line">
              {rows.map((post) => (
                <PostRow key={post.id} post={post} href={`/posts/${post.slug}`} locale={locale} />
              ))}
            </div>
            <Pagination page={posts.current_page} lastPage={posts.last_page} locale={locale} />
          </>
        )}
      </div>
    </main>
  );
}
