import { initials } from "@/lib/text";

// Muted grounds for authors without a photo, picked stably from the username.
const GROUNDS = ["#e9dccb", "#d3e1ea", "#d9e6d2", "#e7d6e2", "#e8e2c6", "#d8dbe9"];

function groundFor(username: string): string {
  let hash = 0;
  for (const char of username) hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
  return GROUNDS[hash % GROUNDS.length];
}

export default function Avatar({
  name,
  username,
  src,
  size,
}: {
  name: string;
  username: string;
  src: string | null;
  size: number;
}) {
  const style = { width: size, height: size };

  if (src) {
    return (
      // Avatars come from arbitrary hosts, so a plain <img> avoids an allowlist.
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={src}
        alt=""
        width={size}
        height={size}
        style={style}
        className="shrink-0 rounded-full bg-surface object-cover"
      />
    );
  }

  return (
    <span
      aria-hidden="true"
      style={{ ...style, background: groundFor(username), fontSize: size * 0.38 }}
      className="inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-[#2b2b28]"
    >
      {initials(name)}
    </span>
  );
}
