import TelegramButton from "@/components/TelegramButton";
import { rootUrl } from "@/lib/hosts";
import { dict, type Locale } from "@/lib/i18n";
import { joinUrl } from "@/lib/site";

// The root domain's invitation to channel owners. Joining is by request (an
// admin makes each author), so every action here opens the bot, which passes
// the request on; nothing promises a sign-up that doesn't exist.

/** Where an author will live: "<name>.<root host>", typed out with a caret. */
function AddressSpecimen({ locale }: { locale: Locale }) {
  const t = dict(locale);

  return (
    <div>
      <p className="text-[0.8125rem] font-medium text-muted">{t.joinAddressLabel}</p>
      <p
        className="mt-2 text-[1.625rem] font-bold leading-tight tracking-[-0.021em] [overflow-wrap:anywhere] sm:text-[2rem]"
        aria-label={`${t.joinAddressExample}.${rootUrl().host}`}
      >
        <span aria-hidden="true">
          {t.joinAddressExample}
          <span className="caret" />
          <span className="text-muted">.{rootUrl().host}</span>
        </span>
      </p>
    </div>
  );
}

/** The opening of the root domain while it has few authors: the full pitch. */
export function AuthorLanding({ bot, locale }: { bot: string; locale: Locale }) {
  const t = dict(locale);

  return (
    <section id="join" aria-labelledby="join-title" className="border-b border-line py-12 sm:py-16 lg:py-20">
      <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,400px)] lg:items-end lg:gap-16">
        <div>
          <h1 id="join-title" className="title max-w-[16ch] text-[2.25rem] leading-[1.08] sm:text-[3.5rem]">
            {t.joinTitle}
          </h1>
          <p className="mt-5 max-w-[54ch] font-serif text-[1.0625rem] leading-relaxed text-ink-soft sm:text-xl">
            {t.joinText}
          </p>
        </div>
        <div className="lg:pb-2">
          <AddressSpecimen locale={locale} />
        </div>
      </div>

      <div className="mt-10 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-5">
        <TelegramButton href={joinUrl(bot)} locale={locale}>
          {t.joinCta}
        </TelegramButton>
        <p className="max-w-[48ch] text-[0.8125rem] leading-snug text-muted">{t.joinNote}</p>
      </div>
    </section>
  );
}

/** What an author gets and how joining goes; follows the landing and the feed. */
export function AuthorDetails({ bot, locale }: { bot: string; locale: Locale }) {
  const t = dict(locale);

  return (
    <section aria-label={t.forAuthors} className="py-12 sm:py-16">
      <h2 className="title text-[1.375rem] sm:text-[1.625rem]">{t.joinBenefitsTitle}</h2>
      <dl className="mt-6 max-w-[880px] border-b border-line">
        {t.joinBenefits.map((benefit) => (
          <div key={benefit.title} className="grid gap-1.5 border-t border-line py-5 sm:grid-cols-[240px_minmax(0,1fr)] sm:gap-10">
            <dt className="text-[1.0625rem] font-semibold leading-snug">{benefit.title}</dt>
            <dd className="max-w-[60ch] leading-relaxed text-ink-soft">{benefit.text}</dd>
          </div>
        ))}
      </dl>

      <h2 className="title mt-14 text-[1.375rem] sm:mt-20 sm:text-[1.625rem]">{t.joinStepsTitle}</h2>
      <ol className="mt-6 grid gap-x-10 sm:grid-cols-3">
        {t.joinSteps(bot).map((step, index) => (
          <li key={step.title} className="border-t border-line py-5">
            <span className="block text-[0.8125rem] font-medium text-muted tabular-nums">{index + 1}</span>
            <h3 className="mt-2 text-[1.0625rem] font-semibold leading-snug">{step.title}</h3>
            <p className="mt-1.5 leading-relaxed text-ink-soft">{step.text}</p>
          </li>
        ))}
      </ol>

      <div className="mt-8">
        <TelegramButton href={joinUrl(bot)} locale={locale}>
          {t.joinCta}
        </TelegramButton>
      </div>
    </section>
  );
}

/** Once the directory is full, the invitation shrinks to one line above the footer. */
export function JoinStrip({ bot, locale }: { bot: string; locale: Locale }) {
  const t = dict(locale);

  return (
    <section
      id="join"
      aria-labelledby="join-strip"
      className="mx-auto mt-16 w-full max-w-[1120px] px-4 sm:px-6"
    >
      <div className="flex flex-col items-start gap-4 border-t border-line pt-10 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 id="join-strip" className="title text-[1.25rem]">
            {t.joinStripTitle}
          </h2>
          <p className="mt-1 text-ink-soft">{t.joinStripText}</p>
        </div>
        <TelegramButton href={joinUrl(bot)} locale={locale}>
          {t.joinCta}
        </TelegramButton>
      </div>
    </section>
  );
}
