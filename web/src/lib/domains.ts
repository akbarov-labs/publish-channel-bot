// Which author a host is for, asked of the blog by the proxy: a subdomain, or
// an author's own domain (Premium). custom_domain is where the space lives
// now: a subdomain visit is sent there, and an own domain whose Premium ended
// is sent back to the subdomain.
//
// The proxy runs on every request, so answers are kept in memory for a minute,
// "no such host" included. This is one long-lived Node process, not an edge
// deployment, so a module-level cache lives as long as the server.

export type HostAuthor = { username: string; custom_domain: string | null };

const API_BASE = process.env.BLOG_API_URL ?? "http://localhost:8001/api";
const TTL_MS = 60_000;
const MAX_ENTRIES = 5_000;

const cache = new Map<string, { at: number; value: HostAuthor | null }>();

/**
 * The author for a host, null when there is none, or undefined when the blog
 * could not be asked: then the proxy does what it did before own domains.
 */
export async function hostAuthor(hostname: string): Promise<HostAuthor | null | undefined> {
  const now = Date.now();
  const hit = cache.get(hostname);
  if (hit && now - hit.at < TTL_MS) return hit.value;

  let value: HostAuthor | null;
  try {
    const response = await fetch(`${API_BASE}/public/hosts/${encodeURIComponent(hostname)}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: AbortSignal.timeout(2_000),
    });
    if (response.status === 404) {
      value = null;
    } else if (response.ok) {
      const data = (await response.json()) as Partial<HostAuthor>;
      if (typeof data.username !== "string") return undefined;
      value = { username: data.username, custom_domain: typeof data.custom_domain === "string" ? data.custom_domain : null };
    } else {
      return undefined;
    }
  } catch {
    return undefined;
  }

  if (cache.size >= MAX_ENTRIES) cache.clear();
  cache.set(hostname, { at: now, value });
  return value;
}
