import { headers } from "next/headers";
import { getAuthor, type Author } from "@/lib/api";
import { authorFromHost } from "@/lib/hosts";
import { defaultLocale, localeOf, type Locale } from "@/lib/i18n";
import { templateOf, type Template } from "@/lib/templates";

/** The author whose subdomain this request is on, or null on the root domain. */
export async function requestAuthor(): Promise<Author | null> {
  const requestHeaders = await headers();
  const username = authorFromHost(requestHeaders.get("x-forwarded-host") ?? requestHeaders.get("host"));
  return username ? await getAuthor(username).catch(() => null) : null;
}

/** The interface language for this request: the author's choice on their space, else the site default. */
export async function requestLocale(): Promise<Locale> {
  const author = await requestAuthor();
  return author ? localeOf(author.locale) : defaultLocale();
}

/** The design for this request: the author's pick on their space; the root domain is classic. */
export async function requestTemplate(): Promise<Template> {
  const author = await requestAuthor();
  return templateOf(author?.template);
}
