import { getAuthor, getAuthorSitemap, getAuthorTopics } from "@/lib/api";
import { sitemap } from "@/lib/feed";
import { authorHome } from "@/lib/hosts";

// Built per request from the blog (and the host): nothing to prerender at build
// time, when the API is not there. The API fetches keep their own short cache.
export const dynamic = "force-dynamic";

// <username>.<root>/sitemap.xml, through the proxy. Sitemaps may only list
// addresses on their own host, so each author has one of their own.
export async function GET(_request: Request, { params }: RouteContext<"/site/[author]/sitemap.xml">) {
  const { author: username } = await params;
  const [author, posts, topics] = await Promise.all([
    getAuthor(username),
    getAuthorSitemap(username),
    getAuthorTopics(username),
  ]);
  if (!author || !posts) return new Response("Not found", { status: 404 });

  return sitemap([
    { loc: authorHome(author, "/"), lastmod: author.latest_post_at },
    ...posts.map((post) => ({
      loc: authorHome(author, `/posts/${encodeURIComponent(post.slug)}`),
      lastmod: post.updated_at,
    })),
    ...topics.map((topic) => ({ loc: authorHome(author, `/topics/${encodeURIComponent(topic.slug)}`) })),
  ]);
}
