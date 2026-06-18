<?php

/**
 * Users Module - Controllers
 *
 * Handles authentication (login, register, logout) and admin user management.
 */

require_once __DIR__ . '/models.php';

/**
 * Show login page (redirect if already authenticated)
 */
function show_login(): void {
    if (is_logged_in()) {
        redirect('/dashboard');
    }
    
    require __DIR__ . '/views/login.php';
}

/**
 * Process login form submission
 * POST /login
 */
function do_login(): void {
    csrf_check();
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        flash('error', t('auth.provide_credentials'));
        redirect('/login');
    }
    
    // Store "remember me" preference so it survives session regeneration
    // in both the MFA middleware and login_user().
    $_SESSION['_remember_me'] = !empty($_POST['remember_me']);
    
    $ip = get_client_ip();
    $rate_limit_error = check_login_rate_limit($ip, $email);
    if ($rate_limit_error) {
        flash('error', $rate_limit_error);
        redirect('/login');
    }
    
    $user = find_user_by_email($email);
    
    if ($user) {
        $password_valid = password_verify($password, $user['password_hash']);
    } else {
        burn_password_verify_time();
        $password_valid = false;
    }
    
    if ($password_valid && ($user['status'] ?? null) === 'active') {
        // Delegate to MFA middleware — it either completes login directly
        // (MFA not required) or redirects to MFA verify/setup and exits.
        require_once __DIR__ . '/../mfa/middleware.php';
        mfa_check_post_login($user, $ip, $email);
        // mfa_check_post_login always redirects (never returns); the return
        // below is unreachable but prevents any future code added after this
        // block from accidentally running on a successful login.
        return;
    }
    
    // Valid credentials but account pending email verification — restore the
    // session variable so the verify-pending page shows the Resend button,
    // and auto-send a fresh verification email so the user doesn't have to
    // click Resend manually (their original token has likely expired).
    if ($password_valid && ($user['status'] ?? null) === 'pending') {
        clear_login_attempts($ip, $email);
        $_SESSION['pending_verification_email'] = $user['email'];

        require_once __DIR__ . '/../../core/verification.php';
        resend_verification($user['id']);

        flash('info', t('auth.account_pending_verification'));
        redirect('/verify-pending');
    }
    
    record_failed_login($ip, $email);
    flash('error', t('auth.invalid_credentials'));
    redirect('/login');
}

/**
 * Show registration page (redirect if already authenticated)
 */
function show_register(): void {
    if (is_logged_in()) {
        redirect('/dashboard');
    }
    
    require __DIR__ . '/views/register.php';
}

/**
 * Process registration form submission
 * POST /register
 */
function do_register(): void {
    csrf_check();
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    
    $ip = get_client_ip();
    $rl_error = check_ip_rate_limit($ip, 'register', REGISTER_MAX_PER_HOUR, 60);
    if ($rl_error) {
        flash('error', $rl_error);
        redirect('/register');
    }
    
    $errors = [];
    
    if ($name === '') {
        $errors[] = t('validation.name_required');
    } elseif (!is_valid_person_name($name)) {
        $errors[] = t('validation.name_invalid');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('validation.invalid_email');
    }
    
    $pw_error = validate_password_strength($password);
    if ($pw_error) {
        $errors[] = $pw_error;
    }
    
    if ($password !== $password_confirm) {
        $errors[] = t('validation.passwords_dont_match');
    }
    
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('/register');
    }
    
    check_ip_rate_limit($ip, 'register', REGISTER_MAX_PER_HOUR, 60, true);
    
    // Normalize response timing to prevent account enumeration.
    // The "existing user" path would otherwise return faster than the
    // "new user" path (which creates a row + sends an email).
    $reg_start = hrtime(true);

    $existing = find_user_by_email($email);
    if ($existing) {
        // Only set the pending email session for genuinely pending users
        // so the verify-pending page shows the Resend button for them.
        // Active/suspended users see the same page but without Resend,
        // guiding them back to login without a dead-end.
        if ($existing['status'] === 'pending') {
            $_SESSION['pending_verification_email'] = $email;

            // Auto-resend a fresh verification email for pending users
            require_once __DIR__ . '/../../core/verification.php';
            resend_verification($existing['id']);
        }

        // Pad the existing-user branch to match typical new-registration latency
        $elapsed_ms = (hrtime(true) - $reg_start) / 1e6;
        $pad_ms = max(0, 200 - $elapsed_ms);   // target ≥ 200 ms
        usleep((int)($pad_ms * 1000));

        flash('success', t('auth.registration_success'));
        redirect('/verify-pending');
    }
    
    $user_id = create_user($email, $password, $name, 'user', 'pending');
    $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
    log_audit('registered', 'user', $user_id, null, $user_uuid);
    
    require_once __DIR__ . '/../../core/verification.php';
    send_registration_verification($user_id, $email, $name);
    
    $_SESSION['pending_verification_email'] = $email;
    
    flash('success', t('auth.registration_success'));
    redirect('/verify-pending');
}

/**
 * Process logout and destroy session
 * POST /logout
 */
function do_logout(): void {
    csrf_check();
    logout();
    flash('success', t('auth.logged_out'));
    redirect('/login');
}

// ========================================
// Forgot Password
// ========================================

/** Rate limit: max reset requests per IP per hour */
const PASSWORD_RESET_REQUEST_MAX_PER_HOUR = 3;

/**
 * Show forgot password page
 * GET /forgot-password
 */
function show_forgot_password(): void {
    if (is_logged_in()) {
        redirect('/dashboard');
    }

    require __DIR__ . '/views/forgot_password.php';
}

/**
 * Process forgot password form submission.
 *
 * Always shows the same success message regardless of whether the email
 * exists to prevent account enumeration. Response timing is normalized
 * so the "no user" branch takes roughly the same time as the "send email" branch.
 *
 * POST /forgot-password
 */
function do_forgot_password(): void {
    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', t('validation.invalid_email'));
        redirect('/forgot-password');
    }

    $ip = get_client_ip();

    // IP-based rate limit — record on every attempt (valid or not)
    $rl = check_ip_rate_limit($ip, 'pwd_reset_req', PASSWORD_RESET_REQUEST_MAX_PER_HOUR, 60, true);
    if ($rl) {
        flash('error', $rl);
        redirect('/forgot-password');
    }

    $start = hrtime(true);

    require_once __DIR__ . '/../../core/verification.php';

    $user = find_user_by_email($email);

    if ($user && $user['status'] === 'active') {
        send_password_reset_email($user['id'], $user['email'], $user['name']);
        log_audit('password_reset_requested', 'user', $user['id'], null, $user['uuid']);
    }

    // Normalize timing to prevent enumeration via response latency
    $elapsed_ms = (hrtime(true) - $start) / 1e6;
    $pad_ms = max(0, 200 - $elapsed_ms);
    usleep((int)($pad_ms * 1000));

    flash('success', t('auth.reset_email_sent'));
    redirect('/forgot-password');
}

/**
 * Show reset password form (from email link)
 * GET /reset-password?token=xxx
 */
function show_reset_password(): void {
    if (is_logged_in()) {
        redirect('/dashboard');
    }

    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        flash('error', t('auth.reset_link_invalid'));
        redirect('/forgot-password');
    }

    require_once __DIR__ . '/../../core/verification.php';

    if (!peek_verification_token($token)) {
        flash('error', t('auth.reset_link_expired'));
        redirect('/forgot-password');
    }

    require __DIR__ . '/views/reset_password.php';
}

/**
 * Process reset password form submission.
 *
 * Validates the token, updates the password, invalidates all sessions,
 * and redirects to login. MFA remains enforced on next login.
 *
 * POST /reset-password
 */
function do_reset_password(): void {
    csrf_check();

    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($token)) {
        flash('error', t('auth.reset_link_invalid'));
        redirect('/forgot-password');
    }

    // Validate password strength
    $errors = [];

    $pw_error = validate_password_strength($password);
    if ($pw_error) {
        $errors[] = $pw_error;
    }

    if ($password !== $password_confirm) {
        $errors[] = t('validation.passwords_dont_match');
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('/reset-password?token=' . urlencode($token));
    }

    require_once __DIR__ . '/../../core/verification.php';

    $token_data = validate_verification_token($token);

    if (!$token_data || $token_data['type'] !== 'password_reset') {
        flash('error', t('auth.reset_link_expired'));
        redirect('/forgot-password');
    }

    $user = find_user_by_id($token_data['user_id']);

    if (!$user || $user['status'] !== 'active') {
        flash('error', t('auth.reset_link_expired'));
        redirect('/forgot-password');
    }

    // Update password
    require_once __DIR__ . '/../account/models.php';
    update_user_password($user['id'], password_hash($password, PASSWORD_DEFAULT));

    // Invalidate all existing sessions across all devices
    increment_session_version($user['id']);

    // Send notification that password was changed
    send_password_changed_notification($user['email'], $user['name']);

    log_audit('password_reset_completed', 'user', $user['id'], null, $user['uuid']);

    flash('success', t('auth.password_has_been_reset'));
    redirect('/login');
}

// ========================================
// Admin User Management
// ========================================

function admin_users_index(): void {
    require_auth();
    require_role('admin');
    
    $acting_user = current_user();
    
    // Get filters
    $role_filter = $_GET['role'] ?? null;
    $status_filter = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    
    // SECURITY: Get roles the acting user is allowed to see
    $visible_roles = get_visible_roles($acting_user);
    
    // Validate role filter is within visible roles
    if ($role_filter && !in_array($role_filter, $visible_roles)) {
        $role_filter = null; // Clear invalid filter
    }
    
    // Get users with role scoping
    $result = search_users($role_filter, $status_filter, $search, $page, 25, $visible_roles);
    $users = $result['rows'];
    $pagination = [
        'total' => $result['total'],
        'pages' => $result['pages'],
        'page' => $result['page'],
        'per_page' => $result['per_page']
    ];
    
    // Get counts by role with scoping
    $counts = get_user_count_by_role($visible_roles);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Users']
    ];
    
    require __DIR__ . '/views/index.php';
}

function admin_users_create(): void {
    require_auth();
    require_role('admin');
    
    $acting_user = current_user();
    $assignable_roles = get_assignable_roles($acting_user);
    
    if (empty($assignable_roles)) {
        flash('error', 'You do not have permission to create users.');
        redirect('/admin/users');
    }
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Users', 'url' => '/admin/users'],
        ['label' => 'Create']
    ];
    
    require __DIR__ . '/views/create.php';
}

function admin_users_store(): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $acting_user = current_user();
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    
    // Validation
    $errors = [];
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    
    if (find_user_by_email($email)) {
        $errors[] = 'A user with this email already exists.';
    }
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    } elseif (!is_valid_person_name($name)) {
        $errors[] = 'Name contains invalid characters. Use letters, spaces, hyphens, apostrophes, and periods only.';
    }
    
    $pw_error = validate_password_strength($password);
    if ($pw_error) {
        $errors[] = $pw_error;
    }
    
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if acting user can assign this role
    if (!can_assign_role($acting_user, $role)) {
        $errors[] = 'You do not have permission to assign this role.';
    }
    
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('/admin/users/create');
    }
    
    // Create user
    $user_id = create_user($email, $password, $name, $role, 'active', $acting_user['id']);
    $user_uuid = db_one('SELECT uuid FROM users WHERE id = ?', [$user_id])['uuid'];
    
    // Log audit
    log_audit('user_created', 'user', $user_id, [
        'role' => $role,
        'created_by' => $acting_user['id']
    ], $user_uuid);
    
    flash('success', "User {$name} ({$role}) created successfully.");
    redirect('/admin/users');
}

function admin_users_edit(string $uuid): void {
    require_auth();
    require_role('admin');
    
    $acting_user = current_user();
    $user = get_user_by_uuid($uuid);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    
    // Check if acting user can manage this user
    if (!can_manage_user($acting_user, $user)) {
        flash('error', 'You do not have permission to manage this user.');
        redirect('/admin/users');
    }
    
    $assignable_roles = get_assignable_roles($acting_user);
    
    // Get user's form permissions
    $permissions = db_query(
        'SELECT p.*, f.name as form_name, f.uuid as form_uuid
         FROM permissions p
         JOIN forms f ON p.form_id = f.id
         WHERE p.user_id = :user_id
         ORDER BY f.name',
        ['user_id' => $user['id']]
    );
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Users', 'url' => '/admin/users'],
        ['label' => 'User Profile', 'url' => '/admin/users/' . $uuid . '/profile'],
        ['label' => 'Edit']
    ];
    
    require __DIR__ . '/views/edit.php';
}

function admin_users_update(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $acting_user = current_user();
    $user = get_user_by_uuid($uuid);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    
    // Check if acting user can manage this user
    if (!can_manage_user($acting_user, $user)) {
        flash('error', 'You do not have permission to manage this user.');
        redirect('/admin/users');
    }
    
    $name = trim($_POST['name'] ?? '');
    $new_role = $_POST['role'] ?? $user['role'];
    // Status is immutable when editing yourself or when the user is pending (email verification flow)
    if ($user['id'] == $acting_user['id'] || $user['status'] === 'pending') {
        $new_status = $user['status'];
    } else {
        $new_status = $_POST['status'] ?? $user['status'];
    }

    $allowed_statuses = ['active', 'suspended'];

    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required.';
    } elseif (!is_valid_person_name($name)) {
        $errors[] = 'Name contains invalid characters. Use letters, spaces, hyphens, apostrophes, and periods only.';
    }

    if ($user['status'] !== 'pending' && !in_array($new_status, $allowed_statuses)) {
        $errors[] = 'Invalid status value.';
    }

    // Check if acting user can assign the new role
    if ($new_role !== $user['role'] && !can_assign_role($acting_user, $new_role)) {
        $errors[] = 'You do not have permission to assign this role.';
    }

    // Prevent self-suspension
    if ($user['id'] == $acting_user['id'] && $new_status === 'suspended') {
        $errors[] = 'You cannot suspend your own account.';
    }
    
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('/admin/users/' . $uuid . '/edit');
    }
    
    // Update user
    $updates = ['name' => $name];
    $changed = [];
    
    if ($new_role !== $user['role']) {
        update_user_role($user['id'], $new_role);
        log_audit('user_role_changed', 'user', $user['id'], [
            'old_role' => $user['role'],
            'new_role' => $new_role,
            'changed_by' => $acting_user['id']
        ], $user['uuid']);
        $changed[] = "role updated to {$new_role}";
    }
    
    if ($new_status !== $user['status']) {
        update_user_status($user['id'], $new_status);
        
        // Invalidate all sessions when account is suspended
        if ($new_status === 'suspended') {
            increment_session_version($user['id']);
        }
        
        log_audit('user_status_changed', 'user', $user['id'], [
            'old_status' => $user['status'],
            'new_status' => $new_status,
            'changed_by' => $acting_user['id']
        ], $user['uuid']);
        $changed[] = "status updated to {$new_status}";
    }
    
    // Global reports flag (only super_admin can toggle, only for admin users)
    if ($acting_user['role'] === 'super_admin' && in_array($new_role, ['admin'])) {
        $new_global_reports = isset($_POST['can_global_reports']) ? 1 : 0;
        $old_global_reports = (int)($user['can_global_reports'] ?? 0);
        if ($new_global_reports !== $old_global_reports) {
            db_exec(
                "UPDATE users SET can_global_reports = ? WHERE id = ?",
                [$new_global_reports, $user['id']]
            );
            log_audit('user_global_reports_changed', 'user', $user['id'], [
                'can_global_reports' => $new_global_reports,
                'changed_by' => $acting_user['id']
            ], $user['uuid']);
            $changed[] = $new_global_reports ? 'granted global reports access' : 'revoked global reports access';
        }
    }
    
    if ($name !== $user['name']) {
        update_user($user['id'], $updates);
        $changed[] = 'name updated';
    }
    
    $message = !empty($changed) 
        ? 'User updated: ' . implode(', ', $changed) . '.'
        : 'No changes made.';
    
    flash('success', $message);
    redirect('/admin/users/' . $uuid . '/edit');
}

function admin_users_reset_password(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $acting_user = current_user();
    $user = get_user_by_uuid($uuid);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    
    // Check if acting user can manage this user
    if (!can_manage_user($acting_user, $user)) {
        flash('error', 'You do not have permission to manage this user.');
        redirect('/admin/users/' . $uuid . '/edit');
    }
    
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';
    
    $errors = [];
    
    $pw_error = validate_password_strength($new_password);
    if ($pw_error) {
        $errors[] = $pw_error;
    }
    
    if ($new_password !== $new_password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('/admin/users/' . $uuid . '/edit');
    }
    
    // Reset password
    admin_reset_password($user['id'], $new_password);
    
    // Invalidate all sessions for the target user (across all devices)
    increment_session_version($user['id']);
    
    // Log audit (do not include password)
    log_audit('user_password_reset', 'user', $user['id'], [
        'reset_by' => $acting_user['uuid']
    ], $user['uuid']);
    
    flash('success', 'Password reset successfully for ' . $user['name'] . '.');
    redirect('/admin/users/' . $uuid . '/edit');
}

function admin_users_toggle_status(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $acting_user = current_user();
    $user = get_user_by_uuid($uuid);
    
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    
    // Check if acting user can manage this user
    if (!can_manage_user($acting_user, $user)) {
        flash('error', 'You do not have permission to manage this user.');
        redirect('/admin/users');
    }
    
    // Prevent self-suspension
    if ($user['id'] == $acting_user['id']) {
        flash('error', 'You cannot change your own account status.');
        redirect('/admin/users');
    }

    // Prevent bypassing email verification via quick-toggle
    if ($user['status'] === 'pending') {
        flash('error', 'Cannot toggle a pending user via quick-action. Use the Edit User form to manually activate or suspend this account.');
        redirect('/admin/users');
    }

    // Toggle status
    $new_status = $user['status'] === 'active' ? 'suspended' : 'active';
    update_user_status($user['id'], $new_status);
    
    // Invalidate all sessions when account is suspended
    if ($new_status === 'suspended') {
        increment_session_version($user['id']);
    }
    
    // Log audit
    log_audit('user_status_changed', 'user', $user['id'], [
        'old_status' => $user['status'],
        'new_status' => $new_status,
        'changed_by' => $acting_user['id']
    ], $user['uuid']);
    
    $action = $new_status === 'suspended' ? 'suspended' : 'activated';
    flash('success', "User {$user['name']} has been {$action}.");
    redirect('/admin/users');
}

// ========================================
// Admin User Profile (read-only overview)
// ========================================

function admin_users_profile(string $uuid): void {
    require_auth();
    require_role('admin');

    $acting_user = current_user();
    $user = get_user_by_uuid($uuid);

    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }

    if (!can_manage_user($acting_user, $user)) {
        flash('error', 'You do not have permission to view this user.');
        redirect('/admin/users');
    }

    $allowed_tabs = ['overview', 'programs', 'submissions', 'permissions', 'activity'];
    $tab = in_array($_GET['tab'] ?? '', $allowed_tabs) ? $_GET['tab'] : 'overview';
    $page = max(1, (int)($_GET['page'] ?? 1));

    // Always load statistics (shown in header cards)
    $statistics = get_user_statistics($user['id']);

    // Tab-specific data
    $programs           = [];
    $programs_result    = null;
    $submissions_result = null;
    $permissions_result = null;
    $activity_result    = null;
    $recent_activity    = [];

    switch ($tab) {
        case 'programs':
            require_once __DIR__ . '/../programs/models.php';
            $programs_result = list_user_program_submissions($user['id'], null, $page, 15);
            break;

        case 'submissions':
            require_once __DIR__ . '/../submissions/models.php';
            $submissions_result = list_user_submissions($user['id'], null, $page, 15);
            break;

        case 'permissions':
            $permissions_result = paginate(
                'SELECT p.*, f.name as form_name, f.uuid as form_uuid
                 FROM permissions p
                 JOIN forms f ON p.form_id = f.id
                 WHERE p.user_id = ?
                 ORDER BY f.name',
                [$user['id']],
                $page,
                20
            );
            break;

        case 'activity':
            $activity_result = get_user_activity_paginated($user['id'], $page, 20);
            break;

        default: // overview
            $tab = 'overview';
            require_once __DIR__ . '/../programs/models.php';
            $programs = get_user_program_submissions($user['id']);
            $recent_activity = get_user_recent_activity($user['id'], 10);
            break;
    }

    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Users', 'url' => '/admin/users'],
        ['label' => 'User Profile']
    ];

    require __DIR__ . '/views/profile.php';
}
