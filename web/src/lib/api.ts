import { cache } from "react";

export type PostMedium = {
  id: number;
  url: string;
  type: "photo" | "video" | "document";
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
  posts_count?: number;
  latest_post_at?: string | null;
};

export type PostSummary = {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  post_type: "article" | "gallery" | "video";
  cover_image: string | null;
  telegraph_url: string | null;
  published_at: string | null;
  url: string;
  media: PostMedium[];
  author?: Author;
};

export type Post = PostSummary & {
  html: string | null;
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

async function get<T>(path: string): Promise<T | null> {
  const response = await fetch(`${API_BASE}/public${path}`, {
    next: { revalidate: REVALIDATE_SECONDS },
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
    get<Paginated<PostSummary>>(`/authors/${segment(username)}/posts?page=${page}`),
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

export const getPost = cache((username: string, slug: string) =>
  get<Post>(`/authors/${segment(username)}/posts/${segment(slug)}`),
);
