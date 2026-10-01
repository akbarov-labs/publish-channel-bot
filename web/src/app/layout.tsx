import type { Metadata, Viewport } from "next";
import { Golos_Text, Literata } from "next/font/google";
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

export const metadata: Metadata = {
  title: { default: SITE_NAME, template: `%s · ${SITE_NAME}` },
  description: "Writing from Telegram channels, each author on their own page.",
};

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#121211" },
  ],
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className={`${golos.variable} ${literata.variable} antialiased`}>
      <body className="flex min-h-dvh flex-col bg-bg text-ink">{children}</body>
    </html>
  );
}
