"""Premium: a monthly Telegram Stars subscription for authors.

/premium shows what it gives and a link to subscribe. Telegram asks before it
charges (pre-checkout), then reports each payment, the first and every
renewal, as a message; the blog keeps who has Premium until when. Cancelling
happens in Telegram and needs nothing from us: no renewal arrives, and the
blog's premium_until passes.

Telegram requires a bot that takes payments to answer /paysupport and
/terms.
"""

from __future__ import annotations

import asyncio
import html
import logging
from datetime import datetime, timezone

from aiogram import F, Router
from aiogram.exceptions import TelegramAPIError
from aiogram.filters import Command
from aiogram.types import (
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    LabeledPrice,
    Message,
    PreCheckoutQuery,
    User,
)

from app.config import get_settings
from app.services.premium import (
    PRE_CHECKOUT_TIMEOUT,
    SUBSCRIPTION_PERIOD,
    PremiumError,
    PremiumStatus,
    invoice_payload,
    payload_user_id,
    premium_status,
    record_payment,
)

log = logging.getLogger(__name__)
router = Router(name="premium")

# Seconds between tries when the blog does not answer a payment.
RECORD_RETRY_DELAYS = (1.0, 3.0, 10.0)

# Every text in the three languages of the platform; the user's Telegram
# language picks one, English otherwise.
TEXTS: dict[str, dict[str, str]] = {
    "not_on_sale": {
        "uz": "Premium hali sotuvda emas.",
        "ru": "Premium пока не продаётся.",
        "en": "Premium is not on sale yet.",
    },
    "not_author": {
        "uz": "Premium saytda sahifasi bor mualliflar uchun. Avval sahifa soʻrang: saytdagi «Sahifa soʻrash» tugmasi.",
        "ru": "Premium — для авторов со страницей на сайте. Сначала запросите страницу кнопкой «Запросить страницу» на сайте.",
        "en": "Premium is for authors with a page on the site. Ask for one first with the “Request a page” button on the site.",
    },
    "blocked": {
        "uz": "Hisobingiz bloklangan, shuning uchun Premium sotib olib boʻlmaydi. Savollar uchun: /paysupport",
        "ru": "Ваш аккаунт заблокирован, поэтому купить Premium нельзя. Вопросы: /paysupport",
        "en": "Your account is blocked, so Premium can’t be bought. Questions: /paysupport",
    },
    "unavailable": {
        "uz": "Hozir tekshirib boʻlmadi. Bir daqiqadan soʻng qayta urinib koʻring.",
        "ru": "Сейчас не получилось проверить. Попробуйте через минуту.",
        "en": "Couldn’t check right now. Please try again in a minute.",
    },
    "invalid": {
        "uz": "Bu toʻlov havolasi eskirgan. Yangisini olish uchun /premium yuboring.",
        "ru": "Эта ссылка на оплату устарела. Отправьте /premium, чтобы получить новую.",
        "en": "This payment link is out of date. Send /premium for a new one.",
    },
    "offer": {
        "uz": (
            "<b>⭐ Premium</b>\n\n"
            "• Oʻz domeningiz, masalan <i>blog.sizning.uz</i>\n"
            "• Sahifalaringizda platforma belgisi yoʻq\n"
            "• Qoʻshimcha sayt dizaynlari\n"
            "• Bir yillik statistika va CSV eksport\n\n"
            "Oyiga {price} ⭐. Har oy oʻzi yangilanadi, Telegram sozlamalarida istalgan payt bekor qilasiz. /terms"
        ),
        "ru": (
            "<b>⭐ Premium</b>\n\n"
            "• Свой домен, например <i>blog.vash.uz</i>\n"
            "• Без отметки платформы на ваших страницах\n"
            "• Дополнительные дизайны сайта\n"
            "• Статистика за год и выгрузка в CSV\n\n"
            "{price} ⭐ в месяц. Продлевается сама, отменить можно в любой момент в настройках Telegram. /terms"
        ),
        "en": (
            "<b>⭐ Premium</b>\n\n"
            "• Your own domain, like <i>blog.yours.uz</i>\n"
            "• No platform credit on your pages\n"
            "• More site designs\n"
            "• A year of stats, with CSV export\n\n"
            "{price} ⭐ a month. It renews by itself; cancel any time in Telegram’s settings. /terms"
        ),
    },
    "invoice_description": {
        "uz": "Premium 30 kunga: oʻz domeningiz, platforma belgisisiz sahifalar, qoʻshimcha dizaynlar va bir yillik statistika.",
        "ru": "Premium на 30 дней: свой домен, страницы без отметки платформы, дополнительные дизайны и статистика за год.",
        "en": "Premium for 30 days: your own domain, pages without the platform credit, more designs and a year of stats.",
    },
    "subscribe": {
        "uz": "Obuna boʻlish — oyiga {price} ⭐",
        "ru": "Подписаться — {price} ⭐ в месяц",
        "en": "Subscribe — {price} ⭐ a month",
    },
    "active": {
        "uz": (
            "⭐ Premium {date} gacha yoqilgan.\n\n"
            "Obuna boʻlgan boʻlsangiz, u har oy oʻzi yangilanadi. Bekor qilish: Telegram → Sozlamalar → "
            "Mening yulduzlarim. Shu sanadan keyin davom ettirish uchun /premium yuboring."
        ),
        "ru": (
            "⭐ Premium включён до {date}.\n\n"
            "Если вы оформили подписку, она продлевается сама. Отменить: Telegram → Настройки → Мои звёзды. "
            "Чтобы продолжить после этой даты, отправьте /premium."
        ),
        "en": (
            "⭐ Premium is on until {date}.\n\n"
            "If you subscribed, it renews by itself. To cancel: Telegram → Settings → My Stars. "
            "To continue after that date, send /premium."
        ),
    },
    "paid": {
        "uz": "✅ Premium {date} gacha yoqildi. Rahmat!",
        "ru": "✅ Premium включён до {date}. Спасибо!",
        "en": "✅ Premium is on until {date}. Thank you!",
    },
    "renewed": {
        "uz": "⭐ Premium {date} gacha uzaytirildi.",
        "ru": "⭐ Premium продлён до {date}.",
        "en": "⭐ Premium is renewed until {date}.",
    },
    "refunded": {
        "uz": "Bu Telegram hisobi hech bir muallifga tegishli emas, shuning uchun toʻlov qaytarildi.",
        "ru": "Этот аккаунт Telegram не принадлежит ни одному автору, поэтому оплата возвращена.",
        "en": "No author has this Telegram account, so the payment was refunded.",
    },
    "delayed": {
        "uz": "Toʻlov qabul qilindi. Premium tez orada yoqiladi; bir kun ichida yoqilmasa, /paysupport ga yozing.",
        "ru": "Оплата получена. Premium скоро включится; если этого не случится в течение дня, напишите /paysupport.",
        "en": "Payment received. Premium will be on shortly; if it isn’t within a day, write to /paysupport.",
    },
    "support_how": {
        "uz": "Muammoni bitta xabarda /paysupport dan keyin yozing, masalan:\n<code>/paysupport Toʻladim, lekin Premium yoqilmadi</code>",
        "ru": "Опишите проблему одним сообщением после /paysupport, например:\n<code>/paysupport Оплатил, но Premium не включился</code>",
        "en": "Describe the problem in one message after /paysupport, for example:\n<code>/paysupport I paid but Premium is off</code>",
    },
    "support_sent": {
        "uz": "Yuborildi. Shu yerda, Telegramda javob beramiz.",
        "ru": "Отправлено. Ответим здесь, в Telegram.",
        "en": "Sent. We’ll answer here in Telegram.",
    },
    "support_unavailable": {
        "uz": "Hozir yuborib boʻlmadi. Birozdan soʻng qayta urinib koʻring.",
        "ru": "Сейчас не получилось отправить. Попробуйте чуть позже.",
        "en": "Couldn’t send it right now. Please try again a little later.",
    },
    "terms": {
        "uz": (
            "<b>Premium shartlari</b>\n\n"
            "• Premium — Telegram Stars bilan toʻlanadigan oylik obuna. Har bir toʻlov 30 kun beradi va "
            "bekor qilmaguningizcha oʻzi yangilanadi.\n"
            "• Istalgan payt bekor qilish mumkin: Telegram → Sozlamalar → Mening yulduzlarim. Premium "
            "toʻlangan davr oxirigacha ishlaydi.\n"
            "• Toʻlov muammolari va pulni qaytarish soʻrovlari: /paysupport. Shu chatda javob beramiz.\n"
            "• Premium imkoniyatlari oʻzgarishi mumkin; toʻlangan davr oxirigacha bor imkoniyatlar saqlanadi.\n"
            "• Toʻlovni Telegram amalga oshiradi; biz faqat Telegram hisobingiz va summani koʻramiz."
        ),
        "ru": (
            "<b>Условия Premium</b>\n\n"
            "• Premium — ежемесячная подписка, оплачиваемая Telegram Stars. Каждая оплата даёт 30 дней, "
            "подписка продлевается сама, пока вы её не отмените.\n"
            "• Отменить можно в любой момент: Telegram → Настройки → Мои звёзды. Premium работает до конца "
            "оплаченного периода.\n"
            "• Проблемы с оплатой и запросы на возврат: /paysupport. Ответим в этом чате.\n"
            "• Возможности Premium могут меняться; то, что есть в оплаченном периоде, остаётся до его конца.\n"
            "• Оплату проводит Telegram; мы видим только ваш аккаунт Telegram и сумму."
        ),
        "en": (
            "<b>Premium terms</b>\n\n"
            "• Premium is a monthly subscription paid in Telegram Stars. Each payment gives 30 days, and it "
            "renews by itself until you cancel.\n"
            "• Cancel any time: Telegram → Settings → My Stars. Premium stays on until the end of the period "
            "you paid for.\n"
            "• Payment problems and refund requests: /paysupport. We answer in this chat.\n"
            "• Premium features can change; what you have in a paid period stays until its end.\n"
            "• Telegram handles the payment; we see only your Telegram account and the amount."
        ),
    },
}


def language_of(user: User | None) -> str:
    language = ((user.language_code if user else None) or "").split("-")[0].lower()
    return language if language in ("uz", "ru", "en") else "en"


def text(key: str, user: User | None, **values: object) -> str:
    return TEXTS[key][language_of(user)].format(**values)


def date_text(moment: datetime | None) -> str:
    return moment.strftime("%d.%m.%Y") if moment else "—"


def blog_args() -> dict[str, str]:
    settings = get_settings()
    return {"base_url": settings.blog_internal_url, "secret": settings.bot_internal_secret}


async def tell_admin(message: Message, notice: str) -> None:
    """Payment trouble the admin has to sort out by hand, if ADMIN_CHAT_ID is set."""
    chat_id = get_settings().admin_chat_id
    if chat_id is None:
        log.error("no ADMIN_CHAT_ID to tell: %s", notice)
        return
    try:
        await message.bot.send_message(chat_id, notice)
    except TelegramAPIError:
        log.exception("could not tell ADMIN_CHAT_ID %s: %s", chat_id, notice)


def who(user: User) -> str:
    name = html.escape(user.full_name)
    handle = f" (@{html.escape(user.username)})" if user.username else ""
    return f'<a href="tg://user?id={user.id}">{name}</a>{handle}, id <code>{user.id}</code>'


# --- /premium -----------------------------------------------------------------


@router.message(Command("premium"), F.chat.type == "private")
async def on_premium(message: Message) -> None:
    settings = get_settings()
    user = message.from_user
    if not settings.sells_premium:
        await message.answer(text("not_on_sale", user))
        return

    try:
        status = await premium_status(user.id, **blog_args())
    except PremiumError:
        log.exception("could not ask the blog about Premium for %s", user.id)
        await message.answer(text("unavailable", user))
        return

    if status is None:
        await message.answer(text("not_author", user))
        return
    if status.blocked:
        await message.answer(text("blocked", user))
        return
    # Paid or gifted, the button would charge them for days they already
    # have, and a second subscription on top of a running one.
    if status.premium:
        await message.answer(text("active", user, date=date_text(status.premium_until)))
        return

    price = settings.premium_price_stars
    try:
        link = await message.bot.create_invoice_link(
            title="Premium",
            description=text("invoice_description", user),
            payload=invoice_payload(user.id),
            currency="XTR",
            prices=[LabeledPrice(label="Premium", amount=price)],
            subscription_period=SUBSCRIPTION_PERIOD,
        )
    except TelegramAPIError:
        log.exception("could not create a Premium invoice link for %s", user.id)
        await message.answer(text("unavailable", user))
        return

    await message.answer(
        text("offer", user, price=price),
        reply_markup=InlineKeyboardMarkup(
            inline_keyboard=[[InlineKeyboardButton(text=text("subscribe", user, price=price), url=link)]]
        ),
    )


# --- Before Telegram charges ----------------------------------------------------


async def pre_checkout_refusal(query: PreCheckoutQuery) -> str | None:
    """Why this payment must not go through, in the payer's language, or None."""
    settings = get_settings()
    user = query.from_user
    if not settings.sells_premium:
        return text("not_on_sale", user)
    # Only links /premium made for this very account.
    if query.currency != "XTR" or payload_user_id(query.invoice_payload) != user.id:
        return text("invalid", user)

    try:
        status = await premium_status(user.id, timeout=PRE_CHECKOUT_TIMEOUT, **blog_args())
    except PremiumError:
        log.exception("could not ask the blog about Premium before charging %s", user.id)
        return text("unavailable", user)

    if status is None:
        return text("not_author", user)
    if status.blocked:
        return text("blocked", user)
    return None


@router.pre_checkout_query()
async def on_pre_checkout(query: PreCheckoutQuery) -> None:
    refusal = await pre_checkout_refusal(query)
    if refusal is None:
        await query.answer(ok=True)
    else:
        await query.answer(ok=False, error_message=refusal)


# --- After Telegram charged -----------------------------------------------------


async def record_with_retries(telegram_user_id: int, message: Message) -> PremiumStatus | None:
    """The blog's answer; PremiumError once every try has failed."""
    payment = message.successful_payment
    for delay in (*RECORD_RETRY_DELAYS, None):
        try:
            return await record_payment(telegram_user_id, payment, **blog_args())
        except PremiumError:
            if delay is None:
                raise
            log.warning("blog did not take a Premium payment yet; again in %ss", delay, exc_info=True)
            await asyncio.sleep(delay)
    raise AssertionError("unreachable")


@router.message(F.successful_payment, F.chat.type == "private")
async def on_successful_payment(message: Message) -> None:
    payment = message.successful_payment
    user = message.from_user
    if payload_user_id(payment.invoice_payload) is None:
        log.warning("a payment from %s with a payload that is not Premium's: %r", user.id, payment.invoice_payload)
        return
    if not get_settings().records_page_requests:
        await tell_admin(message, payment_notice("⚠️ Premium paid, but the bot has no blog to record it", user, message))
        await message.answer(text("delayed", user))
        return

    try:
        status = await record_with_retries(user.id, message)
    except PremiumError:
        log.exception("could not record a Premium payment from %s", user.id)
        await tell_admin(
            message,
            payment_notice("⚠️ Premium paid, but the blog did not record it. Grant it by hand", user, message),
        )
        await message.answer(text("delayed", user))
        return

    if status is None:
        await refund(message)
        return

    key = "renewed" if payment.is_recurring and not payment.is_first_recurring else "paid"
    await message.answer(text(key, user, date=date_text(status.premium_until)))


async def refund(message: Message) -> None:
    """Pre-checkout let them pay, so they stopped being an author in between."""
    payment = message.successful_payment
    user = message.from_user
    try:
        await message.bot.refund_star_payment(
            user_id=user.id, telegram_payment_charge_id=payment.telegram_payment_charge_id
        )
    except TelegramAPIError:
        log.exception("could not refund a Premium payment from %s", user.id)
        await tell_admin(message, payment_notice("⚠️ Refund failed for a Premium payment from a non-author", user, message))
        await message.answer(text("delayed", user))
        return
    await message.answer(text("refunded", user))


def payment_notice(headline: str, user: User, message: Message) -> str:
    payment = message.successful_payment
    until = (
        datetime.fromtimestamp(payment.subscription_expiration_date, timezone.utc).strftime("%d.%m.%Y %H:%M UTC")
        if payment.subscription_expiration_date
        else "—"
    )
    return (
        f"<b>{html.escape(headline)}</b>\n"
        f"From {who(user)}\n"
        f"{payment.total_amount} {html.escape(payment.currency)}, paid until {until}\n"
        f"Charge <code>{html.escape(payment.telegram_payment_charge_id)}</code>"
    )


# --- What Telegram requires -------------------------------------------------------


@router.message(Command("paysupport"), F.chat.type == "private")
async def on_paysupport(message: Message) -> None:
    user = message.from_user
    problem = (message.text or "").partition(" ")[2].strip()
    if not problem:
        await message.answer(text("support_how", user))
        return

    chat_id = get_settings().admin_chat_id
    if chat_id is None:
        log.error("/paysupport from %s, but ADMIN_CHAT_ID is not set: %s", user.id, problem)
        await message.answer(text("support_unavailable", user))
        return
    try:
        await message.bot.send_message(
            chat_id, f"<b>Payment support</b> from {who(user)}\n\n{html.escape(problem)}"
        )
    except TelegramAPIError:
        log.exception("could not pass /paysupport from %s to ADMIN_CHAT_ID", user.id)
        await message.answer(text("support_unavailable", user))
        return
    await message.answer(text("support_sent", user))


@router.message(Command("terms"), F.chat.type == "private")
async def on_terms(message: Message) -> None:
    await message.answer(text("terms", message.from_user))
