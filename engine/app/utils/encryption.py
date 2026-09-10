"""
Encryption utilities for sensitive data storage.
Uses Fernet symmetric encryption for securing API keys and secrets.
"""
import os
import base64
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
from loguru import logger


def get_encryption_key() -> bytes:
    """
    Get or create encryption key from environment.
    Uses ENCRYPTION_KEY env var or generates from SECRET_KEY.
    """
    # Try to get existing encryption key
    key = os.environ.get("ENCRYPTION_KEY")
    if key:
        return key.encode()
    
    # Generate key from SECRET_KEY if available
    secret = os.environ.get("SECRET_KEY", "tailadmin-voice-core-default-key")
    
    # Use PBKDF2 to derive a proper Fernet key
    kdf = PBKDF2HMAC(
        algorithm=hashes.SHA256(),
        length=32,
        salt=b"tailadmin-voice-core-salt",  # Fixed salt for consistent encryption
        iterations=100000,
    )
    key = base64.urlsafe_b64encode(kdf.derive(secret.encode()))
    return key


def encrypt_value(value: str) -> str:
    """
    Encrypt a string value.
    Returns base64-encoded encrypted string.
    """
    if not value:
        return ""
    
    try:
        key = get_encryption_key()
        f = Fernet(key)
        encrypted = f.encrypt(value.encode())
        return base64.urlsafe_b64encode(encrypted).decode()
    except Exception as e:
        logger.error(f"Encryption failed: {e}")
        raise


def decrypt_value(encrypted_value: str) -> str:
    """
    Decrypt an encrypted string value.
    Expects base64-encoded encrypted string.
    """
    if not encrypted_value:
        return ""
    
    try:
        key = get_encryption_key()
        f = Fernet(key)
        # Decode from base64 first
        encrypted_bytes = base64.urlsafe_b64decode(encrypted_value.encode())
        decrypted = f.decrypt(encrypted_bytes)
        return decrypted.decode()
    except Exception as e:
        logger.error(f"Decryption failed: {e}")
        raise


def is_encrypted(value: str) -> bool:
    """
    Check if a value appears to be encrypted.
    Encrypted values start with 'gAAAA' (Fernet magic bytes in base64).
    """
    if not value:
        return False
    try:
        # Try to decode as base64 and check for Fernet magic bytes
        decoded = base64.urlsafe_b64decode(value.encode())
        return decoded.startswith(b'gAAAA')
    except Exception:
        return False
