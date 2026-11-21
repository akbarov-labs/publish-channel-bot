import { hostAuthor } from "@/lib/domains";
import { authorFromHost, authorUrl, customDomainUrl, hostnameOf, isForeignHost, rootUrl } from "@/lib/hosts";

// Built per request from the blog (and the host): nothing to prerender at build
// time, when the API is not there. The API fetches keep their own short cache.
export const dynamic = "force-dynamic";

// One robots.txt for every host (the proxy leaves /robots.txt alone), pointing
// crawlers at the sitemap of the host they asked: an author's own on their
// subdomain or own domain, the directory's on the root domain.
export async function GET(request: Request) {
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  const author = authorFromHost(host);
  const ownDomain = !author && isForeignHost(host) ? (await hostAuthor(hostnameOf(host)))?.custom_domain : null;
  const sitemap = author
    ? authorUrl(author, "/sitemap.xml")
    : ownDomain
      ? customDomainUrl(ownDomain, "/sitemap.xml")
      : `${rootUrl().origin}/sitemap.xml`;

  return new Response(`User-agent: *\nAllow: /\n\nSitemap: ${sitemap}\n`, {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
}
