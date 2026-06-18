<?php

/**
 * TOTP (Time-Based One-Time Password) — Pure PHP implementation
 *
 * Implements RFC 6238 (TOTP) and RFC 4226 (HOTP) using HMAC-SHA1.
 * Compatible with Google Authenticator, Authy, and similar apps.
 *
 * No third-party dependencies required.
 */

/** Base32 alphabet used for TOTP secret encoding */
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** TOTP code length (6 digits is standard) */
const TOTP_DIGITS = 6;

/** TOTP time step in seconds (30s is standard) */
const TOTP_PERIOD = 30;

/** Clock drift tolerance: ±1 window (allows codes from t-30s to t+30s) */
const TOTP_WINDOW = 1;

/**
 * Generate a cryptographically secure random TOTP secret.
 *
 * @param int $bytes Number of random bytes (20 = 160-bit, recommended by RFC)
 * @return string Base32-encoded secret
 */
function totp_generate_secret(int $bytes = 20): string
{
    $raw = random_bytes($bytes);
    return base32_encode($raw);
}

/**
 * Generate the otpauth:// URI for QR code scanning.
 *
 * @param string $secret Base32-encoded secret
 * @param string $email  User's email (used as the account label)
 * @param string $issuer Application name shown in authenticator apps
 * @return string otpauth:// URI
 */
function totp_get_uri(string $secret, string $email, string $issuer): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($email);
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => TOTP_DIGITS,
        'period' => TOTP_PERIOD,
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $label . '?' . $params;
}

/**
 * Verify a TOTP code against a secret and return the matched time step.
 *
 * Checks the current time step and ±TOTP_WINDOW adjacent steps to
 * tolerate minor clock drift between server and authenticator device.
 *
 * Uses timing-safe comparison to prevent side-channel leakage.
 *
 * @param string $secret Base32-encoded secret
 * @param string $code   User-submitted 6-digit code
 * @return int|null Matched time step if valid, otherwise null
 */
function totp_verify_step(string $secret, string $code): ?int
{
    // Reject obviously wrong input before doing any crypto work
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) {
        return null;
    }

    $raw_secret = base32_decode($secret);
    if ($raw_secret === false) {
        return null;
    }

    $current_step = intdiv(time(), TOTP_PERIOD);

    for ($offset = -TOTP_WINDOW; $offset <= TOTP_WINDOW; $offset++) {
        $step = $current_step + $offset;
        $expected = hotp($raw_secret, $step);
        if (hash_equals($expected, $code)) {
            return $step;
        }
    }

    return null;
}

/**
 * Compute an HOTP code (RFC 4226) for a given counter value.
 *
 * @param string $secret Raw binary secret (not base32)
 * @param int    $counter 8-byte counter value
 * @return string Zero-padded numeric code
 */
function hotp(string $secret, int $counter): string
{
    // Pack counter as 8-byte big-endian
    $counter_bytes = pack('J', $counter);

    // HMAC-SHA1
    $hash = hash_hmac('sha1', $counter_bytes, $secret, true);

    // Dynamic truncation (RFC 4226 §5.4)
    $offset = ord($hash[19]) & 0x0F;
    $binary = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );

    $otp = $binary % (10 ** TOTP_DIGITS);

    return str_pad((string)$otp, TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Base32 encode raw bytes (RFC 4648, no padding).
 *
 * @param string $data Raw binary data
 * @return string Base32-encoded string (uppercase, no padding)
 */
function base32_encode(string $data): string
{
    $binary = '';
    foreach (str_split($data) as $byte) {
        $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($binary, 5) as $chunk) {
        // Pad the last chunk to 5 bits if needed
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $result .= BASE32_ALPHABET[bindec($chunk)];
    }

    return $result;
}

/**
 * Base32 decode a string to raw bytes (RFC 4648, tolerates no-padding).
 *
 * @param string $encoded Base32-encoded string
 * @return string|false Raw binary data, or false on invalid input
 */
function base32_decode(string $encoded): string|false
{
    $encoded = strtoupper(rtrim($encoded, '='));

    if ($encoded === '') {
        return '';
    }

    // Validate characters
    if (preg_match('/[^A-Z2-7]/', $encoded)) {
        return false;
    }

    $binary = '';
    foreach (str_split($encoded) as $char) {
        $index = strpos(BASE32_ALPHABET, $char);
        if ($index === false) {
            return false;
        }
        $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($binary, 8) as $byte) {
        if (strlen($byte) < 8) {
            break; // Discard incomplete trailing bits
        }
        $result .= chr(bindec($byte));
    }

    return $result;
}

/**
 * Generate a human-readable recovery code.
 *
 * Format: XXXX-XXXX-XXXX-XXXX (16 base36 chars, grouped)
 *
 * Each character is selected from random_bytes() with rejection sampling
 * to avoid modulo bias (accepts byte values < 252, the largest multiple
 * of 36 that fits in a byte). This preserves the full ~82.7 bits of
 * entropy (16 × log2(36)) without the precision loss that PHP's
 * base_convert() introduces via its internal float conversion.
 *
 * @return string Plaintext recovery code
 */
function mfa_generate_recovery_code(): string
{
    $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // 36 chars
    $limit    = 252; // floor(256 / 36) * 36 — reject bytes >= 252 to avoid modulo bias
    $code     = '';

    while (strlen($code) < 16) {
        $batch = random_bytes(24); // ~98.4% acceptance rate → 24 bytes yields >= 16 usable
        for ($i = 0, $len = strlen($batch); $i < $len && strlen($code) < 16; $i++) {
            $byte = ord($batch[$i]);
            if ($byte < $limit) {
                $code .= $alphabet[$byte % 36];
            }
        }
    }

    return implode('-', str_split($code, 4));
}
