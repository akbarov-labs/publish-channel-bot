from __future__ import annotations

import pytest

from app.services import sync
from app.services.sync import channel_post_to_site
from app.services.telegraph import (
    TelegraphError,
    find_telegraph_url,
    html_to_nodes,
    nodes_to_html,
    page_path,
)
from tests.factories import StubBot, StubMessage, StubPhoto, StubSiteClient, make_channel


def test_headings_are_downgraded_to_the_supported_set():
    """telegra.ph has no h1/h2 - createPage rejects them."""
    nodes = html_to_nodes("<h1>Big</h1><h2>Also big</h2>")
    assert [node["tag"] for node in nodes] == ["h3", "h3"]


def test_unsupported_tags_are_dropped_but_their_text_is_kept():
    nodes = html_to_nodes("<div><span>kept</span></div>")
    assert "kept" in nodes_to_html(nodes)
    assert "span" not in nodes_to_html(nodes)


def test_content_survives_a_round_trip():
    html = '<p>Hello <b>bold</b> and <a href="https://x.test">link</a></p>'
    assert nodes_to_html(html_to_nodes(html)) == html


def test_only_href_and_src_attributes_are_forwarded():
    nodes = html_to_nodes('<a href="https://x.test" onclick="evil()">go</a>')
    assert nodes[0]["attrs"] == {"href": "https://x.test"}


def test_urls_are_recognised_on_both_domains():
    assert find_telegraph_url("see https://telegra.ph/Post-09-19 ok") == "https://telegra.ph/Post-09-19"
    assert find_telegraph_url("see https://graph.org/Post-09-19.") == "https://graph.org/Post-09-19"
    assert find_telegraph_url("no link here") is None


def test_a_foreign_url_is_rejected():
    with pytest.raises(TelegraphError):
        page_path("https://evil.test/Post-09-19")


async def test_a_telegraph_link_becomes_the_full_article(session, monkeypatch):
    channel = await make_channel(session)
    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda site: client)

    async def fake_fetch(url):
        return "The Real Title", "<p>The full article body.</p>"

    monkeypatch.setattr(sync, "fetch_article", fake_fetch)
    sync.recently_sent._seen.clear()

    message = StubMessage(message_id=10, text="Teaser https://telegra.ph/Post-09-19")
    await channel_post_to_site(session, StubBot(), channel, [message])

    created = client.created[0]
    assert created["title"] == "The Real Title"
    assert created["html"] == "<p>The full article body.</p>"
    assert created["telegraph_url"] == "https://telegra.ph/Post-09-19"


async def test_an_unreachable_telegraph_page_falls_back_to_the_message(session, monkeypatch):
    channel = await make_channel(session)
    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda site: client)

    async def boom(url):
        raise TelegraphError("404")

    monkeypatch.setattr(sync, "fetch_article", boom)
    sync.recently_sent._seen.clear()

    message = StubMessage(message_id=11, text="Teaser https://telegra.ph/Gone-09-19")
    await channel_post_to_site(session, StubBot(), channel, [message])

    assert client.created[0]["title"] == "Teaser https://telegra.ph/Gone-09-19"


async def test_a_photo_album_becomes_a_carousel_post(session, monkeypatch):
    channel = await make_channel(session)
    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda site: client)
    sync.recently_sent._seen.clear()

    album = [
        StubMessage(message_id=20, caption="Trip photos", photo=[StubPhoto("a", "ua")]),
        StubMessage(message_id=21, photo=[StubPhoto("b", "ub")]),
        StubMessage(message_id=22, photo=[StubPhoto("c", "uc")]),
    ]
    await channel_post_to_site(session, StubBot(), channel, album)

    created = client.created[0]
    assert created["post_type"] == "gallery"
    assert len(created["media"]) == 3, "every image in the album must reach the carousel"
    assert [item["type"] for item in created["media"]] == ["photo", "photo", "photo"]


async def test_a_single_photo_stays_a_normal_article(session, monkeypatch):
    channel = await make_channel(session)
    client = StubSiteClient()
    monkeypatch.setattr(sync, "site_client_for", lambda site: client)
    sync.recently_sent._seen.clear()

    message = StubMessage(message_id=30, caption="One shot", photo=[StubPhoto()])
    await channel_post_to_site(session, StubBot(), channel, [message])

    assert client.created[0]["post_type"] == "article"
