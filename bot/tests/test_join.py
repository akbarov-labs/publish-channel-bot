from aiogram.types import User

from app.bot.handlers.private import JOIN_REPLY, join_notice, join_reply
from app.config import Settings

BASE = {"bot_token": "1:x", "secret_key": "k"}


def test_reply_follows_the_users_telegram_language():
    assert join_reply("ru") == JOIN_REPLY["ru"]
    assert join_reply("uz") == JOIN_REPLY["uz"]
    assert join_reply("en-GB") == JOIN_REPLY["en"]


def test_reply_falls_back_to_english():
    assert join_reply(None) == JOIN_REPLY["en"]
    assert join_reply("de") == JOIN_REPLY["en"]


def test_notice_links_the_user_and_escapes_their_name():
    user = User(id=42, is_bot=False, first_name="<Ali>", last_name="& Co", username="ali", language_code="uz")
    notice = join_notice(user)
    assert '<a href="tg://user?id=42">&lt;Ali&gt; &amp; Co</a>' in notice
    assert "(@ali)" in notice
    assert "<code>42</code>" in notice


def test_notice_without_a_username():
    user = User(id=7, is_bot=False, first_name="Vali")
    assert "(@" not in join_notice(user)


def test_blank_admin_chat_id_means_unset():
    assert Settings(**BASE, admin_chat_id="").admin_chat_id is None
    assert Settings(**BASE, admin_chat_id="-1001234").admin_chat_id == -1001234
