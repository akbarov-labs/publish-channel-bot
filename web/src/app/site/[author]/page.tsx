import { notFound } from "next/navigation";
import Avatar from "@/components/Avatar";
import Pagination, { pageFrom } from "@/components/Pagination";
import PostList from "@/components/PostList";
import { FeaturedPost } from "@/components/PostRow";
import TopicList from "@/components/TopicList";
import { getAuthor, getAuthorPosts, getAuthorTopics } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";
import { templateOf } from "@/lib/templates";
import { guessLang } from "@/lib/text";

function channelHandle(url: string): string {
  const match = url.match(/t\.me\/([^/?#]+)/);
  return match ? `@${match[1]}` : url.replace(/^https?:\/\//, "");
}

export default async function AuthorHome({ params, searchParams }: PageProps<"/site/[author]">) {
  const { author: username } = await params;
  const page = pageFrom((await searchParams).page);
  const [author, posts, topics] = await Promise.all([
    getAuthor(username),
    getAuthorPosts(username, page),
    page === 1 ? getAuthorTopics(username) : Promise.resolve([]),
  ]);
  if (!author || !posts) notFound();
  const locale = localeOf(author.locale);
  const t = dict(locale);

  const template = templateOf(author.template);

  // Page one leads with the pinned post when there is one, else the newest.
  // Notebook features nothing: the pinned post simply heads the list.
  const pinned = page === 1 ? (posts.pinned ?? undefined) : undefined;
  const listed = pinned ? posts.data.filter((post) => post.id !== pinned.id) : posts.data;
  const featured = page === 1 && template !== "notebook" ? (pinned ?? listed[0]) : undefined;
  const rows =
    template === "notebook" ? (pinned ? [pinned, ...listed] : listed) : featured && !pinned ? listed.slice(1) : listed;

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
          {topics.length > 0 && (
            <TopicList topics={topics.slice(0, 12)} label={t.topics} className="mt-7 flex justify-center [&_ul]:justify-center" />
          )}
        </section>
      )}

      <div className={`mx-auto px-4 ${template === "magazine" ? "max-w-[1040px] sm:px-6" : "max-w-[720px]"}`}>
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
                <FeaturedPost post={featured} href={`/posts/${featured.slug}`} locale={locale} pinned={Boolean(pinned)} />
              </div>
            )}
            {page > 1 && <h1 className="title pb-4 pt-10 text-xl">{t.olderPostsPage(page)}</h1>}
            <PostList posts={rows} locale={locale} template={template} pinnedId={pinned?.id} label={t.allPosts} />
            <Pagination page={posts.current_page} lastPage={posts.last_page} locale={locale} />
          </>
        )}
      </div>
    </main>
  );
}
