import { Telegram } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

/** The one primary action an author's space offers: follow the channel. */
export default function TelegramButton({
  href,
  locale,
  size = "md",
  children,
}: {
  href: string;
  locale: Locale;
  size?: "sm" | "md";
  children?: React.ReactNode;
}) {
  const sizing = size === "sm" ? "h-8 gap-1.5 px-3 text-[0.8125rem]" : "h-10 gap-2 px-4 text-[0.9375rem]";

  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={`inline-flex items-center rounded-full bg-accent font-medium text-on-accent transition-[filter,transform] duration-150 ease-out hover:brightness-110 active:scale-[0.98] ${sizing}`}
    >
      <Telegram className={size === "sm" ? "size-4" : "size-[18px]"} />
      {children ?? dict(locale).followOnTelegram}
    </a>
  );
}
