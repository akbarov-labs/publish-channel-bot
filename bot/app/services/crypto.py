import hashlib
import hmac
import secrets

from cryptography.fernet import Fernet

from app.config import get_settings


def _fernet() -> Fernet:
    return Fernet(get_settings().secret_key.encode())


def encrypt(value: str) -> bytes:
    return _fernet().encrypt(value.encode())


def decrypt(value: bytes) -> str:
    return _fernet().decrypt(value).decode()


def new_webhook_secret() -> str:
    return secrets.token_urlsafe(32)[:64]


def sign(secret: str, raw_body: bytes) -> str:
    return hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()


def verify_signature(secret: str, raw_body: bytes, provided: str | None) -> bool:
    if not provided:
        return False
    expected = sign(secret, raw_body)
    # Strip an optional "sha256=" prefix, which many CMS webhook senders add.
    candidate = provided.split("=", 1)[1] if provided.startswith("sha256=") else provided
    return hmac.compare_digest(expected, candidate)


def content_hash(*parts: str) -> str:
    digest = hashlib.sha256()
    for part in parts:
        digest.update(part.encode())
        digest.update(b"\x00")
    return digest.hexdigest()
