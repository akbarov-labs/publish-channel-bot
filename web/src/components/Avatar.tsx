import { initials } from "@/lib/text";

// Muted grounds for authors without a photo, picked stably from the username.
// Each has its own night value (same hue, low lightness): the light pastels
// would glow as bright discs on the dark page. globals.css picks between them.
const GROUNDS: [light: string, dark: string][] = [
  ["#e9dccb", "#3a3125"],
  ["#d3e1ea", "#24303a"],
  ["#d9e6d2", "#28332a"],
  ["#e7d6e2", "#372a34"],
  ["#e8e2c6", "#35311f"],
  ["#d8dbe9", "#2a2d3b"],
];

function groundFor(username: string): [string, string] {
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
        data-fade="" suppressHydrationWarning
        className="shrink-0 rounded-full bg-surface object-cover"
      />
    );
  }

  const [light, dark] = groundFor(username);

  return (
    <span
      aria-hidden="true"
      style={{ ...style, "--ground": light, "--ground-dark": dark, fontSize: size * 0.38 } as React.CSSProperties}
      className="avatar-initials inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold"
    >
      {initials(name)}
    </span>
  );
}
