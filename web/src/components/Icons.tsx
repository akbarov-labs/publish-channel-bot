// One stroke icon set on a 24px grid, plus the Telegram mark.

type IconProps = { className?: string };

function Stroke({ className, children }: IconProps & { children: React.ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className ?? "size-5"}
    >
      {children}
    </svg>
  );
}

export function ChevronLeft(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="m15 18-6-6 6-6" />
    </Stroke>
  );
}

export function ChevronRight(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="m9 18 6-6-6-6" />
    </Stroke>
  );
}

export function ArrowLeft(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="M19 12H5M11 18l-6-6 6-6" />
    </Stroke>
  );
}

export function ArrowRight(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="M5 12h14M13 6l6 6-6 6" />
    </Stroke>
  );
}

export function Photos(props: IconProps) {
  return (
    <Stroke {...props}>
      <rect x="3" y="5" width="15" height="13" rx="2" />
      <path d="M21 8v10a3 3 0 0 1-3 3H7" />
      <path d="m3 15 4-4 4 4 2-2 5 5" />
    </Stroke>
  );
}

export function Play(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="M7 5.5v13l11-6.5z" />
    </Stroke>
  );
}

export function ExternalLink(props: IconProps) {
  return (
    <Stroke {...props}>
      <path d="M14 4h6v6M10 14 20 4M19 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4" />
    </Stroke>
  );
}

export function Telegram({ className }: IconProps) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className={className ?? "size-5"}>
      <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
    </svg>
  );
}
