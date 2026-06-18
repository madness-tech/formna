<?php

/**
 * MFA Module — Middleware
 *
 * Central decision point called after password verification during login.
 * Determines whether MFA is required and routes the user accordingly.
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/totp.php';

/**
 * Post-login MFA gate.
 *
 * Called from do_login() after the user's password has been verified and
 * the account is confirmed active. This function EITHER completes the
 * login (MFA not required) OR sets up the pre-auth session state and
 * redirects to the appropriate MFA page.
 *
 * This function never returns when MFA is needed — it calls redirect() which exits.
 *
 * @param array<string, mixed> $user   Full user row from database
 * @param string               $ip     Client IP (for clearing rate limits)
 * @param string               $email  Login email (for clearing rate limits)
 * @return void Only returns if MFA is not required (login already completed)
 */
function mfa_check_post_login(array $user, string $ip, string $email): void
{
    $uid = $user['id'];

    // Query MFA state once — reused for both the optional-bypass check
    // and the routing logic below, avoiding redundant DB round-trips.
    $has_confirmed_mfa = mfa_user_has_confirmed($uid);
    $pending = $has_confirmed_mfa ? null : mfa_get_pending($uid);
    $has_pending_mfa = $pending !== null;

    if (!mfa_required_for_role($user['role'])) {
        if (!$has_confirmed_mfa && !$has_pending_mfa) {
            // MFA not required and not enabled — complete login normally
            clear_login_attempts($ip, $email);
            login_user($user);
            flash('success', t('auth.welcome_back'));
            $redirect = $_SESSION['_redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['_redirect_after_login']);
            if (!is_safe_redirect($redirect)) {
                $redirect = '/dashboard';
            }
            redirect($redirect);
        }
    }

    // MFA is required for this role (or user has voluntary MFA).
    // Clear login rate limits since password was valid.
    clear_login_attempts($ip, $email);

    // Preserve values before wiping session data
    $redirect_after = $_SESSION['_redirect_after_login'] ?? null;
    $remember_me = !empty($_SESSION['_remember_me']);

    // Clear all pre-login session data to prevent attacker-controlled data
    // from persisting into the MFA-pending state, then regenerate the
    // session ID to prevent fixation (mirrors login_user() behaviour).
    $_SESSION = [];
    session_regenerate_id(true);

    // Restore preserved values after regeneration
    if ($redirect_after !== null) {
        $_SESSION['_redirect_after_login'] = $redirect_after;
    }
    if ($remember_me) {
        $_SESSION['_remember_me'] = true;
    }

    // Rotate CSRF for the pre-auth session
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    if ($has_confirmed_mfa) {
        // User has MFA set up — send them to the TOTP verify page
        $_SESSION['_mfa_pending_uid'] = $uid;
        redirect('/mfa/verify');
    }

    // User needs to set up MFA for the first time
    if (!$pending) {
        $secret = totp_generate_secret();
        if (!mfa_create_pending($user['id'], $secret)) {
            flash('error', t('mfa.already_enabled'));
            redirect('/login');
        }
    }

    $_SESSION['_mfa_setup_uid'] = $user['id'];
    redirect('/mfa/setup');
}

/**
 * Guard: require that the caller is in the MFA-pending-verification state.
 *
 * Returns the user ID if valid, or redirects away.
 *
 * @return int User ID awaiting MFA verification
 */
function mfa_require_pending(): int
{
    if (is_logged_in()) {
        redirect('/dashboard');
    }

    $uid = $_SESSION['_mfa_pending_uid'] ?? null;
    if (!$uid) {
        redirect('/login');
    }

    return (int)$uid;
}

/**
 * Guard: require that the caller is in the MFA-setup state.
 *
 * Returns the user ID if valid, or redirects away.
 *
 * @return int User ID in MFA setup flow
 */
function mfa_require_setup(): int
{
    if (is_logged_in()) {
        redirect('/dashboard');
    }

    $uid = $_SESSION['_mfa_setup_uid'] ?? null;
    if (!$uid) {
        redirect('/login');
    }

    return (int)$uid;
}

/**
 * Guard: require that the caller is in the MFA-change flow with valid auth window.
 *
 * If the password-auth window has expired, all change session keys are cleared
 * and the user is sent back to settings.
 *
 * @return int User ID in MFA change flow
 */
function mfa_require_change_auth(): int
{
    require_auth();

    $user = current_user();
    $change_uid = $_SESSION['_mfa_change_uid'] ?? null;
    $expires = $_SESSION['_mfa_change_expires'] ?? 0;

    if (!$change_uid || (int)$change_uid !== $user['id'] || time() > $expires) {
        // Expired or invalid — clean up
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_expired'));
        redirect('/settings?tab=security');
    }

    // Verify the change flow is still running in the same browser session that
    // passed the password gate.  Prevents a stolen session cookie (obtained
    // between the gate and confirmation) from completing the change flow.
    $change_sid = $_SESSION['_mfa_change_sid'] ?? null;
    if ($change_sid !== session_id()) {
        mfa_clear_change_session();
        flash('error', t('mfa.change_session_expired'));
        redirect('/settings?tab=security');
    }

    return $user['id'];
}

/**
 * Clear all MFA-change session keys.
 */
function mfa_clear_change_session(): void
{
    unset(
        $_SESSION['_mfa_change_uid'],
        $_SESSION['_mfa_change_expires'],
        $_SESSION['_mfa_change_secret'],
        $_SESSION['_mfa_change_last_step'],
        $_SESSION['_mfa_change_sid'],
        $_SESSION['_mfa_change_new_recovery'],
        $_SESSION['_mfa_change_new_recovery_hash']
    );
}
