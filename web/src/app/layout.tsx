import type { Metadata, Viewport } from "next";
import { Golos_Text, Literata } from "next/font/google";
import localFont from "next/font/local";
import LocaleProvider from "@/components/LocaleProvider";
import { defaultLocale, dict } from "@/lib/i18n";
import { requestLocale } from "@/lib/request";
import { rootUrl } from "@/lib/hosts";
import { SITE_NAME } from "@/lib/site";
import "./globals.css";

// Posts are written in Russian, Uzbek (Latin, with oʻ and gʻ) and English, so
// both faces load their Cyrillic subsets alongside Latin.
const golos = Golos_Text({
  variable: "--font-golos",
  subsets: ["latin", "latin-ext", "cyrillic", "cyrillic-ext"],
  display: "swap",
});

const literata = Literata({
  variable: "--font-literata",
  subsets: ["latin", "latin-ext", "cyrillic", "cyrillic-ext"],
  style: ["normal", "italic"],
  axes: ["opsz"],
  display: "swap",
});

// Literata ships U+02BB (the Uzbek oʻ / gʻ mark) with zero advance width, so
// it lands on top of the next letter. These two faces are Literata's own
// turned comma (U+2018) mapped to U+02BB, and nothing else; they sit ahead of
// Literata in the serif stack and are only fetched for text that has the mark.
// Literata is under the SIL Open Font License 1.1.
const literataOkina = localFont({
  variable: "--font-literata-okina",
  src: [
    { path: "./fonts/literata-okina-normal.woff2", weight: "200 900", style: "normal" },
    { path: "./fonts/literata-okina-italic.woff2", weight: "200 900", style: "italic" },
  ],
  declarations: [{ prop: "unicode-range", value: "U+02BB" }],
  display: "swap",
  preload: false,
  adjustFontFallback: false,
  fallback: [],
});

export const metadata: Metadata = {
  title: { default: SITE_NAME, template: `%s · ${SITE_NAME}` },
  description: dict(defaultLocale()).siteDescription,
  alternates: { types: { "application/rss+xml": [{ url: `${rootUrl().origin}/feed.xml`, title: SITE_NAME }] } },
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#121211" },
  ],
};

// The interface language: the author's choice on their subdomain, the site
// default on the root domain. Post bodies carry their own lang attribute.
export default async function RootLayout({ children }: LayoutProps<"/">) {
  const locale = await requestLocale();

  return (
    <html lang={locale} className={`${golos.variable} ${literata.variable} ${literataOkina.variable} antialiased`}>
      <body className="flex min-h-dvh flex-col bg-bg text-ink">
        <LocaleProvider locale={locale}>{children}</LocaleProvider>
      </body>
    </html>
  );
}
