<?php

/**
 * Dashboard Module - Models
 *
 * Role-specific data for the admin dashboard.
 *
 * Super Admin: System-wide overview + health indicators
 * Admin: Their forms, submissions to those forms, programs
 * Reviewer: Assigned reviews, completed reviews, activity
 */

/**
 * Get dashboard data for a super admin — full system overview
 * @return array<string, mixed>
 */
function get_super_admin_dashboard(): array {
    $data = [];

    // ── Stat cards ───────────────────────────────────────────────────
    $data['active_users'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM users WHERE status = 'active'"
    )['c'] ?? 0);

    $data['published_forms'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM forms WHERE status = 'published' AND deleted_at IS NULL"
    )['c'] ?? 0);

    $data['total_submissions'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM submissions WHERE status != 'draft'"
    )['c'] ?? 0);

    $data['submissions_this_week'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM submissions
         WHERE status != 'draft'
           AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )['c'] ?? 0);

    // Count truly active programs (exclude expired ones still in 'active' status)
    $active_prog_rows = db_query("SELECT settings FROM programs WHERE status = 'active'");
    $active_prog_count = 0;
    foreach ($active_prog_rows as $pr) {
        decode_json_fields($pr, ['settings']);
        $pr['status'] = 'active';
        if (!is_program_effectively_closed($pr)) {
            $active_prog_count++;
        }
    }
    $data['active_programs'] = $active_prog_count;

    // ── Action items (only shown when count > 0) ────────────────────
    $data['pending_reviews'] = (int)(db_one(
        "SELECT COUNT(*) as c
         FROM program_submissions ps
         JOIN programs p ON ps.program_id = p.id
         WHERE ps.status IN ('submitted','in_review') AND p.status = 'active'"
    )['c'] ?? 0);

    $data['pending_clarifications'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM clarification_requests WHERE status = 'open'"
    )['c'] ?? 0);

    $data['responded_clarifications'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM clarification_requests WHERE status = 'responded'"
    )['c'] ?? 0);

    $data['failed_webhooks_24h'] = (int)(db_one(
        "SELECT COUNT(*) as c FROM webhook_log
         WHERE status = 'failed'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )['c'] ?? 0);

    // ── Recent submissions across all forms ─────────────────────────
    $data['recent_submissions'] = db_query("
        SELECT s.uuid, s.submitted_at,
               f.name AS form_name, f.uuid AS form_uuid,
               u.name AS user_name, u.email AS user_email
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'submitted'
        ORDER BY s.submitted_at DESC
        LIMIT 5
    ");

    // ── Recent activity ─────────────────────────────────────────────
    $data['recent_activity'] = db_query("
        SELECT al.action, al.entity_type, al.created_at,
               u.name AS user_name, u.email AS user_email
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 8
    ");

    return $data;
}

/**
 * Get dashboard data for an admin — their forms and related activity
 * @return array<string, mixed>
 */
function get_admin_dashboard(int $user_id): array {
    $data = [];

    // ── Their forms (owned + granted permission) ────────────────────
    $data['my_forms'] = db_query("
        SELECT f.id, f.uuid, f.name, f.status,
               COUNT(s.id) AS submission_count,
               COUNT(CASE WHEN s.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS submissions_this_week
        FROM forms f
        LEFT JOIN submissions s ON s.form_id = f.id AND s.status != 'draft'
        WHERE f.deleted_at IS NULL
          AND (f.created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = f.id AND p.user_id = ?))
        GROUP BY f.id, f.uuid, f.name, f.status, f.updated_at
        ORDER BY f.updated_at DESC
        LIMIT 6
    ", [$user_id, $user_id]);

    $data['total_forms'] = (int)(db_one("
        SELECT COUNT(*) as c FROM forms
        WHERE deleted_at IS NULL
          AND (created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = forms.id AND p.user_id = ?))
    ", [$user_id, $user_id])['c'] ?? 0);

    // ── Submissions to their forms this week ────────────────────────
    $data['submissions_this_week'] = (int)(db_one("
        SELECT COUNT(*) as c
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        WHERE s.status != 'draft'
          AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND f.deleted_at IS NULL
          AND (f.created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = f.id AND p.user_id = ?))
    ", [$user_id, $user_id])['c'] ?? 0);

    // ── Active programs (exclude expired ones still in 'active' status) ──
    $admin_prog_rows = db_query("SELECT settings FROM programs WHERE status = 'active'");
    $admin_active_count = 0;
    foreach ($admin_prog_rows as $pr) {
        decode_json_fields($pr, ['settings']);
        $pr['status'] = 'active';
        if (!is_program_effectively_closed($pr)) {
            $admin_active_count++;
        }
    }
    $data['active_programs'] = $admin_active_count;

    // ── Action items ────────────────────────────────────────────────
    // Pending reviews — scoped to programs that use the admin's forms
    // Uses MySQL JSON_CONTAINS to check if program's form_ids array contains any admin's form
    $my_form_ids = db_query("
        SELECT id FROM forms
        WHERE deleted_at IS NULL
          AND (created_by = ?
               OR EXISTS (SELECT 1 FROM permissions WHERE form_id = forms.id AND user_id = ?))
    ", [$user_id, $user_id]);
    $my_form_id_list = array_column($my_form_ids, 'id');

    if (!empty($my_form_id_list)) {
        // Build JSON_CONTAINS conditions for each form ID (MySQL 5.7+ compatible)
        $json_conditions = [];
        $params = [];
        foreach ($my_form_id_list as $form_id) {
            $json_conditions[] = "JSON_CONTAINS(p.form_ids, ?)";
            $params[] = (string)$form_id;
        }
        $conditions = implode(' OR ', $json_conditions);

        $data['pending_reviews'] = (int)(db_one(
            "SELECT COUNT(*) as c
             FROM program_submissions ps
             JOIN programs p ON ps.program_id = p.id
             WHERE ps.status IN ('submitted','in_review')
               AND p.status = 'active'
               AND ($conditions)",
            $params
        )['c'] ?? 0);
    } else {
        $data['pending_reviews'] = 0;
    }

    $data['pending_clarifications'] = (int)(db_one("
        SELECT COUNT(*) as c
        FROM clarification_requests cr
        JOIN submissions s ON cr.submission_id = s.id
        JOIN forms f ON s.form_id = f.id
        WHERE cr.status = 'open'
          AND (f.created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = f.id AND p.user_id = ?))
    ", [$user_id, $user_id])['c'] ?? 0);

    $data['responded_clarifications'] = (int)(db_one("
        SELECT COUNT(*) as c
        FROM clarification_requests cr
        JOIN submissions s ON cr.submission_id = s.id
        JOIN forms f ON s.form_id = f.id
        WHERE cr.status = 'responded'
          AND (f.created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = f.id AND p.user_id = ?))
    ", [$user_id, $user_id])['c'] ?? 0);

    // ── Recent submissions to their forms ───────────────────────────
    $data['recent_submissions'] = db_query("
        SELECT s.uuid, s.submitted_at,
               f.name AS form_name, f.uuid AS form_uuid,
               u.name AS user_name, u.email AS user_email
        FROM submissions s
        JOIN forms f ON s.form_id = f.id
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'submitted'
          AND (f.created_by = ?
               OR EXISTS (SELECT 1 FROM permissions p WHERE p.form_id = f.id AND p.user_id = ?))
        ORDER BY s.submitted_at DESC
        LIMIT 5
    ", [$user_id, $user_id]);

    return $data;
}

/**
 * Get dashboard data for a reviewer — their review queue and activity
 *
 * All filtering happens at the database level using JSON_CONTAINS with
 * JSON_OBJECT to match both reviewer_id and order in a single stage element.
 * completed_reviews and completed_this_week reflect the last 12 months only.
 * @return array<string, mixed>
 */
function get_reviewer_dashboard(int $user_id): array {
    $data = [
        'pending_reviews'     => 0,
        'completed_reviews'   => 0,
        'completed_this_week' => 0,
        'upcoming_reviews'    => [],
        'assigned_programs'   => 0,
    ];

    // ── Pending reviews: submissions where this reviewer owns the current stage ─
    // JSON_OBJECT matches both reviewer_id AND order in a single stage element,
    // so the DB returns only rows the reviewer actually needs to act on.
    $rows = db_query("
        SELECT ps.id, ps.uuid, ps.status, ps.current_stage, ps.submitted_at,
               ps.updated_at,
               p.id AS program_id, p.name AS program_name, p.uuid AS program_uuid,
               u.name AS user_name, u.email AS user_email
        FROM program_submissions ps
        JOIN programs p ON ps.program_id = p.id
        JOIN users u ON ps.user_id = u.id
        WHERE ps.status IN ('submitted', 'in_review')
          AND p.status = 'active'
          AND JSON_CONTAINS(p.review_stages, JSON_OBJECT('reviewer_id', CAST(? AS UNSIGNED), 'order', ps.current_stage))
        ORDER BY ps.submitted_at ASC
    ", [$user_id]);

    $data['pending_reviews'] = count($rows);
    $data['upcoming_reviews'] = array_slice($rows, 0, 5);

    // ── Assigned programs (reviewer appears in any stage) ────────────
    $reviewer_fragment = json_encode(['reviewer_id' => $user_id]);
    $data['assigned_programs'] = (int)(db_one("
        SELECT COUNT(*) AS c FROM programs
        WHERE status = 'active'
          AND JSON_CONTAINS(review_stages, ?)
    ", [$reviewer_fragment])['c'] ?? 0);

    // ── Completed reviews (decisions by this reviewer) ───────────────
    // Use JSON_CONTAINS to match reviewer_id (stored as integer) in the decisions array.
    // Bounded to the last 12 months to prevent loading unbounded historical data.
    $decided_rows = db_query("
        SELECT ps.decisions, ps.updated_at
        FROM program_submissions ps
        WHERE ps.decisions IS NOT NULL
          AND ps.decisions != '[]'
          AND ps.decisions != 'null'
          AND ps.updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          AND JSON_CONTAINS(ps.decisions, ?)
    ", [$reviewer_fragment]);

    $week_ago = strtotime('-7 days');
    foreach ($decided_rows as $row) {
        decode_json_fields($row, ['decisions']);
        $decisions = $row['decisions'] ?: [];
        foreach ($decisions as $d) {
            if ((int)($d['reviewer_id'] ?? 0) === $user_id) {
                $data['completed_reviews']++;
                $decided_at = strtotime($d['decided_at'] ?? $row['updated_at']);
                if ($decided_at && $decided_at >= $week_ago) {
                    $data['completed_this_week']++;
                }
                break; // Count each submission only once per reviewer
            }
        }
    }

    return $data;
}

/**
 * Get time-of-day greeting for user dashboard header
 */
function get_greeting(string $timezone = 'UTC'): string {
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $now = new DateTime('now', $tz);
    $hour = (int)$now->format('G');

    if ($hour < 12) {
        return t('time.good_morning');
    } elseif ($hour < 17) {
        return t('time.good_afternoon');
    } else {
        return t('time.good_evening');
    }
}

/**
 * Admin-specific greeting (always English for admin UI)
 */
function admin_greeting(string $timezone = 'UTC'): string {
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $now = new DateTime('now', $tz);
    $hour = (int)$now->format('G');

    if ($hour < 12) {
        return 'Good morning';
    } elseif ($hour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
}
