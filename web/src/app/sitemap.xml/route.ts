import { sitemap } from "@/lib/feed";
import { rootUrl } from "@/lib/hosts";

// Built per request from the blog (and the host): nothing to prerender at build
// time, when the API is not there. The API fetches keep their own short cache.
export const dynamic = "force-dynamic";

// The root domain lists only itself: authors' pages live on their own hosts,
// and each of those has its own sitemap.
export async function GET() {
  return sitemap([{ loc: `${rootUrl().origin}/` }]);
}
