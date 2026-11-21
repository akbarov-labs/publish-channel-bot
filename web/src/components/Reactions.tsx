import type { Reaction } from "@/lib/api";

/** The channel's reactions on this post, as Telegram readers left them. */
export default function Reactions({ reactions, label }: { reactions: Reaction[]; label: string }) {
  if (reactions.length === 0) return null;

  return (
    <ul aria-label={label} className="flex flex-wrap gap-2">
      {reactions.map((reaction) => (
        <li
          key={reaction.emoji}
          className="inline-flex h-8 items-center gap-1.5 rounded-full bg-surface px-3 text-[0.875rem] tabular-nums"
        >
          <span aria-hidden="true">{reaction.emoji}</span>
          <span className="sr-only">{reaction.emoji}</span>
          {reaction.count}
        </li>
      ))}
    </ul>
  );
}
