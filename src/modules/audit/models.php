<?php

/**
 * Audit Module - Models
 *
 * Query functions for audit logs with role-based scoping
 */

/**
 * Get the form IDs an admin has access to (owned or via permissions).
 *
 * @return list<int>
 */
function get_admin_form_ids(int $user_id): array {
    $rows = db_query(
        "SELECT id FROM forms WHERE created_by = ?
         UNION
         SELECT form_id FROM permissions WHERE user_id = ?",
        [$user_id, $user_id]
    );
    return array_map('intval', array_column($rows, 'id'));
}

/**
 * Build role-based WHERE clauses for audit log queries
 *
 * Centralises the scoping logic shared by list_audit_logs,
 * get_distinct_actions, and get_distinct_entity_types.
 *
 * Scoping rules:
 *  - Reviewer:    own actions only
 *  - Admin:       own actions + reviewer actions scoped to the admin's forms
 *  - Super Admin: all non-user actions (user actions toggleable)
 *
 * @param array{id:int,role:string} $user Current user
 * @param bool $include_users Whether to include user-role actions (super_admin toggle)
 * @return array{0: array<string>, 1: array<string, mixed>} [where_clauses, params]
 */
function build_audit_scope(array $user, bool $include_users = false): array {
    // Enforce invariant: only super_admins may widen scope to include user-role entries
    $include_users = $include_users && $user['role'] === 'super_admin';

    $where_clauses = [];
    $params = [];

    if ($user['role'] === 'reviewer') {
        // Reviewers only see their own actions
        $where_clauses[] = 'al.user_id = :current_user_id';
        $params['current_user_id'] = $user['id'];
    } elseif ($user['role'] === 'admin') {
        $admin_form_ids = get_admin_form_ids($user['id']);

        if (empty($admin_form_ids)) {
            // Admin has no forms yet — only own actions
            $where_clauses[] = 'al.user_id = :current_user_id';
            $params['current_user_id'] = $user['id'];
        } else {
            // Safe: values are intval-cast database IDs, not user input
            $form_id_list = implode(',', $admin_form_ids);

            // Admins see their own actions + reviewer actions scoped to
            // forms the admin owns or has permissions for.
            $where_clauses[] = "(
                al.user_id = :current_user_id
                OR (
                    u.role = :reviewer_role AND (
                        (al.entity_type = 'form' AND al.entity_id IN ({$form_id_list}))
                        OR (al.entity_type = 'submission' AND al.entity_id IN (
                            SELECT id FROM submissions WHERE form_id IN ({$form_id_list})
                        ))
                        OR (al.entity_type = 'form_version' AND al.entity_id IN (
                            SELECT id FROM form_versions WHERE form_id IN ({$form_id_list})
                        ))
                        OR (al.entity_type = 'clarification_request' AND al.entity_id IN (
                            SELECT cr.id FROM clarification_requests cr
                            JOIN submissions s ON cr.submission_id = s.id
                            WHERE s.form_id IN ({$form_id_list})
                        ))
                        OR (al.entity_type IN ('question', 'questions') AND al.entity_id IN (
                            SELECT q.id FROM questions q
                            JOIN form_versions fv ON q.form_version_id = fv.id
                            WHERE fv.form_id IN ({$form_id_list})
                        ))
                    )
                )
            )";
            $params['current_user_id'] = $user['id'];
            $params['reviewer_role'] = 'reviewer';
        }
    } elseif ($user['role'] === 'super_admin') {
        // Super admins see all actions EXCEPT user actions (unless toggled)
        if (!$include_users) {
            $where_clauses[] = "(u.role IS NULL OR u.role != 'user')";
        }
    }

    return [$where_clauses, $params];
}

/**
 * Get paginated audit logs with role-based scoping and filters
 *
 * @param array{id:int,role:string} $user Current user
 * @param array<string, mixed> $filters Filter parameters
 * @param int $page Current page
 * @param int $per_page Items per page
 * @return array{rows: list<array<string,mixed>>, total:int, pages:int, page:int, per_page:int}
 */
function list_audit_logs(array $user, array $filters = [], int $page = 1, int $per_page = 50): array {
    [$where_clauses, $params] = build_audit_scope($user, $filters['include_users'] ?? false);
    
    // Filter by action type
    if (!empty($filters['action'])) {
        $where_clauses[] = 'al.action = :action';
        $params['action'] = $filters['action'];
    }
    
    // Filter by entity type
    if (!empty($filters['entity_type'])) {
        $where_clauses[] = 'al.entity_type = :entity_type';
        $params['entity_type'] = $filters['entity_type'];
    }
    
    // Filter by user (keyword search on name or email)
    if (!empty($filters['user_search'])) {
        $where_clauses[] = '(u.name LIKE :user_search_name OR u.email LIKE :user_search_email)';
        $user_search = '%' . $filters['user_search'] . '%';
        $params['user_search_name'] = $user_search;
        $params['user_search_email'] = $user_search;
    }
    
    // Filter by date range
    if (!empty($filters['date_from'])) {
        $where_clauses[] = 'al.created_at >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (!empty($filters['date_to'])) {
        $where_clauses[] = 'al.created_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Build WHERE clause
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Sort options
    $sort = $filters['sort'] ?? 'date_desc';
    $order_sql = match($sort) {
        'date_asc' => 'ORDER BY al.created_at ASC',
        'action_asc' => 'ORDER BY al.action ASC, al.created_at DESC',
        'action_desc' => 'ORDER BY al.action DESC, al.created_at DESC',
        'user_asc' => 'ORDER BY u.name ASC, al.created_at DESC',
        'user_desc' => 'ORDER BY u.name DESC, al.created_at DESC',
        'entity_asc' => 'ORDER BY al.entity_type ASC, al.created_at DESC',
        'entity_desc' => 'ORDER BY al.entity_type DESC, al.created_at DESC',
        default => 'ORDER BY al.created_at DESC',
    };
    
    // Main query
    $sql = "
        SELECT
            al.id,
            al.user_id,
            al.action,
            al.entity_type,
            al.entity_id,
            al.entity_uuid,
            al.details,
            al.ip,
            al.created_at,
            u.name as user_name,
            u.email as user_email,
            u.role as user_role,
            u.uuid as user_uuid
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        {$where_sql}
        {$order_sql}
    ";
    
    return paginate($sql, $params, $page, $per_page);
}

/**
 * Get distinct action types from audit log (for filter dropdown)
 *
 * @param array{id:int,role:string} $user Current user
 * @return array<string>
 */
function get_distinct_actions(array $user): array {
    [$where_clauses, $params] = build_audit_scope($user);
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $sql = "
        SELECT DISTINCT al.action
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        {$where_sql}
        ORDER BY al.action ASC
    ";
    
    $results = db_query($sql, $params);
    return array_column($results, 'action');
}

/**
 * Get distinct entity types from audit log (for filter dropdown)
 *
 * @param array{id:int,role:string} $user Current user
 * @return array<string>
 */
function get_distinct_entity_types(array $user): array {
    [$where_clauses, $params] = build_audit_scope($user);
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    $sql = "
        SELECT DISTINCT al.entity_type
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        {$where_sql}
        ORDER BY al.entity_type ASC
    ";
    
    $results = db_query($sql, $params);
    return array_column($results, 'entity_type');
}
