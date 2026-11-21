// One post as readers see it. Shared by the post's own page and by its
// secret preview link, which shows a draft the same way with a banner.

import Link from "next/link";
import Attachments from "@/components/Attachments";
import Avatar from "@/components/Avatar";
import Comments from "@/components/Comments";
import Contents from "@/components/Contents";
import Gallery from "@/components/Gallery";
import { ArrowLeft, ArrowRight, ExternalLink } from "@/components/Icons";
import { ZoomableImage } from "@/components/Lightbox";
import PollView from "@/components/PollView";
import { PostRow } from "@/components/PostRow";
import Prose from "@/components/Prose";
import Reactions from "@/components/Reactions";
import { SeriesLine, SeriesList } from "@/components/SeriesBox";
import ShareButton from "@/components/ShareButton";
import TelegramButton from "@/components/TelegramButton";
import TopicList from "@/components/TopicList";
import ViewCounter from "@/components/ViewCounter";
import { withFootnotes, withHeadingAnchors } from "@/lib/article";
import type { Post, PostSummary } from "@/lib/api";
import { authorUrl } from "@/lib/hosts";
import { dict, formatDate, localeOf, type Dictionary } from "@/lib/i18n";
import { guessLang, readingMinutes, stripTags, withoutLeadingTitle } from "@/lib/text";

/** Long reads get a table of contents and the reading-progress hairline. */
const LONG_READ_MINUTES = 4;

function Neighbour({ post, direction, t }: { post: PostSummary; direction: "older" | "newer"; t: Dictionary }) {
  const older = direction === "older";
  return (
    <Link
      href={`/posts/${post.slug}`}
      className={`press group flex flex-col gap-1.5 py-5 ${older ? "sm:items-end sm:text-right" : ""}`}
    >
      <span className="inline-flex items-center gap-1.5 text-[0.8125rem] text-muted">
        {!older && <ArrowLeft className="size-4 transition-transform duration-150 ease-out group-hover:-translate-x-0.5" />}
        {older ? t.older : t.newer}
        {older && <ArrowRight className="size-4 transition-transform duration-150 ease-out group-hover:translate-x-0.5" />}
      </span>
      <span className="title line-clamp-2 text-[1.0625rem] leading-snug group-hover:underline group-hover:underline-offset-4">
        {post.title}
      </span>
    </Link>
  );
}

/**
 * What to read next: the next part of the series when there is one, else the
 * author's previous post. One clear choice right after the writing.
 */
function upNextFor(post: Post): { summary?: PostSummary; part?: { title: string; slug: string; index: number } } | null {
  if (post.series) {
    const index = post.series.parts.findIndex((part) => part.slug === post.slug);
    const next = index >= 0 ? post.series.parts[index + 1] : undefined;
    if (next) return { part: { ...next, index: index + 1 } };
  }
  return post.older ? { summary: post.older } : null;
}

export default function PostView({ post, preview = false }: { post: Post; preview?: boolean }) {
  const { author } = post;
  const locale = localeOf(author.locale);
  const t = dict(locale);
  const { html, headings } = withHeadingAnchors(
    withFootnotes(post.html ? withoutLeadingTitle(post.html, post.title) : "", t.backToText),
  );
  const lang = guessLang(`${post.title} ${stripTags(html).slice(0, 400)}`);
  const visual = post.media.filter((item) => item.type === "photo" || item.type === "video");
  const video = post.post_type === "video" ? visual.find((item) => item.type === "video") : undefined;
  const isGallery = post.post_type === "gallery" && visual.length > 0;
  const minutes = readingMinutes(html);
  const longRead = post.post_type === "article" && minutes >= LONG_READ_MINUTES;
  const upNext = preview ? null : upNextFor(post);
  const upNextSlug = upNext?.summary?.slug ?? upNext?.part?.slug;
  const neighbourIds = new Set([post.older?.id, post.newer?.id]);
  const more = post.more.filter((item) => !neighbourIds.has(item.id) && item.slug !== upNextSlug);
  const showOlder = post.older && post.older.slug !== upNextSlug;
  const url = authorUrl(author.username, `/posts/${post.slug}`);
  const files = post.media.some((item) => item.type === "audio" || item.type === "document");
  const hasEnd = (files && post.post_type !== "audio") || (post.reactions?.length ?? 0) > 0 || (post.topics?.length ?? 0) > 0 || post.telegraph_url;

  const typeLine =
    post.post_type === "gallery"
      ? t.photos(visual.length)
      : post.post_type === "video"
        ? t.video
        : post.post_type === "audio"
          ? t.audio
          : post.post_type === "poll"
            ? t.poll
            : t.minutesRead(minutes);

  return (
    <main>
      {preview ? (
        <p
          role="status"
          lang={locale}
          className="sticky top-14 z-20 border-b border-line bg-accent-soft px-4 py-2.5 text-center text-[0.8125rem] font-medium text-ink"
        >
          {t.draftPreview}
        </p>
      ) : (
        <ViewCounter slug={post.slug} />
      )}
      {longRead && <div aria-hidden="true" className="reading-progress" />}

      <article lang={lang}>
        <header className="mx-auto max-w-[680px] px-4 pt-10 sm:pt-16">
          <h1 className="title text-[2rem] leading-[1.14] sm:text-[2.75rem]">{post.title}</h1>

          <div className="mt-7 flex items-center gap-3">
            <Link href="/" aria-hidden="true" tabIndex={-1} className="shrink-0">
              <Avatar name={author.name} username={author.username} src={author.avatar_url} size={44} />
            </Link>
            <div className="min-w-0 flex-1 text-[0.9375rem] leading-snug">
              <Link href="/" className="font-semibold hover:underline hover:underline-offset-4">
                {author.name}
              </Link>
              <p className="text-[0.8125rem] text-muted tabular-nums" lang={locale}>
                {typeLine}
                {post.published_at && (
                  <>
                    <span aria-hidden="true"> · </span>
                    <time dateTime={post.published_at}>{formatDate(post.published_at, locale)}</time>
                  </>
                )}
                {post.views ? (
                  <>
                    <span aria-hidden="true"> · </span>
                    {t.views(post.views)}
                  </>
                ) : null}
              </p>
            </div>
            {/* A preview link is secret; nothing on it invites passing it on. */}
            {!preview && <ShareButton url={url} title={post.title} locale={locale} />}
          </div>

          {post.series && (
            <div lang={locale}>
              <SeriesLine series={post.series} current={post.slug} t={t} />
            </div>
          )}
        </header>

        {isGallery && (
          <div className="mx-auto mt-8 max-w-[960px] sm:mt-10 sm:px-6">
            <Gallery items={visual} label={`${post.title}: ${t.photos(visual.length)}`} locale={locale} />
          </div>
        )}

        {video && (
          <div className="mx-auto mt-8 max-w-[960px] sm:mt-10 sm:px-6">
            <video src={video.url} controls playsInline preload="metadata" poster={post.cover_image ?? undefined} className="w-full bg-black sm:rounded-md" />
          </div>
        )}

        {!isGallery && !video && post.cover_image && (
          <figure className="mx-auto mt-8 max-w-[960px] overflow-hidden bg-surface sm:mt-10 sm:rounded-md">
            <ZoomableImage src={post.cover_image} alt="" locale={locale} className="w-full object-cover" />
          </figure>
        )}

        {post.poll && (
          <div lang={locale} className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
            <PollView poll={post.poll} telegramPost={post.telegram_post} t={t} />
          </div>
        )}

        {post.post_type === "audio" && (
          <div lang={locale} className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
            <Attachments media={post.media} t={t} locale={locale} />
          </div>
        )}

        {longRead && headings.length >= 3 && (
          <div lang={locale}>
            <Contents headings={headings} label={t.contents} />
          </div>
        )}

        {html && (
          <div className="mx-auto mt-8 max-w-[680px] px-4 sm:mt-10">
            <Prose html={html} locale={locale} />
          </div>
        )}

        {hasEnd && (
          <footer lang={locale} className="mx-auto mt-12 flex max-w-[680px] flex-col gap-6 px-4">
            {files && post.post_type !== "audio" && <Attachments media={post.media} t={t} locale={locale} />}
            {post.reactions && post.reactions.length > 0 && <Reactions reactions={post.reactions} label={t.reactions} />}
            {post.topics && post.topics.length > 0 && <TopicList topics={post.topics} label={t.topics} />}
            {post.telegraph_url && (
              <a
                href={post.telegraph_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 self-start text-[0.9375rem] text-muted underline decoration-line underline-offset-4 transition-colors hover:text-ink hover:decoration-ink"
              >
                {t.instantView}
                <ExternalLink className="size-4" />
              </a>
            )}
          </footer>
        )}
      </article>

      <div lang={locale} className="mx-auto mt-14 max-w-[680px] px-4">
        {upNext && (
          <section aria-labelledby="up-next" className="border-t border-line pt-8">
            <h2 id="up-next" className="text-[0.9375rem] font-semibold">
              {t.upNext}
            </h2>
            {upNext.summary ? (
              <div className="-mt-2">
                <PostRow post={upNext.summary} href={`/posts/${upNext.summary.slug}`} locale={locale} />
              </div>
            ) : (
              upNext.part &&
              post.series && (
                <Link href={`/posts/${upNext.part.slug}`} className="press group mt-4 flex items-center justify-between gap-6 py-2">
                  <span className="min-w-0">
                    <span className="title block text-[1.125rem] leading-snug group-hover:underline group-hover:underline-offset-4 sm:text-[1.3125rem]">
                      {upNext.part.title}
                    </span>
                    <span className="mt-1.5 block text-[0.8125rem] text-muted tabular-nums">
                      {post.series.title} · {t.partOf(upNext.part.index + 1, post.series.parts.length)}
                    </span>
                  </span>
                  <ArrowRight className="size-5 shrink-0 text-muted transition-transform duration-150 ease-out group-hover:translate-x-1" />
                </Link>
              )
            )}
          </section>
        )}

        {post.series && (
          <div className={upNext ? "mt-8" : ""}>
            <SeriesList series={post.series} current={post.slug} t={t} />
          </div>
        )}

        <section aria-label={t.aboutAuthor} className="mt-12 flex flex-col gap-5 border-t border-line pt-10 sm:flex-row sm:items-start">
          <Link href="/" aria-hidden="true" tabIndex={-1} className="shrink-0">
            <Avatar name={author.name} username={author.username} src={author.avatar_url} size={64} />
          </Link>
          <div className="min-w-0 flex-1">
            <h2 className="title text-xl">
              <Link href="/" className="hover:underline hover:underline-offset-4">
                {t.writtenBy(author.name)}
              </Link>
            </h2>
            {author.bio && (
              <p lang={guessLang(author.bio)} className="mt-2 font-serif leading-relaxed text-ink-soft">
                {author.bio}
              </p>
            )}
            <div className="mt-5 flex flex-wrap items-center gap-3">
              {author.telegram_url && <TelegramButton href={author.telegram_url} locale={locale} size="sm" />}
              <Link
                href="/"
                className="inline-flex h-8 items-center rounded-full border border-line px-3 text-[0.8125rem] font-medium transition-[border-color,transform] duration-150 ease-out hover:border-ink active:scale-[0.97]"
              >
                {t.allPosts}
                {author.posts_count ? ` (${author.posts_count})` : ""}
              </Link>
            </div>
          </div>
        </section>

        {!preview && post.telegram_post && <Comments post={post.telegram_post} locale={locale} />}

        {(post.newer || showOlder) && (
          <nav aria-label={t.morePosts} className="mt-12 grid border-y border-line sm:grid-cols-2 sm:gap-8">
            <div>{post.newer && <Neighbour post={post.newer} direction="newer" t={t} />}</div>
            <div className={post.newer ? "border-t border-line sm:border-t-0" : ""}>
              {showOlder && post.older && <Neighbour post={post.older} direction="older" t={t} />}
            </div>
          </nav>
        )}

        {more.length > 0 && (
          <section aria-labelledby="more" className="mt-12">
            <h2 id="more" className="text-[0.9375rem] font-semibold">
              {t.moreFrom(author.name)}
            </h2>
            <div className="divide-y divide-line">
              {more.map((item) => (
                <PostRow key={item.id} post={item} href={`/posts/${item.slug}`} locale={locale} />
              ))}
            </div>
          </section>
        )}
      </div>
    </main>
  );
}
