<?php

// Rate limiting constants
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;
const PASSWORD_CHANGE_MAX_ATTEMPTS = 5;
const PASSWORD_CHANGE_LOCKOUT_MINUTES = 15;
const EMAIL_CHANGE_MAX_PER_HOUR = 3;
const SUBMISSION_RATE_LIMIT_PER_HOUR = 10;
const PROGRAM_SUBMISSION_RATE_LIMIT_PER_HOUR = 5;
const USER_SEARCH_RATE_LIMIT_PER_MINUTE = 30;
const FILE_UPLOAD_RATE_LIMIT_PER_MINUTE = 10;
const DRAFT_SAVE_RATE_LIMIT_PER_MINUTE = 10;
const REGISTER_MAX_PER_HOUR = 5;

/**
 * Burn bcrypt time to normalize authentication-related response timing.
 *
 * Uses a real pre-computed bcrypt hash so that password_verify() performs a
 * full bcrypt computation.  A malformed hash would be rejected almost
 * instantly, creating a timing side-channel that lets an attacker distinguish
 * "no user" from "wrong password".
 */
function burn_password_verify_time(): void {
    password_verify('dummy', '$2y$12$Ke1qIIe9o7YHY/PnkFC0beXG5OKFr8WVL3a9gJAq1HOVvfNzQzR7C');
}

/**
 * Get the client's IP address.
 *
 * Forwarded headers (X-Forwarded-For, X-Real-IP, X-Client-IP) are only read
 * when the direct connection comes from a known trusted proxy. Without this
 * check an attacker can send arbitrary headers to spoof their IP and bypass
 * IP-based rate limiting entirely.
 */
function get_client_ip(): string {
    global $config;

    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $trusted_proxies = $config['app']['trusted_proxy_ips'] ?? [];
    if ($trusted_proxies && in_array($remote_addr, $trusted_proxies, true)) {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return $remote_addr;
}

/**
 * Check if a login attempt is rate-limited by IP or email
 *
 * @return string|null Error message if rate-limited, null if allowed
 */
function check_login_rate_limit(string $ip, string $email): ?string {
    $email = strtolower($email);
    $now = now();

    // Check both IP and email lockouts in one query
    $rows = db_query(
        'SELECT identifier_type, attempts, locked_until
         FROM rate_limits
         WHERE (identifier_type = :type_ip AND identifier_value = :ip)
            OR (identifier_type = :type_email AND identifier_value = :email)',
        [
            'type_ip' => 'ip',
            'ip' => $ip,
            'type_email' => 'email',
            'email' => $email,
        ]
    );

    foreach ($rows as $row) {
        if ($row['locked_until'] && $row['locked_until'] > $now) {
            $locked_until = new DateTime($row['locked_until'], new DateTimeZone('UTC'));
            $now_dt = new DateTime($now, new DateTimeZone('UTC'));
            $remaining = (int) ceil(($locked_until->getTimestamp() - $now_dt->getTimestamp()) / 60);
            $remaining = max($remaining, 1);
            $label = $remaining === 1 ? 'minute' : 'minutes';

            return "Too many failed login attempts. Please try again in {$remaining} {$label}.";
        }
    }

    return null;
}

/**
 * Record a failed login attempt for both IP and email.
 * Locks the identifier if attempts reach the threshold.
 *
 * Uses SELECT ... FOR UPDATE inside a transaction to prevent the race condition
 * where concurrent requests read the same attempt count and both increment to
 * the same value, effectively doubling the allowed attempts before lockout.
 */
function record_failed_login(string $ip, string $email): void {
    $email = strtolower($email);
    $now = now();
    $locked_until = gmdate('Y-m-d H:i:s', time() + (LOGIN_LOCKOUT_MINUTES * 60));

    foreach ([['ip', $ip], ['email', $email]] as [$type, $value]) {
        db_transaction(function () use ($type, $value, $now, $locked_until) {
            // Lock the row for the duration of this transaction so concurrent
            // requests block here instead of reading a stale attempt count.
            $row = db_one(
                'SELECT id, attempts, locked_until FROM rate_limits
                 WHERE identifier_type = :type AND identifier_value = :value
                 LIMIT 1 FOR UPDATE',
                ['type' => $type, 'value' => $value]
            );

            if (!$row) {
                // First failure — insert a new record.
                db_insert('rate_limits', [
                    'identifier_type' => $type,
                    'identifier_value' => $value,
                    'attempts'        => 1,
                    'locked_until'    => null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                return;
            }

            // If a previous lockout has expired, reset the counter.
            $attempts = $row['attempts'];
            if ($row['locked_until'] && $row['locked_until'] <= $now) {
                $attempts = 0;
            }

            $attempts++;
            $lock = ($attempts >= LOGIN_MAX_ATTEMPTS) ? $locked_until : null;

            db_exec(
                'UPDATE rate_limits
                 SET attempts = :attempts, locked_until = :locked_until, updated_at = :updated_at
                 WHERE id = :id',
                [
                    'attempts'     => $attempts,
                    'locked_until' => $lock,
                    'updated_at'   => $now,
                    'id'           => $row['id'],
                ]
            );
        });
    }
}

/**
 * Clear login attempt counters on successful authentication
 */
function clear_login_attempts(string $ip, string $email): void {
    $email = strtolower($email);
    db_exec(
        'DELETE FROM rate_limits
         WHERE (identifier_type = :type_ip AND identifier_value = :ip)
            OR (identifier_type = :type_email AND identifier_value = :email)',
        [
            'type_ip' => 'ip',
            'ip' => $ip,
            'type_email' => 'email',
            'email' => $email,
        ]
    );
}

/**
 * Check/record a per-user rate limit for a named action.
 * Returns an error string if limited, null if allowed.
 * Pass $record = true to count the attempt.
 * 
 * @param int $user_id User ID
 * @param string $action Action name (e.g., 'pwd_chg', 'email_chg')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_minutes Time window in minutes
 * @param bool $record Whether to record this attempt
 * @return string|null Error message if limited, null if allowed
 */
function check_user_action_rate_limit(
    int $user_id,
    string $action,
    int $max_attempts,
    int $window_minutes,
    bool $record = false
): ?string {
    // Prefix user IDs so they can never collide with IP addresses stored by
    // check_ip_rate_limit() under the same action name (fragile but real risk).
    $identifier = 'uid:' . $user_id;
    $since = gmdate('Y-m-d H:i:s', time() - $window_minutes * 60);
    $row = db_one(
        "SELECT COUNT(*) AS cnt FROM rate_limits
         WHERE identifier_type = :type AND identifier_value = :val
           AND created_at >= :since",
        ['type' => $action, 'val' => $identifier, 'since' => $since]
    );
    $count = (int)($row['cnt'] ?? 0);

    if ($count >= $max_attempts) {
        return t('auth.action_rate_limited', [
            'minutes' => $window_minutes,
            'label'   => $window_minutes === 1 ? t('time.minute') : t('time.minutes'),
        ]);
    }

    if ($record) {
        db_insert('rate_limits', [
            'identifier_type'  => $action,
            'identifier_value' => $identifier,
            'attempts'         => 1,
            'locked_until'     => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    return null;
}

/**
 * Record a single rate-limit attempt for a user action without checking.
 *
 * Use this when you call check_user_action_rate_limit() with $record=false
 * up front and only want to record on failure (e.g. password gates where
 * successful attempts should not consume rate-limit slots).
 */
function record_user_action_attempt(int $user_id, string $action): void
{
    $identifier = 'uid:' . $user_id;
    db_insert('rate_limits', [
        'identifier_type'  => $action,
        'identifier_value' => $identifier,
        'attempts'         => 1,
        'locked_until'     => null,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

/**
 * Check if a form submission is rate-limited.
 * Prevents rapid-fire abuse across all forms, regardless of submission limit.
 *
 * @return string|null Error message if rate-limited, null if allowed
 */
function check_submission_rate_limit(int $user_id, int $form_id): ?string {
    $one_hour_ago = gmdate('Y-m-d H:i:s', time() - 3600);

    $row = db_one(
        "SELECT COUNT(*) as cnt FROM submissions
         WHERE user_id = :user_id AND form_id = :form_id AND status != 'draft'
           AND created_at >= :since",
        ['user_id' => $user_id, 'form_id' => $form_id, 'since' => $one_hour_ago]
    );

    $recent_count = (int)($row['cnt'] ?? 0);
    if ($recent_count >= SUBMISSION_RATE_LIMIT_PER_HOUR) {
        return 'You are submitting too quickly. Please wait a while before submitting again.';
    }

    return null;
}

/**
 * Check if a program submission is rate-limited (safety net when admin hasn't set a limit).
 * Only applies when the program's max_submissions_per_user is 0 (unlimited).
 *
 * @return string|null Error message if rate-limited, null if allowed
 */
function check_program_submission_rate_limit(int $user_id, int $program_id): ?string {
    $one_hour_ago = gmdate('Y-m-d H:i:s', time() - 3600);

    $row = db_one(
        "SELECT COUNT(*) as cnt FROM program_submissions
         WHERE user_id = :user_id AND program_id = :program_id AND status != 'draft'
           AND created_at >= :since",
        ['user_id' => $user_id, 'program_id' => $program_id, 'since' => $one_hour_ago]
    );

    $recent_count = (int)($row['cnt'] ?? 0);
    if ($recent_count >= PROGRAM_SUBMISSION_RATE_LIMIT_PER_HOUR) {
        return 'You are submitting too quickly. Please wait a while before submitting again.';
    }

    return null;
}

/**
 * Check/record an IP-based rate limit for a named action.
 * Returns an error string if limited, null if allowed.
 * Pass $record = true to count the attempt.
 *
 * @param string $ip Client IP address
 * @param string $action Action name (e.g., 'register')
 * @param int $max_attempts Maximum attempts allowed
 * @param int $window_minutes Time window in minutes
 * @param bool $record Whether to record this attempt
 * @return string|null Error message if limited, null if allowed
 */
function check_ip_rate_limit(string $ip, string $action, int $max_attempts, int $window_minutes, bool $record = false): ?string {
    $since = gmdate('Y-m-d H:i:s', time() - $window_minutes * 60);
    $row = db_one(
        "SELECT COUNT(*) AS cnt FROM rate_limits
         WHERE identifier_type = :type AND identifier_value = :val
           AND created_at >= :since",
        ['type' => $action, 'val' => $ip, 'since' => $since]
    );

    if ((int)($row['cnt'] ?? 0) >= $max_attempts) {
        return 'Too many attempts. Please try again later.';
    }

    if ($record) {
        db_insert('rate_limits', [
            'identifier_type'  => $action,
            'identifier_value' => $ip,
            'attempts'         => 1,
            'locked_until'     => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    return null;
}

/**
 * Validate that a redirect path is safe (same-origin, no open-redirect tricks).
 */
function is_safe_redirect(string $path): bool {
    return $path !== ''
        && $path[0] === '/'
        && !preg_match('#^/[/\\\\]#', $path)
        && !preg_match('#[\x00-\x1f]#', $path);
}

/**
 * Complete login for a validated user record
 *
 * @param array<string, mixed> $user
 */
function login_user(array $user): bool {
    if (($user['status'] ?? null) !== 'active') {
        return false;
    }

    // Preserve values before wiping session data
    $redirect_after_login = $_SESSION['_redirect_after_login'] ?? null;
    $remember_me = !empty($_SESSION['_remember_me']);

    // Clear all pre-login session data to prevent session data carryover,
    // then regenerate the session ID to prevent session fixation.
    $_SESSION = [];
    session_regenerate_id(true);
    
    // Rotate CSRF token on privilege elevation (login)
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    // Restore redirect target if one was saved before login
    if ($redirect_after_login !== null) {
        $_SESSION['_redirect_after_login'] = $redirect_after_login;
    }
    
    // Store user in session (including session_version for cross-device invalidation)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
    global $config;
    $_SESSION['user'] = [
        'id' => $user['id'],
        'uuid' => $user['uuid'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
        'timezone' => $user['timezone'] ?? $config['app']['timezone'] ?? 'Asia/Dubai',
    ];
    
    // "Remember me": extend session cookie lifetime to 30 days so the
    // session persists across browser restarts.  Without this the cookie
    // lifetime is 0 (session cookie — deleted when the browser closes).
    if ($remember_me) {
        $lifetime = 30 * 24 * 60 * 60; // 30 days
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => time() + $lifetime,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly'  => $p['httponly'],
                'samesite' => $p['samesite'],
            ]
        );
    }

    // Update last login timestamp
    require_once __DIR__ . '/../modules/users/models.php';
    touch_login($user['id']);
    
    // Log audit
    log_audit('login', 'user', $user['id'], null, $user['uuid']);
    
    return true;
}

/**
 * Increment a user's session_version in the database.
 * All other sessions for this user will be invalidated on their next request
 * because their stored session_version will no longer match the DB value.
 */
function increment_session_version(int $user_id): void {
    db_exec(
        'UPDATE users SET session_version = session_version + 1 WHERE id = ?',
        [$user_id]
    );
}

/**
 * Verify the current session's version against the database.
 * If the session_version stored in the session does not match the DB,
 * the session is stale (password/email changed, account suspended, etc.)
 * and the user is logged out.
 */
function check_session_version(): void {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    try {
        $row = db_one(
            'SELECT session_version, status FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    } catch (\PDOException $e) {
        error_log('Session version check failed: ' . $e->getMessage());
        // Fail closed: invalidate session so a suspended user cannot remain
        // logged in during a transient DB error.
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        return;
    }

    $invalidate = false;

    if (!$row) {
        // User deleted or not found
        $invalidate = true;
    } elseif ($row['status'] !== 'active') {
        // Account suspended
        $invalidate = true;
    } elseif (isset($row['session_version'])) {
        // Session version mismatch — credentials changed elsewhere
        $db_version = (int)$row['session_version'];
        $session_version = (int)($_SESSION['session_version'] ?? 0);
        if ($session_version !== $db_version) {
            $invalidate = true;
        }
    }

    if ($invalidate) {
        // Destroy the stale session and start a fresh one so that the CSRF
        // token and other session-dependent features remain functional for
        // the remainder of this request (e.g. rendering the login page).
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Log out current user
 */
function logout(): void {
    $user = current_user();
    
    if ($user) {
        log_audit('logout', 'user', $user['id'], null, $user['uuid']);
    }
    
    $_SESSION = [];
    session_destroy();

    // Clear the session cookie from the browser so no stale session ID persists
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }
}

/**
 * Get current logged-in user
 *
 * @return array{id:int,uuid:string,email:string,name:string,role:string,timezone:string}|null
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Require authentication (redirect if not logged in)
 *
 * For API (fetch/XHR) requests, returns a JSON 401 response so the client
 * can handle session expiry gracefully instead of receiving an HTML redirect
 * that cannot be parsed as JSON.
 *
 * For normal page requests, saves the originally requested URI in the session
 * so do_login() can redirect the user back to their intended destination
 * after a successful login, instead of always going to /dashboard.
 */
function require_auth(): void {
    if (!is_logged_in()) {
        if (is_api_request()) {
            json_response(['error' => 'Unauthenticated'], 401);
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (is_safe_redirect($uri)) {
            $_SESSION['_redirect_after_login'] = $uri;
        }
        flash('error', 'Please log in to continue.');
        redirect('/login');
    }
}

/**
 * Detect whether the current request is an API/AJAX call.
 *
 * Checks the request path (/api/…) and common fetch/XHR indicators so that
 * auth failures return JSON instead of an HTML redirect.
 */
function is_api_request(): bool {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

    if (str_starts_with($path, '/api/')) {
        return true;
    }

    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
        return true;
    }

    return false;
}

/**
 * Require specific role (with hierarchy: super_admin > admin > reviewer > user)
 */
function require_role(string $required_role): void {
    require_auth();
    
    $user = current_user();
    $hierarchy = ['user' => 0, 'reviewer' => 1, 'admin' => 2, 'super_admin' => 3];
    
    $user_level = $hierarchy[$user['role']] ?? 0;
    $required_level = $hierarchy[$required_role] ?? 0;
    
    if ($user_level < $required_level) {
        http_response_code(403);
        require __DIR__ . '/../layouts/error_403.php';
        exit;
    }
}

/**
 * Check if user has permission to access a form
 */
function has_form_permission(int $form_id, string $min_access = 'view_results'): bool {
    $user = current_user();
    
    if (!$user) {
        return false;
    }
    
    // Super admin has access to everything
    if ($user['role'] === 'super_admin') {
        return true;
    }
    
    // Check if user created the form
    $form = db_one('SELECT created_by FROM forms WHERE id = :id', ['id' => $form_id]);
    if ($form && $form['created_by'] == $user['id']) {
        return true;
    }
    
    // Check permissions table
    $permission = db_one(
        'SELECT access FROM permissions WHERE user_id = :user_id AND form_id = :form_id',
        ['user_id' => $user['id'], 'form_id' => $form_id]
    );
    
    if (!$permission) {
        return false;
    }
    
    $access_levels = ['view_results' => 1, 'manage' => 2, 'full' => 3];
    $user_level = $access_levels[$permission['access']] ?? 0;
    $required_level = $access_levels[$min_access] ?? 1;
    
    return $user_level >= $required_level;
}

// Validate session version on every request (loaded after bootstrap.php starts
// the session and after db.php provides database access via composer autoload).
// Invalidates stale sessions after password/email change, admin password reset,
// or account suspension — across all devices.
if (!($GLOBALS['FORMNA_DISABLE_SESSION_VERSION_CHECK'] ?? false)) {
    check_session_version();
}
