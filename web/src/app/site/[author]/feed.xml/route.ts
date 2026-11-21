import { getAuthor, getAuthorFeed } from "@/lib/api";
import { rss } from "@/lib/feed";
import { authorHome } from "@/lib/hosts";
import { dict, localeOf } from "@/lib/i18n";

// Built per request from the blog (and the host): nothing to prerender at build
// time, when the API is not there. The API fetches keep their own short cache.
export const dynamic = "force-dynamic";

// <username>.<root>/feed.xml, through the proxy: the author's latest posts, in full.
export async function GET(_request: Request, { params }: RouteContext<"/site/[author]/feed.xml">) {
  const { author: username } = await params;
  const [author, posts] = await Promise.all([getAuthor(username), getAuthorFeed(username)]);
  if (!author || !posts) return new Response("Not found", { status: 404 });

  const locale = localeOf(author.locale);

  return rss({
    title: author.name,
    description: author.bio ?? dict(locale).siteDescription,
    link: authorHome(author),
    self: authorHome(author, "/feed.xml"),
    language: locale,
    image: author.avatar_url,
    items: posts.data.map((post) => ({ ...post, authorName: author.name })),
  });
}
