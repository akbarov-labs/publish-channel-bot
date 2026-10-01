// Which author a request is for, from its Host header. Shared by the proxy
// (which rewrites author subdomains to /site/<username>) and by pages that
// need to link across hosts.

/** The reader site's root, e.g. https://example.com. Authors live one label below it. */
export function rootUrl(): URL {
  return new URL(process.env.ROOT_URL ?? "http://localhost:3000");
}

/** A DNS label, the same shape the blog enforces on usernames. */
const USERNAME = /^[a-z0-9](?:[a-z0-9-]{1,30}[a-z0-9])$/;

export function authorFromHost(host: string | null): string | null {
  if (!host) return null;

  const hostname = host.split(":")[0].toLowerCase();
  const root = rootUrl().hostname;

  if (!hostname.endsWith(`.${root}`)) return null;

  const label = hostname.slice(0, -(root.length + 1));
  if (label === "www" || !USERNAME.test(label)) return null;

  return label;
}

export function authorUrl(username: string, path = ""): string {
  const root = rootUrl();
  return `${root.protocol}//${username}.${root.host}${path}`;
}
