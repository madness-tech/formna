<?php

/**
 * Email Verification & Token Management
 * 
 * Handles secure token generation, validation, and verification flows
 * for email verification, email changes, and password changes.
 */

/**
 * Generate a cryptographically secure random token
 * 
 * @return string 64-character hex token
 */
function generate_verification_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Create a verification token record
 *
 * @param int $user_id User ID
 * @param string $type Token type (email_verification, email_change)
 * @param string $email Email to verify
 * @param array<string, mixed>|null $payload Additional data (e.g., new_email for email_change)
 * @param int $expiry_minutes Token expiration time in minutes (default: 60)
 * @return string The generated token
 */
function create_verification_token(
    int $user_id,
    string $type,
    string $email,
    ?array $payload = null,
    int $expiry_minutes = 60
): string {
    $pdo = db();
    
    // Clean up any existing tokens of the same type for this user
    cleanup_user_tokens($user_id, $type);
    
    // Generate new token — store only the SHA-256 hash in the database so that
    // a database leak does not expose usable tokens.
    $token = generate_verification_token();
    $token_hash = hash('sha256', $token);
    $expires_at = gmdate('Y-m-d H:i:s', time() + ($expiry_minutes * 60));
    
    $stmt = $pdo->prepare("
        INSERT INTO verification_tokens 
        (user_id, token, type, email, payload, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $token_hash,
        $type,
        $email,
        $payload ? json_encode($payload) : null,
        $expires_at
    ]);
    
    return $token;
}

/**
 * Validate and consume a verification token
 *
 * @param string $token The token to validate
 * @return array<string, mixed>|null Token data if valid, null if invalid/expired
 */
function validate_verification_token(string $token): ?array {
    $pdo = db();
    
    // Hash the incoming token to compare against the stored hash
    $token_hash = hash('sha256', $token);
    
    $stmt = $pdo->prepare("
        SELECT id, user_id, type, email, payload, expires_at
        FROM verification_tokens
        WHERE token = ? AND expires_at > UTC_TIMESTAMP()
    ");
    
    $stmt->execute([$token_hash]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_data) {
        return null;
    }
    
    // Parse JSON payload if present
    if ($token_data['payload']) {
        $token_data['payload'] = json_decode($token_data['payload'], true);
    }
    
    // Delete the token (one-time use)
    $delete_stmt = $pdo->prepare("DELETE FROM verification_tokens WHERE id = ?");
    $delete_stmt->execute([$token_data['id']]);
    
    return $token_data;
}

/**
 * Delete old/expired tokens for a user and type
 * 
 * @param int $user_id User ID
 * @param string $type Token type
 * @return void
 */
function cleanup_user_tokens(int $user_id, string $type): void {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        DELETE FROM verification_tokens
        WHERE user_id = ? AND type = ?
    ");
    
    $stmt->execute([$user_id, $type]);
}

/**
 * Check if a token is valid without consuming it
 * 
 * Used to verify a token exists before showing a form (e.g. password reset),
 * so expired or already-consumed tokens are rejected on the GET request
 * rather than after the user fills in the form.
 * 
 * @param string $token The raw token to check
 * @return bool True if the token exists and has not expired
 */
function peek_verification_token(string $token): bool {
    $pdo = db();

    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM verification_tokens
        WHERE token = ? AND expires_at > UTC_TIMESTAMP()
    ");

    $stmt->execute([$token_hash]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}

/**
 * Check if a valid token exists for user and type
 * 
 * @param int $user_id User ID
 * @param string $type Token type
 * @return bool True if valid token exists
 */
function verify_token_exists(int $user_id, string $type): bool {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM verification_tokens
        WHERE user_id = ? AND type = ? AND expires_at > UTC_TIMESTAMP()
    ");
    
    $stmt->execute([$user_id, $type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Resend verification email with rate limiting
 * 
 * @param int $user_id User ID
 * @return bool True if email sent, false if rate limited
 */
function resend_verification(int $user_id): bool {
    $pdo = db();
    
    // Check rate limit: max 3 emails per hour from audit log
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM email_resend_log
        WHERE user_id = ? 
        AND type = 'email_verification'
        AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] >= 3) {
        return false; // Rate limited
    }
    
    // Get user info
    $user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return false;
    }
    
    // Record resend attempt before generating token
    $log_stmt = $pdo->prepare("
        INSERT INTO email_resend_log (user_id, type, created_at)
        VALUES (?, 'email_verification', UTC_TIMESTAMP())
    ");
    $log_stmt->execute([$user_id]);
    
    // Generate new token
    $token = create_verification_token(
        $user_id,
        'email_verification',
        $user['email']
    );
    
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('registration_verification', [
        'email' => $user['email'],
        'name' => $user['name']
    ], [
        'user_name' => $user['name'],
        'verification_link' => full_url("/verify-email?token={$token}")
    ]);
    
    return true;
}

/**
 * Clean up all expired tokens (should be run via cron)
 * 
 * @return int Number of tokens deleted
 */
function cleanup_expired_tokens(): int {
    $pdo = db();
    
    $stmt = $pdo->prepare("DELETE FROM verification_tokens WHERE expires_at < UTC_TIMESTAMP()");
    $stmt->execute();
    
    return $stmt->rowCount();
}

/**
 * Send email verification for registration
 * 
 * @param int $user_id User ID
 * @param string $email Email address
 * @param string $name User name
 * @return string The verification token
 */
function send_registration_verification(int $user_id, string $email, string $name): string {
    // Create verification token
    $token = create_verification_token(
        $user_id,
        'email_verification',
        $email
    );
    
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('registration_verification', [
        'email' => $email,
        'name' => $name
    ], [
        'user_name' => $name,
        'verification_link' => full_url("/verify-email?token={$token}")
    ]);
    
    return $token;
}

/**
 * Send email change verification
 * 
 * @param int $user_id User ID
 * @param string $old_email Current email address
 * @param string $new_email New email address
 * @param string $name User name
 * @return string The verification token
 */
function send_email_change_verification(
    int $user_id,
    string $old_email,
    string $new_email,
    string $name
): string {
    // Create verification token with payload
    $token = create_verification_token(
        $user_id,
        'email_change',
        $new_email,
        [
            'old_email' => $old_email,
            'new_email' => $new_email
        ]
    );
    
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('email_change_verification', [
        'email' => $new_email,
        'name' => $name
    ], [
        'user_name' => $name,
        'verification_link' => full_url("/verify-email-change?token={$token}")
    ]);
    
    return $token;
}

/**
 * Send notification after email is changed
 * 
 * @param string $old_email Previous email address
 * @param string $new_email New email address
 * @param string $name User name
 * @return void
 */
function send_email_changed_notification(
    string $old_email,
    string $new_email,
    string $name
): void {
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('email_changed_notification', [
        'email' => $old_email,
        'name' => $name
    ], [
        'user_name' => $name,
        'new_email' => $new_email
    ]);
}

/**
 * Send password reset email
 * 
 * @param int $user_id User ID
 * @param string $email User email address
 * @param string $name User name
 * @return string The reset token
 */
function send_password_reset_email(int $user_id, string $email, string $name): string {
    // Create verification token (type: password_reset, 60-minute expiry)
    $token = create_verification_token(
        $user_id,
        'password_reset',
        $email
    );
    
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('password_reset', [
        'email' => $email,
        'name' => $name
    ], [
        'user_name' => $name,
        'reset_link' => full_url("/reset-password?token={$token}")
    ]);
    
    return $token;
}

/**
 * Send notification after password is changed
 * 
 * @param string $email User email address
 * @param string $name User name
 * @return void
 */
function send_password_changed_notification(string $email, string $name): void {
    require_once __DIR__ . '/../modules/emails/sender.php';
    
    queue_email_from_template('password_changed_notification', [
        'email' => $email,
        'name' => $name
    ], [
        'user_name' => $name
    ]);
}
