<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/modules/mfa/totp.php';

final class TotpTest extends TestCase
{
    #[Test]
    public function base32_encode_and_decode_round_trip_binary_data(): void
    {
        $raw = "Hello!\x00\xff";
        $encoded = base32_encode($raw);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $encoded);
        $this->assertSame($raw, base32_decode($encoded));
        $this->assertSame($raw, base32_decode(strtolower($encoded) . '=='));
    }

    #[Test]
    public function base32_decode_rejects_invalid_input(): void
    {
        $this->assertFalse(base32_decode('INVALID!'));
    }

    #[Test]
    public function hotp_matches_rfc_4226_test_vector_for_counter_zero(): void
    {
        $secret = base32_decode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        $this->assertNotFalse($secret);
        $this->assertSame('755224', hotp($secret, 0));
    }

    #[Test]
    public function totp_verify_step_accepts_current_step_and_rejects_bad_formats(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $currentStep = intdiv(time(), TOTP_PERIOD);
        $code = hotp((string) base32_decode($secret), $currentStep);

        $matched = totp_verify_step($secret, $code);

        $this->assertIsInt($matched);
        $this->assertGreaterThanOrEqual($currentStep - TOTP_WINDOW, $matched);
        $this->assertLessThanOrEqual($currentStep + TOTP_WINDOW, $matched);
        $this->assertNull(totp_verify_step($secret, 'abc123'));
        $this->assertNull(totp_verify_step('%%%%', '123456'));
    }

    #[Test]
    public function totp_generate_secret_returns_expected_shape(): void
    {
        $secret = totp_generate_secret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }

    #[Test]
    public function totp_get_uri_includes_expected_parameters(): void
    {
        $uri = totp_get_uri('JBSWY3DPEHPK3PXP', 'user@example.com', 'FORMNA');

        $this->assertStringStartsWith('otpauth://totp/FORMNA:user%40example.com?', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=FORMNA', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    #[Test]
    public function recovery_codes_have_expected_format_and_are_unique(): void
    {
        $a = mfa_generate_recovery_code();
        $b = mfa_generate_recovery_code();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}(?:-[A-Z0-9]{4}){3}$/', $a);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}(?:-[A-Z0-9]{4}){3}$/', $b);
        $this->assertNotSame($a, $b);
    }
}