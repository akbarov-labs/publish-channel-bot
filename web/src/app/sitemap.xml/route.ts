import { sitemap } from "@/lib/feed";
import { rootUrl } from "@/lib/hosts";

// The root domain lists only itself: authors' pages live on their own hosts,
// and each of those has its own sitemap.
export async function GET() {
  return sitemap([{ loc: `${rootUrl().origin}/` }]);
}
