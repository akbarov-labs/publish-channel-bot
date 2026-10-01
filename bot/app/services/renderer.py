"""Conversions between Telegram's message HTML and a blog's article HTML."""

from __future__ import annotations

import re
from html import escape
from html.parser import HTMLParser

# The complete set of tags Telegram's HTML parse_mode accepts.
TELEGRAM_TAGS = {
    "b", "strong", "i", "em", "u", "ins", "s", "strike", "del",
    "a", "code", "pre", "blockquote", "tg-spoiler", "span",
}
# Block-level tags whose boundaries become newlines in a Telegram message.
BLOCK_TAGS = {"p", "div", "section", "article", "ul", "ol", "li", "h1", "h2", "h3", "h4", "h5", "h6"}
HEADING_TAGS = {"h1", "h2", "h3", "h4", "h5", "h6"}


def telegram_html_to_article(html: str) -> str:
    """Telegram message HTML -> article HTML the site can store.

    Telegram sends one flat blob with \\n line breaks; blogs want paragraphs.
    """
    normalized = html.replace("<tg-spoiler>", '<span class="spoiler">').replace(
        "</tg-spoiler>", "</span>"
    )
    paragraphs = [p.strip() for p in re.split(r"\n{2,}", normalized) if p.strip()]
    return "\n".join(f"<p>{p.replace(chr(10), '<br>')}</p>" for p in paragraphs)


def derive_title(text: str, fallback: str = "Untitled") -> str:
    """Use the first non-empty line as the post title, as most channel posts do."""
    for line in text.splitlines():
        stripped = re.sub(r"<[^>]+>", "", line).strip()
        if stripped:
            return stripped[:200]
    return fallback


class _TelegramSanitizer(HTMLParser):
    """Reduces arbitrary article HTML to the subset Telegram will render.

    Stops emitting once the character budget is spent and closes whatever tags
    are still open, so the output is never truncated mid-tag.
    """

    def __init__(self, budget: int) -> None:
        super().__init__(convert_charrefs=True)
        self.budget = budget
        self.out: list[str] = []
        self.open_tags: list[str] = []
        self.used = 0
        self.truncated = False

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if self.truncated:
            return
        if tag == "br":
            self._newline()
        elif tag in HEADING_TAGS:
            self._newline(2)
            self.out.append("<b>")
            self.open_tags.append("b")
        elif tag == "li":
            self._newline()
            self._emit_text("• ")
        elif tag in BLOCK_TAGS:
            self._newline(2)
        elif tag == "a":
            href = dict(attrs).get("href")
            if href:
                self.out.append(f'<a href="{escape(href, quote=True)}">')
                self.open_tags.append("a")
        elif tag in TELEGRAM_TAGS:
            self.out.append(f"<{tag}>")
            self.open_tags.append(tag)

    def handle_endtag(self, tag: str) -> None:
        if self.truncated:
            return
        closing = "b" if tag in HEADING_TAGS else tag
        if closing in self.open_tags:
            # Close everything opened after it too, so nesting stays valid.
            while self.open_tags:
                current = self.open_tags.pop()
                self.out.append(f"</{current}>")
                if current == closing:
                    break
        if tag == "li":
            self._newline(1)
        elif tag in BLOCK_TAGS:
            self._newline(2)

    def handle_data(self, data: str) -> None:
        if self.truncated:
            return
        self._emit_text(re.sub(r"\s+", " ", data))

    def _emit_text(self, text: str) -> None:
        if not text:
            return
        remaining = self.budget - self.used
        if len(text) > remaining:
            self.out.append(escape(text[:remaining].rstrip()))
            self.used = self.budget
            self.truncated = True
            return
        self.out.append(escape(text))
        self.used += len(text)

    def _newline(self, count: int = 1) -> None:
        rendered = "".join(self.out)
        if not rendered or rendered.endswith("\n" * count):
            return
        self.out.append("\n" * count)

    def result(self) -> tuple[str, bool]:
        while self.open_tags:
            self.out.append(f"</{self.open_tags.pop()}>")
        text = re.sub(r"\n{3,}", "\n\n", "".join(self.out)).strip()
        return text, self.truncated


def article_html_to_telegram(html: str, budget: int = 700) -> tuple[str, bool]:
    """Article HTML -> (Telegram-safe HTML, was_truncated)."""
    parser = _TelegramSanitizer(budget)
    parser.feed(html or "")
    parser.close()
    return parser.result()


def build_channel_post(
    title: str,
    body_html: str,
    url: str,
    budget: int = 700,
    instant_view_url: str | None = None,
) -> str:
    """The message body published to the channel when a site post goes live."""
    body, truncated = article_html_to_telegram(body_html, budget)
    parts = [f"<b>{escape(title)}</b>"]
    if body:
        parts.append(body)

    links = []
    # The telegra.ph link goes first: Telegram builds the preview, and therefore
    # the Instant View button, from the first link in the message.
    if instant_view_url:
        links.append(f'<a href="{escape(instant_view_url, quote=True)}">Read in Telegram →</a>')
    if url:
        label = "Read more →" if truncated and not instant_view_url else "Open on the blog →"
        links.append(f'<a href="{escape(url, quote=True)}">{label}</a>')
    if links:
        parts.append("  ·  ".join(links))
    return "\n\n".join(parts)
