// <username>.<root>/posts/<slug>/view, through the proxy: counts one view.
// The blog's public API is not reachable from outside, so the site passes the
// reader's address on for its per-address cap.

const API_BASE = process.env.BLOG_API_URL ?? "http://localhost:8001/api";

export async function POST(request: Request, { params }: RouteContext<"/site/[author]/posts/[slug]/view">) {
  const { author, slug } = await params;
  const forwarded = request.headers.get("x-forwarded-for") ?? request.headers.get("x-real-ip");

  try {
    await fetch(`${API_BASE}/public/authors/${encodeURIComponent(author)}/posts/${encodeURIComponent(slug)}/view`, {
      method: "POST",
      headers: { Accept: "application/json", ...(forwarded ? { "X-Forwarded-For": forwarded } : {}) },
      cache: "no-store",
    });
  } catch {
    // A missed view is not worth an error.
  }

  return new Response(null, { status: 204 });
}
