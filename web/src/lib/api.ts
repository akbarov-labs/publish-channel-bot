export type PostMedium = {
  id: number;
  url: string;
  type: "photo" | "video" | "document";
  position: number;
};

export type Post = {
  id: number;
  title: string;
  slug: string;
  html: string | null;
  excerpt: string | null;
  post_type: "article" | "gallery" | "video";
  cover_image: string | null;
  telegraph_url: string | null;
  published_at: string | null;
  media: PostMedium[];
};

type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
};

const API_BASE = process.env.BLOG_API_URL ?? "http://localhost:8001/api";

// Posts change when the author publishes, so a short revalidation window keeps
// pages static without leaving new posts unpublished for long.
const REVALIDATE_SECONDS = 60;

export async function fetchPosts(page = 1): Promise<Paginated<Post>> {
  const response = await fetch(`${API_BASE}/public/posts?page=${page}`, {
    next: { revalidate: REVALIDATE_SECONDS },
  });

  if (!response.ok) {
    throw new Error(`Could not load posts: ${response.status}`);
  }

  return response.json();
}

export async function fetchPost(slug: string): Promise<Post | null> {
  const response = await fetch(`${API_BASE}/public/posts/${encodeURIComponent(slug)}`, {
    next: { revalidate: REVALIDATE_SECONDS },
  });

  if (response.status === 404) {
    return null;
  }
  if (!response.ok) {
    throw new Error(`Could not load post ${slug}: ${response.status}`);
  }

  return response.json();
}

export function formatDate(value: string | null): string {
  if (!value) return "";
  return new Date(value).toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
