<?php
/**
 * Encrypts/decrypts the TOTP (authenticator) secret so it is never stored
 * in plaintext in the database. Uses libsodium's secretbox (XSalsa20-Poly1305),
 * built into PHP 7.2+.
 *
 * Requires an env var TOTP_ENCRYPTION_KEY containing a base64-encoded
 * 32-byte key, generated once via:
 *   php -r "echo base64_encode(random_bytes(32));"
 *
 * IMPORTANT: this key must live outside the database and outside git
 * (e.g. in a local .env file that is in .gitignore, or your host's
 * environment variable settings). Losing this key means every stored
 * 2FA secret becomes permanently undecryptable, and every user will need
 * to re-enroll in 2FA.
 */

function totp_encryption_key(): string
{
    $key = getenv('TOTP_ENCRYPTION_KEY');

    if (empty($key)) {
        throw new RuntimeException(
            'TOTP_ENCRYPTION_KEY is not set. Generate one with: ' .
            'php -r "echo base64_encode(random_bytes(32));"'
        );
    }

    $decoded = base64_decode($key, true);

    if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('TOTP_ENCRYPTION_KEY is invalid (must decode to 32 bytes).');
    }

    return $decoded;
}

function encrypt_secret(string $plaintext): string
{
    $key = totp_encryption_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

    return base64_encode($nonce . $ciphertext);
}

function decrypt_secret(string $stored): string
{
    $key = totp_encryption_key();
    $decoded = base64_decode($stored, true);

    if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Stored TOTP secret is malformed.');
    }

    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

    if ($plaintext === false) {
        throw new RuntimeException('Failed to decrypt TOTP secret — wrong key or data tampered with.');
    }

    return $plaintext;
}
