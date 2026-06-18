<?php

/**
 * Account Settings Controllers
 * 
 * Handlers for account management, email verification, and password changes
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../../core/verification.php';
require_once __DIR__ . '/../audit/logger.php';

/**
 * Show account settings page
 */
function show_account_settings(): void
{
    require_auth();
    
    $user_id = current_user()['id'];
    $user = get_user_for_settings($user_id);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/dashboard');
    }
    
    // Get pending verifications
    $pending_verifications = get_user_pending_verifications($user_id);
    
    // Determine active tab from query parameter
    $active_tab = in_array($_GET['tab'] ?? '', ['account', 'security']) ? $_GET['tab'] : 'account';
    
    require __DIR__ . '/views/settings.php';
}

/**
 * Handle name update
 */
function update_account_name(): void
{
    require_auth();
    csrf_check();
    
    $user_id = current_user()['id'];
    $name = trim($_POST['name'] ?? '');
    
    // Validate name
    if (empty($name)) {
        flash('error', t('account.name_empty'));
        redirect('/settings');
    }

    if (!is_valid_person_name($name)) {
        flash('error', t('validation.name_invalid'));
        redirect('/settings');
    }
    
    if (strlen($name) > 100) {
        flash('error', t('account.name_too_long'));
        redirect('/settings');
    }
    
    // Update name
    if (update_user_name($user_id, $name)) {
        $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
        log_audit('user_name_changed', 'user', $user_id, [
            'new_name' => $name
        ], $user_uuid);
        
        flash('success', t('account.name_updated'));
    } else {
        flash('error', t('account.name_update_failed'));
    }
    
    redirect('/settings');
}

/**
 * Handle email change request (sends verification to new email)
 */
function request_email_change(): void
{
    require_auth();
    csrf_check();
    
    $user_id = current_user()['id'];
    
    $rl_error = check_user_action_rate_limit(
        $user_id, 'email_chg',
        EMAIL_CHANGE_MAX_PER_HOUR, 60, true
    );
    if ($rl_error) {
        flash('error', $rl_error);
        redirect('/settings');
    }
    
    $user = get_user_for_settings($user_id);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/settings');
    }
    
    $new_email = trim($_POST['new_email'] ?? '');
    
    // Validate email
    if (empty($new_email)) {
        flash('error', t('account.email_empty'));
        redirect('/settings');
    }
    
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        flash('error', t('account.email_invalid'));
        redirect('/settings');
    }
    
    // Check if email is the same as current
    if ($new_email === $user['email']) {
        flash('error', t('account.email_same'));
        redirect('/settings');
    }
    
    // Check if email already exists for another user
    if (email_exists_for_other_user($new_email, $user_id)) {
        flash('error', t('account.email_in_use'));
        redirect('/settings');
    }
    
    // Send verification to new email
    send_email_change_verification(
        $user_id,
        $user['email'],
        $new_email,
        $user['name']
    );
    
    flash('success', t('account.email_verification_sent'));
    redirect('/settings');
}

/**
 * Verify email change (completes the change)
 */
function verify_email_change(): void
{
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        flash('error', 'Invalid verification link.');
        redirect('/login');
    }
    
    // Validate token
    $token_data = validate_verification_token($token);
    
    if (!$token_data || $token_data['type'] !== 'email_change') {
        flash('error', 'This verification link is invalid or has expired.');
        redirect('/login');
    }
    
    $user_id = $token_data['user_id'];
    $new_email = $token_data['email'];
    $payload = $token_data['payload'] ?? [];
    $old_email = $payload['old_email'] ?? '';
    
    // Get user info
    $user = get_user_for_settings($user_id);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/login');
    }
    
    // Update email
    if (update_user_email($user_id, $new_email)) {
        // Send notification to old email
        if ($old_email) {
            send_email_changed_notification($old_email, $new_email, $user['name']);
        }
        
        $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
        log_audit('user_email_changed', 'user', $user_id, [
            'old_email' => $old_email,
            'new_email' => $new_email
        ], $user_uuid);
        
        // Invalidate all sessions for this user (across all devices)
        increment_session_version($user_id);
        
        redirect('/login?msg=email_changed');
    } else {
        flash('error', 'Failed to update email. Please try again.');
        redirect('/login');
    }
}

/**
 * Handle password change
 */
function change_password(): void
{
    require_auth();
    csrf_check();
    
    $user_id = current_user()['id'];
    
    $rl_error = check_user_action_rate_limit(
        $user_id, 'pwd_chg',
        PASSWORD_CHANGE_MAX_ATTEMPTS, PASSWORD_CHANGE_LOCKOUT_MINUTES
    );
    if ($rl_error) {
        flash('error', $rl_error);
        redirect('/settings?tab=security');
    }
    
    $user = get_user_for_settings($user_id);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/settings?tab=security');
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (!verify_user_password($user_id, $current_password)) {
        check_user_action_rate_limit(
            $user_id, 'pwd_chg',
            PASSWORD_CHANGE_MAX_ATTEMPTS, PASSWORD_CHANGE_LOCKOUT_MINUTES,
            true
        );
        flash('error', t('account.current_password_wrong'));
        redirect('/settings?tab=security');
    }
    
    // Validate new password strength
    $pw_error = validate_password_strength($new_password);
    if ($pw_error) {
        flash('error', $pw_error);
        redirect('/settings?tab=security');
    }
    
    if ($new_password !== $confirm_password) {
        flash('error', 'New password and confirmation do not match.');
        redirect('/settings?tab=security');
    }
    
    // Prevent re-use of current password
    if (verify_user_password($user_id, $new_password)) {
        flash('error', 'New password must be different from your current password.');
        redirect('/settings?tab=security');
    }
    
    // Update password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    if (update_user_password($user_id, $password_hash)) {
        // Send notification email
        send_password_changed_notification($user['email'], $user['name']);
        
        $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
        log_audit('user_password_changed', 'user', $user_id, null, $user_uuid);
        
        // Invalidate all sessions for this user (across all devices)
        increment_session_version($user_id);
        
        // Clear session data and cookie before destroying current session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        
        redirect('/login?msg=password_changed');
    } else {
        flash('error', t('account.password_update_failed'));
        redirect('/settings?tab=security');
    }
}

/**
 * Handle timezone update
 */
function update_timezone(): void
{
    require_auth();
    csrf_check();
    
    $user_id = current_user()['id'];
    $timezone = trim($_POST['timezone'] ?? '');
    
    // Validate timezone
    if (empty($timezone)) {
        flash('error', 'Timezone cannot be empty.');
        redirect('/settings');
    }
    
    // Verify timezone is valid
    try {
        new DateTimeZone($timezone);
    } catch (Exception $e) {
        flash('error', 'Invalid timezone selected.');
        redirect('/settings');
    }
    
    // Update timezone
    if (update_user_timezone($user_id, $timezone)) {
        // Update session
        $_SESSION['user']['timezone'] = $timezone;
        
        $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
        log_audit('user_timezone_changed', 'user', $user_id, [
            'new_timezone' => $timezone
        ], $user_uuid);
        
        flash('success', t('account.timezone_updated'));
    } else {
        flash('error', t('account.timezone_update_failed'));
    }
    
    redirect('/settings');
}

/**
 * Handle email verification from registration
 */
function verify_registration_email(): void
{
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        flash('error', 'Invalid verification link.');
        redirect('/login');
    }
    
    // Validate token
    $token_data = validate_verification_token($token);
    
    if (!$token_data || $token_data['type'] !== 'email_verification') {
        flash('error', 'This verification link is invalid or has expired.');
        redirect('/login');
    }
    
    $user_id = $token_data['user_id'];
    
    // Get user
    require_once __DIR__ . '/../users/models.php';
    $user = find_user_by_id($user_id);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/login');
    }
    
    // Update user status and email_verified_at
    $pdo = db();
    $update_stmt = $pdo->prepare("
        UPDATE users
        SET status = 'active', email_verified_at = NOW()
        WHERE id = ?
    ");
    
    if ($update_stmt->execute([$user_id])) {
        $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
        log_audit('email_verified', 'user', $user_id, null, $user_uuid);
        
        $fresh_user = find_user_by_id($user_id);

        if (!$fresh_user) {
            flash('error', 'User not found.');
            redirect('/login');
        }

        // Auto-login the user using the standard login flow
        if (!login_user($fresh_user)) {
            flash('error', 'Your account is not active. Please contact support.');
            redirect('/login');
        }
        
        flash('success', 'Your email has been verified! Welcome to ' . get_branding()['brand_name'] . '™.');
        redirect('/dashboard');
    } else {
        flash('error', 'Failed to verify email. Please try again.');
        redirect('/login');
    }
}

/**
 * Resend verification email
 */
function resend_verification_email(): void
{
    require_auth();
    csrf_check();
    
    $user_id = current_user()['id'];
    
    if (resend_verification($user_id)) {
        flash('success', t('verify_pending.resend_success'));
    } else {
        flash('error', t('verify_pending.resend_rate_limited'));
    }
    
    redirect('/settings');
}

/**
 * Resend verification email for pending (not yet logged-in) users.
 * Uses session-stored email from registration — no auth required.
 */
function resend_pending_verification(): void
{
    csrf_check();
    
    $email = $_SESSION['pending_verification_email'] ?? '';
    
    if (empty($email)) {
        flash('error', t('verify_pending.no_pending_found'));
        redirect('/register');
    }
    
    // Look up the user
    require_once __DIR__ . '/../users/models.php';
    $user = find_user_by_email($email);
    
    if (!$user) {
        flash('error', t('verify_pending.no_pending_found'));
        redirect('/register');
    }
    
    // Already-verified users don't need another verification email.
    // Clear the stale session variable and guide them to log in.
    if ($user['status'] !== 'pending') {
        unset($_SESSION['pending_verification_email']);
        flash('info', t('auth.email_already_verified'));
        redirect('/login');
    }
    
    if (resend_verification($user['id'])) {
        flash('success', t('verify_pending.resend_success'));
    } else {
        flash('error', t('verify_pending.resend_rate_limited'));
    }
    
    redirect('/verify-pending');
}

/**
 * Show verification pending page
 */
function show_verify_pending(): void
{
    $pending_email = $_SESSION['pending_verification_email'] ?? null;
    require __DIR__ . '/views/verify_pending.php';
}
