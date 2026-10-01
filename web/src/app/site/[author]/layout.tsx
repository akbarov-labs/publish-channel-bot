import type { Metadata } from "next";
import { notFound } from "next/navigation";
import AuthorBar from "@/components/AuthorBar";
import { getAuthor } from "@/lib/api";
import { authorUrl, rootUrl } from "@/lib/hosts";
import { SITE_NAME } from "@/lib/site";

// Reached through the proxy: <username>.<root>/... is rewritten to /site/<username>/...

export async function generateMetadata({ params }: LayoutProps<"/site/[author]">): Promise<Metadata> {
  const author = await getAuthor((await params).author);
  if (!author) return {};

  return {
    title: { absolute: author.name, template: `%s — ${author.name}` },
    description: author.bio ?? undefined,
    metadataBase: new URL(authorUrl(author.username)),
    openGraph: { siteName: author.name, images: author.avatar_url ? [author.avatar_url] : undefined },
  };
}

export default async function AuthorLayout({ children, params }: LayoutProps<"/site/[author]">) {
  const author = await getAuthor((await params).author);
  if (!author) notFound();

  return (
    <>
      <AuthorBar author={author} />

      <div className="flex-1">{children}</div>

      <footer className="mt-20 border-t border-line">
        <div className="mx-auto flex max-w-[1120px] flex-col gap-2 px-4 py-8 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <span>
            © {new Date().getFullYear()} {author.name}
          </span>
          <a href={rootUrl().origin} className="hover:text-ink hover:underline hover:underline-offset-4">
            Published from Telegram on {SITE_NAME}
          </a>
        </div>
      </footer>
    </>
  );
}
