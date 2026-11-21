import Link from "next/link";
import Avatar from "@/components/Avatar";
import Highlight from "@/components/Highlight";
import { Photos, Pin, Play } from "@/components/Icons";
import type { PostSummary } from "@/lib/api";
import { dict, formatDate, type Locale } from "@/lib/i18n";
import { cleanExcerpt, coverOf, guessLang, photoCount, typeLabel } from "@/lib/text";

/** In-site paths go through the router; other authors live on other hosts. */
export function SmartLink({
  href,
  className,
  children,
}: {
  href: string;
  className?: string;
  children: React.ReactNode;
}) {
  if (/^https?:\/\//.test(href)) {
    return (
      <a href={href} className={className}>
        {children}
      </a>
    );
  }
  return (
    <Link href={href} className={className}>
      {children}
    </Link>
  );
}

/**
 * Date · type or read time · extra. A pinned post leads with "Pinned" (muted,
 * with the pin mark) instead of a label above its title.
 */
export function PostMeta({
  post,
  locale,
  extra,
  pinned = false,
}: {
  post: PostSummary;
  locale: Locale;
  extra?: string;
  pinned?: boolean;
}) {
  const t = dict(locale);
  // Articles say how long they take, so a two-line note and a long read differ.
  const readTime =
    post.post_type === "article" && post.reading_minutes && post.reading_minutes > 1 ? t.minutesRead(post.reading_minutes) : null;
  const parts = [formatDate(post.published_at, locale), typeLabel(post, t) ?? readTime, extra].filter(Boolean);

  return (
    <p className="flex flex-wrap items-center gap-x-1.5 text-[0.8125rem] text-muted tabular-nums">
      {pinned && (
        <span className="flex items-center gap-x-1 font-medium text-ink-soft">
          <Pin className="size-3.5" />
          {t.pinned}
        </span>
      )}
      {parts.map((part, index) => (
        <span key={index} className="flex items-center gap-x-1.5">
          {(index > 0 || pinned) && <span aria-hidden="true">·</span>}
          {index === 0 ? <time dateTime={post.published_at ?? undefined}>{part}</time> : part}
        </span>
      ))}
    </p>
  );
}

function MediaBadge({ post }: { post: PostSummary }) {
  if (post.post_type === "gallery") {
    return (
      <span className="absolute bottom-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-black/65 px-1.5 py-0.5 text-xs font-medium text-white tabular-nums">
        <Photos className="size-3.5" />
        {photoCount(post)}
      </span>
    );
  }
  if (post.post_type === "video") {
    return (
      <span className="absolute bottom-1.5 left-1.5 inline-flex size-6 items-center justify-center rounded-full bg-black/65 text-white">
        <Play className="size-3.5" />
      </span>
    );
  }
  return null;
}

/** One post in a feed: text on the left, a thumbnail on the right. */
export function PostRow({
  post,
  href,
  showAuthor = false,
  authorHref,
  locale,
  highlight,
}: {
  post: PostSummary;
  href: string;
  showAuthor?: boolean;
  authorHref?: string;
  locale: Locale;
  /** Search words to mark in the title and excerpt. */
  highlight?: string[];
}) {
  const excerpt = cleanExcerpt(post);
  const cover = coverOf(post);
  const lang = guessLang(`${post.title} ${excerpt ?? ""}`);

  return (
    <article lang={lang} className="press group relative flex gap-5 py-7 sm:gap-10">
      <div className="min-w-0 flex-1">
        {showAuthor && post.author && (
          <a
            href={authorHref}
            className="relative z-10 mb-2.5 inline-flex items-center gap-2 text-[0.8125rem] font-medium text-ink hover:underline"
          >
            <Avatar name={post.author.name} username={post.author.username} src={post.author.avatar_url} size={20} />
            {post.author.name}
          </a>
        )}
        <h3 className="title text-[1.125rem] leading-snug sm:text-[1.3125rem]">
          <SmartLink
            href={href}
            className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4"
          >
            <Highlight text={post.title} words={highlight} />
          </SmartLink>
        </h3>
        {excerpt && (
          <p className="mt-1.5 line-clamp-2 text-[0.9375rem] leading-relaxed text-muted sm:line-clamp-3 sm:text-base">
            <Highlight text={excerpt} words={highlight} />
          </p>
        )}
        <div className="mt-3">
          <PostMeta post={post} locale={locale} />
        </div>
      </div>
      {cover && (
        <div className="relative mt-1 size-20 shrink-0 self-start overflow-hidden rounded-[4px] bg-surface sm:h-[108px] sm:w-40">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={cover} alt="" loading="lazy" decoding="async" data-fade="" suppressHydrationWarning className="absolute inset-0 h-full w-full object-cover" />
          <MediaBadge post={post} />
        </div>
      )}
    </article>
  );
}

/** The newest (or the pinned) post on an author's front page, given room to breathe. */
export function FeaturedPost({
  post,
  href,
  locale,
  pinned = false,
}: {
  post: PostSummary;
  href: string;
  locale: Locale;
  /** Leads the meta line with "Pinned". */
  pinned?: boolean;
}) {
  const excerpt = cleanExcerpt(post);
  const cover = coverOf(post);
  const lang = guessLang(`${post.title} ${excerpt ?? ""}`);

  return (
    <article lang={lang} className="press group relative pb-10 pt-2">
      {cover && (
        <div className="relative mb-6 overflow-hidden rounded-md bg-surface">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={cover}
            alt=""
            fetchPriority="high"
            data-fade="" suppressHydrationWarning
            className="aspect-[16/9] w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.015]"
          />
          {post.post_type === "gallery" && (
            <span className="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-black/65 px-2.5 py-1 text-xs font-medium text-white tabular-nums">
              <Photos className="size-4" />
              {typeLabel(post, dict(locale))}
            </span>
          )}
        </div>
      )}
      <h2 className="title text-[1.625rem] leading-[1.2] sm:text-[2.125rem]">
        <SmartLink
          href={href}
          className="after:absolute after:inset-0 after:content-[''] group-hover:underline group-hover:decoration-2 group-hover:underline-offset-[6px]"
        >
          {post.title}
        </SmartLink>
      </h2>
      {excerpt && (
        <p className="mt-3 line-clamp-3 font-serif text-[1.0625rem] leading-relaxed text-ink-soft sm:text-lg">
          {excerpt}
        </p>
      )}
      <div className="mt-4">
        <PostMeta post={post} locale={locale} pinned={pinned} />
      </div>
    </article>
  );
}
