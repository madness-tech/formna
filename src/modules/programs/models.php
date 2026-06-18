<?php

/**
 * Programs Module - Database Operations
 *
 * A Program groups one or more forms and defines a multi-stage review process.
 * Users attach completed form submissions, then submit their program entry for review.
 */

require_once __DIR__ . '/../forms/models.php';
require_once __DIR__ . '/../forms/scoring.php';

// ============================================================================
// PROGRAM CRUD
// ============================================================================

/**
 * Default settings for a new program
 *
 * @return array<string, mixed>
 */
function program_default_settings(): array {
    return [
        'max_submissions_per_user' => 1,
        'submission_period_start' => null,
        'submission_period_end' => null,
        'rules' => '',
        'instructions' => '',
        'batch_notifications' => false,
    ];
}

/**
 * Create a new program
 *
 * @param array<int> $form_ids
 * @param array<string, mixed> $settings
 */
function create_program(string $name, ?string $description, array $form_ids, array $settings, int $created_by): int {
    return db_insert('programs', [
        'uuid' => uuid(),
        'name' => $name,
        'description' => $description,
        'form_ids' => json_encode(array_values($form_ids)),
        'status' => 'draft',
        'review_stages' => json_encode([]),
        'settings' => json_encode(array_merge(program_default_settings(), $settings)),
        'created_by' => $created_by,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Get program by ID (decodes JSON fields)
 *
 * @return array<string, mixed>|null
 */
function get_program(int $id): ?array {
    $row = db_one("SELECT * FROM programs WHERE id = ?", [$id]);
    return $row ? _decode_program($row) : null;
}

/**
 * Get program by UUID (decodes JSON fields)
 *
 * @return array<string, mixed>|null
 */
function get_program_by_uuid(string $uuid): ?array {
    $row = db_one("SELECT * FROM programs WHERE uuid = ?", [$uuid]);
    return $row ? _decode_program($row) : null;
}

/**
 * Get program with its form details attached by ID
 *
 * @return array<string, mixed>|null
 */
function get_program_with_forms_by_id(int $id): ?array {
    $program = get_program($id);
    if (!$program) {
        return null;
    }
    if (empty($program['form_ids'])) {
        $program['forms'] = [];
        return $program;
    }

    $form_ids = array_map('intval', $program['form_ids']);
    $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
    $forms = db_query(
        "SELECT id, uuid, name, description, status FROM forms WHERE id IN ($placeholders) AND deleted_at IS NULL ORDER BY FIELD(id, $placeholders)",
        array_merge($form_ids, $form_ids)
    );
    $program['forms'] = $forms;
    return $program;
}

/**
 * Get program with its form details attached by UUID
 *
 * @return array<string, mixed>|null
 */
function get_program_with_forms_by_uuid(string $uuid): ?array {
    $program = get_program_by_uuid($uuid);
    if (!$program) {
        return null;
    }
    if (empty($program['form_ids'])) {
        $program['forms'] = [];
        return $program;
    }

    $form_ids = array_map('intval', $program['form_ids']);
    $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
    $forms = db_query(
        "SELECT id, uuid, name, description, status FROM forms WHERE id IN ($placeholders) AND deleted_at IS NULL ORDER BY FIELD(id, $placeholders)",
        array_merge($form_ids, $form_ids)
    );
    $program['forms'] = $forms;
    return $program;
}

/**
 * List all programs (admin view)
 *
 * Supports a 'closed' pseudo-status filter that returns both explicitly closed
 * programs AND active programs whose submission period has expired. When filtering
 * by 'active', programs with expired periods are excluded (they appear under 'closed').
 *
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function list_programs(?int $created_by = null, ?string $status = null, int $page = 1, int $per_page = 20): array {
    $is_closed_filter = ($status === 'closed');

    $sql = "SELECT p.*, u.name as creator_name,
                   (SELECT COUNT(*) FROM program_submissions ps WHERE ps.program_id = p.id AND ps.status != 'draft') as submission_count
            FROM programs p
            LEFT JOIN users u ON p.created_by = u.id";

    $params = [];
    $conditions = [];
    
    if ($created_by !== null) {
        $conditions[] = "p.created_by = ?";
        $params[] = $created_by;
    }

    if ($is_closed_filter) {
        // "Closed" = explicitly closed OR active with expired submission period
        $conditions[] = "p.status IN ('active', 'closed')";
    } elseif ($status !== null) {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY p.updated_at DESC";

    // For 'closed' and 'active' filters we need PHP post-filtering on JSON settings
    if ($is_closed_filter || $status === 'active') {
        $all_rows = db_query($sql, $params);
        $filtered = [];
        foreach ($all_rows as $row) {
            $row = _decode_program($row);
            $effectively_closed = is_program_effectively_closed($row);
            if ($is_closed_filter && $effectively_closed) {
                $filtered[] = $row;
            } elseif ($status === 'active' && !$effectively_closed) {
                $filtered[] = $row;
            }
        }
        return paginate_array($filtered, $page, $per_page);
    }

    $result = paginate($sql, $params, $page, $per_page);
    $result['rows'] = array_map('_decode_program', $result['rows']);
    return $result;
}

/**
 * Check if a program has any non-draft submissions
 */
function program_has_submissions(int $program_id): bool {
    $row = db_one(
        "SELECT COUNT(*) as c FROM program_submissions WHERE program_id = ? AND status NOT IN ('draft')",
        [$program_id]
    );
    return (int)($row['c'] ?? 0) > 0;
}

/**
 * Update program details
 * Note: Once submissions exist, form_ids and stage structure cannot be modified.
 * Reviewers CAN be reassigned on existing stages (decisions history is preserved in program_submissions.decisions).
 *
 * @param array<int> $form_ids
 * @param array<int|string, mixed> $review_stages
 * @param array<string, mixed> $settings
 */
function update_program(int $id, string $name, ?string $description, array $form_ids, array $review_stages, array $settings): bool {
    $hasSubmissions = program_has_submissions($id);
    
    // If program has submissions, preserve critical structural fields
    if ($hasSubmissions) {
        $existing = get_program($id);
        if (!$existing) {
            return false;
        }
        // Restore original form_ids — cannot change required forms
        $form_ids = $existing['form_ids'];
        
        // Preserve stage structure but allow reviewer reassignment
        $review_stages = _merge_reviewer_reassignments($existing['review_stages'], $review_stages);
    }
    
    $count = db_update('programs', [
        'name' => $name,
        'description' => $description,
        'form_ids' => json_encode(array_values($form_ids)),
        'review_stages' => json_encode($review_stages),
        'settings' => json_encode($settings),
        'updated_at' => now(),
    ], 'id = ?', [$id]);
    return $count >= 0;
}

/**
 * Update program status
 */
function update_program_status(int $id, string $status): bool {
    $count = db_update('programs', [
        'status' => $status,
        'updated_at' => now(),
    ], 'id = ?', [$id]);
    return $count >= 0;
}

/**
 * Delete program (only if no submitted entries)
 */
function delete_program(int $id): bool {
    return db_transaction(function() use ($id) {
        // Lock the program row to prevent race conditions
        $active = db_one(
            "SELECT COUNT(*) as c FROM program_submissions WHERE program_id = ? AND status NOT IN ('draft') FOR UPDATE",
            [$id]
        );
        if ($active && (int)$active['c'] > 0) {
            return false;
        }
        // Delete drafts first, then the program
        db_exec("DELETE FROM program_submissions WHERE program_id = ?", [$id]);
        return db_exec("DELETE FROM programs WHERE id = ?", [$id]) > 0;
    });
}

// ============================================================================
// PROGRAM SUBMISSIONS
// ============================================================================

/**
 * Get or create a draft program submission for a user
 *
 * @return array<string, mixed>
 */
function get_or_create_program_draft(int $program_id, int $user_id): array {
    return db_transaction(function() use ($program_id, $user_id) {
        // Lock to prevent race condition creating duplicate drafts
        $existing = db_one(
            "SELECT * FROM program_submissions WHERE program_id = ? AND user_id = ? AND status = 'draft' ORDER BY created_at DESC LIMIT 1 FOR UPDATE",
            [$program_id, $user_id]
        );
        if ($existing) {
            return _decode_program_submission($existing);
        }

        $id = db_insert('program_submissions', [
            'uuid' => uuid(),
            'program_id' => $program_id,
            'user_id' => $user_id,
            'status' => 'draft',
            'current_stage' => 1,
            'decisions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return _decode_program_submission(db_one("SELECT * FROM program_submissions WHERE id = ?", [$id]));
    });
}

/**
 * Count how many non-draft program submissions a user has for a program
 */
function count_user_program_submissions(int $user_id, int $program_id): int {
    $row = db_one(
        "SELECT COUNT(*) as c FROM program_submissions WHERE user_id = ? AND program_id = ? AND status != 'draft'",
        [$user_id, $program_id]
    );
    return (int)($row['c'] ?? 0);
}

/**
 * Attach a form submission to a program draft
 */
function attach_submission_to_program(int $program_submission_id, int $form_id, int $submission_id): bool {
    return db_transaction(function() use ($program_submission_id, $form_id, $submission_id) {
        $ps = db_one(
            "SELECT id, status FROM program_submissions WHERE id = ? FOR UPDATE",
            [$program_submission_id]
        );
        if (!$ps || $ps['status'] !== 'draft') {
            return false;
        }

        db_exec(
            "REPLACE INTO program_submission_entries (program_submission_id, form_id, submission_id) VALUES (?, ?, ?)",
            [$program_submission_id, $form_id, $submission_id]
        );
        return true;
    });
}

/**
 * Detach a form submission from a program draft
 */
function detach_submission_from_program(int $program_submission_id, int $form_id): bool {
    return db_transaction(function() use ($program_submission_id, $form_id) {
        $ps = db_one(
            "SELECT id, status FROM program_submissions WHERE id = ? FOR UPDATE",
            [$program_submission_id]
        );
        if (!$ps || $ps['status'] !== 'draft') {
            return false;
        }

        return db_exec(
            "DELETE FROM program_submission_entries WHERE program_submission_id = ? AND form_id = ?",
            [$program_submission_id, $form_id]
        ) > 0;
    });
}

/**
 * Discard (hard-delete) a user's draft program submission
 */
function discard_program_draft(int $program_id, int $user_id): bool {
    $deleted = db_exec(
        "DELETE FROM program_submissions WHERE program_id = ? AND user_id = ? AND status = 'draft'",
        [$program_id, $user_id]
    );
    return $deleted > 0;
}

/**
 * Submit a program entry (move from draft to submitted).
 * Re-checks the per-user submission limit inside the transaction (locked) to
 * prevent a race condition where two concurrent requests both pass the
 * pre-transaction count check and both succeed.
 */
function submit_program_entry(int $program_submission_id): bool {
    return db_transaction(function() use ($program_submission_id) {
        $ps = db_one(
            "SELECT * FROM program_submissions WHERE id = ? FOR UPDATE",
            [$program_submission_id]
        );
        if (!$ps || $ps['status'] !== 'draft') {
            return false;
        }

        $program  = get_program($ps['program_id']);
        $max_subs = (int)(($program['settings']['max_submissions_per_user'] ?? 1));
        if ($max_subs !== 0) {
            $existing = db_one(
                "SELECT COUNT(*) as c FROM program_submissions
                 WHERE user_id = ? AND program_id = ? AND status != 'draft'
                 FOR UPDATE",
                [$ps['user_id'], $ps['program_id']]
            );
            if ((int)($existing['c'] ?? 0) >= $max_subs) {
                return false;
            }
        }

        return db_update('program_submissions', [
            'status'       => 'submitted',
            'submitted_at' => now(),
            'updated_at'   => now(),
        ], 'id = ?', [$program_submission_id]) >= 0;
    });
}

/**
 * Get program submission by ID
 *
 * @return array<string, mixed>|null
 */
function get_program_submission(int $id): ?array {
    $row = db_one("SELECT * FROM program_submissions WHERE id = ?", [$id]);
    return $row ? _decode_program_submission($row) : null;
}

/**
 * Get program submission by UUID
 *
 * @return array<string, mixed>|null
 */
function get_program_submission_by_uuid(string $uuid): ?array {
    $row = db_one("SELECT * FROM program_submissions WHERE uuid = ?", [$uuid]);
    return $row ? _decode_program_submission($row) : null;
}

/**
 * Get program submission with full details (program, user, all form submissions)
 *
 * @return array<string, mixed>|null
 */
function get_program_submission_with_details(string $uuid): ?array {
    $ps = get_program_submission_by_uuid($uuid);
    if (!$ps) {
        return null;
    }

    $ps['program'] = get_program_with_forms_by_id($ps['program_id']);
    $ps['user'] = db_one("SELECT id, name, email FROM users WHERE id = ?", [$ps['user_id']]);

    // Load each attached form submission with its questions
    $ps['form_submissions'] = [];
    if (!empty($ps['submission_ids'])) {
        foreach ($ps['submission_ids'] as $form_id => $submission_id) {
            $sub = db_one("SELECT * FROM submissions WHERE id = ?", [$submission_id]);
            if ($sub) {
                decode_json_fields($sub, ['answers', 'metadata']);
                $form = db_one("SELECT * FROM forms WHERE id = ?", [$sub['form_id']]);
                $version = db_one("SELECT * FROM form_versions WHERE id = ?", [$sub['form_version_id']]);
                $questions = $version ? get_questions($version['id']) : [];
                $ps['form_submissions'][$form_id] = [
                    'submission' => $sub,
                    'form' => $form,
                    'questions' => $questions,
                ];
            }
        }
    }

    // Collect all reviewer IDs from stages AND decisions for a single lookup
    $allReviewerIds = [];
    if (!empty($ps['program']['review_stages'])) {
        foreach ($ps['program']['review_stages'] as $stage) {
            if (!empty($stage['reviewer_id'])) {
                $allReviewerIds[] = (int)$stage['reviewer_id'];
            }
        }
    }
    if (!empty($ps['decisions'])) {
        foreach ($ps['decisions'] as $decision) {
            if (!empty($decision['reviewer_id'])) {
                $allReviewerIds[] = (int)$decision['reviewer_id'];
            }
        }
    }

    // Resolve all reviewer names in one query
    $reviewerMap = [];
    $allReviewerIds = array_unique(array_filter($allReviewerIds));
    if (!empty($allReviewerIds)) {
        $ph = implode(',', array_fill(0, count($allReviewerIds), '?'));
        $reviewers = db_query("SELECT id, name, email FROM users WHERE id IN ($ph)", array_values($allReviewerIds));
        foreach ($reviewers as $r) {
            $reviewerMap[$r['id']] = $r;
        }
    }

    // Enrich reviewer names in stages
    if (!empty($ps['program']['review_stages'])) {
        foreach ($ps['program']['review_stages'] as &$stage) {
            $stage['reviewer'] = $reviewerMap[$stage['reviewer_id']] ?? null;
        }
    }

    // Enrich reviewer names in decisions
    if (!empty($ps['decisions'])) {
        foreach ($ps['decisions'] as &$decision) {
            $rid = (int)($decision['reviewer_id'] ?? 0);
            $decision['reviewer_name'] = $reviewerMap[$rid]['name'] ?? 'Unknown';
        }
    }

    return $ps;
}

/**
 * List program submissions for a program (admin view)
 *
 * @return array<string, mixed>
 */
function list_program_submissions(int $program_id, ?string $status = null, int $page = 1, int $perPage = 20): array {
    $sql = "SELECT ps.*, u.name as user_name, u.email as user_email
            FROM program_submissions ps
            JOIN users u ON ps.user_id = u.id
            WHERE ps.program_id = ?";

    $params = [$program_id];
    
    // If status is specified, filter by that status
    // Otherwise, exclude drafts (default behavior)
    if ($status) {
        $sql .= " AND ps.status = ?";
        $params[] = $status;
    } else {
        $sql .= " AND ps.status != 'draft'";
    }
    
    // Order by submitted_at for non-drafts, created_at for drafts
    if ($status === 'draft') {
        $sql .= " ORDER BY ps.created_at DESC";
    } else {
        $sql .= " ORDER BY ps.submitted_at DESC";
    }

    $result = paginate($sql, $params, $page, $perPage);

    foreach ($result['rows'] as &$row) {
        $row = _decode_program_submission($row);
    }
    return $result;
}

/**
 * Get program statistics
 *
 * @return array<string, mixed>
 */
function get_program_stats(int $program_id): array {
    $row = db_one("
        SELECT
            COUNT(CASE WHEN status != 'draft' THEN 1 END) as total,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
            COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted,
            COUNT(CASE WHEN status = 'in_review' THEN 1 END) as in_review,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
        FROM program_submissions WHERE program_id = ?
    ", [$program_id]);
    return $row ?: ['total' => 0, 'draft' => 0, 'submitted' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0];
}

// ============================================================================
// REVIEW PROCESS
// ============================================================================

/**
 * Get pending reviews for a reviewer
 *
 * @return array<int, array<string, mixed>>
 */
function get_pending_program_reviews(?int $reviewer_id, ?int $program_id = null): array {
    // We need submissions in 'submitted' or 'in_review' status where
    // the current stage reviewer matches this user (or all if reviewer_id is null for admins)
    $sql = "SELECT ps.*, p.name as program_name, p.uuid as program_uuid, p.review_stages, p.settings as program_settings,
               u.name as user_name, u.email as user_email
        FROM program_submissions ps
        JOIN programs p ON ps.program_id = p.id
        JOIN users u ON ps.user_id = u.id
        WHERE ps.status IN ('submitted', 'in_review')
          AND p.status = 'active'";
    
    $params = [];
    
    if ($program_id !== null) {
        $sql .= " AND ps.program_id = ?";
        $params[] = $program_id;
    }
    
    $sql .= " ORDER BY ps.submitted_at ASC";
    
    $rows = db_query($sql, $params);

    // Collect all reviewer IDs for batch name resolution
    $allReviewerIds = [];
    $filteredRows = [];

    foreach ($rows as $row) {
        decode_json_fields($row, ['review_stages', 'decisions']);
        $stages = $row['review_stages'] ?: [];
        $decisions = $row['decisions'] ?: [];
        $currentStage = (int)$row['current_stage'];

        // Find the stage config for current_stage
        $stageConfig = null;
        foreach ($stages as $s) {
            if ((int)$s['order'] === $currentStage) {
                $stageConfig = $s;
                break;
            }
        }

        // If reviewer_id is null (admin/super_admin), include all pending reviews
        // Otherwise, filter to only reviews assigned to this reviewer
        if ($stageConfig && ($reviewer_id === null || (int)$stageConfig['reviewer_id'] === $reviewer_id)) {
            $row['current_stage_info'] = $stageConfig;
            decode_json_fields($row, ['program_settings']);
            $row['submission_ids'] = get_submission_ids_map((int)$row['id']);
            $row['settings'] = $row['program_settings'] ?: [];

            // Build previous stage outcomes from decisions
            $row['previous_stage_outcomes'] = [];
            foreach ($decisions as $d) {
                $row['previous_stage_outcomes'][(int)$d['stage']] = $d;
            }

            // Collect reviewer IDs for name resolution
            if (!empty($stageConfig['reviewer_id'])) {
                $allReviewerIds[] = (int)$stageConfig['reviewer_id'];
            }

            $filteredRows[] = $row;
        }
    }

    // Resolve reviewer names in one query
    $reviewerMap = [];
    $allReviewerIds = array_unique(array_filter($allReviewerIds));
    if (!empty($allReviewerIds)) {
        $ph = implode(',', array_fill(0, count($allReviewerIds), '?'));
        $reviewers = db_query("SELECT id, name FROM users WHERE id IN ($ph)", array_values($allReviewerIds));
        foreach ($reviewers as $r) {
            $reviewerMap[$r['id']] = $r['name'];
        }
    }

    // Attach reviewer name to each review
    foreach ($filteredRows as &$row) {
        $rid = (int)($row['current_stage_info']['reviewer_id'] ?? 0);
        $row['current_stage_info']['reviewer_name'] = $reviewerMap[$rid] ?? 'Unassigned';
    }

    return $filteredRows;
}

/**
 * Record a review decision on a program submission
 */
function record_program_decision(int $ps_id, int $stage, int $reviewer_id, string $decision, ?int $score = null, ?string $justification = null): bool {
    return db_transaction(function() use ($ps_id, $stage, $reviewer_id, $decision, $score, $justification) {
        $ps = db_one(
            "SELECT * FROM program_submissions WHERE id = ? FOR UPDATE",
            [$ps_id]
        );
        if (!$ps) {
            return false;
        }

        decode_json_fields($ps, ['decisions']);
        $decisions = $ps['decisions'] ?: [];
        $decisions[] = [
            'stage' => $stage,
            'reviewer_id' => $reviewer_id,
            'decision' => $decision,
            'score' => $score,
            'justification' => $justification,
            'decided_at' => now(),
        ];

        return db_update('program_submissions', [
            'decisions' => json_encode($decisions),
            'updated_at' => now(),
        ], 'id = ?', [$ps_id]) >= 0;
    });
}

/**
 * Advance to next stage or finalize as approved
 */
function advance_program_stage(int $ps_id): bool {
    return db_transaction(function() use ($ps_id) {
        $ps = db_one(
            "SELECT * FROM program_submissions WHERE id = ? FOR UPDATE",
            [$ps_id]
        );
        if (!$ps) {
            return false;
        }
        $ps = _decode_program_submission($ps);

        $program = get_program($ps['program_id']);
        $stages = $program['review_stages'] ?? [];
        $currentStage = $ps['current_stage'];

        // Find next stage
        $nextStage = null;
        foreach ($stages as $s) {
            if ((int)$s['order'] === $currentStage + 1) {
                $nextStage = $s;
                break;
            }
        }

        if ($nextStage) {
            db_update('program_submissions', [
                'current_stage' => $currentStage + 1,
                'status' => 'in_review',
                'updated_at' => now(),
            ], 'id = ?', [$ps_id]);
        } else {
            // Final stage passed — approve
            db_update('program_submissions', [
                'status' => 'approved',
                'updated_at' => now(),
            ], 'id = ?', [$ps_id]);
        }

        return true;
    });
}

/**
 * Reject a program submission
 */
function reject_program_submission(int $ps_id): bool {
    return db_update('program_submissions', [
        'status' => 'rejected',
        'updated_at' => now(),
    ], 'id = ?', [$ps_id]) >= 0;
}

/**
 * End a program (close it, optionally send batch notifications)
 */
function end_program(int $id): bool {
    return db_transaction(function() use ($id) {
        $program = db_one("SELECT * FROM programs WHERE id = ? FOR UPDATE", [$id]);
        if (!$program) {
            return false;
        }
        $program = _decode_program($program);

        db_update('programs', [
            'status' => 'closed',
            'updated_at' => now(),
        ], 'id = ?', [$id]);

        // If batch notifications enabled, queue them
        if (!empty($program['settings']['batch_notifications'])) {
            $rows = db_query(
                "SELECT ps.*, u.email, u.name as user_name
                 FROM program_submissions ps
                 JOIN users u ON ps.user_id = u.id
                 WHERE ps.program_id = ? AND ps.status IN ('approved', 'rejected')
                 FOR UPDATE",
                [$id]
            );

            foreach ($rows as $row) {
                $user = ['id' => $row['user_id'], 'name' => $row['user_name'], 'email' => $row['email']];
                queue_email_from_template(
                    'program_decision',
                    $user,
                    [
                        'program_name' => $program['name'],
                        'decision' => ucfirst($row['status']),
                        'justification' => '',
                        'submission_link' => base_url("/programs/{$program['uuid']}"),
                    ]
                );
            }
        }

        return true;
    });
}

// ============================================================================
// USER-FACING QUERIES
// ============================================================================

/**
 * Get available programs for users (active + within submission period)
 *
 * @return array<int, array<string, mixed>>
 */
function get_available_programs(): array {
    $rows = db_query("SELECT * FROM programs WHERE status = 'active' ORDER BY updated_at DESC");
    $programs = [];
    foreach ($rows as $row) {
        $p = _decode_program($row);
        $s = $p['settings'];

        if (!empty($s['submission_period_start']) && !is_past($s['submission_period_start'])) {
            continue; // Not open yet
        }
        if (is_program_effectively_closed($p)) {
            continue; // Expired
        }
        $programs[] = $p;
    }
    return $programs;
}

/**
 * Paginated browse of available programs with optional search
 *
 * Excludes programs that are effectively closed (explicitly closed or past submission period).
 *
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function browse_available_programs(?string $search = null, int $page = 1, int $per_page = 12): array {
    $sql = "
        SELECT p.*
        FROM programs p
        WHERE p.status = 'active'
    ";
    $params = [];

    if ($search) {
        $like = '%' . $search . '%';
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.updated_at DESC";

    $all_programs = db_query($sql, $params);

    $filtered = [];
    foreach ($all_programs as $row) {
        $p = _decode_program($row);
        $s = $p['settings'];

        // Not yet open
        if (!empty($s['submission_period_start']) && !is_past($s['submission_period_start'])) {
            continue;
        }

        // Expired or explicitly closed
        if (is_program_effectively_closed($p)) {
            continue;
        }

        $filtered[] = $p;
    }

    return paginate_array($filtered, $page, $per_page);
}

/**
 * Get recently closed programs (within grace period)
 *
 * Returns programs that are either:
 * - Explicitly closed (status = 'closed') within the last N days
 * - Active but past their submission_period_end within the last N days
 *
 * Explicit admin closure (updated_at) takes priority over natural expiry
 * (submission_period_end) when determining the "closed on" date.
 *
 * @return list<array<string, mixed>>
 */
function get_recently_closed_programs(?string $search = null, int $grace_days = 7): array {
    $sql = "
        SELECT p.*
        FROM programs p
        WHERE p.status IN ('active', 'closed')
    ";
    $params = [];

    if ($search) {
        $like = '%' . $search . '%';
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.updated_at DESC";

    $all_programs = db_query($sql, $params);

    $recently_closed = [];
    foreach ($all_programs as $row) {
        $p = _decode_program($row);

        if (!is_program_effectively_closed($p)) {
            continue;
        }

        // Explicit admin closure takes priority for the "closed on" date
        if ($p['status'] === 'closed') {
            $closed_at = $p['updated_at'];
        } else {
            $closed_at = $p['settings']['submission_period_end'];
        }

        if (is_recently_closed($closed_at, $grace_days)) {
            $p['closed_at'] = $closed_at;
            $recently_closed[] = $p;
        }
    }

    return $recently_closed;
}

/**
 * Get all program submissions for a user (non-draft)
 * Only shows submissions for active or closed programs (not draft)
 *
 * @return array<int, array<string, mixed>>
 */
function get_user_program_submissions(int $user_id): array {
    $rows = db_query("
        SELECT ps.*, p.name as program_name, p.uuid as program_uuid
        FROM program_submissions ps
        JOIN programs p ON ps.program_id = p.id
        WHERE ps.user_id = ? AND ps.status != 'draft'
          AND p.status IN ('active', 'closed')
        ORDER BY ps.submitted_at DESC
    ", [$user_id]);

    return array_map('_decode_program_submission', $rows);
}

/**
 * Paginated list of user's program submissions with optional status filter
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function list_user_program_submissions(int $user_id, ?string $status_filter = null, int $page = 1, int $per_page = 15): array {
    $sql = "
        SELECT ps.*, p.name as program_name, p.uuid as program_uuid
        FROM program_submissions ps
        JOIN programs p ON ps.program_id = p.id
        WHERE ps.user_id = ?
          AND p.status IN ('active', 'closed')
    ";
    $params = [$user_id];

    // If status_filter is provided, filter by that specific status
    // If null (showing "All"), include all statuses including drafts
    if ($status_filter) {
        $sql .= " AND ps.status = ?";
        $params[] = $status_filter;
    }
    // When no filter, include all (including drafts)

    $sql .= " ORDER BY COALESCE(ps.submitted_at, ps.updated_at) DESC";

    $result = paginate($sql, $params, $page, $per_page);

    foreach ($result['rows'] as &$row) {
        $row = _decode_program_submission($row);
    }

    return $result;
}

/**
 * Get user's draft program submissions (for dashboard)
 *
 * @return array<int, array<string, mixed>>
 */
function get_user_program_drafts(int $user_id): array {
    $rows = db_query("
        SELECT ps.*, p.name as program_name, p.uuid as program_uuid
        FROM program_submissions ps
        JOIN programs p ON ps.program_id = p.id
        WHERE ps.user_id = ? AND ps.status = 'draft'
        ORDER BY ps.updated_at DESC
    ", [$user_id]);

    return array_map('_decode_program_submission', $rows);
}

// ============================================================================
// CROSS-MODULE QUERIES
// ============================================================================

/**
 * Get all programs that a specific form submission is attached to.
 * A single submission can be linked to multiple programs.
 *
 * @return array<int, array<string, mixed>> Each entry contains program info + program_submission status
 */
function get_programs_for_submission(int $submission_id): array {
    $rows = db_query("
        SELECT ps.uuid as ps_uuid, ps.status as ps_status, ps.current_stage, ps.decisions,
               ps.submitted_at as ps_submitted_at,
               p.id as program_id, p.uuid as program_uuid, p.name as program_name,
               p.review_stages, p.status as program_status
        FROM program_submission_entries pse
        JOIN program_submissions ps ON pse.program_submission_id = ps.id
        JOIN programs p ON ps.program_id = p.id
        WHERE ps.status != 'draft'
          AND pse.submission_id = ?
        ORDER BY ps.created_at DESC
    ", [$submission_id]);

    $results = [];
    foreach ($rows as $row) {
        decode_json_fields($row, ['decisions', 'review_stages']);
        $row['decisions'] = $row['decisions'] ?: [];
        $row['review_stages'] = $row['review_stages'] ?: [];
        $results[] = $row;
    }

    return $results;
}

/**
 * Calculate program score for a single row in the list
 * Uses pre-loaded submission data to avoid N+1 queries
 *
 * IMPORTANT: Each submission is tied to a specific form_version_id.
 * We must use questions from THAT version, not the active version,
 * otherwise question IDs won't match and scoring will be incorrect.
 *
 * @param array<string|int, mixed> $submission_ids
 * @param array<int, array{form: array<string, mixed>}> $scored_forms
 * @param array<int, array<string, mixed>> $submissions_lookup Pre-loaded submission data indexed by ID
 * @return array{total: int, max_possible: int, has_scoring: bool}|null
 */
function _calc_row_program_score(array $submission_ids, array $scored_forms, array $submissions_lookup): ?array {
    if (empty($scored_forms) || empty($submission_ids)) {
        return null;
    }
    $form_scores = [];
    foreach ($scored_forms as $form_id => $data) {
        $sub_id = $submission_ids[(string)$form_id] ?? ($submission_ids[$form_id] ?? null);
        if (!$sub_id) {
            continue;
        }

        // Use pre-loaded submission data (already has answers decoded)
        $sub = $submissions_lookup[$sub_id] ?? null;
        if (!$sub) {
            continue;
        }

        $answers = $sub['answers'] ?: [];

        // Load questions from the SUBMISSION'S version, not active version
        // This ensures question IDs match between answers and scoring config
        $questions = get_questions($sub['form_version_id']);

        // Only calculate if this version actually has scoring configured
        if (has_scoring_config($questions)) {
            $form_scores[$form_id] = calculate_form_score($questions, $answers);
        }
    }
    if (empty($form_scores)) {
        return null;
    }
    return calculate_program_score($form_scores);
}

/**
 * Get audit trail events for a submission.
 *
 * @return array<int, array<string, mixed>>
 */
function get_submission_audit_trail(int $submission_id): array {
    return db_query("
        SELECT al.action, al.details, al.created_at, u.name as user_name
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.entity_type = 'submission' AND al.entity_id = ?
        ORDER BY al.created_at ASC
    ", [$submission_id]);
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================

/**
 * Check if any associated forms have submission deadlines earlier than the program's closing date.
 * Returns an array of form names whose deadlines fall before the program deadline.
 *
 * @param array<int> $form_ids
 * @param string $program_end  Program submission_period_end (UTC datetime string)
 * @return array<int, array{name: string, deadline: string}>  Forms with earlier deadlines
 */
function get_form_deadline_conflicts(array $form_ids, string $program_end): array {
    if (empty($form_ids) || empty($program_end)) {
        return [];
    }

    $ids = array_map('intval', $form_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $forms = db_query(
        "SELECT id, name, settings FROM forms WHERE id IN ($placeholders) AND deleted_at IS NULL",
        $ids
    );

    $conflicts = [];
    foreach ($forms as $form) {
        decode_json_fields($form, ['settings']);
        $deadline = $form['settings']['submission_deadline'] ?? null;
        if ($deadline && $deadline < $program_end) {
            $conflicts[] = [
                'name' => $form['name'],
                'deadline' => $deadline,
            ];
        }
    }

    return $conflicts;
}

/**
 * Get program-linked forms that are not eligible for program activation.
 *
 * A form is ineligible when it is missing, soft-deleted, or not currently published.
 *
 * @param array<int> $form_ids
 * @return array<int, array{id:int,name:string,reason:string,status:?string}>
 */
function get_program_unavailable_forms_for_activation(array $form_ids): array {
    if (empty($form_ids)) {
        return [];
    }

    $ids = array_values(array_unique(array_map('intval', $form_ids)));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_query(
        "SELECT id, name, status, deleted_at FROM forms WHERE id IN ($placeholders)",
        $ids
    );

    $by_id = [];
    foreach ($rows as $row) {
        $by_id[(int)$row['id']] = $row;
    }

    $unavailable = [];
    foreach ($ids as $id) {
        $form = $by_id[$id] ?? null;
        if (!$form) {
            $unavailable[] = [
                'id' => $id,
                'name' => "Form #$id",
                'reason' => 'missing',
                'status' => null,
            ];
            continue;
        }

        if (!empty($form['deleted_at'])) {
            $unavailable[] = [
                'id' => $id,
                'name' => (string)$form['name'],
                'reason' => 'deleted',
                'status' => (string)($form['status'] ?? ''),
            ];
            continue;
        }

        if (($form['status'] ?? '') !== 'published') {
            $unavailable[] = [
                'id' => $id,
                'name' => (string)$form['name'],
                'reason' => 'not_published',
                'status' => (string)($form['status'] ?? ''),
            ];
        }
    }

    return $unavailable;
}

// ============================================================================
// INTERNAL HELPERS
// ============================================================================

/**
 * Merge reviewer reassignments into existing stages.
 * Preserves all structural fields (order, name, action, pass_threshold)
 * and only updates reviewer_id from the submitted data.
 *
 * Returns the merged stages array, or the original if submitted data is invalid.
 *
 * @param array<int|string, mixed> $existingStages
 * @param array<int|string, mixed> $submittedStages
 * @return array<int|string, mixed>
 */
function _merge_reviewer_reassignments(array $existingStages, array $submittedStages): array {
    if (empty($submittedStages)) {
        return $existingStages;
    }

    // Index submitted stages by order for quick lookup
    $submittedByOrder = [];
    foreach ($submittedStages as $s) {
        $order = (int)($s['order'] ?? 0);
        if ($order > 0 && !empty($s['reviewer_id'])) {
            $submittedByOrder[$order] = (int)$s['reviewer_id'];
        }
    }

    // Apply reviewer_id updates to existing stages
    foreach ($existingStages as &$stage) {
        $order = (int)$stage['order'];
        if (isset($submittedByOrder[$order])) {
            $stage['reviewer_id'] = $submittedByOrder[$order];
        }
    }

    return $existingStages;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function _decode_program(array $row): array {
    decode_json_fields($row, ['form_ids', 'review_stages', 'settings']);
    $row['form_ids'] = $row['form_ids'] ?: [];
    $row['review_stages'] = $row['review_stages'] ?: [];
    $row['settings'] = $row['settings'] ?: [];
    return $row;
}

/**
 * Load the junction table rows as a form_id => submission_id map
 *
 * @return array<string, int>
 */
function get_submission_ids_map(int $program_submission_id): array {
    $rows = db_query(
        "SELECT form_id, submission_id FROM program_submission_entries WHERE program_submission_id = ?",
        [$program_submission_id]
    );
    $map = [];
    foreach ($rows as $row) {
        $map[(string)$row['form_id']] = (int)$row['submission_id'];
    }
    return $map;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function _decode_program_submission(array $row): array {
    decode_json_fields($row, ['decisions', 'review_stages']);
    $row['submission_ids'] = get_submission_ids_map((int)$row['id']);
    $row['decisions'] = $row['decisions'] ?? [] ?: [];
    if (isset($row['review_stages'])) {
        $row['review_stages'] = $row['review_stages'] ?: [];
    }
    return $row;
}
