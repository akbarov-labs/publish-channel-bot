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

/** The host name alone, lowercase, without the port. */
export function hostnameOf(host: string | null): string {
  return (host ?? "").split(":")[0].toLowerCase();
}

/** A host that is neither the root domain nor one of its subdomains: maybe an author's own domain. */
export function isForeignHost(host: string | null): boolean {
  const hostname = hostnameOf(host);
  const root = rootUrl().hostname;
  return hostname !== "" && hostname !== root && !hostname.endsWith(`.${root}`);
}

/** An address on an author's own domain, with the same scheme (and, in development, port) as the root. */
export function customDomainUrl(domain: string, path = ""): string {
  const root = rootUrl();
  return `${root.protocol}//${domain}${root.port ? `:${root.port}` : ""}${path}`;
}

/** Where an author's space lives: their own domain while it is live, else their subdomain. */
export function authorHome(author: { username: string; custom_domain?: string | null }, path = ""): string {
  return author.custom_domain ? customDomainUrl(author.custom_domain, path) : authorUrl(author.username, path);
}
