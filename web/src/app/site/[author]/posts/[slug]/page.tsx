import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import Avatar from "@/components/Avatar";
import Gallery from "@/components/Gallery";
import { ArrowLeft, ArrowRight, ExternalLink } from "@/components/Icons";
import { PostRow } from "@/components/PostRow";
import ShareButton from "@/components/ShareButton";
import TelegramButton from "@/components/TelegramButton";
import { getPost, type PostSummary } from "@/lib/api";
import { authorUrl } from "@/lib/hosts";
import { cleanExcerpt, formatDate, guessLang, readingMinutes, stripTags, withoutLeadingTitle } from "@/lib/text";

type Props = PageProps<"/site/[author]/posts/[slug]">;

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { author, slug } = await params;
  const post = await getPost(author, slug);
  if (!post) return { title: "Not found" };

  const description = cleanExcerpt(post) ?? undefined;
  const url = authorUrl(post.author.username, `/posts/${post.slug}`);

  return {
    title: post.title,
    description,
    alternates: { canonical: url },
    openGraph: {
      type: "article",
      url,
      title: post.title,
      description,
      images: post.cover_image ? [post.cover_image] : undefined,
      publishedTime: post.published_at ?? undefined,
      authors: [post.author.name],
    },
    twitter: { card: post.cover_image ? "summary_large_image" : "summary" },
  };
}

function Neighbour({ post, direction }: { post: PostSummary; direction: "older" | "newer" }) {
  const older = direction === "older";
  return (
    <Link
      href={`/posts/${post.slug}`}
      className={`group flex flex-col gap-1.5 py-5 ${older ? "sm:items-end sm:text-right" : ""}`}
    >
      <span className="inline-flex items-center gap-1.5 text-[0.8125rem] text-muted">
        {!older && <ArrowLeft className="size-4" />}
        {older ? "Older" : "Newer"}
        {older && <ArrowRight className="size-4" />}
      </span>
      <span className="title line-clamp-2 text-[1.0625rem] leading-snug group-hover:underline group-hover:underline-offset-4">
        {post.title}
      </span>
    </Link>
  );
}

export default async function PostPage({ params }: Props) {
  const { author: username, slug } = await params;
  const post = await getPost(username, slug);
  if (!post) notFound();

  const { author } = post;
  const html = post.html ? withoutLeadingTitle(post.html, post.title) : "";
  const lang = guessLang(`${post.title} ${stripTags(html).slice(0, 400)}`);
  const visual = post.media.filter((item) => item.type !== "document");
  const video = post.post_type === "video" ? visual.find((item) => item.type === "video") : undefined;
  const isGallery = post.post_type === "gallery" && visual.length > 0;
  const minutes = readingMinutes(html);
  const neighbourIds = new Set([post.older?.id, post.newer?.id]);
  const more = post.more.filter((item) => !neighbourIds.has(item.id));
  const url = authorUrl(author.username, `/posts/${post.slug}`);

  return (
    <main>
      <article lang={lang}>
        <header className="mx-auto max-w-[680px] px-4 pt-10 sm:pt-16">
          <h1 className="title text-[2rem] leading-[1.14] sm:text-[2.75rem]">{post.title}</h1>

          <div className="mt-7 flex items-center gap-3">
            <Link href="/" className="shrink-0">
              <Avatar name={author.name} username={author.username} src={author.avatar_url} size={44} />
            </Link>
            <div className="min-w-0 flex-1 text-[0.9375rem] leading-snug">
              <Link href="/" className="font-semibold hover:underline hover:underline-offset-4">
                {author.name}
              </Link>
              <p className="text-[0.8125rem] text-muted tabular-nums" lang="en">
                {post.post_type === "gallery"
                  ? `${visual.length} photos`
                  : post.post_type === "video"
                    ? "Video"
                    : `${minutes} min read`}
                <span aria-hidden="true"> · </span>
                <time dateTime={post.published_at ?? undefined}>{formatDate(post.published_at)}</time>
              </p>
            </div>
            <ShareButton url={url} title={post.title} />
          </div>
        </header>

        {isGallery && (
          <div className="mx-auto mt-8 max-w-[960px] sm:mt-10 sm:px-6">
            <Gallery items={visual} label={`${post.title}: ${visual.length} photos`} />
          </div>
        )}

        {video && (
          <div className="mx-auto mt-8 max-w-[960px] sm:mt-10 sm:px-6">
            <video src={video.url} controls playsInline preload="metadata" poster={post.cover_image ?? undefined} className="w-full bg-black sm:rounded-md" />
          </div>
        )}

        {!isGallery && !video && post.cover_image && (
          <figure className="mx-auto mt-8 max-w-[960px] sm:mt-10 sm:px-6">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={post.cover_image} alt="" fetchPriority="high" className="w-full bg-surface object-cover sm:rounded-md" />
          </figure>
        )}

        {html && (
          // The body is written by the author, in the admin or in their channel,
          // so it is trusted content.
          <div className="prose-post mx-auto mt-8 max-w-[680px] px-4 sm:mt-10" dangerouslySetInnerHTML={{ __html: html }} />
        )}

        {post.telegraph_url && (
          <div className="mx-auto mt-10 max-w-[680px] px-4">
            <a
              href={post.telegraph_url}
              target="_blank"
              rel="noopener noreferrer"
              lang="en"
              className="inline-flex items-center gap-2 text-[0.9375rem] text-muted underline decoration-line underline-offset-4 transition-colors hover:text-ink hover:decoration-ink"
            >
              Read with Instant View on telegra.ph
              <ExternalLink className="size-4" />
            </a>
          </div>
        )}
      </article>

      <div lang="en" className="mx-auto mt-16 max-w-[680px] px-4">
        <section aria-label="About the author" className="flex flex-col gap-5 border-t border-line pt-10 sm:flex-row sm:items-start">
          <Link href="/" className="shrink-0">
            <Avatar name={author.name} username={author.username} src={author.avatar_url} size={64} />
          </Link>
          <div className="min-w-0 flex-1">
            <h2 className="title text-xl">
              <Link href="/" className="hover:underline hover:underline-offset-4">
                Written by {author.name}
              </Link>
            </h2>
            {author.bio && (
              <p lang={guessLang(author.bio)} className="mt-2 font-serif leading-relaxed text-ink-soft">
                {author.bio}
              </p>
            )}
            <div className="mt-5 flex flex-wrap items-center gap-3">
              {author.telegram_url && <TelegramButton href={author.telegram_url} size="sm" />}
              <Link
                href="/"
                className="inline-flex h-8 items-center rounded-full border border-line px-3 text-[0.8125rem] font-medium transition-colors hover:border-ink"
              >
                All posts{author.posts_count ? ` (${author.posts_count})` : ""}
              </Link>
            </div>
          </div>
        </section>

        {(post.older || post.newer) && (
          <nav aria-label="More posts" className="mt-12 grid border-y border-line sm:grid-cols-2 sm:gap-8">
            <div>{post.newer && <Neighbour post={post.newer} direction="newer" />}</div>
            <div className={post.newer ? "border-t border-line sm:border-t-0" : ""}>
              {post.older && <Neighbour post={post.older} direction="older" />}
            </div>
          </nav>
        )}

        {more.length > 0 && (
          <section aria-labelledby="more" className="mt-12">
            <h2 id="more" className="text-[0.9375rem] font-semibold">
              More from {author.name}
            </h2>
            <div className="divide-y divide-line">
              {more.map((item) => (
                <PostRow key={item.id} post={item} href={`/posts/${item.slug}`} />
              ))}
            </div>
          </section>
        )}
      </div>
    </main>
  );
}
