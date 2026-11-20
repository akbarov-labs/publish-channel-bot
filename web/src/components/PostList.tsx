import { Pin } from "@/components/Icons";
import { PostMeta, PostRow, SmartLink } from "@/components/PostRow";
import type { PostSummary } from "@/lib/api";
import { dict, formatDate, type Locale } from "@/lib/i18n";
import type { Template } from "@/lib/templates";
import { cleanExcerpt, coverOf, guessLang } from "@/lib/text";

/** Magazine: a card with its picture on top. */
function PostCard({ post, locale }: { post: PostSummary; locale: Locale }) {
  const excerpt = cleanExcerpt(post);
  const cover = coverOf(post);

  return (
    <article lang={guessLang(`${post.title} ${excerpt ?? ""}`)} className="group relative flex flex-col">
      <div className="relative mb-4 aspect-[3/2] overflow-hidden rounded-md bg-surface">
        {cover ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={cover}
            alt=""
            loading="lazy"
            decoding="async"
            className="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.02]"
          />
        ) : (
          <div aria-hidden="true" className="flex h-full items-end bg-accent-soft p-4">
            <span className="title line-clamp-3 text-[1.375rem] leading-tight text-accent">{post.title}</span>
          </div>
        )}
      </div>
      <h3 className="title text-[1.1875rem] leading-snug sm:text-[1.25rem]">
        <SmartLink
          href={`/posts/${post.slug}`}
          className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4"
        >
          {post.title}
        </SmartLink>
      </h3>
      {excerpt && <p className="mt-1.5 line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{excerpt}</p>}
      <div className="mt-3">
        <PostMeta post={post} locale={locale} />
      </div>
    </article>
  );
}

/** Notebook: the date in the margin, the words after it, no pictures. */
function NoteRow({ post, locale, pinned }: { post: PostSummary; locale: Locale; pinned?: boolean }) {
  const excerpt = cleanExcerpt(post);

  return (
    <article lang={guessLang(`${post.title} ${excerpt ?? ""}`)} className="group relative grid gap-1 py-6 sm:grid-cols-[8.5rem_1fr] sm:gap-6">
      <p className="pt-1 text-[0.8125rem] text-muted tabular-nums" lang={locale}>
        {pinned ? (
          <span className="inline-flex items-center gap-1 font-medium text-accent">
            <Pin className="size-3.5" />
            {dict(locale).pinned}
          </span>
        ) : (
          <time dateTime={post.published_at ?? undefined}>{formatDate(post.published_at, locale)}</time>
        )}
      </p>
      <div className="min-w-0">
        <h3 className="title text-[1.25rem] leading-snug sm:text-[1.375rem]">
          <SmartLink
            href={`/posts/${post.slug}`}
            className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4"
          >
            {post.title}
          </SmartLink>
        </h3>
        {excerpt && <p className="mt-1.5 line-clamp-2 font-serif text-[1rem] leading-relaxed text-ink-soft">{excerpt}</p>}
      </div>
    </article>
  );
}

/** A list of posts in the author's chosen design. */
export default function PostList({
  posts,
  locale,
  template,
  pinnedId,
}: {
  posts: PostSummary[];
  locale: Locale;
  template: Template;
  pinnedId?: number;
}) {
  if (template === "magazine") {
    return (
      <div className="grid gap-x-8 gap-y-12 border-t border-line pt-10 sm:grid-cols-2">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} locale={locale} />
        ))}
      </div>
    );
  }

  if (template === "notebook") {
    return (
      <div className="divide-y divide-line border-t border-line">
        {posts.map((post) => (
          <NoteRow key={post.id} post={post} locale={locale} pinned={post.id === pinnedId} />
        ))}
      </div>
    );
  }

  return (
    <div className="divide-y divide-line border-t border-line">
      {posts.map((post) => (
        <PostRow key={post.id} post={post} href={`/posts/${post.slug}`} locale={locale} />
      ))}
    </div>
  );
}
