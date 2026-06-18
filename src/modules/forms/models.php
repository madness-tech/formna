<?php

/**
 * Forms Module - Database Operations
 * 
 * Handles all form and question database operations following functional programming pattern.
 */

const MAX_QUESTIONS_PER_VERSION = 250;

// ============================================================================
// FORM OPERATIONS
// ============================================================================

/**
 * Create a new form with initial draft version
 * @return array{form_id: int, version_id: int}
 */
function create_form(string $name, string $description, string $instructions, int $created_by): array {
    return db_transaction(function() use ($name, $description, $instructions, $created_by) {
        $form_id = db_insert('forms', [
            'uuid' => uuid(),
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
            'status' => 'draft',
            'created_by' => $created_by,
            'settings' => json_encode([
                'is_editable_after_submit' => false,
                'editable_days' => null,
                'editable_until_date' => null,
                'submission_deadline' => null,
                'expiry_days' => null,
                'submission_limit' => 1,
                'prerequisite_form_ids' => [],
                'conditional_access' => []
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create initial draft version
        $version_id = create_version($form_id);

        $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id])['uuid'];
        log_audit('created', 'form', $form_id, ['name' => $name], $form_uuid);

        return ['form_id' => $form_id, 'version_id' => $version_id];
    });
}

/**
 * Get form by numeric ID
 * @return array<string, mixed>|null
 */
function get_form_by_id(int $id): ?array {
    $form = db_one('SELECT * FROM forms WHERE id = ? AND deleted_at IS NULL', [$id]);
    if ($form) {
        decode_json_fields($form, ['settings']);
    }
    return $form;
}

/**
 * Get form by UUID
 * @return array<string, mixed>|null
 */
function get_form_by_uuid(string $uuid): ?array {
    $form = db_one('SELECT * FROM forms WHERE uuid = ? AND deleted_at IS NULL', [$uuid]);
    if ($form) {
        decode_json_fields($form, ['settings']);
    }
    return $form;
}

/**
 * Get form by numeric ID including soft-deleted forms
 *
 * Used for viewing submissions from deleted forms
 * @return array<string, mixed>|null
 */
function get_form_by_id_including_deleted(int $id): ?array {
    $form = db_one('SELECT * FROM forms WHERE id = ?', [$id]);
    if ($form) {
        decode_json_fields($form, ['settings']);
    }
    return $form;
}

/**
 * Get form by UUID including soft-deleted forms
 *
 * Used for viewing submissions from deleted forms
 * @return array<string, mixed>|null
 */
function get_form_by_uuid_including_deleted(string $uuid): ?array {
    $form = db_one('SELECT * FROM forms WHERE uuid = ?', [$uuid]);
    if ($form) {
        decode_json_fields($form, ['settings']);
    }
    return $form;
}

/**
 * List forms with optional filters and pagination
 *
 * Supports a 'closed' pseudo-status filter that returns published forms
 * whose submission deadline has passed. When filtering by 'published',
 * forms with past deadlines are excluded (they appear under 'closed').
 *
 * @return array<string, mixed>
 */
function list_forms(?int $created_by = null, ?string $status_filter = null, int $page = 1, int $per_page = 20): array {
    $where = ['f.deleted_at IS NULL'];
    $params = [];
    $is_closed_filter = ($status_filter === 'closed');

    // Include forms created by user OR where user has permissions
    if ($created_by !== null) {
        $where[] = '(f.created_by = :created_by OR EXISTS (
            SELECT 1 FROM permissions p
            WHERE p.form_id = f.id
            AND p.user_id = :perm_user_id
        ))';
        $params['created_by'] = $created_by;
        $params['perm_user_id'] = $created_by;
    }

    if ($is_closed_filter) {
        // "Closed" = published forms with past deadline (computed, not a DB status)
        $where[] = "f.status = 'published'";
    } elseif ($status_filter !== null) {
        $where[] = 'f.status = :status';
        $params['status'] = $status_filter;
    }

    $where_clause = implode(' AND ', $where);
    $sql = "SELECT f.*, u.name as creator_name,
            (SELECT COUNT(*) FROM submissions WHERE form_id = f.id AND status != 'draft') as submission_count
            FROM forms f
            LEFT JOIN users u ON f.created_by = u.id
            WHERE $where_clause
            ORDER BY f.updated_at DESC";

    // For 'closed' and 'published' filters we need PHP post-filtering on JSON deadline
    if ($is_closed_filter || $status_filter === 'published') {
        $all_rows = db_query($sql, $params);
        $filtered = [];
        foreach ($all_rows as $row) {
            decode_json_fields($row, ['settings']);
            $closed = is_form_closed($row);
            if ($is_closed_filter && $closed) {
                $filtered[] = $row;
            } elseif ($status_filter === 'published' && !$closed) {
                $filtered[] = $row;
            }
        }

        $total = count($filtered);
        $pages = max(1, (int)ceil($total / $per_page));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $per_page;

        return [
            'rows' => array_slice($filtered, $offset, $per_page),
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    $result = paginate($sql, $params, $page, $per_page);
    
    // Decode settings for each form
    foreach ($result['rows'] as &$form) {
        decode_json_fields($form, ['settings']);
    }
    
    return $result;
}

/**
 * Update form basic info
 * @param array<string, mixed> $data
 */
function update_form(int $id, array $data): bool {
    return db_transaction(function() use ($id, $data) {
        $data['updated_at'] = now();
        $count = db_update('forms', $data, 'id = ?', [$id]);
        
        $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$id])['uuid'];
        log_audit('updated', 'form', $id, $data, $form_uuid);
        
        return $count >= 0;
    });
}

/**
 * Atomically merge keys into a form's settings JSON
 *
 * Only the specified keys are changed; all other existing keys are preserved.
 * Uses a row lock to prevent concurrent writes from overwriting each other.
 *
 * @param array<string, mixed> $changes Key-value pairs to merge into existing settings
 */
function patch_form_settings(int $id, array $changes): bool {
    $result = patch_json_field('forms', 'settings', $changes, 'id = ?', [$id]);

    if ($result) {
        $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$id])['uuid'];
        log_audit('updated', 'form', $id, ['action' => 'settings_updated'], $form_uuid);
    }

    return $result;
}

/**
 * Detect circular dependencies in prerequisite forms
 *
 * Uses a BFS expansion to load only forms reachable from the new prerequisites,
 * capped at $max_forms to bound both memory usage and recursion depth.
 *
 * @param int $form_id The form being updated
 * @param array<int, int> $new_prerequisite_ids The new prerequisite form IDs being set
 * @return string|false Error message if circular dependency found, false otherwise
 */
function detect_circular_prerequisite(int $form_id, array $new_prerequisite_ids): string|false {
    if (empty($new_prerequisite_ids)) {
        return false;
    }

    $to_load    = array_unique(array_merge([$form_id], $new_prerequisite_ids));
    $loaded     = [];
    $prerequisite_map = [];
    $form_names = [];
    $max_forms  = 200;

    while (!empty($to_load) && count($loaded) < $max_forms) {
        $ids = array_values(array_diff($to_load, array_keys($loaded)));
        if (empty($ids)) break;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_query(
            "SELECT id, name, settings FROM forms WHERE deleted_at IS NULL AND id IN ($placeholders)",
            $ids
        );

        $next_to_load = [];
        foreach ($rows as $f) {
            decode_json_fields($f, ['settings']);
            $prereqs = $f['settings']['prerequisite_form_ids'] ?? [];
            $prerequisite_map[$f['id']] = $prereqs;
            $form_names[$f['id']]       = $f['name'];
            $loaded[$f['id']]           = true;
            foreach ($prereqs as $p) {
                if (!isset($loaded[$p])) $next_to_load[] = $p;
            }
        }
        $to_load = $next_to_load;
    }

    $prerequisite_map[$form_id] = $new_prerequisite_ids;

    foreach ($new_prerequisite_ids as $prereq_id) {
        if ($prereq_id == $form_id) {
            return 'Form cannot be a prerequisite of itself';
        }

        $visited = [];
        $path    = [$form_id];

        if (has_circular_dependency($prereq_id, $form_id, $prerequisite_map, $visited, $path)) {
            $path_names = array_map(fn($id) => $form_names[$id] ?? "Form #$id", $path);
            return 'Forms create a circular dependency: ' . implode(' → ', $path_names);
        }
    }

    return false;
}

/**
 * Recursive DFS helper to detect whether following prerequisites from $current_id
 * can reach $target_id (indicating a cycle).
 *
 * Uses path-based backtracking so that shared dependencies (diamond patterns) are
 * not incorrectly flagged as cycles. A hard depth cap guards against stack overflows
 * on pathologically deep chains.
 *
 * @param int $current_id Current form being traversed
 * @param int $target_id The form whose re-appearance signals a cycle
 * @param array<int, array<int, int>> $prerequisite_map Map of form ID → prerequisite IDs
 * @param array<int, bool> $visited Nodes in the current recursion path (by reference)
 * @param array<int, int> $path Current path for error reporting (by reference)
 * @param int $depth Current recursion depth
 * @return bool True if a cycle involving $target_id is found
 */
function has_circular_dependency(
    int $current_id,
    int $target_id,
    array $prerequisite_map,
    array &$visited,
    array &$path,
    int $depth = 0
): bool {
    if ($depth > 20) {
        error_log("WARNING: Prerequisite chain depth exceeded 20 hops starting from form ID $current_id. Treating as circular.");
        return true;
    }

    if ($current_id == $target_id) {
        $path[] = $current_id;
        return true;
    }

    if (isset($visited[$current_id])) {
        return false;
    }

    $visited[$current_id] = true;
    $path[] = $current_id;

    foreach ($prerequisite_map[$current_id] ?? [] as $prereq_id) {
        if (has_circular_dependency($prereq_id, $target_id, $prerequisite_map, $visited, $path, $depth + 1)) {
            return true;
        }
    }

    array_pop($path);
    unset($visited[$current_id]);

    return false;
}

/**
 * Check if form has any submissions or in-progress drafts.
 * Used to lock scoring configuration once any user has started filling out the form.
 */
function form_has_submissions(int $id): bool {
    $submitted = db_one('SELECT COUNT(*) as count FROM submissions WHERE form_id = ?', [$id]);
    if (($submitted['count'] ?? 0) > 0) {
        return true;
    }

    $drafts = db_one('SELECT COUNT(*) as count FROM drafts WHERE form_id = ?', [$id]);
    return ($drafts['count'] ?? 0) > 0;
}

/**
 * Get programs that include a specific form
 * @return list<array<string, mixed>>
 */
function get_programs_using_form(int $form_id): array {
    $programs = db_query('SELECT * FROM programs WHERE status = ?', ['active']);
    $using_programs = [];
    
    foreach ($programs as $program) {
        decode_json_fields($program, ['form_ids']);
        if (in_array($form_id, $program['form_ids'] ?: [])) {
            $using_programs[] = $program;
        }
    }
    
    return $using_programs; // Already a proper indexed array
}

/**
 * Remove form from a program
 */
function remove_form_from_program(int $program_id, int $form_id): bool {
    return db_transaction(function() use ($program_id, $form_id) {
        $program = db_one('SELECT * FROM programs WHERE id = ? FOR UPDATE', [$program_id]);
        if (!$program) {
            return false;
        }
        
        decode_json_fields($program, ['form_ids']);
        $form_ids = $program['form_ids'] ?: [];
        $form_ids = array_values(array_filter($form_ids, function($id) use ($form_id) {
            return $id != $form_id;
        }));
        
        $count = db_update('programs', [
            'form_ids' => json_encode($form_ids),
            'updated_at' => now()
        ], 'id = ?', [$program_id]);
        
        $form_ref = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id]);
        $program = db_one('SELECT uuid FROM programs WHERE id = ?', [$program_id]);
        log_audit('updated', 'program', $program_id, ['action' => 'form_removed', 'form_uuid' => $form_ref['uuid'] ?? null], $program['uuid'] ?? null);
        
        return $count >= 0;
    });
}

/**
 * Unpublish a form (change status from published back to draft)
 * Returns form to draft status, hiding it from users but keeping all data intact
 * Matches the Programs module pattern for consistency
 */
function unpublish_form(int $id): bool {
    return db_transaction(function() use ($id) {
        // Get current form to verify it's published
        $form = get_form_by_id($id);
        if (!$form || $form['status'] !== 'published') {
            return false;
        }

        // Defense-in-depth: prevent unpublishing while linked to active programs
        $active_programs = get_programs_using_form($id);
        if (!empty($active_programs)) {
            return false;
        }
        
        $count = db_update('forms', [
            'status' => 'draft',
            'updated_at' => now()
        ], 'id = ?', [$id]);
        
        $form_uuid = $form['uuid'];
        log_audit('unpublished', 'form', $id, ['previous_status' => 'published', 'new_status' => 'draft'], $form_uuid);
        
        return $count >= 0;
    });
}

/**
 * Republish a previously unpublished form (change status from draft back to published)
 * Only works if the form has an active version (was previously published)
 * Matches the Programs module pattern for consistency
 */
function republish_form(int $id): bool {
    return db_transaction(function() use ($id) {
        $form = get_form_by_id($id);
        if (!$form || $form['status'] !== 'draft') {
            return false;
        }
        
        // Must have an active version to republish
        if (!$form['active_version_id']) {
            return false;
        }
        
        $count = db_update('forms', [
            'status' => 'published',
            'updated_at' => now()
        ], 'id = ?', [$id]);
        
        $form_uuid = $form['uuid'];
        log_audit('republished', 'form', $id, ['previous_status' => 'draft', 'new_status' => 'published'], $form_uuid);
        
        return $count >= 0;
    });
}

/**
 * Soft delete form
 */
function soft_delete_form(int $id): bool {
    return db_transaction(function() use ($id) {
        $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$id])['uuid'];
        $count = db_update('forms', [
            'deleted_at' => now(),
            'updated_at' => now()
        ], 'id = ?', [$id]);
        
        log_audit('deleted', 'form', $id, null, $form_uuid);
        
        return $count >= 0;
    });
}

// ============================================================================
// FORM VERSION OPERATIONS
// ============================================================================

/**
 * Create new draft version for a form
 */
function create_version(int $form_id, ?int $copy_from_version = null): int {
    return db_transaction(function() use ($form_id, $copy_from_version) {
        // Get next version number
        $current_max = db_one('SELECT MAX(version_number) as max_version FROM form_versions WHERE form_id = ?', [$form_id]);
        $version_number = ($current_max['max_version'] ?? 0) + 1;

        $version_id = db_insert('form_versions', [
            'form_id' => $form_id,
            'version_number' => $version_number,
            'status' => 'draft',
            'created_at' => now()
        ]);

        // If copying from another version, duplicate its questions
        if ($copy_from_version) {
            $questions = get_questions($copy_from_version);
            
            // Build a mapping of old UIDs to new UIDs to fix visibility references
            $uid_mapping = [];
            
            // First pass: Create all questions and build the UID mapping
            foreach ($questions as $q) {
                $old_uid = $q['uid'];
                $new_question_id = add_question($version_id, $q['type'], $q['sort_order'], $q['config']);
                
                // Get the new question to retrieve its new UID
                $new_question = get_question_by_id($new_question_id);
                if ($new_question) {
                    $uid_mapping[$old_uid] = $new_question['uid'];
                }
            }
            
            // Second pass: Update visibility references to use new UIDs
            if (!empty($uid_mapping)) {
                $updated_questions = get_questions($version_id);
                foreach ($updated_questions as $q) {
                    $config = $q['config'];
                    $needs_update = false;
                    
                    // Check if this question has visibility config that references an old UID
                    if (isset($config['visibility']['question_uid'])) {
                        $old_ref_uid = $config['visibility']['question_uid'];
                        
                        // If this UID is in our mapping, update it to the new UID
                        if (isset($uid_mapping[$old_ref_uid])) {
                            $config['visibility']['question_uid'] = $uid_mapping[$old_ref_uid];
                            $needs_update = true;
                        }
                    }
                    
                    // Update the question if visibility references were changed
                    if ($needs_update) {
                        update_question_by_uid($q['uid'], $config);
                    }
                }
            }
        }

        return $version_id;
    });
}

/**
 * Publish a version (make it active)
 */
function publish_version(int $form_id, int $version_id): bool {
    return db_transaction(function() use ($form_id, $version_id) {
        // Collect affected draft users before clearing (for notifications)
        $stale_drafts = db_query(
            'SELECT d.user_id, f.name as form_name, f.uuid as form_uuid
             FROM drafts d
             JOIN forms f ON d.form_id = f.id
             WHERE d.form_id = ? AND d.form_version_id != ?',
            [$form_id, $version_id]
        );

        // Mark all other versions as superseded
        db_exec('UPDATE form_versions SET status = ? WHERE form_id = ? AND id != ?', 
            ['superseded', $form_id, $version_id]);

        // Mark this version as active
        db_update('form_versions', [
            'status' => 'active',
            'published_at' => now()
        ], 'id = ?', [$version_id]);

        // Update form's active_version_id and status
        db_update('forms', [
            'active_version_id' => $version_id,
            'status' => 'published',
            'updated_at' => now()
        ], 'id = ?', [$form_id]);

        // Delete stale drafts — they reference the old version's question IDs
        // and cannot be meaningfully restored against the new version
        $cleared = db_exec('DELETE FROM drafts WHERE form_id = ? AND form_version_id != ?', [$form_id, $version_id]);

        $form_ref = db_one('SELECT uuid, name FROM forms WHERE id = ?', [$form_id]);
        $form_uuid = $form_ref['uuid'] ?? null;

        log_audit('published', 'form_version', $version_id, [
            'form_uuid' => $form_uuid,
            'drafts_cleared' => $cleared,
        ], null);

        // Notify affected users that their draft was cleared
        require_once __DIR__ . '/../notifications/models.php';
        foreach ($stale_drafts as $draft) {
            create_notification($draft['user_id'], 'draft_cleared', [
                'form_name' => $draft['form_name'],
                'form_uuid' => $draft['form_uuid'],
                'form_link' => '/forms/' . $draft['form_uuid'],
            ]);
        }

        return true;
    });
}

/**
 * Get version by ID
 * @return array<string, mixed>|null
 */
function get_version(int $version_id): ?array {
    return db_one('SELECT * FROM form_versions WHERE id = ?', [$version_id]);
}

/**
 * Get active version for a form
 * @return array<string, mixed>|null
 */
function get_active_version(int $form_id): ?array {
    return db_one('SELECT * FROM form_versions WHERE form_id = ? AND status = ?', [$form_id, 'active']);
}

/**
 * Get draft version for a form
 * @return array<string, mixed>|null
 */
function get_draft_version(int $form_id): ?array {
    return db_one('SELECT * FROM form_versions WHERE form_id = ? AND status = ?', [$form_id, 'draft']);
}

/**
 * Check if two versions have identical questions (same types, order, and config)
 *
 * Uses MySQL-level MD5 hashing so only short hash strings are transferred to PHP,
 * avoiding the need to load and compare full config JSON blobs in memory.
 *
 * Used to prevent publishing a draft that has no changes from the active version,
 * which would unnecessarily clear in-progress user drafts and send notifications.
 */
function versions_are_identical(int $version_id_a, int $version_id_b): bool {
    $sql = 'SELECT MD5(CONCAT(type, sort_order, config)) as hash
            FROM questions
            WHERE form_version_id = ? AND deleted_at IS NULL
            ORDER BY sort_order ASC';

    $hashes_a = array_column(db_query($sql, [$version_id_a]), 'hash');
    $hashes_b = array_column(db_query($sql, [$version_id_b]), 'hash');

    return $hashes_a === $hashes_b;
}

/**
 * Discard a draft version (hard delete)
 * Only allows discarding if version_number > 1 to prevent deleting initial version
 * Questions are cascade deleted via foreign key constraint
 */
function discard_draft_version(int $form_id, int $version_id): bool {
    return db_transaction(function() use ($form_id, $version_id) {
        // Get version to verify it's safe to delete
        $version = get_version($version_id);
        
        if (!$version) {
            return false;
        }
        
        // Safety checks
        if ($version['form_id'] != $form_id) {
            throw new InvalidArgumentException('Version does not belong to this form');
        }
        
        if ($version['status'] !== 'draft') {
            throw new InvalidArgumentException('Can only discard draft versions');
        }
        
        if ($version['version_number'] <= 1) {
            throw new InvalidArgumentException('Cannot discard version 1');
        }
        
        // Hard delete the version (questions cascade delete via FK)
        $count = db_exec('DELETE FROM form_versions WHERE id = ?', [$version_id]);
        
        $form_ref = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id]);
        log_audit('discarded', 'form_version', $version_id, ['form_uuid' => $form_ref['uuid'] ?? null, 'version_number' => $version['version_number']], null);
        
        return $count > 0;
    });
}


// ============================================================================
// QUESTION OPERATIONS
// ============================================================================

/**
 * Get all questions for a version, ordered by sort_order
 * By default, excludes soft-deleted questions to maintain data integrity
 * @return array<int, array<string, mixed>>
 */
function get_questions(int $version_id): array {
    $questions = db_query('SELECT * FROM questions WHERE form_version_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC', [$version_id]);
    
    foreach ($questions as &$question) {
        decode_json_fields($question, ['config']);
    }
    
    return $questions;
}

/**
 * Get total question count for a version (excludes soft-deleted questions)
 */
function get_question_count(int $version_id): int {
    $row = db_one(
        'SELECT COUNT(*) AS count FROM questions WHERE form_version_id = ? AND deleted_at IS NULL',
        [$version_id]
    );

    return (int)($row['count'] ?? 0);
}


/**
 * Get single question by ID
 * @return array<string, mixed>|null
 */
function get_question_by_id(int $id): ?array {
    $question = db_one('SELECT * FROM questions WHERE id = ?', [$id]);
    if ($question) {
        decode_json_fields($question, ['config']);
    }
    return $question;
}

/**
 * Get single question by UID
 * @return array<string, mixed>|null
 */
function get_question_by_uid(string $uid): ?array {
    $question = db_one('SELECT * FROM questions WHERE uid = ?', [$uid]);
    if ($question) {
        decode_json_fields($question, ['config']);
    }
    return $question;
}

/**
 * Add new question to a version
 * @param array<string, mixed> $config
 */
function add_question(int $version_id, string $type, int $sort_order, array $config): int {
    return db_transaction(function() use ($version_id, $type, $sort_order, $config) {
        $question_id = db_insert('questions', [
            'uid' => uuid(),
            'form_version_id' => $version_id,
            'sort_order' => $sort_order,
            'type' => $type,
            'config' => json_encode($config),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        log_audit('created', 'question', $question_id, ['type' => $type, 'version_id' => $version_id], null);

        return $question_id;
    });
}

/**
 * Update question configuration by ID
 * @param array<string, mixed> $config
 */
function update_question_by_id(int $id, array $config): bool {
    return db_transaction(function() use ($id, $config) {
        $question = get_question_by_id($id);
        if (!$question) {
            return false;
        }

        $count = db_update('questions', [
            'config' => json_encode($config),
            'updated_at' => now()
        ], 'id = ?', [$id]);

        log_audit('updated', 'question', (int)$question['id'], null, null);

        return $count >= 0;
    });
}

/**
 * Update question configuration by UID
 * @param array<string, mixed> $config
 */
function update_question_by_uid(string $uid, array $config): bool {
    return db_transaction(function() use ($uid, $config) {
        $question = get_question_by_uid($uid);
        if (!$question) {
            return false;
        }

        $count = db_update('questions', [
            'config' => json_encode($config),
            'updated_at' => now()
        ], 'uid = ?', [$uid]);

        log_audit('updated', 'question', (int)$question['id'], null, null);

        return $count >= 0;
    });
}

/**
 * Find questions that depend on a given question via conditional visibility
 * @return list<array<string, mixed>>
 */
function get_questions_depending_on(string $question_uid, int $version_id): array {
    $questions = get_questions($version_id);
    $dependents = [];

    foreach ($questions as $q) {
        if (isset($q['config']['visibility']['question_uid']) && $q['config']['visibility']['question_uid'] === $question_uid) {
            $dependents[] = $q;
        }
    }

    return $dependents;
}

/**
 * Delete question (hard delete) by ID
 *
 * Questions can only be deleted from draft versions (enforced by the API layer).
 * Since draft versions never have submissions, hard delete is always safe and
 * avoids bloating the database with orphaned soft-deleted rows.
 */
function delete_question_by_id(int $id): bool {
    return db_transaction(function() use ($id) {
        $question = get_question_by_id($id);
        if (!$question) {
            return false;
        }

        $count = db_exec('DELETE FROM questions WHERE id = ?', [$id]);

        log_audit('deleted', 'question', (int)$question['id'], null, null);

        return $count > 0;
    });
}

/**
 * Delete question (hard delete) by UID
 *
 * Questions can only be deleted from draft versions (enforced by the API layer).
 * Since draft versions never have submissions, hard delete is always safe and
 * avoids bloating the database with orphaned soft-deleted rows.
 */
function delete_question_by_uid(string $uid): bool {
    return db_transaction(function() use ($uid) {
        $question = get_question_by_uid($uid);
        if (!$question) {
            return false;
        }

        $count = db_exec('DELETE FROM questions WHERE uid = ?', [$uid]);

        log_audit('deleted', 'question', (int)$question['id'], null, null);

        return $count > 0;
    });
}

/**
 * Reorder questions for a version
 * Optimized to use a single UPDATE query with CASE statement
 * @param array<int, int|string> $ordered_ids
 */
function reorder_questions(int $version_id, array $ordered_ids): bool {
    return db_transaction(function() use ($version_id, $ordered_ids) {
        if (empty($ordered_ids)) {
            return true;
        }
        
        // Validate that all IDs are of the same type (all numeric or all UIDs)
        // to prevent mixed array attacks and ensure query consistency
        $id_types = array_map(function($id) {
            return (is_numeric($id) && strlen($id) <= 10) ? 'numeric' : 'uid';
        }, $ordered_ids);
        
        $unique_types = array_unique($id_types);
        if (count($unique_types) > 1) {
            error_log("SECURITY WARNING: Mixed ID types detected in reorder_questions - " . json_encode($ordered_ids));
            throw new InvalidArgumentException('All question identifiers must be of the same type (either all IDs or all UIDs)');
        }
        
        // Determine which field to use based on validated consistent type
        $id_field = $unique_types[0] === 'numeric' ? 'id' : 'uid';
        
        // Build CASE statement for sort_order
        $case_parts = [];
        $params = [];
        $sort_order = 1;
        
        foreach ($ordered_ids as $question_id) {
            $case_parts[] = "WHEN $id_field = ? THEN ?";
            $params[] = $question_id;
            $params[] = $sort_order;
            $sort_order++;
        }
        
        $case_sql = implode(' ', $case_parts);
        
        // Build IN clause placeholders
        $placeholders = implode(',', array_fill(0, count($ordered_ids), '?'));
        
        // Add updated_at timestamp (must come before IN clause params to match SQL order)
        $updated_at = now();
        $params[] = $updated_at;
        
        // Add question IDs for IN clause
        foreach ($ordered_ids as $question_id) {
            $params[] = $question_id;
        }
        
        // Add version_id
        $params[] = $version_id;
        
        // Execute single UPDATE query
        $sql = "UPDATE questions
                SET sort_order = CASE $case_sql END,
                    updated_at = ?
                WHERE $id_field IN ($placeholders)
                AND form_version_id = ?";

        $affected = db_exec($sql, $params);

        // Guard against foreign-version IDs or stale IDs (e.g. question deleted in another tab)
        if ($affected !== count($ordered_ids)) {
            error_log(sprintf(
                "INTEGRITY WARNING: reorder_questions expected %d updates for version %d, got %d. Possible foreign-version IDs.",
                count($ordered_ids), $version_id, $affected
            ));
            throw new InvalidArgumentException(
                "Reorder failed: " . (count($ordered_ids) - $affected) . " question(s) do not belong to this version."
            );
        }

        log_audit('reordered', 'questions', $version_id, ['count' => count($ordered_ids)], null);

        return true;
    });
}

// ============================================================================
// FORM STATISTICS
// ============================================================================

/**
 * Get form statistics
 * @return array<string, int>
 */
function get_form_stats(int $form_id): array {
    $stats = [];
    
    // Total non-draft submissions (matches what list_submissions displays)
    $result = db_one('SELECT COUNT(*) as count FROM submissions WHERE form_id = ? AND status != ?', [$form_id, 'draft']);
    $stats['total_submissions'] = $result['count'] ?? 0;
    
    // Submitted (not draft)
    $result = db_one('SELECT COUNT(*) as count FROM submissions WHERE form_id = ? AND status = ?', [$form_id, 'submitted']);
    $stats['submitted'] = $result['count'] ?? 0;
    
    // Drafts
    $result = db_one('SELECT COUNT(*) as count FROM drafts WHERE form_id = ?', [$form_id]);
    $stats['drafts'] = $result['count'] ?? 0;
    
    // Clarification requested
    $result = db_one('SELECT COUNT(*) as count FROM submissions WHERE form_id = ? AND status = ?', [$form_id, 'clarification_requested']);
    $stats['clarification_requested'] = $result['count'] ?? 0;
    
    return $stats;
}

// ============================================================================
// FORM ADMIN PERMISSIONS
// ============================================================================

/**
 * Get all admins for a form (including owner)
 * @return array{owner: array<string, mixed>|null, admins: list<array<string, mixed>>}
 */
function get_form_admins(int $form_id): array {
    // Get form owner
    $form = db_one('SELECT created_by FROM forms WHERE id = ?', [$form_id]);
    if (!$form) {
        return ['owner' => null, 'admins' => []];
    }
    
    $owner = db_one(
        'SELECT id, uuid, name, email, role FROM users WHERE id = ?',
        [$form['created_by']]
    );
    
    // Get granted admins
    $admins = db_query(
        'SELECT u.id, u.uuid, u.name, u.email, u.role, p.access, p.created_at as granted_at
         FROM permissions p
         JOIN users u ON p.user_id = u.id
         WHERE p.form_id = ?
         ORDER BY p.created_at DESC',
        [$form_id]
    );
    
    return [
        'owner' => $owner,
        'admins' => $admins
    ];
}

/**
 * Add an admin to a form
 * @return bool
 */
function add_form_admin(int $form_id, int $user_id, string $access): bool {
    return db_transaction(function() use ($form_id, $user_id, $access) {
        // Validate access level
        if (!in_array($access, ['view_results', 'manage'])) {
            return false;
        }
        
        // Check if user is not already the owner
        $form = db_one('SELECT created_by FROM forms WHERE id = ?', [$form_id]);
        if (!$form || $form['created_by'] == $user_id) {
            return false; // Owner doesn't need permission grant
        }
        
        // Check if permission already exists
        $existing = db_one(
            'SELECT id FROM permissions WHERE user_id = ? AND form_id = ?',
            [$user_id, $form_id]
        );
        
        if ($existing) {
            return false; // Already exists
        }
        
        // Insert permission
        db_insert('permissions', [
            'user_id' => $user_id,
            'form_id' => $form_id,
            'access' => $access,
            'created_at' => now()
        ]);
        
        // Log audit
        $user = db_one('SELECT uuid, email FROM users WHERE id = ?', [$user_id]);
        $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id])['uuid'] ?? null;
        log_audit('form_admin_added', 'form', $form_id, [
            'granted_user_uuid' => $user['uuid'] ?? null,
            'granted_user_email' => $user['email'] ?? '',
            'access_level' => $access,
            'granted_by' => current_user()['uuid']
        ], $form_uuid);
        
        return true;
    });
}

/**
 * Remove an admin from a form
 * @return bool
 */
function remove_form_admin(int $form_id, int $user_id): bool {
    return db_transaction(function() use ($form_id, $user_id) {
        // Verify user is not the owner
        $form = db_one('SELECT created_by FROM forms WHERE id = ?', [$form_id]);
        if (!$form || $form['created_by'] == $user_id) {
            return false; // Cannot remove owner
        }
        
        // Get permission details for audit
        $permission = db_one(
            'SELECT access FROM permissions WHERE user_id = ? AND form_id = ?',
            [$user_id, $form_id]
        );
        
        if (!$permission) {
            return false; // No permission to remove
        }
        
        // Delete permission
        $deleted = db_exec(
            'DELETE FROM permissions WHERE user_id = ? AND form_id = ?',
            [$user_id, $form_id]
        );
        
        if ($deleted) {
            // Log audit
            $user = db_one('SELECT uuid, email FROM users WHERE id = ?', [$user_id]);
            $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id])['uuid'] ?? null;
            log_audit('form_admin_removed', 'form', $form_id, [
                'removed_user_uuid' => $user['uuid'] ?? null,
                'removed_user_email' => $user['email'] ?? '',
                'previous_access' => $permission['access'],
                'removed_by' => current_user()['uuid']
            ], $form_uuid);
        }
        
        return $deleted;
    });
}

/**
 * Update admin access level
 * @return bool
 */
function update_form_admin_access(int $form_id, int $user_id, string $access): bool {
    return db_transaction(function() use ($form_id, $user_id, $access) {
        // Validate access level
        if (!in_array($access, ['view_results', 'manage'])) {
            return false;
        }
        
        // Verify user is not the owner
        $form = db_one('SELECT created_by FROM forms WHERE id = ?', [$form_id]);
        if (!$form || $form['created_by'] == $user_id) {
            return false; // Cannot update owner
        }
        
        // Get current permission
        $current = db_one(
            'SELECT access FROM permissions WHERE user_id = ? AND form_id = ?',
            [$user_id, $form_id]
        );
        
        if (!$current) {
            return false;
        }
        
        // Update permission
        $updated = db_update(
            'permissions',
            ['access' => $access],
            'user_id = ? AND form_id = ?',
            [$user_id, $form_id]
        );
        
        if ($updated) {
            // Log audit
            $user = db_one('SELECT uuid, email FROM users WHERE id = ?', [$user_id]);
            $form_uuid = db_one('SELECT uuid FROM forms WHERE id = ?', [$form_id])['uuid'] ?? null;
            log_audit('form_admin_updated', 'form', $form_id, [
                'updated_user_uuid' => $user['uuid'] ?? null,
                'updated_user_email' => $user['email'] ?? '',
                'old_access' => $current['access'],
                'new_access' => $access,
                'updated_by' => current_user()['uuid']
            ], $form_uuid);
        }
        
        return $updated >= 0;
    });
}

/**
 * Check if user is the form owner
 * @return bool
 */
function is_form_owner(int $form_id, int $user_id): bool {
    $form = db_one('SELECT created_by FROM forms WHERE id = ?', [$form_id]);
    return $form && $form['created_by'] == $user_id;
}
