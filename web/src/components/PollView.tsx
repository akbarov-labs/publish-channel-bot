import type { Poll } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";

/**
 * A channel poll. Votes are counted in Telegram; the Bot API only reports
 * them at the moment the post arrived, so bars appear only when that
 * snapshot has any.
 */
export default function PollView({ poll, telegramPost, t }: { poll: Poll; telegramPost?: string | null; t: Dictionary }) {
  const total = poll.total_votes;

  return (
    <section aria-label={t.poll} className="rounded-md border border-line p-5 sm:p-6">
      <p className="text-[0.8125rem] font-semibold uppercase tracking-[0.06em] text-muted">{t.poll}</p>
      <ul className="mt-4 space-y-2.5">
        {poll.options.map((option, index) => {
          const share = total > 0 ? Math.round((option.votes / total) * 100) : null;
          return (
            <li key={index} className="relative overflow-hidden rounded-md bg-surface px-4 py-3">
              {share !== null && (
                <span aria-hidden="true" className="absolute inset-y-0 left-0 bg-accent-soft" style={{ width: `${share}%` }} />
              )}
              <span className="relative flex items-baseline justify-between gap-4 font-sans text-[0.9375rem]">
                <span>{option.text}</span>
                {share !== null && <span className="shrink-0 text-muted tabular-nums">{share}%</span>}
              </span>
            </li>
          );
        })}
      </ul>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 font-sans text-[0.8125rem] text-muted">
        <span className="tabular-nums">{total > 0 ? t.pollVotes(total) : ""}</span>
        {telegramPost && !poll.closed && (
          <a
            href={`https://t.me/${telegramPost}`}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex h-9 items-center rounded-full bg-accent px-4 font-medium text-on-accent transition-[filter] hover:brightness-110"
          >
            {t.voteInTelegram}
          </a>
        )}
      </div>
    </section>
  );
}
