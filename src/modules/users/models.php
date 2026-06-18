<?php

/**
 * @return array<string, mixed>|null
 */
function find_user_by_email(string $email): ?array {
    return db_one('SELECT * FROM users WHERE email = :email', ['email' => $email]);
}

/**
 * @return array<string, mixed>|null
 */
function find_user_by_id(int $id): ?array {
    return db_one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
}

function create_user(string $email, string $password, ?string $name, string $role = 'user', string $status = 'active', ?int $invited_by = null): int {
    return db_insert('users', [
        'uuid' => uuid(),
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'role' => $role,
        'status' => $status,
        'invited_by' => $invited_by,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param array<string, mixed> $data
 */
function update_user(int $id, array $data): int {
    $data['updated_at'] = now();
    return db_update('users', $data, 'id = ?', [$id]);
}

/**
 * @return array<string, mixed>
 */
function list_users(?string $role_filter = null, int $page = 1, int $per_page = 25): array {
    $sql = 'SELECT * FROM users WHERE 1=1';
    $params = [];
    
    if ($role_filter) {
        $sql .= ' AND role = :role';
        $params['role'] = $role_filter;
    }
    
    $sql .= ' ORDER BY created_at DESC';
    
    return paginate($sql, $params, $page, $per_page);
}

/**
 * @param array<string>|null $visible_roles
 * @return array<string, int>
 */
function get_user_count_by_role(?array $visible_roles = null): array {
    $sql = 'SELECT role, COUNT(*) as count FROM users';
    $params = [];
    
    if ($visible_roles !== null) {
        $placeholders = implode(',', array_fill(0, count($visible_roles), '?'));
        $sql .= ' WHERE role IN (' . $placeholders . ')';
        $params = $visible_roles;
    }
    
    $sql .= ' GROUP BY role';
    
    $rows = db_query($sql, $params);
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['role']] = (int)$row['count'];
    }
    return $counts;
}

/**
 * @param array<string>|null $visible_roles
 * @return array<string, mixed>
 */
function search_users(?string $role, ?string $status, ?string $search, int $page = 1, int $per_page = 25, ?array $visible_roles = null): array {
    $sql = 'SELECT u.id, u.uuid, u.email, u.name, u.role, u.status, u.last_login_at, u.created_at, 
                   inv.name as invited_by_name 
            FROM users u 
            LEFT JOIN users inv ON u.invited_by = inv.id 
            WHERE 1=1';
    $params = [];
    
    // SECURITY: Only show users with roles the acting user can see
    if ($visible_roles !== null) {
        $placeholders = implode(',', array_fill(0, count($visible_roles), '?'));
        $sql .= ' AND u.role IN (' . $placeholders . ')';
        $params = array_merge($params, $visible_roles);
    }
    
    if ($role) {
        $sql .= ' AND u.role = ?';
        $params[] = $role;
    }
    
    if ($status) {
        $sql .= ' AND u.status = ?';
        $params[] = $status;
    }
    
    if ($search) {
        $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    $sql .= ' ORDER BY u.created_at DESC';
    
    return paginate($sql, $params, $page, $per_page);
}

/**
 * @return array<string, mixed>|null
 */
function get_user_for_admin(int $id): ?array {
    return db_one('SELECT u.*, inv.name as invited_by_name, inv.email as invited_by_email 
                   FROM users u 
                   LEFT JOIN users inv ON u.invited_by = inv.id 
                   WHERE u.id = :id', ['id' => $id]);
}

/**
 * @return array<string, mixed>|null
 */
function get_user_by_uuid(string $uuid): ?array {
    return db_one('SELECT u.*, inv.name as invited_by_name, inv.email as invited_by_email 
                   FROM users u 
                   LEFT JOIN users inv ON u.invited_by = inv.id 
                   WHERE u.uuid = :uuid', ['uuid' => $uuid]);
}

/**
 * @param array<string, mixed> $acting_user
 * @param array<string, mixed> $target_user
 */
function can_manage_user(array $acting_user, array $target_user): bool {
    $hierarchy = ['user' => 0, 'reviewer' => 1, 'admin' => 2, 'super_admin' => 3];
    
    $acting_level = $hierarchy[$acting_user['role']] ?? 0;
    $target_level = $hierarchy[$target_user['role']] ?? 0;
    
    // Super admin can manage anyone except themselves for critical changes
    if ($acting_user['role'] === 'super_admin') {
        return true;
    }
    
    // Admin can manage reviewer and user only
    if ($acting_user['role'] === 'admin') {
        return in_array($target_user['role'], ['reviewer', 'user']);
    }
    
    return false;
}

/**
 * @param array<string, mixed> $acting_user
 */
function can_assign_role(array $acting_user, string $target_role): bool {
    // Super admin can assign admin, reviewer, or user
    if ($acting_user['role'] === 'super_admin') {
        return in_array($target_role, ['admin', 'reviewer', 'user']);
    }
    
    // Admin can only assign reviewer or user
    if ($acting_user['role'] === 'admin') {
        return in_array($target_role, ['reviewer', 'user']);
    }
    
    return false;
}

function update_user_role(int $id, string $new_role): int {
    return db_update('users', [
        'role' => $new_role,
        'updated_at' => now(),
    ], 'id = ?', [$id]);
}

function update_user_status(int $id, string $new_status): int {
    return db_update('users', [
        'status' => $new_status,
        'updated_at' => now(),
    ], 'id = ?', [$id]);
}

function admin_reset_password(int $id, string $new_password): int {
    return db_update('users', [
        'password_hash' => password_hash($new_password, PASSWORD_DEFAULT),
        'updated_at' => now(),
    ], 'id = ?', [$id]);
}

function touch_login(int $id): void {
    db_update('users', ['last_login_at' => now()], 'id = ?', [$id]);
}

/**
 * @param array<string, mixed> $acting_user
 * @return array<string, string>
 */
function get_assignable_roles(array $acting_user): array {
    if ($acting_user['role'] === 'super_admin') {
        return [
            'admin' => 'Admin',
            'reviewer' => 'Reviewer',
            'user' => 'User'
        ];
    }
    
    if ($acting_user['role'] === 'admin') {
        return [
            'reviewer' => 'Reviewer',
            'user' => 'User'
        ];
    }
    
    return [];
}

/**
 * @param array<string, mixed> $acting_user
 * @return array<string>
 */
function get_visible_roles(array $acting_user): array {
    if ($acting_user['role'] === 'super_admin') {
        return ['super_admin', 'admin', 'reviewer', 'user'];
    }
    
    if ($acting_user['role'] === 'admin') {
        return ['admin', 'reviewer', 'user'];  // Cannot see super_admins
    }
    
    if ($acting_user['role'] === 'reviewer') {
        return ['reviewer', 'user'];  // Cannot see admins or super_admins
    }
    
    return ['user'];  // Regular users can only see other users
}

/**
 * Get aggregated statistics for a user's profile
 *
 * @return array{programs: array<string, int>, submissions: array<string, int>, permissions_count: int, clarifications: array<string, int>}
 */
function get_user_statistics(int $user_id): array {
    // Program submissions by status
    $program_rows = db_query(
        "SELECT status, COUNT(*) as c FROM program_submissions WHERE user_id = ? GROUP BY status",
        [$user_id]
    );
    $programs = ['total' => 0, 'draft' => 0, 'submitted' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($program_rows as $r) {
        $programs[$r['status']] = (int)$r['c'];
        if ($r['status'] !== 'draft') {
            $programs['total'] += (int)$r['c'];
        }
    }

    // Form submissions by status
    $sub_rows = db_query(
        "SELECT status, COUNT(*) as c FROM submissions WHERE user_id = ? GROUP BY status",
        [$user_id]
    );
    $submissions = ['total' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0, 'under_review' => 0];
    foreach ($sub_rows as $r) {
        $submissions[$r['status']] = (int)$r['c'];
        $submissions['total'] += (int)$r['c'];
    }

    // Permissions count
    $perm_row = db_one("SELECT COUNT(*) as c FROM permissions WHERE user_id = ?", [$user_id]);
    $permissions_count = (int)($perm_row['c'] ?? 0);

    // Clarification requests (linked via submissions the user owns)
    $clar_rows = db_query(
        "SELECT cr.status, COUNT(*) as c
         FROM clarification_requests cr
         JOIN submissions s ON cr.submission_id = s.id
         WHERE s.user_id = ?
         GROUP BY cr.status",
        [$user_id]
    );
    $clarifications = ['total' => 0, 'open' => 0, 'responded' => 0, 'resolved' => 0];
    foreach ($clar_rows as $r) {
        $clarifications[$r['status']] = (int)$r['c'];
        $clarifications['total'] += (int)$r['c'];
    }

    return compact('programs', 'submissions', 'permissions_count', 'clarifications');
}

/**
 * Get a user's recent activity from the audit log
 *
 * @return list<array<string, mixed>>
 */
function get_user_recent_activity(int $user_id, int $limit = 20): array {
    return db_query(
        "SELECT al.action, al.entity_type, al.entity_id,
                CASE al.entity_type
                    WHEN 'user'                  THEN (SELECT uuid FROM users                  WHERE id = al.entity_id)
                    WHEN 'form'                  THEN (SELECT uuid FROM forms                  WHERE id = al.entity_id)
                    WHEN 'submission'            THEN (SELECT uuid FROM submissions             WHERE id = al.entity_id)
                    WHEN 'clarification_request' THEN (SELECT uuid FROM clarification_requests WHERE id = al.entity_id)
                    WHEN 'program'               THEN (SELECT uuid FROM programs                WHERE id = al.entity_id)
                    WHEN 'program_submission'    THEN (SELECT uuid FROM program_submissions     WHERE id = al.entity_id)
                    ELSE NULL
                END as entity_uuid,
                al.details, al.created_at
         FROM audit_log al
         WHERE al.user_id = ?
         ORDER BY al.created_at DESC
         LIMIT ?",
        [$user_id, $limit]
    );
}

/**
 * Paginated audit-log activity for a user (admin profile activity tab)
 *
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function get_user_activity_paginated(int $user_id, int $page = 1, int $per_page = 20): array {
    return paginate(
        "SELECT action, entity_type, entity_id,
                CASE entity_type
                    WHEN 'user'                  THEN (SELECT uuid FROM users                  WHERE id = entity_id)
                    WHEN 'form'                  THEN (SELECT uuid FROM forms                  WHERE id = entity_id)
                    WHEN 'submission'            THEN (SELECT uuid FROM submissions             WHERE id = entity_id)
                    WHEN 'clarification_request' THEN (SELECT uuid FROM clarification_requests WHERE id = entity_id)
                    WHEN 'program'               THEN (SELECT uuid FROM programs                WHERE id = entity_id)
                    WHEN 'program_submission'    THEN (SELECT uuid FROM program_submissions     WHERE id = entity_id)
                    ELSE NULL
                END as entity_uuid,
                details, created_at
         FROM audit_log
         WHERE user_id = ?
         ORDER BY created_at DESC",
        [$user_id],
        $page,
        $per_page
    );
}
