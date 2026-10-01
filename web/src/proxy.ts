import { NextResponse, type NextRequest } from "next/server";
import { authorFromHost, authorUrl } from "@/lib/hosts";

// <username>.<root> is served from app/site/[author]; the root host serves the
// directory. /site/* is an internal path, so on the root host it redirects to
// the author's real address instead of existing twice.
export function proxy(request: NextRequest) {
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  const author = authorFromHost(host);
  const { pathname, search } = request.nextUrl;

  if (author) {
    const url = request.nextUrl.clone();
    url.pathname = `/site/${author}${pathname === "/" ? "" : pathname}`;
    return NextResponse.rewrite(url);
  }

  const internal = pathname.match(/^\/site\/([^/]+)(\/.*)?$/);
  if (internal) {
    return NextResponse.redirect(authorUrl(internal[1], `${internal[2] ?? "/"}${search}`));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/|favicon.ico|robots.txt|.*\\.(?:svg|png|jpg|jpeg|webp|ico)$).*)"],
};
