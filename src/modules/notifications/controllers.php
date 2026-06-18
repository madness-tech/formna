<?php

/**
 * Notifications Module - Controllers
 * 
 * API endpoints for notifications
 */

require_once __DIR__ . '/models.php';

/**
 * Get notifications for current user
 * GET /api/notifications        (end-user UI — uses user's locale)
 * GET /api/admin/notifications  (admin UI — i18n forces English)
 * 
 * Returns unread count and recent notifications
 */
function api_get_notifications(): void {
    require_auth();

    header('Content-Type: application/json');
    
    $user = current_user();
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    
    $unread_count = get_unread_notification_count($user['id']);
    $notifications = get_user_notifications($user['id'], $limit, $unread_only);
    
    // Format notifications for display
    $formatted = array_map(function($notification) {
        return format_notification($notification);
    }, $notifications);
    
    echo json_encode([
        'count' => $unread_count,
        'notifications' => $formatted
    ]);
}

/**
 * Mark notification as read
 * POST /api/notifications/{id}/read
 */
function api_mark_notification_read(int $notification_id): void {
    require_auth();
    csrf_check();
    
    header('Content-Type: application/json');
    
    $user = current_user();
    $success = mark_notification_read($notification_id, $user['id']);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => t('api.notification_not_found')]);
    }
}

/**
 * Mark all notifications as read
 * POST /api/notifications/read-all
 */
function api_mark_all_notifications_read(): void {
    require_auth();
    csrf_check();
    
    header('Content-Type: application/json');
    
    $user = current_user();
    $count = mark_all_notifications_read($user['id']);
    
    echo json_encode([
        'success' => true,
        'marked' => $count
    ]);
}

/**
 * Clear all notifications
 * POST /api/notifications/clear-all
 */
function api_clear_all_notifications(): void {
    require_auth();
    csrf_check();
    
    header('Content-Type: application/json');
    
    $user = current_user();
    $count = clear_all_notifications($user['id']);
    
    echo json_encode([
        'success' => true,
        'cleared' => $count
    ]);
}

/**
 * Format a notification for display
 * 
 * @param array<string,mixed> $notification
 * @return array<string,mixed>
 */
function format_notification(array $notification): array {
    $data = $notification['data'];
    $type = $notification['type'];
    
    // Escape all user/admin-supplied values before substituting into translated
    // strings. t() uses plain str_replace with no HTML encoding, and the
    // frontend renders the resulting message as innerHTML, so unescaped values
    // are a stored-XSS vector.
    $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    // Generate message and link based on notification type
    [$message, $link, $icon] = match($type) {
        'clarification_response' => [
            t('notifications.messages.clarification_response', [
                'name' => $e($data['respondent_name'] ?? ''),
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['submission_link'] ?? '#',
            'chat-bubble-left-right'
        ],
        
        'form_admin_added' => [
            t('notifications.messages.form_admin_added', [
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['form_link'] ?? '#',
            'user-plus'
        ],
        
        'clarification_request' => [
            t('notifications.messages.clarification_request', [
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['clarification_link'] ?? '#',
            'exclamation-circle'
        ],
        
        'clarification_rejected' => [
            t('notifications.messages.clarification_rejected', [
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['clarification_link'] ?? '#',
            'exclamation-circle'
        ],
        
        'clarification_resolved' => [
            t('notifications.messages.clarification_resolved', [
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['submission_link'] ?? '/submissions',
            'chat-bubble-left-right'
        ],

        'program_decision' => [
            ($data['decision'] ?? '') === 'approved'
                ? t('notifications.messages.program_approved', ['program' => $e($data['program_name'] ?? '')])
                : t('notifications.messages.program_rejected', ['program' => $e($data['program_name'] ?? '')]),
            $data['program_link'] ?? '/my-programs',
            ($data['decision'] ?? '') === 'approved' ? 'check-circle' : 'x-circle'
        ],
        
        'draft_cleared' => [
            t('notifications.messages.draft_cleared', [
                'form' => $e($data['form_name'] ?? ''),
            ]),
            $data['form_link'] ?? '#',
            'document-text'
        ],
        
        default => [
            t('notifications.messages.default'),
            '#',
            'bell'
        ]
    };
    
    return [
        'id' => $notification['id'],
        'type' => $type,
        'message' => $message,
        'link' => $link,
        'icon' => $icon,
        'read' => $notification['read_at'] !== null,
        'created_at' => $notification['created_at'],
        'time_ago' => time_ago($notification['created_at'])
    ];
}
