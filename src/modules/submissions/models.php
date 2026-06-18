<?php

/**
 * Submissions Module - Database Operations
 *
 * Handles all submission, draft, and file operations.
 */

// ============================================================================
// FORM AVAILABILITY
// ============================================================================

/**
 * Get published forms available to a user
 */
/**
 * Get published forms available to a user
 * @return array<int, array<string, mixed>>
 */
function get_available_forms(int $user_id): array {
    $sql = "
        SELECT f.*, fv.id as version_id
        FROM forms f
        LEFT JOIN form_versions fv ON f.active_version_id = fv.id
        WHERE f.status = 'published'
        AND f.deleted_at IS NULL
        ORDER BY f.updated_at DESC
    ";
    
    return db_query($sql);
}

/**
 * Get prerequisite completion status for a form's prerequisites
 *
 * Returns an array of prerequisite forms with their name and whether the user
 * has completed them (has a submission with status 'submitted' or 'clarification_requested').
 *
 * @param int $user_id
 * @param array<int, int> $prerequisite_form_ids
 * @return array{met: bool, forms: list<array{id: int, name: string, uuid: string, completed: bool}>}
 */
function get_prerequisite_status(int $user_id, array $prerequisite_form_ids): array {
    if (empty($prerequisite_form_ids)) {
        return ['met' => true, 'forms' => []];
    }

    $placeholders = implode(',', array_fill(0, count($prerequisite_form_ids), '?'));
    $prereq_forms = db_query(
        "SELECT id, uuid, name FROM forms WHERE id IN ($placeholders) AND deleted_at IS NULL",
        $prerequisite_form_ids
    );

    $all_met = true;
    $result = [];
    foreach ($prereq_forms as $pf) {
        $submission = db_one(
            "SELECT id FROM submissions WHERE user_id = ? AND form_id = ? AND status IN ('submitted', 'clarification_requested') LIMIT 1",
            [$user_id, $pf['id']]
        );
        $completed = (bool)$submission;
        if (!$completed) {
            $all_met = false;
        }
        $result[] = [
            'id' => (int)$pf['id'],
            'name' => $pf['name'],
            'uuid' => $pf['uuid'],
            'completed' => $completed,
        ];
    }

    return ['met' => $all_met, 'forms' => $result];
}

/**
 * Paginated browse of published forms with optional search
 *
 * Excludes forms whose submission deadline has passed (closed forms).
 * Since the deadline lives in a JSON settings column, filtering happens in PHP.
 * When $user_id is provided, prerequisite completion status is attached to each form.
 *
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function browse_available_forms(?string $search = null, int $page = 1, int $per_page = 12, ?int $user_id = null): array {
    $sql = "
        SELECT f.id, f.uuid, f.name, f.description, f.status, f.settings, f.created_at
        FROM forms f
        WHERE f.status = 'published'
          AND f.deleted_at IS NULL
    ";
    $params = [];

    if ($search) {
        $sql .= " AND (f.name LIKE ? OR f.description LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY f.updated_at DESC";

    $all_forms = db_query($sql, $params);

    // Filter out closed forms (past deadline) and enrich with prerequisite data
    $filtered = [];
    foreach ($all_forms as $row) {
        decode_json_fields($row, ['settings']);
        if (is_form_closed($row)) {
            continue;
        }

        // Attach prerequisite status when user context is available
        $prereq_ids = $row['settings']['prerequisite_form_ids'] ?? [];
        if ($user_id !== null && !empty($prereq_ids)) {
            $row['prerequisite_status'] = get_prerequisite_status($user_id, $prereq_ids);
        } else {
            $row['prerequisite_status'] = ['met' => true, 'forms' => []];
        }

        $filtered[] = $row;
    }

    return paginate_array($filtered, $page, $per_page);
}

/**
 * Get recently closed forms (past deadline but within grace period)
 *
 * Returns published forms whose submission deadline passed within the last N days.
 * Used to show a "Recently Closed" section on the browse page so users know
 * these forms existed but are no longer accepting submissions.
 *
 * @return list<array<string, mixed>>
 */
function get_recently_closed_forms(?string $search = null, int $grace_days = 7): array {
    $sql = "
        SELECT f.id, f.uuid, f.name, f.description, f.status, f.settings, f.created_at
        FROM forms f
        WHERE f.status = 'published'
          AND f.deleted_at IS NULL
    ";
    $params = [];

    if ($search) {
        $sql .= " AND (f.name LIKE ? OR f.description LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY f.updated_at DESC";

    $all_forms = db_query($sql, $params);

    $recently_closed = [];
    foreach ($all_forms as $row) {
        decode_json_fields($row, ['settings']);
        if (!is_form_closed($row)) {
            continue;
        }
        $deadline = $row['settings']['submission_deadline'];
        if (is_recently_closed($deadline, $grace_days)) {
            $row['closed_at'] = $deadline;
            $recently_closed[] = $row;
        }
    }

    return $recently_closed;
}

// ============================================================================
// SUBMISSION OPERATIONS
// ============================================================================

/**
 * Create a new submission
 * @param array<string, mixed> $answers
 * @param array<string, mixed> $metadata
 */
function create_submission(int $form_id, int $version_id, int $user_id, array $answers, array $metadata = []): int {
    return db_transaction(function() use ($form_id, $version_id, $user_id, $answers, $metadata) {
        $submission_id = db_insert('submissions', [
            'uuid' => uuid(),
            'form_id' => $form_id,
            'form_version_id' => $version_id,
            'user_id' => $user_id,
            'status' => 'submitted',
            'answers' => json_encode($answers),
            'submitted_at' => now(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $submission_uuid = db_one('SELECT uuid FROM submissions WHERE id = ?', [$submission_id])['uuid'];
        $form_ref = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id]);
        log_audit('submitted', 'submission', $submission_id, ['form_uuid' => $form_ref['uuid'] ?? null], $submission_uuid);
        
        return $submission_id;
    });
}

/**
 * Get submission by ID
 * @return array<string, mixed>|null
 */
function get_submission_by_id(int $id): ?array {
    $submission = db_one('SELECT * FROM submissions WHERE id = ?', [$id]);
    if ($submission) {
        decode_json_fields($submission, ['answers', 'metadata']);
    }
    return $submission;
}

/**
 * Get submission by UUID
 * @return array<string, mixed>|null
 */
function get_submission_by_uuid(string $uuid): ?array {
    $submission = db_one('SELECT * FROM submissions WHERE uuid = ?', [$uuid]);
    if ($submission) {
        decode_json_fields($submission, ['answers', 'metadata']);
    }
    return $submission;
}

/**
 * Get user's submission for a specific form
 * @return array<string, mixed>|null
 */
function get_user_submission(int $user_id, int $form_id, ?string $status = null): ?array {
    $sql = "SELECT * FROM submissions WHERE user_id = ? AND form_id = ?";
    $params = [$user_id, $form_id];
    
    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY submitted_at DESC LIMIT 1";
    
    $submission = db_one($sql, $params);
    if ($submission) {
        decode_json_fields($submission, ['answers', 'metadata']);
    }
    return $submission;
}

/**
 * Count how many submissions a user has for a form (optionally filtered by status)
 */
function count_user_submissions(int $user_id, int $form_id, ?string $status = null): int {
    $sql = "SELECT COUNT(*) as count FROM submissions WHERE user_id = ? AND form_id = ?";
    $params = [$user_id, $form_id];

    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
    } else {
        // By default, count all non-draft statuses (submitted, clarification_requested, etc.)
        $sql .= " AND status != 'draft'";
    }

    $result = db_one($sql, $params);
    return (int) ($result['count'] ?? 0);
}

/**
 * Get all user submissions for a specific form, paginated
 * Used when forms allow multiple submissions
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function get_user_submissions_for_form(int $user_id, int $form_id, int $page = 1, int $per_page = 15): array {
    $sql = "
        SELECT s.*, f.name as form_name, f.uuid as form_uuid
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        WHERE s.user_id = ? AND s.form_id = ?
          AND s.status != 'draft'
        ORDER BY s.submitted_at DESC
    ";
    $params = [$user_id, $form_id];

    $result = paginate($sql, $params, $page, $per_page);

    // Decode JSON fields
    foreach ($result['rows'] as &$row) {
        decode_json_fields($row, ['answers', 'metadata']);
    }

    return $result;
}

/**
 * Get recent submissions for a user (for dashboard display)
 * @return array<int, array<string, mixed>>
 */
function get_user_submissions(int $user_id, int $limit = 5): array {
    $sql = "
        SELECT s.*, f.name as form_name
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        WHERE s.user_id = :user_id
        AND s.status != 'draft'
        ORDER BY s.submitted_at DESC
        LIMIT $limit
    ";
    
    return db_query($sql, ['user_id' => $user_id]);
}

/**
 * Count total non-draft submissions for a user across all forms
 */
function count_all_user_submissions(int $user_id): int {
    $row = db_one(
        "SELECT COUNT(*) as c FROM submissions WHERE user_id = ? AND status != 'draft'",
        [$user_id]
    );
    return (int)($row['c'] ?? 0);
}

/**
 * Paginated list of user's own submissions
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function list_user_submissions(int $user_id, ?string $status_filter = null, int $page = 1, int $per_page = 15): array {
    // When filtering by 'draft', query the drafts table instead
    if ($status_filter === 'draft') {
        $sql = "
            SELECT NULL as uuid, 'draft' as status, NULL as submitted_at, d.updated_at,
                   f.name as form_name, f.uuid as form_uuid
            FROM drafts d
            JOIN forms f ON d.form_id = f.id
            WHERE d.user_id = ?
              AND f.status = 'published'
              AND f.deleted_at IS NULL
            ORDER BY d.updated_at DESC
        ";
        return paginate($sql, [$user_id], $page, $per_page);
    }

    // When no status filter (showing "All"), show all non-draft submissions
    // Drafts are accessible via the dedicated ?status=draft filter
    if ($status_filter === null) {
        $sql = "
            SELECT s.uuid, s.status, s.submitted_at, s.updated_at,
                   f.name as form_name, f.uuid as form_uuid
            FROM submissions s
            JOIN forms f ON s.form_id = f.id
            WHERE s.user_id = ? AND s.status != 'draft'
            ORDER BY s.submitted_at DESC
        ";
        return paginate($sql, [$user_id], $page, $per_page);
    }

    // Specific status filter (not draft, not null)
    $sql = "
        SELECT s.uuid, s.status, s.submitted_at, s.updated_at,
               f.name as form_name, f.uuid as form_uuid
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        WHERE s.user_id = ? AND s.status = ?
        ORDER BY s.submitted_at DESC
    ";

    return paginate($sql, [$user_id, $status_filter], $page, $per_page);
}

/**
 * List submissions for a form (admin view)
 * @return array<string, mixed>
 */
function list_submissions(int $form_id, ?string $status_filter = null, int $page = 1, int $per_page = 20): array {
    $where = ['form_id = ?', "s.status != 'draft'"];
    $params = [$form_id];
    
    if ($status_filter !== null) {
        $where[] = 's.status = ?';
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where);
    $sql = "SELECT s.*, u.name as user_name, u.email as user_email
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE $where_clause
            ORDER BY s.submitted_at DESC";
    
    $result = paginate($sql, $params, $page, $per_page);
    
    // Decode JSON fields
    foreach ($result['rows'] as &$row) {
        decode_json_fields($row, ['answers', 'metadata']);
    }
    
    return $result;
}

/**
 * Build where clause and parameters for admin submissions queries.
 *
 * @param int|null $created_by User ID who created the forms (null for super_admin to see all)
 * @param array<string, mixed> $filters Associative array of filters: status, form_id, date_from, date_to, include_deleted, search
 * @return array{where: string, params: array<string, mixed>}
 */
function build_all_submissions_filters(?int $created_by, array $filters = []): array {
    $where = ['1=1'];
    $params = [];
    
    // SECURITY: Filter by form ownership AND permissions
    // Super admins ($created_by = null) see all, admins see forms they own or have permissions for
    if ($created_by !== null) {
        $where[] = '(f.created_by = :created_by OR EXISTS (
            SELECT 1 FROM permissions p
            WHERE p.form_id = f.id
            AND p.user_id = :perm_user_id
        ))';
        $params['created_by'] = $created_by;
        $params['perm_user_id'] = $created_by;
    }
    
    // Exclude draft submissions (only show completed submissions)
    $where[] = "s.status != 'draft'";
    
    // Status filter
    if (!empty($filters['status'])) {
        $where[] = 's.status = :status';
        $params['status'] = $filters['status'];
    }
    
    // Form filter
    if (!empty($filters['form_id'])) {
        $where[] = 'f.id = :form_id';
        $params['form_id'] = $filters['form_id'];
    }
    
    // Date range filters
    if (!empty($filters['date_from'])) {
        $where[] = 's.submitted_at >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        // Add 23:59:59 to include entire day
        $where[] = 's.submitted_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Deleted forms filter - exclude deleted by default unless explicitly included
    if (empty($filters['include_deleted'])) {
        $where[] = 'f.deleted_at IS NULL';
    }
    
    // User search filter (by name or email)
    if (!empty($filters['search'])) {
        $where[] = '(u.name LIKE :search_name OR u.email LIKE :search_email)';
        $params['search_name'] = '%' . $filters['search'] . '%';
        $params['search_email'] = '%' . $filters['search'] . '%';
    }
    
    // Clarification status filter (find submissions with clarifications in a given status)
    if (!empty($filters['clarification_status'])) {
        $where[] = "EXISTS (SELECT 1 FROM clarification_requests cr WHERE cr.submission_id = s.id AND cr.status = :clarification_status)";
        $params['clarification_status'] = $filters['clarification_status'];
    }
    
    $where_clause = implode(' AND ', $where);

    return ['where' => $where_clause, 'params' => $params];
}

/**
 * List all submissions across all forms (admin view)
 *
 * SECURITY: Respects form ownership - admins only see submissions from their own forms,
 * super_admins see all submissions. Includes submissions from soft-deleted forms.
 *
 * @param int|null $created_by User ID who created the forms (null for super_admin to see all)
 * @param array<string, mixed> $filters Associative array of filters: status, form_id, date_from, date_to, include_deleted, search
 * @param int $page Current page number
 * @param int $per_page Items per page
 * @return array<string, mixed> Paginated results with submission data
 */
function list_all_submissions(?int $created_by = null, array $filters = [], int $page = 1, int $per_page = 20): array {
    $filter_data = build_all_submissions_filters($created_by, $filters);
    $where_clause = $filter_data['where'];
    $params = $filter_data['params'];
    
    // Determine sort order
    $order_by = 's.submitted_at DESC';
    if (!empty($filters['sort'])) {
        $allowed_sorts = [
            'date_asc' => 's.submitted_at ASC',
            'date_desc' => 's.submitted_at DESC',
            'form_asc' => 'f.name ASC, s.submitted_at DESC',
            'form_desc' => 'f.name DESC, s.submitted_at DESC',
            'user_asc' => 'u.name ASC, s.submitted_at DESC',
            'user_desc' => 'u.name DESC, s.submitted_at DESC',
            'status_asc' => 's.status ASC, s.submitted_at DESC',
            'status_desc' => 's.status DESC, s.submitted_at DESC'
        ];
        $order_by = $allowed_sorts[$filters['sort']] ?? $order_by;
    }
    
    $sql = "SELECT
                s.uuid, s.status, s.submitted_at, s.user_id,
                f.name as form_name, f.uuid as form_uuid, f.deleted_at as form_deleted_at, f.id as form_id,
                u.name as user_name, u.email as user_email,
                (SELECT cr.status FROM clarification_requests cr WHERE cr.submission_id = s.id ORDER BY cr.created_at DESC LIMIT 1) as clarification_status
            FROM submissions s
            LEFT JOIN forms f ON s.form_id = f.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE $where_clause
            ORDER BY $order_by";
    
    return paginate($sql, $params, $page, $per_page);
}

/**
 * Aggregate totals for admin submissions list (respects same filters as list_all_submissions).
 *
 * @param int|null $created_by User ID who created the forms (null for super_admin to see all)
 * @param array<string, mixed> $filters Associative array of filters
 * @return array<string, int>
 */
function get_all_submissions_stats(?int $created_by = null, array $filters = []): array {
    $filter_data = build_all_submissions_filters($created_by, $filters);
    $where_clause = $filter_data['where'];
    $params = $filter_data['params'];

    $row = db_one(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN s.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN s.status = 'clarification_requested' THEN 1 ELSE 0 END) as clarification_requested,
            SUM(CASE WHEN f.deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_forms
        FROM submissions s
        LEFT JOIN forms f ON s.form_id = f.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE $where_clause",
        $params
    );

    return [
        'total' => (int)($row['total'] ?? 0),
        'submitted' => (int)($row['submitted'] ?? 0),
        'clarification_requested' => (int)($row['clarification_requested'] ?? 0),
        'deleted_forms' => (int)($row['deleted_forms'] ?? 0)
    ];
}

/**
 * Get list of forms for filter dropdown (respects ownership)
 *
 * SECURITY: Only returns forms the admin has access to
 * @return array<int, array<string, mixed>>
 */
function get_forms_for_filter(?int $created_by = null): array {
    $sql = "SELECT id, name, uuid, deleted_at
            FROM forms
            WHERE 1=1";
    $params = [];
    
    if ($created_by !== null) {
        $sql .= " AND (created_by = :created_by OR EXISTS (
            SELECT 1 FROM permissions p
            WHERE p.form_id = forms.id
            AND p.user_id = :perm_user_id
        ))";
        $params['created_by'] = $created_by;
        $params['perm_user_id'] = $created_by;
    }
    
    $sql .= " ORDER BY deleted_at IS NULL DESC, name ASC";
    
    return db_query($sql, $params);
}

/**
 * Update submission answers (for editing)
 * @param array<string, mixed> $answers
 */
function update_submission_answers(int $id, array $answers): void {
    db_transaction(function() use ($id, $answers) {
        db_update('submissions',
            ['answers' => json_encode($answers), 'updated_at' => now()],
            'id = ?',
            [$id]
        );
        
        $submission_uuid = db_one('SELECT uuid FROM submissions WHERE id = ?', [$id])['uuid'];
        log_audit('updated', 'submission', $id, ['action' => 'edited_answers'], $submission_uuid);
    });
}

// ============================================================================
// DRAFT OPERATIONS
// ============================================================================

/**
 * Save or update draft
 * @param array<string, mixed> $data
 */
function save_draft(int $form_id, int $version_id, int $user_id, array $data): void {
    db_transaction(function() use ($form_id, $version_id, $user_id, $data) {
        // Lock to prevent race condition creating duplicate drafts
        $existing = db_one(
            'SELECT id FROM drafts WHERE form_id = ? AND user_id = ? FOR UPDATE',
            [$form_id, $user_id]
        );
        
        if ($existing) {
            db_update('drafts',
                [
                    'form_version_id' => $version_id,
                    'data' => json_encode($data),
                    'updated_at' => now()
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            db_insert('drafts', [
                'form_id' => $form_id,
                'form_version_id' => $version_id,
                'user_id' => $user_id,
                'data' => json_encode($data),
                'updated_at' => now()
            ]);
        }
    });
}

/**
 * Get draft for user and form
 * @return array<string, mixed>|null
 */
function get_draft(int $form_id, int $user_id): ?array {
    return db_one(
        'SELECT * FROM drafts WHERE form_id = ? AND user_id = ?',
        [$form_id, $user_id]
    );
}

/**
 * Get all drafts for a user
 * @return array<int, array<string, mixed>>
 */
function get_user_drafts(int $user_id): array {
    $sql = "
        SELECT d.*, f.name as form_name, f.uuid as form_uuid
        FROM drafts d
        JOIN forms f ON d.form_id = f.id
        WHERE d.user_id = :user_id
          AND f.status = 'published'
          AND f.deleted_at IS NULL
        ORDER BY d.updated_at DESC
    ";
    
    return db_query($sql, ['user_id' => $user_id]);
}

/**
 * Delete draft
 */
function delete_draft(int $form_id, int $user_id): void {
    db_exec('DELETE FROM drafts WHERE form_id = ? AND user_id = ?', [$form_id, $user_id]);
}

// ============================================================================
// FILE OPERATIONS
// ============================================================================

/**
 * Handle file upload and storage
 * @param array<string, mixed> $file
 * @return array<string, mixed>
 */
function handle_file_upload(array $file, int $question_id, int $user_id, ?int $submission_id = null): array {
    global $config;

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }
    
    // Generate unique filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $stored_name = uuid() . '.' . $ext;
    $upload_dir = rtrim($config['storage']['local_path'], '/') . '/';
    $stored_path = $upload_dir . $stored_name;
    
    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $stored_path)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    // Use server-detected MIME type, not browser-supplied $_FILES['type']
    $detected_mime = mime_content_type($stored_path) ?: 'application/octet-stream';
    
    // Save file record (store just the filename; full path is resolved via config)
    $file_id = db_insert('files', [
        'submission_id' => $submission_id,
        'question_id' => $question_id,
        'user_id' => $user_id,
        'original_name' => $file['name'],
        'stored_path' => $stored_name,
        'mime_type' => $detected_mime,
        'size_bytes' => $file['size'],
        'created_at' => now()
    ]);
    
    return [
        'success' => true,
        'file_id' => $file_id,
        'filename' => $file['name'],
        'stored_path' => $stored_name
    ];
}

/**
 * Get files for a submission
 */
/** @return array<int, array<string, mixed>> */
function get_files(int $submission_id): array {
    return db_query('SELECT * FROM files WHERE submission_id = ? ORDER BY created_at', [$submission_id]);
}

/**
 * Get single file by ID
 * @return array<string, mixed>|null
 */
function get_file(int $file_id): ?array {
    return db_one('SELECT * FROM files WHERE id = ?', [$file_id]);
}

/**
 * Safely delete a physical file and its DB record.
 * @param array<string, mixed> $file
 */
function delete_file_record_and_storage(array $file): void {
    global $config;

    $storage_root = realpath($config['storage']['local_path'] ?? '');
    if ($storage_root !== false) {
        $candidate = $storage_root . DIRECTORY_SEPARATOR . basename((string)$file['stored_path']);
        $resolved = realpath($candidate);
        if ($resolved !== false && str_starts_with($resolved, $storage_root . DIRECTORY_SEPARATOR) && is_file($resolved)) {
            unlink($resolved);
        }
    }

    db_exec('DELETE FROM files WHERE id = ?', [(int)$file['id']]);
}

/**
 * Delete a user-owned unlinked (pre-submission) file.
 */
function delete_unlinked_user_file(int $file_id, int $user_id): bool {
    $file = db_one(
        'SELECT * FROM files WHERE id = ? AND user_id = ? AND submission_id IS NULL',
        [$file_id, $user_id]
    );

    if (!$file) {
        return false;
    }

    delete_file_record_and_storage($file);
    return true;
}

/**
 * Delete a file linked to a specific submission owned by a user.
 */
function delete_submission_owned_file(int $file_id, int $submission_id, int $user_id): bool {
    $file = db_one(
        'SELECT * FROM files WHERE id = ? AND submission_id = ? AND user_id = ?',
        [$file_id, $submission_id, $user_id]
    );

    if (!$file) {
        return false;
    }

    delete_file_record_and_storage($file);
    return true;
}

/**
 * Remove files that were previously referenced by file questions but are no longer referenced.
 *
 * @param array<int, array<string, mixed>> $questions
 * @param array<string, mixed> $previous_answers
 * @param array<string, mixed> $new_answers
 */
function cleanup_removed_file_answers(array $questions, array $previous_answers, array $new_answers, int $user_id, ?int $submission_id = null): void {
    $to_delete = [];

    foreach ($questions as $question) {
        if (($question['type'] ?? null) !== 'file') {
            continue;
        }

        $qid = (string)$question['id'];
        $old_file_id = isset($previous_answers[$qid]) && is_numeric($previous_answers[$qid]) ? (int)$previous_answers[$qid] : null;
        $new_file_id = isset($new_answers[$qid]) && is_numeric($new_answers[$qid]) ? (int)$new_answers[$qid] : null;

        if ($old_file_id !== null && $old_file_id > 0 && $old_file_id !== $new_file_id) {
            $to_delete[$old_file_id] = true;
        }
    }

    foreach (array_keys($to_delete) as $file_id) {
        if ($submission_id === null) {
            delete_unlinked_user_file((int)$file_id, $user_id);
        } else {
            delete_submission_owned_file((int)$file_id, $submission_id, $user_id);
        }
    }
}

/**
 * Associate pre-uploaded files (submission_id = null) with a submission.
 * Files are uploaded via AJAX before the submission exists, so this must
 * be called after create_submission() or update_submission_answers() to
 * link them correctly.
 *
 * @param int[] $file_ids
 */
function associate_files_with_submission(array $file_ids, int $submission_id, int $user_id): void {
    foreach ($file_ids as $file_id) {
        $file_id = (int)$file_id;
        if ($file_id > 0) {
            db_update(
                'files',
                ['submission_id' => $submission_id],
                'id = ? AND user_id = ? AND submission_id IS NULL',
                [$file_id, $user_id]
            );
        }
    }
}

/**
 * Resolve file metadata (name, size) for file question answers.
 * Used to display file indicators during edit and validation-error re-renders.
 *
 * @param array<int, array<string, mixed>> $questions Form questions
 * @param array<string, mixed> $answers Answer data keyed by question ID
 * @return array<int, array{original_name: string, size_bytes: int}> Keyed by question ID
 */
function resolve_file_metadata_for_answers(array $questions, array $answers): array {
    $file_meta = [];
    foreach ($questions as $q) {
        if ($q['type'] !== 'file') continue;
        $file_id = $answers[(string)$q['id']] ?? null;
        if ($file_id && is_numeric($file_id)) {
            $file = get_file((int)$file_id);
            if ($file) {
                $file_meta[$q['id']] = [
                    'original_name' => $file['original_name'],
                    'size_bytes' => (int)$file['size_bytes'],
                ];
            }
        }
    }
    return $file_meta;
}

/**
 * Save file reference to submission
 * @param array<string, mixed> $file_info
 */
function save_file(int $submission_id, int $question_id, int $user_id, array $file_info): int {
    return db_insert('files', [
        'submission_id' => $submission_id,
        'question_id' => $question_id,
        'user_id' => $user_id,
        'original_name' => $file_info['original_name'],
        'stored_path' => $file_info['stored_path'],
        'mime_type' => $file_info['mime_type'],
        'size_bytes' => $file_info['size_bytes'],
        'created_at' => now()
    ]);
}
