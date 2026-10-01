from __future__ import annotations

from app.services.renderer import (
    article_html_to_telegram,
    build_channel_post,
    derive_title,
    telegram_html_to_article,
)


def test_headings_and_lists_become_telegram_markup():
    html = "<h2>Intro</h2><p>Body <b>bold</b></p><ul><li>one</li><li>two</li></ul>"
    text, truncated = article_html_to_telegram(html, budget=500)
    assert "<b>Intro</b>" in text
    assert "• one\n• two" in text
    assert not truncated


def test_unsupported_tags_are_dropped_but_text_survives():
    text, _ = article_html_to_telegram('<figure><img src="x.png"><figcaption>Cap</figcaption></figure>', 500)
    assert "<img" not in text and "figure" not in text
    assert "Cap" in text


def test_truncation_never_leaves_an_open_tag():
    text, truncated = article_html_to_telegram("<p>Some <b>bold text that runs on</b> forever</p>", budget=20)
    assert truncated
    assert text.count("<b>") == text.count("</b>")


def test_links_are_preserved_with_escaped_href():
    text, _ = article_html_to_telegram('<p><a href="https://x.test/a?b=1&c=2">go</a></p>', 500)
    assert 'href="https://x.test/a?b=1&amp;c=2"' in text


def test_channel_post_links_out_and_flags_truncation():
    short = build_channel_post("Title", "<p>tiny</p>", "https://blog.test/p/1", budget=500)
    long = build_channel_post("Title", "<p>" + "word " * 200 + "</p>", "https://blog.test/p/1", budget=50)
    assert "Open on the blog" in short
    assert "Read more" in long
    assert "https://blog.test/p/1" in short


def test_instant_view_link_comes_first():
    """Telegram builds the preview from the first link, so telegra.ph leads."""
    text = build_channel_post(
        "Title", "<p>body</p>", "https://blog.test/p/1",
        instant_view_url="https://telegra.ph/Title-09-19",
    )
    assert text.index("telegra.ph") < text.index("blog.test")
    assert "Read in Telegram" in text


def test_telegram_text_becomes_paragraphs():
    assert telegram_html_to_article("one\ntwo\n\nthree") == "<p>one<br>two</p>\n<p>three</p>"


def test_spoilers_are_translated_for_the_blog():
    assert 'class="spoiler"' in telegram_html_to_article("<tg-spoiler>hidden</tg-spoiler>")


def test_title_comes_from_the_first_line_without_markup():
    assert derive_title("<b>My Post</b>\nrest of the body") == "My Post"
    assert derive_title("   ", fallback="Untitled") == "Untitled"
