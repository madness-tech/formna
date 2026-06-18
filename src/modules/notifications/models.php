<?php

/**
 * Notifications Module - Models
 * 
 * Handles in-app notifications for actionable events
 */

/**
 * Create a notification for a user
 * 
 * @param int $user_id User to notify
 * @param string $type Notification type (e.g., 'clarification_response', 'form_admin_added')
 * @param array<string,mixed> $data Notification data (flexible JSON structure)
 * @return int Notification ID
 */
function create_notification(int $user_id, string $type, array $data): int {
    $sql = "INSERT INTO notifications (user_id, type, data, created_at)
            VALUES (?, ?, ?, ?)";
    
    db_exec($sql, [
        $user_id,
        $type,
        json_encode($data),
        now()
    ]);
    
    return (int) db()->lastInsertId();
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id
 * @return int Count of unread notifications
 */
function get_unread_notification_count(int $user_id): int {
    $sql = "SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND read_at IS NULL";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$user_id]);
    
    return (int) $stmt->fetchColumn();
}

/**
 * Get recent notifications for a user (with limit)
 * 
 * @param int $user_id
 * @param int $limit Max number of notifications to retrieve
 * @param bool $unread_only If true, only return unread notifications
 * @return list<array<string,mixed>>
 */
function get_user_notifications(int $user_id, int $limit = 20, bool $unread_only = false): array {
    $sql = "SELECT id, type, data, read_at, created_at
            FROM notifications
            WHERE user_id = ?";
    
    if ($unread_only) {
        $sql .= " AND read_at IS NULL";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$user_id, $limit]);
    $notifications = $stmt->fetchAll();
    
    // Decode JSON data field
    foreach ($notifications as &$notification) {
        decode_json_fields($notification, ['data']);
    }
    
    return $notifications;
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id
 * @param int $user_id User ID for security check
 * @return bool True if notification was marked as read
 */
function mark_notification_read(int $notification_id, int $user_id): bool {
    $sql = "UPDATE notifications
            SET read_at = ?
            WHERE id = ? AND user_id = ? AND read_at IS NULL";
    
    $affected = db_exec($sql, [now(), $notification_id, $user_id]);
    
    return $affected > 0;
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id
 * @return int Number of notifications marked as read
 */
function mark_all_notifications_read(int $user_id): int {
    $sql = "UPDATE notifications
            SET read_at = ?
            WHERE user_id = ? AND read_at IS NULL";
    
    return db_exec($sql, [now(), $user_id]);
}

/**
 * Clear all notifications for a user (both read and unread)
 *
 * @param int $user_id
 * @return int Number of notifications deleted
 */
function clear_all_notifications(int $user_id): int {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    
    return db_exec($sql, [$user_id]);
}

/**
 * Delete old read notifications (cleanup utility)
 *
 * @param int $days_old Delete notifications older than this many days
 * @return int Number of notifications deleted
 */
function delete_old_notifications(int $days_old = 90): int {
    $cutoff = datetime_add(now(), "-$days_old days");
    
    $sql = "DELETE FROM notifications
            WHERE read_at IS NOT NULL AND read_at < ?";
    
    return db_exec($sql, [$cutoff]);
}
