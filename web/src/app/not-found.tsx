import Link from "next/link";
import AuthorBar from "@/components/AuthorBar";
import SiteBar from "@/components/SiteBar";
import { rootUrl } from "@/lib/hosts";
import { defaultLocale, dict, localeOf } from "@/lib/i18n";
import { requestAuthor } from "@/lib/request";
import { SITE_NAME } from "@/lib/site";

// Shared by the root domain and by author subdomains (an unknown username, or
// a post that was removed). On a real author's subdomain the reader keeps the
// author's bar, and the author's language, so they are never stranded; "/" is
// that host's front page.
export default async function NotFound() {
  const author = await requestAuthor();
  const t = dict(author ? localeOf(author.locale) : defaultLocale());

  return (
    <>
      {author ? <AuthorBar author={author} /> : <SiteBar />}
      <main id="main" tabIndex={-1} className="mx-auto flex w-full max-w-[560px] outline-none flex-1 flex-col items-center justify-center px-4 py-24 text-center">
        <h1 className="title text-[2rem] leading-tight">{t.pageMissingTitle}</h1>
        <p className="mt-3 max-w-[42ch] text-muted">
          {author ? t.pageMissingAuthorText(author.name) : t.pageMissingText}
        </p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          {/* Without a known author, "/" may be this same missing subdomain, so go to the root origin. */}
          <Link
            href={author ? "/" : rootUrl().origin}
            className="inline-flex h-10 items-center rounded-full bg-ink px-4 text-[0.9375rem] font-medium text-bg transition-opacity hover:opacity-85"
          >
            {author ? t.allPostsBy(author.name) : t.browseAuthors}
          </Link>
          {/* The root origin is already the authors page, so only an author's space needs a second way out. */}
          {author && (
            <a
              href={rootUrl().origin}
              className="inline-flex h-10 items-center rounded-full border border-line px-4 text-[0.9375rem] font-medium transition-colors hover:border-ink"
            >
              {t.allAuthors}
            </a>
          )}
        </div>
      </main>
      <footer className="border-t border-line">
        <div className="mx-auto max-w-[1120px] px-4 py-8 text-sm text-muted sm:px-6">
          {author ? `© ${new Date().getFullYear()} ${author.name}` : SITE_NAME}
        </div>
      </footer>
    </>
  );
}
