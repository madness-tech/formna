<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../src/modules/mfa/models.php';

final class MfaModelsTest extends DatabaseTestCase
{
    #[Test]
    public function mfa_create_pending_inserts_new_pending_record_with_encrypted_secret(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, confirmed_at FROM user_mfa')
            ? ['rows' => []]
            : null);

        $this->assertTrue(mfa_create_pending(7, 'JBSWY3DPEHPK3PXP'));

        $insertQueries = $this->queriesMatching('INSERT INTO `user_mfa`');
        $this->assertCount(1, $insertQueries);
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $insertQueries[0]['params']['totp_secret']);
        $this->assertNull($insertQueries[0]['params']['confirmed_at']);
        $this->assertNull($insertQueries[0]['params']['recovery_hash']);
    }

    #[Test]
    public function mfa_create_pending_replaces_existing_pending_record_and_clears_stale_state(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, confirmed_at FROM user_mfa')
            ? ['rows' => [['id' => 44, 'confirmed_at' => null]]]
            : null);

        $this->assertTrue(mfa_create_pending(7, 'JBSWY3DPEHPK3PXP'));

        $updateQueries = $this->queriesMatching('UPDATE user_mfa');
        $this->assertCount(1, $updateQueries);
        $this->assertStringContainsString('recovery_hash = NULL', $updateQueries[0]['sql']);
        $this->assertStringContainsString('last_used_step = NULL', $updateQueries[0]['sql']);
    }

    #[Test]
    public function mfa_create_pending_refuses_to_overwrite_confirmed_mfa(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, confirmed_at FROM user_mfa')
            ? ['rows' => [['id' => 44, 'confirmed_at' => now()]]]
            : null);

        $this->assertFalse(mfa_create_pending(7, 'JBSWY3DPEHPK3PXP'));
        $this->assertCount(0, $this->queriesMatching('UPDATE user_mfa'));
    }

    #[Test]
    public function mfa_verify_and_confirm_stores_recovery_hash_and_last_used_step_without_confirming(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $currentStep = intdiv(time(), TOTP_PERIOD);
        $code = hotp((string) base32_decode($secret), $currentStep);
        $encryptedSecret = encrypt_data($secret);

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM user_mfa WHERE user_id = :uid AND confirmed_at IS NULL FOR UPDATE')
            ? ['rows' => [[
                'id' => 55,
                'totp_secret' => $encryptedSecret,
                'last_used_step' => null,
            ]]]
            : null);

        $recovery = mfa_verify_and_confirm(7, $code);

        $this->assertIsString($recovery);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}(?:-[A-Z0-9]{4}){3}$/', $recovery);

        $updateQueries = $this->queriesMatching('UPDATE user_mfa');
        $this->assertCount(1, $updateQueries);
        $normalizedRecovery = strtoupper(str_replace('-', '', $recovery));
        $this->assertTrue(password_verify($normalizedRecovery, $updateQueries[0]['params']['hash']));
        $this->assertSame($currentStep, $updateQueries[0]['params']['last_used_step']);
        $this->assertArrayNotHasKey('confirmed_at', $updateQueries[0]['params']);
    }

    #[Test]
    public function mfa_verify_and_confirm_rejects_replayed_steps(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $currentStep = intdiv(time(), TOTP_PERIOD);
        $code = hotp((string) base32_decode($secret), $currentStep);
        $encryptedSecret = encrypt_data($secret);

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM user_mfa WHERE user_id = :uid AND confirmed_at IS NULL FOR UPDATE')
            ? ['rows' => [[
                'id' => 55,
                'totp_secret' => $encryptedSecret,
                'last_used_step' => $currentStep,
            ]]]
            : null);

        $this->assertNull(mfa_verify_and_confirm(7, $code));
    }

    #[Test]
    public function mfa_finalize_setup_requires_a_verified_pending_record(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, recovery_hash')
            ? ['rows' => [['id' => 60, 'recovery_hash' => 'hashed']]]
            : null);

        $this->assertTrue(mfa_finalize_setup(7));
        $this->assertCount(1, $this->queriesMatching('UPDATE user_mfa SET confirmed_at'));

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, recovery_hash')
            ? ['rows' => [['id' => 60, 'recovery_hash' => null]]]
            : null);
        $this->assertFalse(mfa_finalize_setup(7));
    }

    #[Test]
    public function mfa_verify_totp_once_enforces_replay_protection_via_affected_rows(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $currentStep = intdiv(time(), TOTP_PERIOD);
        $code = hotp((string) base32_decode($secret), $currentStep);

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'UPDATE user_mfa')
            ? ['rows' => [], 'rowCount' => 1]
            : null);
        $this->assertTrue(mfa_verify_totp_once(7, $secret, $code));

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'UPDATE user_mfa')
            ? ['rows' => [], 'rowCount' => 0]
            : null);
        $this->assertFalse(mfa_verify_totp_once(7, $secret, $code));
    }

    #[Test]
    public function mfa_consume_recovery_code_accepts_normalized_codes_and_deletes_record(): void
    {
        $hash = password_hash('ABCD1234EFGH5678', PASSWORD_DEFAULT);
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'SELECT id, recovery_hash FROM user_mfa')
            ? ['rows' => [['id' => 77, 'recovery_hash' => $hash]]]
            : null);

        $this->assertTrue(mfa_consume_recovery_code(7, 'abcd-1234-efgh-5678'));
        $this->assertCount(1, $this->queriesMatching('DELETE FROM user_mfa WHERE id = ?'));
    }

    #[Test]
    public function mfa_required_for_role_uses_cached_site_flags_and_super_admin_override(): void
    {
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM site_settings WHERE id = 1')
            ? ['rows' => [[
                'mfa_enabled_admin' => 1,
                'mfa_enabled_reviewer' => 0,
                'mfa_enabled_user' => 1,
            ]]]
            : null);

        $this->assertTrue(mfa_required_for_role('super_admin'));
        $this->assertTrue(mfa_required_for_role('admin'));
        $this->assertFalse(mfa_required_for_role('reviewer'));
        $this->assertTrue(mfa_required_for_role('user'));

        $GLOBALS['_mfa_role_flags_cache'] = ['mfa_enabled_admin' => 0, 'mfa_enabled_reviewer' => 1, 'mfa_enabled_user' => 0];
        mfa_update_role_flags(true, false, true);
        $this->assertArrayNotHasKey('_mfa_role_flags_cache', $GLOBALS);
    }
}