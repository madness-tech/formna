<?php

/**
 * Account Settings Models
 * 
 * Database operations for account management and verification
 */

/**
 * Get user's pending verification tokens
 *
 * @param int $user_id User ID
 * @return array<int, array{type: string, email: string, created_at: string, expires_at: string}> List of pending verification tokens
 */
function get_user_pending_verifications(int $user_id): array
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT type, email, created_at, expires_at
        FROM verification_tokens
        WHERE user_id = ? AND expires_at > NOW()
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update user email after verification
 * 
 * @param int $user_id User ID
 * @param string $new_email New email address
 * @return bool Success status
 */
function update_user_email(int $user_id, string $new_email): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        UPDATE users
        SET email = ?, email_verified_at = NOW()
        WHERE id = ?
    ");
    
    return $stmt->execute([$new_email, $user_id]);
}

/**
 * Update user password
 * 
 * @param int $user_id User ID
 * @param string $password_hash Hashed password
 * @return bool Success status
 */
function update_user_password(int $user_id, string $password_hash): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        UPDATE users
        SET password_hash = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([$password_hash, $user_id]);
}

/**
 * Update user name
 * 
 * @param int $user_id User ID
 * @param string $name New name
 * @return bool Success status
 */
function update_user_name(int $user_id, string $name): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([$name, $user_id]);
}

/**
 * Check if email already exists for another user
 * 
 * @param string $email Email to check
 * @param int $exclude_user_id User ID to exclude from check
 * @return bool True if email exists for another user
 */
function email_exists_for_other_user(string $email, int $exclude_user_id): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM users
        WHERE email = ? AND id != ?
    ");
    
    $stmt->execute([$email, $exclude_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Get user by ID with basic info
 *
 * @param int $user_id User ID
 * @return array{id: int, name: string, email: string, email_verified_at: ?string, created_at: string}|null User data or null
 */
function get_user_for_settings(int $user_id): ?array
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT id, name, email, email_verified_at, timezone, created_at
        FROM users
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user ?: null;
}

/**
 * Update user timezone
 */
function update_user_timezone(int $user_id, string $timezone): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        UPDATE users
        SET timezone = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([$timezone, $user_id]);
}

/**
 * Verify user's current password
 * 
 * @param int $user_id User ID
 * @param string $password Plain text password to verify
 * @return bool True if password is correct
 */
function verify_user_password(int $user_id, string $password): bool
{
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT password_hash
        FROM users
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return false;
    }
    
    return password_verify($password, $result['password_hash']);
}
