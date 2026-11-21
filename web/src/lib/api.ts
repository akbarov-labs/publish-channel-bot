import { cache } from "react";

export type PostMedium = {
  id: number;
  url: string;
  type: "photo" | "video" | "document" | "audio";
  position: number;
};

export type Author = {
  username: string;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  telegram_url: string | null;
  site_url: string;
  /** The language of the author's interface; posts keep their own. */
  locale?: string;
  /** The design the author picked: classic, magazine or notebook. */
  template?: string;
  posts_count?: number;
  latest_post_at?: string | null;
};

export type Topic = {
  name: string;
  slug: string;
  posts_count?: number;
};

export type Reaction = { emoji: string; count: number };

export type Poll = {
  question: string;
  options: { text: string; votes: number }[];
  total_votes: number;
  closed: boolean;
  multiple: boolean;
  quiz: boolean;
};

export type PostSummary = {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  post_type: "article" | "gallery" | "video" | "audio" | "poll";
  cover_image: string | null;
  telegraph_url: string | null;
  published_at: string | null;
  url: string;
  media: PostMedium[];
  author?: Author;
  pinned?: boolean;
  /** At 200 words a minute; listings carry it since they have no body. */
  reading_minutes?: number;
  topics?: Topic[];
  views?: number;
  reactions?: Reaction[];
  /** "channel/123": the post in its public channel, for comments. */
  telegram_post?: string | null;
  poll?: Poll | null;
};

export type SeriesSummary = {
  title: string;
  slug: string;
  parts: { title: string; slug: string }[];
};

export type Series = {
  title: string;
  slug: string;
  description: string | null;
  posts: PostSummary[];
};

export type Post = PostSummary & {
  html: string | null;
  series?: SeriesSummary | null;
  author: Author;
  older: PostSummary | null;
  newer: PostSummary | null;
  more: PostSummary[];
};

export type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
};

const API_BASE = process.env.BLOG_API_URL ?? "http://localhost:8001/api";

// Posts change when the author publishes, so a short revalidation window keeps
// pages cheap without leaving new posts unpublished for long.
const REVALIDATE_SECONDS = 60;

async function get<T>(path: string, fresh = false): Promise<T | null> {
  const response = await fetch(`${API_BASE}/public${path}`, {
    ...(fresh ? { cache: "no-store" as const } : { next: { revalidate: REVALIDATE_SECONDS } }),
    headers: { Accept: "application/json" },
  });

  if (response.status === 404) return null;
  if (!response.ok) {
    throw new Error(`Blog API ${path} answered ${response.status}`);
  }

  return response.json();
}

const segment = encodeURIComponent;

// cache() lets generateMetadata and the page share one request per render.

export const getAuthors = cache(async () => (await get<{ data: Author[] }>("/authors"))?.data ?? []);

export const getLatestPosts = cache(
  async (page = 1, perPage = 12) =>
    (await get<Paginated<PostSummary>>(`/posts?page=${page}&per_page=${perPage}`))!,
);

export const getAuthor = cache((username: string) => get<Author>(`/authors/${segment(username)}`));

export const getAuthorPosts = cache(
  (username: string, page = 1) =>
    get<Paginated<PostSummary> & { pinned?: PostSummary | null }>(`/authors/${segment(username)}/posts?page=${page}`),
);

/** One topic's posts, newest first; null when the author has no such topic. */
export const getTopicPosts = cache((username: string, topic: string, page = 1) =>
  get<Paginated<PostSummary>>(
    `/authors/${segment(username)}/posts?topic=${segment(topic)}&page=${page}`,
  ),
);

/** Posts whose title or text has every word of the query. */
export const searchPosts = cache((username: string, query: string, page = 1) =>
  get<Paginated<PostSummary>>(`/authors/${segment(username)}/posts?q=${segment(query)}&page=${page}`),
);

export const getSeries = cache((username: string, slug: string) =>
  get<Series>(`/authors/${segment(username)}/series/${segment(slug)}`),
);

/** An author's topics that have posts, the busiest first. */
export const getAuthorTopics = cache(
  async (username: string) => (await get<{ data: Topic[] }>(`/authors/${segment(username)}/topics`))?.data ?? [],
);

/** The latest posts with their bodies, for the author's RSS feed. */
export const getAuthorFeed = cache(
  (username: string) =>
    get<Paginated<PostSummary & { html: string | null }>>(
      `/authors/${segment(username)}/posts?per_page=20&with_body=1`,
    ),
);

/** Every visible post's slug and last change, for the author's sitemap. */
export const getAuthorSitemap = cache(
  async (username: string) =>
    (await get<{ data: { slug: string; updated_at: string | null }[] }>(`/authors/${segment(username)}/sitemap`))
      ?.data ?? null,
);

/** A post through its secret preview link, never cached: the author is editing it. */
export const getPreview = cache((username: string, token: string) =>
  get<Post>(`/authors/${segment(username)}/preview/${segment(token)}`, true),
);

export const getPost = cache((username: string, slug: string) =>
  get<Post>(`/authors/${segment(username)}/posts/${segment(slug)}`),
);
