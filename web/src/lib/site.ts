// The platform's own name, shown only on the root domain and in footers.
// Authors' spaces carry the author's name instead.
export const SITE_NAME = process.env.SITE_NAME ?? "Channels";

// The production bot's @username (without the @). When set, the root domain
// invites channel owners to ask for a page: the button opens the bot with a
// "join" payload, which it answers and forwards to the admin (ADMIN_CHAT_ID).
// Unset, the root domain stays a plain directory.
export const BOT_USERNAME = process.env.BOT_USERNAME?.replace(/^@/, "").trim() || null;

export function joinUrl(bot: string): string {
  return `https://t.me/${bot}?start=join`;
}
