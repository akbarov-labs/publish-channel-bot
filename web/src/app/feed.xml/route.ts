import { getLatestPosts } from "@/lib/api";
import { rss } from "@/lib/feed";
import { rootUrl } from "@/lib/hosts";
import { defaultLocale, dict } from "@/lib/i18n";
import { SITE_NAME } from "@/lib/site";

// The root domain's feed: the latest posts from every author. Listings carry
// no bodies, so items link out with their excerpt.
export async function GET() {
  const posts = await getLatestPosts(1, 30);
  const locale = defaultLocale();
  const root = rootUrl().origin;

  return rss({
    title: SITE_NAME,
    description: dict(locale).siteDescription,
    link: `${root}/`,
    self: `${root}/feed.xml`,
    language: locale,
    items: posts.data.map((post) => ({ ...post, authorName: post.author?.name })),
  });
}
