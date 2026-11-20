import Link from "next/link";
import Avatar from "@/components/Avatar";
import { Search } from "@/components/Icons";
import TelegramButton from "@/components/TelegramButton";
import type { Author } from "@/lib/api";
import { dict, localeOf } from "@/lib/i18n";

/** The slim bar on every page of an author's space. */
export default function AuthorBar({ author }: { author: Author }) {
  const locale = localeOf(author.locale);
  return (
    <header className="sticky top-0 z-30 border-b border-line bg-bg">
      <div className="mx-auto flex h-14 max-w-[1120px] items-center justify-between gap-4 px-4 sm:px-6">
        <Link href="/" className="flex min-w-0 items-center gap-2.5">
          <Avatar name={author.name} username={author.username} src={author.avatar_url} size={30} />
          <span className="truncate text-[0.9375rem] font-semibold tracking-[-0.01em]">{author.name}</span>
        </Link>
        <div className="flex shrink-0 items-center gap-1.5">
          <Link
            href="/search"
            aria-label={dict(locale).search}
            className="inline-flex size-8 items-center justify-center rounded-full text-muted transition-colors hover:bg-surface hover:text-ink"
          >
            <Search className="size-[18px]" />
          </Link>
          {author.telegram_url && (
            <TelegramButton href={author.telegram_url} locale={locale} size="sm">
              {dict(locale).follow}
            </TelegramButton>
          )}
        </div>
      </div>
    </header>
  );
}
