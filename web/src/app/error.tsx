"use client";

// Shown when the blog API is unreachable or answers with an error.
export default function Error({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <main className="mx-auto flex w-full max-w-[560px] flex-1 flex-col items-center justify-center px-4 py-24 text-center">
      <h1 className="title text-[2rem] leading-tight">Posts didn’t load</h1>
      <p className="mt-3 max-w-[42ch] text-muted">
        The blog didn’t answer in time. This is usually brief, so try again in a moment.
      </p>
      <button
        type="button"
        onClick={reset}
        className="mt-8 inline-flex h-10 items-center rounded-full bg-ink px-4 text-[0.9375rem] font-medium text-bg transition-opacity hover:opacity-85"
      >
        Try again
      </button>
    </main>
  );
}
