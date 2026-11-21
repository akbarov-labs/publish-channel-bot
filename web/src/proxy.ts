import { NextResponse, type NextRequest } from "next/server";
import { hostAuthor } from "@/lib/domains";
import { authorFromHost, authorUrl, customDomainUrl, hostnameOf, isForeignHost } from "@/lib/hosts";
import { AUTHOR_HEADER } from "@/lib/request-headers";

// <username>.<root> and an author's own domain are served from
// app/site/[author]; the root host serves the directory. /site/* is an
// internal path, so on the root host it redirects to the author's real address
// instead of existing twice.
//
// Own domains (Premium): a subdomain visit goes to the own domain while it is
// live, and an own domain whose Premium ended goes back to the subdomain.
// Both redirects are temporary (302), so neither is cached for good and the
// pair can never loop in a browser. Canonical links name the live address.
export async function proxy(request: NextRequest) {
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  const hostname = hostnameOf(host);
  const subdomain = authorFromHost(host);
  const { pathname, search } = request.nextUrl;

  // Only the proxy says which author a request is for.
  const headers = new Headers(request.headers);
  headers.delete(AUTHOR_HEADER);

  if (subdomain) {
    const found = await hostAuthor(hostname);
    if (found?.custom_domain) {
      return NextResponse.redirect(customDomainUrl(found.custom_domain, `${pathname}${search}`), 302);
    }
    return rewriteToAuthor(request, subdomain, headers);
  }

  if (isForeignHost(host)) {
    const found = await hostAuthor(hostname);
    if (found && found.custom_domain === hostname) {
      return rewriteToAuthor(request, found.username, headers);
    }
    if (found) {
      return NextResponse.redirect(authorUrl(found.username, `${pathname}${search}`), 302);
    }
  }

  const internal = pathname.match(/^\/site\/([^/]+)(\/.*)?$/);
  if (internal) {
    return NextResponse.redirect(authorUrl(internal[1], `${internal[2] ?? "/"}${search}`));
  }

  return NextResponse.next({ request: { headers } });
}

function rewriteToAuthor(request: NextRequest, username: string, headers: Headers) {
  const url = request.nextUrl.clone();
  url.pathname = `/site/${username}${url.pathname === "/" ? "" : url.pathname}`;
  headers.set(AUTHOR_HEADER, username);
  return NextResponse.rewrite(url, { request: { headers } });
}

export const config = {
  matcher: ["/((?!_next/|favicon.ico|robots.txt|.*\\.(?:svg|png|jpg|jpeg|webp|ico)$).*)"],
};
