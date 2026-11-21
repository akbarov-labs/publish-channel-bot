import Highlight from "@/components/Highlight";
import { Pin } from "@/components/Icons";
import { PostMeta, PostRow, SmartLink } from "@/components/PostRow";
import type { PostSummary } from "@/lib/api";
import { dict, formatDate, type Locale } from "@/lib/i18n";
import type { Template } from "@/lib/templates";
import { cleanExcerpt, coverOf, guessLang } from "@/lib/text";

type ItemProps = { post: PostSummary; locale: Locale; highlight?: string[] };

/**
 * Magazine: a card with its picture on top. A post without one gets a quiet
 * typographic block (its opening words in the serif) rather than a fake image.
 */
function PostCard({ post, locale, highlight }: ItemProps) {
  const excerpt = cleanExcerpt(post);
  const cover = coverOf(post);

  return (
    <article lang={guessLang(`${post.title} ${excerpt ?? ""}`)} className="press group relative flex flex-col">
      {cover ? (
        <div className="relative mb-4 aspect-[3/2] overflow-hidden rounded-md bg-surface">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={cover}
            alt=""
            loading="lazy"
            decoding="async"
            data-fade="" suppressHydrationWarning
            className="h-full w-full object-cover transition-[transform,opacity] duration-500 ease-out group-hover:scale-[1.02]"
          />
        </div>
      ) : (
        excerpt && (
          <div aria-hidden="true" className="mb-4 flex aspect-[3/2] items-end rounded-md bg-surface p-5 sm:p-6">
            <p className="line-clamp-4 font-serif text-[1.125rem] leading-snug text-ink-soft">{excerpt}</p>
          </div>
        )
      )}
      <h3 className="title text-[1.125rem] leading-snug sm:text-[1.3125rem]">
        <SmartLink
          href={`/posts/${post.slug}`}
          className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4"
        >
          <Highlight text={post.title} words={highlight} />
        </SmartLink>
      </h3>
      {excerpt && (
        <p className="mt-1.5 line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">
          <Highlight text={excerpt} words={highlight} />
        </p>
      )}
      <div className="mt-3">
        <PostMeta post={post} locale={locale} pinned={post.pinned} />
      </div>
    </article>
  );
}

/** Notebook: the date in the margin, the words after it, no pictures. */
function NoteRow({ post, locale, highlight, pinned }: ItemProps & { pinned?: boolean }) {
  const excerpt = cleanExcerpt(post);

  return (
    <article
      lang={guessLang(`${post.title} ${excerpt ?? ""}`)}
      className="press group relative grid gap-1 py-6 sm:grid-cols-[8.5rem_1fr] sm:gap-6"
    >
      <p className="pt-1 text-[0.8125rem] text-muted tabular-nums" lang={locale}>
        {pinned ? (
          <span className="inline-flex items-center gap-1 font-medium text-ink-soft">
            <Pin className="size-3.5" />
            {dict(locale).pinned}
          </span>
        ) : (
          <time dateTime={post.published_at ?? undefined}>{formatDate(post.published_at, locale)}</time>
        )}
      </p>
      <div className="min-w-0">
        <h3 className="title text-[1.3125rem] leading-snug">
          <SmartLink
            href={`/posts/${post.slug}`}
            className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4"
          >
            <Highlight text={post.title} words={highlight} />
          </SmartLink>
        </h3>
        {excerpt && (
          <p className="mt-1.5 line-clamp-2 font-serif text-[1.0625rem] leading-relaxed text-ink-soft">
            <Highlight text={excerpt} words={highlight} />
          </p>
        )}
      </div>
    </article>
  );
}

/**
 * A list of posts in the author's chosen design. `label` names the list for
 * the page outline (a visually hidden h2), so post titles (h3) never skip a
 * level after the page's h1.
 */
export default function PostList({
  posts,
  locale,
  template,
  label,
  pinnedId,
  highlight,
}: {
  posts: PostSummary[];
  locale: Locale;
  template: Template;
  label: string;
  pinnedId?: number;
  highlight?: string[];
}) {
  const heading = <h2 className="sr-only">{label}</h2>;

  if (template === "magazine") {
    return (
      <section className="grid gap-x-8 gap-y-12 border-t border-line pt-10 sm:grid-cols-2">
        {heading}
        {posts.map((post) => (
          <PostCard key={post.id} post={post} locale={locale} highlight={highlight} />
        ))}
      </section>
    );
  }

  if (template === "notebook") {
    return (
      <section className="divide-y divide-line border-t border-line">
        {heading}
        {posts.map((post) => (
          <NoteRow key={post.id} post={post} locale={locale} pinned={post.id === pinnedId} highlight={highlight} />
        ))}
      </section>
    );
  }

  return (
    <section className="divide-y divide-line border-t border-line">
      {heading}
      {posts.map((post) => (
        <PostRow key={post.id} post={post} href={`/posts/${post.slug}`} locale={locale} highlight={highlight} />
      ))}
    </section>
  );
}
