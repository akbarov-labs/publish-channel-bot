"use client";

import { createContext, useContext } from "react";
import type { Locale } from "@/lib/i18n";

// The interface locale of the current host, for client components that the
// server cannot hand it to directly, such as error.tsx.
const LocaleContext = createContext<Locale>("uz");

export default function LocaleProvider({ locale, children }: { locale: Locale; children: React.ReactNode }) {
  return <LocaleContext.Provider value={locale}>{children}</LocaleContext.Provider>;
}

export function useLocale(): Locale {
  return useContext(LocaleContext);
}
