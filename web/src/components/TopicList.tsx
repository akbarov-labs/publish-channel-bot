import Link from "next/link";
import type { Topic } from "@/lib/api";

export function topicHref(topic: Pick<Topic, "slug">): string {
  return `/topics/${encodeURIComponent(topic.slug)}`;
}

/** Topics as a row of quiet chips: under a post, or on the front page. */
export default function TopicList({
  topics,
  label,
  counts = false,
  className = "",
}: {
  topics: Topic[];
  label: string;
  counts?: boolean;
  className?: string;
}) {
  if (topics.length === 0) return null;

  return (
    <nav aria-label={label} className={className}>
      <ul className="flex flex-wrap gap-2">
        {topics.map((topic) => (
          <li key={topic.slug}>
            <Link
              href={topicHref(topic)}
              className="inline-flex h-8 items-center gap-1.5 rounded-full border border-line px-3 text-[0.8125rem] text-ink-soft transition-colors duration-150 hover:border-ink hover:text-ink"
            >
              <span aria-hidden="true" className="text-muted">
                #
              </span>
              {topic.name}
              {counts && topic.posts_count !== undefined && (
                <span className="text-muted tabular-nums">{topic.posts_count}</span>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}
