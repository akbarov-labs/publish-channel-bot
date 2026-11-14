// The reader site's own words, in the three languages an author can pick for
// their space. Post content is never translated; it keeps the language it was
// written in (see guessLang in lib/text).
//
// Uzbek is Latin script, with the proper oʻ / gʻ mark (U+02BB) and tutuq
// belgisi (U+02BC); the serif stack has a face just for U+02BB.

export type Locale = "uz" | "ru" | "en";

export const LOCALES: readonly Locale[] = ["uz", "ru", "en"];

export function isLocale(value: unknown): value is Locale {
  return typeof value === "string" && (LOCALES as readonly string[]).includes(value);
}

/** The locale an author picked, or Uzbek when the API sends nothing usable. */
export function localeOf(value: unknown): Locale {
  return isLocale(value) ? value : "uz";
}

/** The root directory, the not-found and the error pages: DEFAULT_LOCALE, else Uzbek. */
export function defaultLocale(): Locale {
  return localeOf(process.env.DEFAULT_LOCALE);
}

// Plural forms by Intl.PluralRules category; `#` is replaced by the number.
type Forms = { one: string; few?: string; many?: string; other: string };

const pluralRules = new Map<Locale, Intl.PluralRules>();

function plural(locale: Locale, count: number, forms: Forms): string {
  let rules = pluralRules.get(locale);
  if (!rules) {
    rules = new Intl.PluralRules(locale);
    pluralRules.set(locale, rules);
  }
  const category = rules.select(count) as keyof Forms;
  return (forms[category] ?? forms.other).replace("#", String(count));
}

export type Dictionary = {
  siteDescription: string;
  notFoundTitle: string;

  // Root directory
  heroTitle: string;
  heroText: string;
  emptyTitle: string;
  emptyText: string;
  authors: string;
  latestPosts: (page: number) => string;
  lastPosted: (date: string) => string;
  directoryFooter: string;

  // An author's space
  posts: (count: number) => string;
  publishedOn: (site: string) => string;
  noPostsTitle: string;
  noPostsText: (name: string) => string;
  olderPostsPage: (page: number) => string;
  follow: string;
  followOnTelegram: string;

  // A post
  photos: (count: number) => string;
  video: string;
  minutesRead: (minutes: number) => string;
  instantView: string;
  aboutAuthor: string;
  writtenBy: (name: string) => string;
  allPosts: string;
  morePosts: string;
  moreFrom: (name: string) => string;
  newer: string;
  older: string;
  share: string;
  linkCopied: string;
  copyThisLink: string;

  // Gallery
  slideOf: (index: number, count: number) => string;
  photoOf: (index: number, count: number) => string;
  previousPhoto: string;
  nextPhoto: string;
  showPhoto: (index: number) => string;

  // Pagination
  pages: string;
  pageOf: (page: number, last: number) => string;

  // Not found
  pageMissingTitle: string;
  pageMissingAuthorText: (name: string) => string;
  pageMissingText: string;
  allPostsBy: (name: string) => string;
  browseAuthors: string;
  allAuthors: string;

  // Error
  errorTitle: string;
  errorText: string;
  tryAgain: string;
};

const uz: Dictionary = {
  siteDescription: "Telegram kanallaridan yozuvlar — har bir muallif oʻz sahifasida.",
  notFoundTitle: "Topilmadi",

  heroTitle: "Telegram kanallaridan yozuvlar",
  heroText:
    "Bu yerdagi har bir muallif oʻz kanalida yozadi. Postlar, albomlar va katta maqolalar uning sahifasiga oʻzi tushadi — ilk bor chiqqan sanasi bilan.",
  emptyTitle: "Hozircha hech narsa chop etilmagan",
  emptyText: "Telegram kanalni botga ulang — postlari shu yerda, har bir muallifning oʻz sahifasida chiqadi.",
  authors: "Mualliflar",
  latestPosts: (page) => (page === 1 ? "Soʻnggi postlar" : `Soʻnggi postlar, ${page}-sahifa`),
  lastPosted: (date) => `oxirgisi ${date}`,
  directoryFooter: "Bu yerdagi har bir sahifa Telegram kanalida yozilgan.",

  posts: (count) => `${count} ta post`,
  publishedOn: (site) => `Telegramdan ${site} orqali chop etilgan`,
  noPostsTitle: "Hozircha postlar yoʻq",
  noPostsText: () => "Muallif kanalida post joylashi bilan u shu yerda paydo boʻladi.",
  olderPostsPage: (page) => `Avvalgi postlar, ${page}-sahifa`,
  follow: "Obuna boʻlish",
  followOnTelegram: "Telegramda obuna boʻlish",

  photos: (count) => `${count} ta surat`,
  video: "Video",
  minutesRead: (minutes) => `${minutes} daqiqalik oʻqish`,
  instantView: "telegra.ph’da Instant View bilan oʻqish",
  aboutAuthor: "Muallif haqida",
  writtenBy: (name) => `Muallif: ${name}`,
  allPosts: "Barcha postlar",
  morePosts: "Boshqa postlar",
  moreFrom: () => "Muallifning boshqa postlari",
  newer: "Yangiroq",
  older: "Avvalroq",
  share: "Ulashish",
  linkCopied: "Havola nusxalandi",
  copyThisLink: "Havolani nusxalang",

  slideOf: (index, count) => `${index} / ${count}`,
  photoOf: (index, count) => `Surat ${index} / ${count}`,
  previousPhoto: "Oldingi surat",
  nextPhoto: "Keyingi surat",
  showPhoto: (index) => `${index}-suratni koʻrsatish`,

  pages: "Sahifalar",
  pageOf: (page, last) => `${page}-sahifa, jami ${last}`,

  pageMissingTitle: "Bunday sahifa yoʻq",
  pageMissingAuthorText: () =>
    "Havola eskirgan yoki post olib tashlangan boʻlishi mumkin. Muallifning barcha postlari bir bosishda ochiladi.",
  pageMissingText: "Havola eskirgan yoki sahifa olib tashlangan boʻlishi mumkin. Manzilni tekshiring yoki mualliflarni koʻring.",
  allPostsBy: () => "Muallifning barcha postlari",
  browseAuthors: "Mualliflarni koʻrish",
  allAuthors: "Barcha mualliflar",

  errorTitle: "Postlar yuklanmadi",
  errorText: "Blog oʻz vaqtida javob bermadi. Bu odatda tez oʻtib ketadi — birozdan soʻng qayta urinib koʻring.",
  tryAgain: "Qayta urinish",
};

const ru: Dictionary = {
  siteDescription: "Тексты из Telegram-каналов — у каждого автора своя страница.",
  notFoundTitle: "Не найдено",

  heroTitle: "Тексты из Telegram-каналов",
  heroText:
    "Каждый автор здесь пишет в своём канале. Посты, альбомы и лонгриды сами появляются на его странице — с той датой, когда вышли впервые.",
  emptyTitle: "Пока ничего не опубликовано",
  emptyText: "Подключите Telegram-канал к боту, и его посты появятся здесь — у каждого автора своя страница.",
  authors: "Авторы",
  latestPosts: (page) => (page === 1 ? "Свежие посты" : `Свежие посты, страница ${page}`),
  lastPosted: (date) => `последний ${date}`,
  directoryFooter: "Каждая страница здесь написана в Telegram-канале.",

  posts: (count) => plural("ru", count, { one: "# пост", few: "# поста", many: "# постов", other: "# поста" }),
  publishedOn: (site) => `Опубликовано из Telegram на ${site}`,
  noPostsTitle: "Пока нет постов",
  noPostsText: () => "Когда автор опубликует пост в канале, он появится здесь.",
  olderPostsPage: (page) => `Более ранние посты, страница ${page}`,
  follow: "Подписаться",
  followOnTelegram: "Подписаться в Telegram",

  photos: (count) => `${count} фото`,
  video: "Видео",
  minutesRead: (minutes) => `${minutes} мин чтения`,
  instantView: "Читать в Instant View на telegra.ph",
  aboutAuthor: "Об авторе",
  writtenBy: (name) => `Автор — ${name}`,
  allPosts: "Все посты",
  morePosts: "Другие посты",
  moreFrom: () => "Ещё у автора",
  newer: "Новее",
  older: "Раньше",
  share: "Поделиться",
  linkCopied: "Ссылка скопирована",
  copyThisLink: "Скопируйте ссылку",

  slideOf: (index, count) => `${index} из ${count}`,
  photoOf: (index, count) => `Фото ${index} из ${count}`,
  previousPhoto: "Предыдущее фото",
  nextPhoto: "Следующее фото",
  showPhoto: (index) => `Показать фото ${index}`,

  pages: "Страницы",
  pageOf: (page, last) => `Страница ${page} из ${last}`,

  pageMissingTitle: "Такой страницы нет",
  pageMissingAuthorText: () => "Возможно, ссылка устарела или пост удалили. Все посты автора — в одно касание.",
  pageMissingText: "Возможно, ссылка устарела или страницу удалили. Проверьте адрес или загляните к авторам.",
  allPostsBy: () => "Все посты автора",
  browseAuthors: "К авторам",
  allAuthors: "Все авторы",

  errorTitle: "Посты не загрузились",
  errorText: "Блог не ответил вовремя. Обычно это ненадолго — попробуйте ещё раз через минуту.",
  tryAgain: "Попробовать снова",
};

const en: Dictionary = {
  siteDescription: "Writing from Telegram channels, each author on their own page.",
  notFoundTitle: "Not found",

  heroTitle: "Writing from Telegram channels",
  heroText:
    "Every author here writes in their own channel. Posts, albums and long reads arrive on their page on their own, with the dates they were first published.",
  emptyTitle: "Nothing published yet",
  emptyText: "Connect a Telegram channel to the bot and its posts appear here, each author on their own page.",
  authors: "Authors",
  latestPosts: (page) => (page === 1 ? "Latest posts" : `Latest posts, page ${page}`),
  lastPosted: (date) => `last ${date}`,
  directoryFooter: "Every page here is written in a Telegram channel.",

  posts: (count) => plural("en", count, { one: "# post", other: "# posts" }),
  publishedOn: (site) => `Published from Telegram on ${site}`,
  noPostsTitle: "No posts yet",
  noPostsText: (name) => `When ${name} posts in their channel, it appears here.`,
  olderPostsPage: (page) => `Older posts, page ${page}`,
  follow: "Follow",
  followOnTelegram: "Follow on Telegram",

  photos: (count) => plural("en", count, { one: "# photo", other: "# photos" }),
  video: "Video",
  minutesRead: (minutes) => `${minutes} min read`,
  instantView: "Read with Instant View on telegra.ph",
  aboutAuthor: "About the author",
  writtenBy: (name) => `Written by ${name}`,
  allPosts: "All posts",
  morePosts: "More posts",
  moreFrom: (name) => `More from ${name}`,
  newer: "Newer",
  older: "Older",
  share: "Share",
  linkCopied: "Link copied",
  copyThisLink: "Copy this link",

  slideOf: (index, count) => `${index} of ${count}`,
  photoOf: (index, count) => `Photo ${index} of ${count}`,
  previousPhoto: "Previous photo",
  nextPhoto: "Next photo",
  showPhoto: (index) => `Show photo ${index}`,

  pages: "Pages",
  pageOf: (page, last) => `Page ${page} of ${last}`,

  pageMissingTitle: "This page isn’t here",
  pageMissingAuthorText: (name) =>
    `The link may be old, or the post was taken down. Everything ${name} has published is one tap away.`,
  pageMissingText: "The link may be old, or the page was taken down. Check the address, or browse the authors.",
  allPostsBy: (name) => `All posts by ${name}`,
  browseAuthors: "Browse authors",
  allAuthors: "All authors",

  errorTitle: "Posts didn’t load",
  errorText: "The blog didn’t answer in time. This is usually brief, so try again in a moment.",
  tryAgain: "Try again",
};

const DICTIONARIES: Record<Locale, Dictionary> = { uz, ru, en };

export function dict(locale: Locale): Dictionary {
  return DICTIONARIES[locale];
}

// Intl names for dates: Uzbek in Latin script, British English day-month order.
const INTL_LOCALE: Record<Locale, string> = { uz: "uz-Latn", ru: "ru", en: "en-GB" };

const TIME_ZONE = process.env.SITE_TIME_ZONE ?? "UTC";

const formats = new Map<string, Intl.DateTimeFormat>();

function dateFormat(locale: Locale, withYear: boolean): Intl.DateTimeFormat {
  const key = `${locale}:${withYear}`;
  let format = formats.get(key);
  if (!format) {
    format = new Intl.DateTimeFormat(INTL_LOCALE[locale], {
      day: "numeric",
      month: "short",
      year: withYear ? "numeric" : undefined,
      timeZone: TIME_ZONE,
    });
    formats.set(key, format);
  }
  return format;
}

/** "3 Oct" / "3 окт." / "3-okt", with the year added outside the current one. */
export function formatDate(value: string | null | undefined, locale: Locale): string {
  if (!value) return "";
  const date = new Date(value);
  const withYear = date.getUTCFullYear() !== new Date().getUTCFullYear();
  return dateFormat(locale, withYear).format(date);
}
