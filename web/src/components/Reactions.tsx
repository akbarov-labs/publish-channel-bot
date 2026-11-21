import type { Reaction } from "@/lib/api";

/**
 * The channel's reactions on this post, as Telegram readers left them. Shown,
 * not pressable: a reaction here could not reach the channel, so outlined
 * counts rather than buttons.
 */
export default function Reactions({ reactions, label }: { reactions: Reaction[]; label: string }) {
  if (reactions.length === 0) return null;

  return (
    <ul aria-label={label} className="flex flex-wrap gap-2">
      {reactions.map((reaction) => (
        <li
          key={reaction.emoji}
          className="inline-flex h-8 items-center gap-1.5 rounded-full border border-line px-3 text-[0.8125rem] text-ink-soft tabular-nums"
        >
          <span className="text-[0.9375rem] leading-none">{reaction.emoji}</span>
          {reaction.count}
        </li>
      ))}
    </ul>
  );
}
