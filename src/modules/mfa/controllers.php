<?php

/**
 * MFA Module — Controllers
 *
 * Route handlers for TOTP verification, setup, user-initiated change,
 * admin reset, and super-admin MFA settings.
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../users/models.php';
require_once __DIR__ . '/../account/models.php';

// ─── Rate Limit Constants ────────────────────────────────────────────────────

const MFA_VERIFY_MAX_ATTEMPTS     = 5;
const MFA_VERIFY_WINDOW_MINUTES   = 15;
const MFA_RECOVERY_MAX_ATTEMPTS   = 3;
const MFA_RECOVERY_WINDOW_MINUTES = 60;
const MFA_SETUP_MAX_ATTEMPTS      = 5;
const MFA_SETUP_WINDOW_MINUTES    = 60;
const MFA_CHANGE_AUTH_MAX         = 3;
const MFA_CHANGE_AUTH_MINUTES     = 60;
const MFA_CHANGE_VERIFY_MAX       = 5;
const MFA_CHANGE_VERIFY_MINUTES   = 15;
const MFA_ENABLE_AUTH_MAX         = 3;
const MFA_ENABLE_AUTH_MINUTES     = 60;
const MFA_ADMIN_RESET_MAX         = 3;
const MFA_ADMIN_RESET_MINUTES     = 60;

// ═══════════════════════════════════════════════════════════════════════════════
// FLOW 1: TOTP Verification (pre-login)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * GET /mfa/verify — Show TOTP entry form
 */
function mfa_show_verify(): void
{
    mfa_require_pending();
    require __DIR__ . '/views/verify.php';
}

/**
 * POST /mfa/verify — Verify TOTP code
 */
function mfa_do_verify(): void
{
    csrf_check();
    $uid = mfa_require_pending();

    $code = trim($_POST['code'] ?? '');
    $is_recovery = !empty($_POST['use_recovery']);

    if ($is_recovery) {
        // Recovery code path — stricter rate limit
        $rl = check_user_action_rate_limit(
            $uid, 'mfa_recovery',
            MFA_RECOVERY_MAX_ATTEMPTS, MFA_RECOVERY_WINDOW_MINUTES, true
        );
        if ($rl) {
            flash('error', $rl);
            redirect('/mfa/verify');
        }

        if (mfa_consume_recovery_code($uid, $code)) {
            // Recovery code was valid; MFA record deleted.
            // Complete login — if MFA is required for the user's role, they'll
            // be prompted to set up a new authenticator on their next login.
            $user = find_user_by_id($uid);
            if (!$user || $user['status'] !== 'active') {
                unset($_SESSION['_mfa_pending_uid']);
                flash('error', t('mfa.account_unavailable'));
                redirect('/login');
            }

            unset($_SESSION['_mfa_pending_uid']);
            login_user($user);
            log_audit('mfa_recovery_used', 'user', $uid, null, $user['uuid']);
            flash('success', t('mfa.recovery_accepted'));

            $redirect = $_SESSION['_redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['_redirect_after_login']);
            if (!is_safe_redirect($redirect)) {
                $redirect = '/dashboard';
            }
            redirect($redirect);
        }

        flash('error', t('mfa.invalid_recovery_code'));
        redirect('/mfa/verify');
    }

    // Standard TOTP code path
    $rl = check_user_action_rate_limit(
        $uid, 'mfa_verify',
        MFA_VERIFY_MAX_ATTEMPTS, MFA_VERIFY_WINDOW_MINUTES, true
    );
    if ($rl) {
        flash('error', $rl);
        redirect('/mfa/verify');
    }

    $mfa = mfa_get_confirmed($uid);
    if (!$mfa) {
        // MFA row disappeared (admin reset while user on verify page)
        unset($_SESSION['_mfa_pending_uid']);
        flash('error', t('mfa.config_not_found'));
        redirect('/login');
    }

    $secret = decrypt_data($mfa['totp_secret']);

    if (!mfa_verify_totp_once($uid, $secret, $code)) {
        flash('error', t('mfa.invalid_code'));
        redirect('/mfa/verify');
    }

    // Valid TOTP — complete login
    $user = find_user_by_id($uid);
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['_mfa_pending_uid']);
        flash('error', t('mfa.account_unavailable'));
        redirect('/login');
    }

    unset($_SESSION['_mfa_pending_uid']);
    login_user($user);

    flash('success', t('auth.welcome_back'));
    $redirect = $_SESSION['_redirect_after_login'] ?? '/dashboard';
    unset($_SESSION['_redirect_after_login']);
    if (!is_safe_redirect($redirect)) {
        $redirect = '/dashboard';
    }
    redirect($redirect);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FLOW 2: First-Time TOTP Setup (pre-login)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * GET /mfa/setup — Show QR code for first-time setup
 */
function mfa_show_setup(): void
{
    $uid = mfa_require_setup();

    $pending = mfa_get_pending($uid);
    if (!$pending) {
        // No pending setup found — restart
        unset($_SESSION['_mfa_setup_uid']);
        flash('error', t('mfa.setup_expired'));
        redirect('/login');
    }

    $secret = decrypt_data($pending['totp_secret']);

    // Load user email for the TOTP URI label
    $user = find_user_by_id($uid);
    if (!$user) {
        unset($_SESSION['_mfa_setup_uid']);
        redirect('/login');
    }

    $issuer = get_branding()['brand_name'];
    $totp_uri = totp_get_uri($secret, $user['email'], $issuer);

    require __DIR__ . '/views/setup.php';
}

/**
 * POST /mfa/setup — Verify TOTP code during first-time setup
 */
function mfa_do_setup(): void
{
    csrf_check();
    $uid = mfa_require_setup();

    $code = trim($_POST['code'] ?? '');

    $rl = check_user_action_rate_limit(
        $uid, 'mfa_setup_verify',
        MFA_SETUP_MAX_ATTEMPTS, MFA_SETUP_WINDOW_MINUTES, true
    );
    if ($rl) {
        flash('error', $rl);
        redirect('/mfa/setup');
    }

    $pending = mfa_get_pending($uid);
    if (!$pending) {
        unset($_SESSION['_mfa_setup_uid']);
        flash('error', t('mfa.setup_expired'));
        redirect('/login');
    }

    $recovery_plain = mfa_verify_and_confirm($uid, $code);
    if (!$recovery_plain) {
        flash('error', t('mfa.invalid_code_check_app'));
        redirect('/mfa/setup');
    }

    // Store recovery code in session for one-time display.
    // MFA is not yet confirmed — mfa_finalize_setup() is called in
    // mfa_do_setup_complete() after the user acknowledges the code.
    $_SESSION['_mfa_recovery_code'] = $recovery_plain;

    redirect('/mfa/setup/recovery');
}

/**
 * GET /mfa/setup/recovery — Display recovery code (one-time view)
 */
function mfa_show_setup_recovery(): void
{
    $uid = mfa_require_setup();

    // Read and immediately unset before rendering so a concurrent tab or a
    // request interrupted after output-flush cannot re-display the code.
    $recovery_code = $_SESSION['_mfa_recovery_code'] ?? null;
    unset($_SESSION['_mfa_recovery_code']);

    if (!$recovery_code) {
        // Already viewed or session lost — they need to log in again
        unset($_SESSION['_mfa_setup_uid']);
        flash('error', t('mfa.recovery_already_shown'));
        redirect('/login');
    }

    require __DIR__ . '/views/setup_recovery.php';
}

/**
 * POST /mfa/setup/recovery — User acknowledges saving the recovery code
 */
function mfa_do_setup_complete(): void
{
    csrf_check();
    $uid = mfa_require_setup();

    // Clear the one-time recovery code from session
    unset($_SESSION['_mfa_recovery_code']);

    // Finalize MFA: set confirmed_at now that the user has seen the recovery code.
    // If this fails (e.g., record was deleted by an admin reset in the meantime),
    // the user is sent back to login where they'll re-enter the setup flow.
    if (!mfa_finalize_setup($uid)) {
        unset($_SESSION['_mfa_setup_uid']);
        flash('error', t('mfa.setup_expired'));
        redirect('/login');
    }

    // Load fresh user data and complete login.
    // Audit log is here (not in mfa_do_setup) because MFA is only truly
    // active after the user acknowledged the recovery code.
    $user = find_user_by_id($uid);
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['_mfa_setup_uid']);
        flash('error', t('mfa.account_unavailable'));
        redirect('/login');
    }

    unset($_SESSION['_mfa_setup_uid']);
    login_user($user);
    log_audit('mfa_setup_completed', 'user', $uid, null, $user['uuid']);

    flash('success', t('mfa.setup_complete'));
    $redirect = $_SESSION['_redirect_after_login'] ?? '/dashboard';
    unset($_SESSION['_redirect_after_login']);
    if (!is_safe_redirect($redirect)) {
        $redirect = '/dashboard';
    }
    redirect($redirect);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FLOW 3: User-Initiated MFA Change (password-gated, from /settings)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * GET /mfa/change — Password gate for MFA change
 */
function mfa_show_change_auth(): void
{
    require_auth();

    // Only allow if user actually has confirmed MFA
    $user = current_user();
    if (!mfa_user_has_confirmed($user['id'])) {
        flash('error', t('mfa.not_configured'));
        redirect('/settings?tab=security');
    }

    require __DIR__ . '/views/change_auth.php';
}

/**
 * POST /mfa/change/auth — Verify password for MFA change
 */
function mfa_do_change_auth(): void
{
    require_auth();
    csrf_check();

    $user = current_user();
    $uid = $user['id'];

    // Rate limit password attempts (record only on failure below)
    $rl = check_user_action_rate_limit(
        $uid, 'mfa_change_auth',
        MFA_CHANGE_AUTH_MAX, MFA_CHANGE_AUTH_MINUTES
    );
    if ($rl) {
        flash('error', $rl);
        redirect('/mfa/change');
    }

    $password = $_POST['password'] ?? '';
    $code = trim($_POST['code'] ?? '');
    if (!verify_user_password($uid, $password)) {
        record_user_action_attempt($uid, 'mfa_change_auth');
        flash('error', t('mfa.incorrect_password'));
        redirect('/mfa/change');
    }

    $mfa = mfa_get_confirmed($uid);
    if (!$mfa) {
        flash('error', t('mfa.not_configured'));
        redirect('/settings?tab=security');
    }

    $secret = decrypt_data($mfa['totp_secret']);
    if (!mfa_verify_totp_once($uid, $secret, $code)) {
        record_user_action_attempt($uid, 'mfa_change_auth');
        flash('error', t('mfa.invalid_code'));
        redirect('/mfa/change');
    }

    // Password valid — start the change flow
    $secret = totp_generate_secret();

    $_SESSION['_mfa_change_uid']     = $uid;
    $_SESSION['_mfa_change_expires'] = time() + 600; // 10-minute window
    $_SESSION['_mfa_change_secret']  = encrypt_data($secret);
    // Bind the change flow to the current session to prevent an attacker
    // who later obtains a session cookie from hijacking a partially
    // completed change (low risk given the 10-minute expiry, but cheap defence).
    $_SESSION['_mfa_change_sid']     = session_id();

    redirect('/mfa/change/setup');
}

/**
 * GET /mfa/change/setup — Show new QR code for MFA change
 */
function mfa_show_change_setup(): void
{
    $uid = mfa_require_change_auth();

    $encrypted_secret = $_SESSION['_mfa_change_secret'] ?? null;
    if (!$encrypted_secret) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_invalid'));
        redirect('/settings?tab=security');
    }

    $secret = decrypt_data($encrypted_secret);
    $user = current_user();
    $issuer = get_branding()['brand_name'];
    $totp_uri = totp_get_uri($secret, $user['email'], $issuer);

    require __DIR__ . '/views/change_setup.php';
}

/**
 * POST /mfa/change/setup — Verify new TOTP code during change
 */
function mfa_do_change_setup(): void
{
    csrf_check();
    $uid = mfa_require_change_auth();

    $code = trim($_POST['code'] ?? '');

    $rl = check_user_action_rate_limit(
        $uid, 'mfa_change_verify',
        MFA_CHANGE_VERIFY_MAX, MFA_CHANGE_VERIFY_MINUTES, true
    );
    if ($rl) {
        mfa_clear_change_session();
        flash('error', $rl);
        redirect('/settings?tab=security');
    }

    $encrypted_secret = $_SESSION['_mfa_change_secret'] ?? null;
    if (!$encrypted_secret) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_invalid'));
        redirect('/settings?tab=security');
    }

    $secret = decrypt_data($encrypted_secret);

    // Verify the new TOTP code with replay protection.
    // The new secret has no DB row yet, so step tracking uses the session
    // rather than the database (consistent with how mfa_verify_and_confirm
    // works for fist-time setup, which uses a DB row without a confirmed_at).
    $step = totp_verify_step($secret, $code);
    if ($step === null) {
        flash('error', t('mfa.invalid_code_check_new_app'));
        redirect('/mfa/change/setup');
    }
    $last_step = $_SESSION['_mfa_change_last_step'] ?? null;
    if ($last_step !== null && $step <= (int)$last_step) {
        flash('error', t('mfa.invalid_code_check_new_app'));
        redirect('/mfa/change/setup');
    }
    $_SESSION['_mfa_change_last_step'] = $step;

    // New code verified — generate new recovery code.
    // Pre-compute the hash now so the plaintext only needs to live in session
    // for the single GET that displays it, then it can be cleared.
    $recovery_plain = mfa_generate_recovery_code();
    $recovery_normalized = strtoupper(str_replace('-', '', $recovery_plain));
    $_SESSION['_mfa_change_new_recovery'] = $recovery_plain;
    $_SESSION['_mfa_change_new_recovery_hash'] = password_hash($recovery_normalized, PASSWORD_DEFAULT);

    redirect('/mfa/change/recovery');
}

/**
 * GET /mfa/change/recovery — Show new recovery code before completing change
 */
function mfa_show_change_recovery(): void
{
    $uid = mfa_require_change_auth();

    // Read and immediately clear the plaintext so it cannot be re-displayed
    // by refreshing the page or by a concurrent tab.  The pre-computed hash
    // remains in _mfa_change_new_recovery_hash for mfa_do_change_confirm().
    $recovery_code = $_SESSION['_mfa_change_new_recovery'] ?? null;
    unset($_SESSION['_mfa_change_new_recovery']);

    if (!$recovery_code) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_invalid'));
        redirect('/settings?tab=security');
    }

    require __DIR__ . '/views/change_recovery.php';
}

/**
 * POST /mfa/change/confirm — Finalize MFA change: replace old, invalidate sessions
 */
function mfa_do_change_confirm(): void
{
    csrf_check();
    $uid = mfa_require_change_auth();
    $user = current_user();

    $encrypted_secret = $_SESSION['_mfa_change_secret'] ?? null;
    $recovery_hash = $_SESSION['_mfa_change_new_recovery_hash'] ?? null;

    if (!$encrypted_secret || !$recovery_hash) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_invalid'));
        redirect('/settings?tab=security');
    }

    // Re-check expiry immediately before the irreversible DB write to close
    // the TOCTOU gap between mfa_require_change_auth() and mfa_replace().
    if (time() > ($_SESSION['_mfa_change_expires'] ?? 0)) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_expired'));
        redirect('/settings?tab=security');
    }

    $secret = decrypt_data($encrypted_secret);

    // Atomically replace old MFA with new one
    if (!mfa_replace($uid, $secret, $recovery_hash)) {
        mfa_clear_change_session();
        flash('error', t('mfa.update_failed'));
        redirect('/settings?tab=security');
    }

    log_audit('mfa_changed', 'user', $uid, null, $user['uuid']);

    // Invalidate ALL sessions (including current) across all devices
    increment_session_version($uid);

    // Clean up and destroy current session
    mfa_clear_change_session();
    logout();

    // Start a fresh session for flash messages
    session_start();
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    flash('success', t('mfa.updated_successfully'));
    redirect('/login');
}

// ═══════════════════════════════════════════════════════════════════════════════
// FLOW 4: Admin MFA Reset
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * POST /admin/users/{uuid}/reset-mfa — Admin resets a user's MFA
 */
function admin_users_reset_mfa(string $uuid): void
{
    require_auth();
    require_role('admin');
    csrf_check();

    $acting_user = current_user();
    $rl = check_user_action_rate_limit(
        $acting_user['id'],
        'admin_mfa_reset',
        MFA_ADMIN_RESET_MAX,
        MFA_ADMIN_RESET_MINUTES,
        true
    );
    if ($rl) {
        flash('error', $rl);
        redirect('/admin/users/' . $uuid . '/profile');
    }
    $target = get_user_by_uuid($uuid);

    if (!$target) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }

    if (!can_manage_user($acting_user, $target)) {
        flash('error', 'You do not have permission to manage this user.');
        redirect('/admin/users');
    }

    // Prevent admins from resetting super_admin MFA
    // (super_admins can only reset their own via the user flow)
    if ($target['role'] === 'super_admin' && $acting_user['role'] !== 'super_admin') {
        flash('error', 'You cannot reset MFA for a super admin.');
        redirect('/admin/users/' . $uuid . '/profile');
    }

    // Don't allow resetting your own MFA via admin panel
    if ($target['id'] === $acting_user['id']) {
        flash('error', 'Use your account settings to change your own MFA.');
        redirect('/settings');
    }

    // Require the acting admin's password to authorise this destructive action.
    // This closes the XSS-assisted silent-reset vector: even if an attacker can
    // forge a POST (CSRF is already checked), they still need the admin's password.
    $admin_password = $_POST['admin_password'] ?? '';
    if (!verify_user_password($acting_user['id'], $admin_password)) {
        flash('error', t('mfa.admin_reset_incorrect_password'));
        redirect('/admin/users/' . $uuid . '/profile');
    }

    // Delete MFA record and invalidate sessions
    mfa_delete($target['id']);
    increment_session_version($target['id']);

    log_audit('mfa_reset_by_admin', 'user', $target['id'], [
        'reset_by' => $acting_user['uuid'],
    ], $target['uuid']);

    flash('success', 'MFA has been reset for ' . sanitize($target['name']) . '.');
    redirect('/admin/users/' . $uuid . '/profile');
}

// ═══════════════════════════════════════════════════════════════════════════════
// FLOW 5: Super Admin — MFA Settings per Role
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * GET /admin/mfa/settings — Show MFA settings page
 */
function mfa_admin_settings(): void
{
    require_auth();
    require_role('super_admin');

    $flags = mfa_get_role_flags();

    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'MFA Settings'],
    ];

    require __DIR__ . '/views/admin_settings.php';
}

/**
 * POST /admin/mfa/settings — Update MFA role toggles
 */
function mfa_admin_settings_save(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $admin    = isset($_POST['mfa_enabled_admin']);
    $reviewer = isset($_POST['mfa_enabled_reviewer']);
    $user     = isset($_POST['mfa_enabled_user']);

    mfa_update_role_flags($admin, $reviewer, $user);

    $acting = current_user();
    log_audit('mfa_settings_changed', 'system', 0, [
        'mfa_enabled_admin'    => $admin,
        'mfa_enabled_reviewer' => $reviewer,
        'mfa_enabled_user'     => $user,
        'changed_by'           => $acting['uuid'],
    ], null);

    flash('success', 'MFA settings updated.');
    redirect('/admin/mfa/settings');
}

// ═══════════════════════════════════════════════════════════════════════
// FLOW 6: User-Initiated MFA Enable (from /settings)
// ═══════════════════════════════════════════════════════════════════════

/**
 * GET /mfa/enable — Password gate for enabling MFA
 */
function mfa_show_enable_auth(): void
{
    require_auth();

    $user = current_user();
    if (mfa_user_has_confirmed($user['id'])) {
        flash('error', t('mfa.already_enabled'));
        redirect('/settings?tab=security');
    }

    require __DIR__ . '/views/enable_auth.php';
}

/**
 * POST /mfa/enable — Verify password and start MFA enrollment
 */
function mfa_do_enable_auth(): void
{
    require_auth();
    csrf_check();

    $user = current_user();
    $uid = $user['id'];

    if (mfa_user_has_confirmed($uid)) {
        flash('error', t('mfa.already_enabled'));
        redirect('/settings?tab=security');
    }

    $rl = check_user_action_rate_limit(
        $uid, 'mfa_enable_auth',
        MFA_ENABLE_AUTH_MAX, MFA_ENABLE_AUTH_MINUTES
    );
    if ($rl) {
        flash('error', $rl);
        redirect('/mfa/enable');
    }

    $password = $_POST['password'] ?? '';
    if (!verify_user_password($uid, $password)) {
        record_user_action_attempt($uid, 'mfa_enable_auth');
        flash('error', t('mfa.incorrect_password'));
        redirect('/mfa/enable');
    }

    // Invalidate all other sessions BEFORE creating the pending record so no
    // concurrent session can operate in the window between record creation
    // and session invalidation.
    increment_session_version($uid);

    // Create or replace pending MFA record for next login.
    $secret = totp_generate_secret();
    if (!mfa_create_pending($uid, $secret)) {
        flash('error', t('mfa.already_enabled'));
        redirect('/settings?tab=security');
    }

    log_audit('mfa_enable_requested', 'user', $uid, null, $user['uuid']);

    // End current session and prompt re-login
    logout();

    session_start();
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    flash('success', t('mfa.enabled_login_setup'));
    redirect('/login');
}
