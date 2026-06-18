<?php

/**
 * Audit Module - Controllers
 *
 * Handles audit log viewing and filtering
 */

require_once __DIR__ . '/models.php';

/**
 * Display audit log with filters and pagination
 * GET /admin/audit-log
 *
 * Role-based access:
 * - Reviewer: See own actions only
 * - Admin: See own actions + reviewer actions scoped to admin's forms
 * - Super Admin: See all admin/reviewer actions (user actions hidden by default)
 */
function audit_log_index(): void {
    require_auth();
    require_role('reviewer');
    
    $user = current_user();
    $page = (int)($_GET['page'] ?? 1);
    
    // Build filters array from GET parameters
    $filters = [];
    
    if (!empty($_GET['action'])) {
        $filters['action'] = $_GET['action'];
    }
    
    if (!empty($_GET['entity_type'])) {
        $filters['entity_type'] = $_GET['entity_type'];
    }
    
    if (!empty($_GET['user_search'])) {
        $filters['user_search'] = trim($_GET['user_search']);
    }
    
    if (!empty($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Swap dates if from > to
    if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
        [$filters['date_from'], $filters['date_to']] = [$filters['date_to'], $filters['date_from']];
    }
    
    if (!empty($_GET['include_users']) && $user['role'] === 'super_admin') {
        $filters['include_users'] = true;
    }
    
    if (!empty($_GET['sort'])) {
        $filters['sort'] = $_GET['sort'];
    }
    
    // Get audit logs with role-based scoping
    $result = list_audit_logs($user, $filters, $page, 50);
    
    // Get filter options
    $available_actions = get_distinct_actions($user);
    $available_entity_types = get_distinct_entity_types($user);
    
    // Store current filters for view (use validated/swapped dates)
    $current_filters = [
        'action' => $_GET['action'] ?? '',
        'entity_type' => $_GET['entity_type'] ?? '',
        'user_search' => $_GET['user_search'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'include_users' => isset($_GET['include_users']),
        'sort' => $_GET['sort'] ?? 'date_desc'
    ];
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Audit Log']
    ];
    
    $title = 'Audit Log';
    
    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}
