<?php

/**
 * MFA Module — Database Operations
 *
 * All user_mfa and site_settings MFA-related queries live here.
 */

require_once __DIR__ . '/../../core/encryption.php';
require_once __DIR__ . '/totp.php';

/**
 * Get a user's confirmed MFA record.
 *
 * @return array{id: int, user_id: int, totp_secret: string, confirmed_at: string, recovery_hash: ?string, last_used_step: ?int}|null
 */
function mfa_get_confirmed(int $user_id): ?array
{
    return db_one(
        'SELECT id, user_id, totp_secret, confirmed_at, recovery_hash, last_used_step
         FROM user_mfa
         WHERE user_id = :uid AND confirmed_at IS NOT NULL',
        ['uid' => $user_id]
    );
}

/**
 * Get a user's pending (unconfirmed) MFA record.
 *
 * @return array{id: int, user_id: int, totp_secret: string, last_used_step: ?int}|null
 */
function mfa_get_pending(int $user_id): ?array
{
    return db_one(
        'SELECT id, user_id, totp_secret, last_used_step
         FROM user_mfa
         WHERE user_id = :uid AND confirmed_at IS NULL',
        ['uid' => $user_id]
    );
}

/**
 * Create or replace a pending (unconfirmed) MFA record for a user.
 *
 * If the user already has an unconfirmed row, it is replaced. If the user has
 * a confirmed row, this is a no-op (callers must delete the confirmed row
 * first if doing a change flow).
 *
 * The TOTP secret is encrypted before storage.
 *
 * @param int    $user_id   User ID
 * @param string $secret    Base32-encoded TOTP secret (plaintext)
 * @return bool True if a pending record was created or updated
 */
function mfa_create_pending(int $user_id, string $secret): bool
{
    $encrypted = encrypt_data($secret);
    $now = now();

    return db_transaction(function () use ($user_id, $encrypted, $now): bool {
        // Lock any existing row to prevent race conditions
        $existing = db_one(
            'SELECT id, confirmed_at FROM user_mfa WHERE user_id = :uid FOR UPDATE',
            ['uid' => $user_id]
        );

        if ($existing && $existing['confirmed_at'] !== null) {
            // User already has confirmed MFA — don't overwrite during setup flow.
            // The change flow handles this differently.
            return false;
        }

        if ($existing) {
            // Replace existing pending row.  Clear recovery_hash so a stale
            // hash from a prior interrupted setup cannot satisfy
            // mfa_finalize_setup() for the new secret.
            db_exec(
                'UPDATE user_mfa
                 SET totp_secret = :secret, recovery_hash = NULL,
                     last_used_step = NULL, updated_at = :now
                 WHERE id = :id',
                ['secret' => $encrypted, 'now' => $now, 'id' => $existing['id']]
            );
        } else {
            db_insert('user_mfa', [
                'user_id'     => $user_id,
                'totp_secret' => $encrypted,
                'confirmed_at' => null,
                'recovery_hash' => null,
                'last_used_step' => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        return true;
    });
}

/**
 * Verify a setup TOTP code and prepare the MFA record for confirmation.
 *
 * This verifies the TOTP code, generates a recovery code, and stores its
 * hash, but does NOT set confirmed_at. The record remains pending until
 * mfa_finalize_setup() is called after the user acknowledges the recovery
 * code. This prevents a scenario where MFA is confirmed but the user never
 * sees their recovery code (e.g., session loss between redirect hops).
 *
 * @param int    $user_id User ID
 * @param string $code    TOTP code submitted by user
 * @return string|null Plaintext recovery code if verified, or null on failure
 */
function mfa_verify_and_confirm(int $user_id, string $code): ?string
{
    $now = now();

    return db_transaction(function () use ($user_id, $code, $now): ?string {
        $row = db_one(
            'SELECT id, totp_secret, last_used_step
             FROM user_mfa WHERE user_id = :uid AND confirmed_at IS NULL FOR UPDATE',
            ['uid' => $user_id]
        );

        if (!$row) {
            return null;
        }

        $secret = decrypt_data($row['totp_secret']);

        $step = totp_verify_step($secret, $code);
        if ($step === null) {
            return null;
        }

        if ($row['last_used_step'] !== null && $step <= (int)$row['last_used_step']) {
            return null;
        }

        $recovery_plain = mfa_generate_recovery_code();
        $recovery_normalized = strtoupper(str_replace('-', '', $recovery_plain));
        $recovery_hash = password_hash($recovery_normalized, PASSWORD_DEFAULT);

        // Store recovery hash and step, but leave confirmed_at NULL.
        // mfa_finalize_setup() will set confirmed_at once the user
        // acknowledges saving their recovery code.
        db_exec(
            'UPDATE user_mfa
             SET recovery_hash = :hash,
                 last_used_step = :last_used_step, updated_at = :now
             WHERE id = :id',
            [
                'hash'           => $recovery_hash,
                'last_used_step' => $step,
                'now'            => $now,
                'id'             => $row['id'],
            ]
        );

        return $recovery_plain;
    });
}

/**
 * Finalize MFA setup by setting confirmed_at on a verified pending record.
 *
 * Only succeeds if the record exists, is still pending (confirmed_at IS NULL),
 * and already has a recovery_hash (meaning the TOTP was verified).
 *
 * @param int $user_id User ID
 * @return bool True if the record was confirmed
 */
function mfa_finalize_setup(int $user_id): bool
{
    $now = now();

    return db_transaction(function () use ($user_id, $now): bool {
        $row = db_one(
            'SELECT id, recovery_hash
             FROM user_mfa
             WHERE user_id = :uid AND confirmed_at IS NULL
             FOR UPDATE',
            ['uid' => $user_id]
        );

        if (!$row || !$row['recovery_hash']) {
            return false;
        }

        db_exec(
            'UPDATE user_mfa SET confirmed_at = :confirmed, updated_at = :now WHERE id = :id',
            ['confirmed' => $now, 'now' => $now, 'id' => $row['id']]
        );

        return true;
    });
}

/**
 * Delete all MFA records for a user (admin reset or recovery code consumption).
 */
function mfa_delete(int $user_id): void
{
    db_exec('DELETE FROM user_mfa WHERE user_id = ?', [$user_id]);
}

/**
 * Atomically replace a confirmed MFA record with a new one (user-initiated change).
 *
 * Uses SELECT FOR UPDATE to prevent TOCTOU race conditions.
 *
 * @param int    $user_id          User ID
 * @param string $new_secret       New base32-encoded TOTP secret (plaintext)
 * @param string $new_recovery_hash bcrypt hash of the new recovery code
 * @return bool  True if replacement succeeded
 */
function mfa_replace(int $user_id, string $new_secret, string $new_recovery_hash): bool
{
    $encrypted = encrypt_data($new_secret);
    $now = now();

    return db_transaction(function () use ($user_id, $encrypted, $new_recovery_hash, $now): bool {
        $existing = db_one(
            'SELECT id FROM user_mfa WHERE user_id = :uid FOR UPDATE',
            ['uid' => $user_id]
        );

        if (!$existing) {
            return false;
        }

        db_exec(
            'UPDATE user_mfa
             SET totp_secret = :secret, confirmed_at = :confirmed,
                 recovery_hash = :hash, last_used_step = NULL, updated_at = :now
             WHERE id = :id',
            [
                'secret'    => $encrypted,
                'confirmed' => $now,
                'hash'      => $new_recovery_hash,
                'now'       => $now,
                'id'        => $existing['id'],
            ]
        );

        return true;
    });
}

/**
 * Atomically verify a TOTP code with replay protection.
 */
function mfa_verify_totp_once(int $user_id, string $secret, string $code): bool
{
    $step = totp_verify_step($secret, $code);
    if ($step === null) {
        return false;
    }

    $affected = db_exec(
        'UPDATE user_mfa
         SET last_used_step = :step, updated_at = :now
         WHERE user_id = :uid
           AND confirmed_at IS NOT NULL
           AND (last_used_step IS NULL OR last_used_step < :step_guard)',
        [
            'step'       => $step,
            'step_guard' => $step,
            'now'        => now(),
            'uid'        => $user_id,
        ]
    );

    return $affected > 0;
}

/**
 * Consume the recovery code: verify it, then delete the MFA record.
 *
 * @param int    $user_id User ID
 * @param string $code    Plaintext recovery code submitted by user
 * @return bool  True if recovery code was valid and MFA was deleted
 */
function mfa_consume_recovery_code(int $user_id, string $code): bool
{
    return db_transaction(function () use ($user_id, $code): bool {
        $row = db_one(
            'SELECT id, recovery_hash FROM user_mfa
             WHERE user_id = :uid AND confirmed_at IS NOT NULL
             FOR UPDATE',
            ['uid' => $user_id]
        );

        // Normalize first so both branches below call password_verify with
        // the same input type — eliminating any timing delta that would let
        // an attacker distinguish "no MFA record" from "wrong code".
        $normalized = strtoupper(str_replace('-', '', trim($code)));

        if (!$row || !$row['recovery_hash']) {
            // No record: spend the same bcrypt time as a real failed verify.
            burn_password_verify_time();
            return false;
        }

        $stored_hash = $row['recovery_hash'];

        if (!password_verify($normalized, $stored_hash)) {
            return false;
        }

        // Valid recovery code — delete the MFA record entirely.
        // User will be forced to re-setup MFA on next login.
        db_exec('DELETE FROM user_mfa WHERE id = ?', [$row['id']]);

        return true;
    });
}

// ─── Site Settings: MFA Flags ────────────────────────────────────────────────

/**
 * Check if MFA is required for a given role.
 *
 * Super admins always require MFA (hardcoded). Other roles depend on the
 * site_settings toggles.
 */
function mfa_required_for_role(string $role): bool
{
    if ($role === 'super_admin') {
        return true;
    }

    $flags = mfa_get_role_flags();

    return match ($role) {
        'admin'    => (bool)$flags['mfa_enabled_admin'],
        'reviewer' => (bool)$flags['mfa_enabled_reviewer'],
        'user'     => (bool)$flags['mfa_enabled_user'],
        default    => false,
    };
}

/**
 * Get MFA flags from site_settings (cached per request).
 *
 * Uses $GLOBALS so the cache can be busted by mfa_update_role_flags().
 *
 * @return array{mfa_enabled_admin: int, mfa_enabled_reviewer: int, mfa_enabled_user: int}
 */
function mfa_get_role_flags(): array
{
    if (isset($GLOBALS['_mfa_role_flags_cache'])) {
        return $GLOBALS['_mfa_role_flags_cache'];
    }

    $row = db_one(
        'SELECT mfa_enabled_admin, mfa_enabled_reviewer, mfa_enabled_user
         FROM site_settings WHERE id = 1'
    );

    $GLOBALS['_mfa_role_flags_cache'] = $row ?: [
        'mfa_enabled_admin'    => 0,
        'mfa_enabled_reviewer' => 0,
        'mfa_enabled_user'     => 0,
    ];

    return $GLOBALS['_mfa_role_flags_cache'];
}

/**
 * Update MFA role flags in site_settings.
 *
 * @param bool $admin    Enable MFA for admins
 * @param bool $reviewer Enable MFA for reviewers
 * @param bool $user     Enable MFA for users
 */
function mfa_update_role_flags(bool $admin, bool $reviewer, bool $user): void
{
    db_update('site_settings', [
        'mfa_enabled_admin'    => $admin ? 1 : 0,
        'mfa_enabled_reviewer' => $reviewer ? 1 : 0,
        'mfa_enabled_user'     => $user ? 1 : 0,
        'updated_at'           => now(),
    ], 'id = ?', [1]);

    unset($GLOBALS['_mfa_role_flags_cache']);
}

/**
 * Check whether a user has confirmed MFA.
 */
function mfa_user_has_confirmed(int $user_id): bool
{
    $row = db_one(
        'SELECT 1 FROM user_mfa WHERE user_id = :uid AND confirmed_at IS NOT NULL',
        ['uid' => $user_id]
    );
    return $row !== null;
}
