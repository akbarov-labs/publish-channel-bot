import { getAuthor, getAuthorSitemap } from "@/lib/api";
import { sitemap } from "@/lib/feed";
import { authorUrl } from "@/lib/hosts";

// <username>.<root>/sitemap.xml, through the proxy. Sitemaps may only list
// addresses on their own host, so each author has one of their own.
export async function GET(_request: Request, { params }: RouteContext<"/site/[author]/sitemap.xml">) {
  const { author: username } = await params;
  const [author, posts] = await Promise.all([getAuthor(username), getAuthorSitemap(username)]);
  if (!author || !posts) return new Response("Not found", { status: 404 });

  return sitemap([
    { loc: authorUrl(author.username, "/"), lastmod: author.latest_post_at },
    ...posts.map((post) => ({
      loc: authorUrl(author.username, `/posts/${encodeURIComponent(post.slug)}`),
      lastmod: post.updated_at,
    })),
  ]);
}
