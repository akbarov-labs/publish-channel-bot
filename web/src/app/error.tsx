"use client";

import { useLocale } from "@/components/LocaleProvider";
import { dict } from "@/lib/i18n";

// Shown when the blog API is unreachable or answers with an error. The root
// layout provides the host's interface language.
export default function Error({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const t = dict(useLocale());

  return (
    <main className="mx-auto flex w-full max-w-[560px] flex-1 flex-col items-center justify-center px-4 py-24 text-center">
      <h1 className="title text-[2rem] leading-tight">{t.errorTitle}</h1>
      <p className="mt-3 max-w-[42ch] text-muted">{t.errorText}</p>
      <button
        type="button"
        onClick={reset}
        className="mt-8 inline-flex h-10 items-center rounded-full bg-ink px-4 text-[0.9375rem] font-medium text-bg transition-opacity hover:opacity-85"
      >
        {t.tryAgain}
      </button>
    </main>
  );
}
