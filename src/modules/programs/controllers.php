<?php

/**
 * Programs Module - Controllers
 *
 * Admin: manage programs, view submissions, review queue
 * User:  browse programs, attach submissions, submit entries
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../forms/models.php';
require_once __DIR__ . '/../submissions/models.php';
require_once __DIR__ . '/../ai/models.php';
require_once __DIR__ . '/../audit/logger.php';
require_once __DIR__ . '/../emails/sender.php';
require_once __DIR__ . '/../notifications/models.php';

// ============================================================================
// ADMIN — PROGRAM MANAGEMENT
// ============================================================================

/**
 * Require that the current admin owns the program, or is a super_admin.
 * Redirects with an error flash if the check fails.
 *
 * @param array<string, mixed> $program
 */
function _require_program_ownership(array $program): void
{
    $user = current_user();
    if ($user['role'] === 'super_admin') {
        return;
    }
    if ((int)$program['created_by'] !== (int)$user['id']) {
        flash('error', 'You do not have permission to modify this program.');
        redirect('/admin/programs');
    }
}

/**
 * List all programs
 */
function programs_index(): void {
    require_auth();
    require_role('admin');

    $user = current_user();
    $isSuperAdmin = $user['role'] === 'super_admin';
    $status = $_GET['status'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $result = list_programs($isSuperAdmin ? null : $user['id'], $status, $page, 20);
    $programs = $result['rows'];

    $pageTitle = 'Programs';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Programs']
    ];

    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Create a new program - directly to setup page
 */
function program_store(): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $user = current_user();
    
    // Create a minimal program with default name
    $defaultName = 'New Program';
    
    try {
        $programId = create_program($defaultName, '', [], [], $user['id']);
        $program = get_program($programId);
        log_audit('created', 'program', $programId, ['name' => $defaultName], $program['uuid']);
        flash('success', 'Program created. Configure the details below.');
        redirect("/admin/programs/{$program['uuid']}/setup");
    } catch (Exception $e) {
        flash('error', 'Failed to create program: ' . $e->getMessage());
        redirect('/admin/programs');
    }
}

/**
 * Delete a program
 */
function program_delete(): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $uuid = trim($_POST['uuid'] ?? '');
    if (!$uuid) {
        flash('error', 'Invalid program.');
        redirect('/admin/programs');
    }

    $program = get_program_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }

    _require_program_ownership($program);

    $result = delete_program($program['id']);
    if ($result) {
        log_audit('deleted', 'program', $program['id'], null, $program['uuid']);
        flash('success', 'Program deleted successfully.');
    } else {
        flash('error', 'Cannot delete a program that has submissions. Close it instead.');
    }

    redirect('/admin/programs');
}

/**
 * Show program setup page (forms, review stages, settings — all in one)
 */
function program_setup(string $uuid): void {
    require_auth();
    require_role('admin');

    $program = get_program_with_forms_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    // Published forms for selection + currently linked forms (even if now unpublished)
    $linkedFormIds = array_values(array_unique(array_map('intval', $program['form_ids'] ?? [])));
    if (!empty($linkedFormIds)) {
        $placeholders = implode(',', array_fill(0, count($linkedFormIds), '?'));
        $allForms = db_query(
            "SELECT id, name, status
             FROM forms
             WHERE deleted_at IS NULL
               AND (status = 'published' OR id IN ($placeholders))
             ORDER BY name",
            $linkedFormIds
        );
    } else {
        $allForms = db_query("SELECT id, name, status FROM forms WHERE status = 'published' AND deleted_at IS NULL ORDER BY name");
    }

    // All potential reviewers
    $reviewers = db_query(
        "SELECT id, name, email, role FROM users WHERE role IN ('admin','reviewer','super_admin') AND status = 'active' ORDER BY name"
    );

    $stats = get_program_stats($program['id']);

    // Detect form deadline conflicts for persistent warning banner
    $deadlineConflicts = [];
    $programEnd = $program['settings']['submission_period_end'] ?? null;
    if ($programEnd && !empty($program['form_ids'])) {
        $deadlineConflicts = get_form_deadline_conflicts($program['form_ids'], $programEnd);
    }

    $unavailableForms = get_program_unavailable_forms_for_activation($program['form_ids'] ?? []);

    $pageTitle = 'Program Setup — ' . sanitize($program['name']);
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Programs', 'url' => '/admin/programs'],
        ['label' => 'Program Details', 'url' => '/admin/programs/' . $program['uuid'] . '/setup'],
        ['label' => 'Setup']
    ];

    ob_start();
    require __DIR__ . '/views/setup.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Save program setup (forms + stages + settings)
 */
function program_save(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $program = get_program_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    // Check if program has submissions
    $hasSubmissions = program_has_submissions($program['id']);

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // If program has submissions, preserve original form_ids (disabled fields don't submit)
    if ($hasSubmissions) {
        $formIds = $program['form_ids'];
    } else {
        $formIds = array_map('intval', $_POST['form_ids'] ?? []);
    }

    // Settings — convert datetime-local inputs to UTC for storage
    $submissionPeriodStart = !empty($_POST['submission_period_start'])
        ? to_db_datetime($_POST['submission_period_start'])
        : null;
    $submissionPeriodEnd = !empty($_POST['submission_period_end'])
        ? to_db_datetime($_POST['submission_period_end'])
        : null;

    // If program has submissions, preserve the original start date (cannot modify)
    if ($hasSubmissions) {
        $submissionPeriodStart = $program['settings']['submission_period_start'] ?? null;
    }

    // Validate opening date is not in the past (only when editable)
    if (!$hasSubmissions && $submissionPeriodStart) {
        if (is_past($submissionPeriodStart)) {
            flash('error', 'Submission opening date cannot be in the past. Please select a current or future date.');
            redirect("/admin/programs/$uuid/setup");
        }
    }

    // Validate closure date is not in the past
    if ($submissionPeriodEnd) {
        if (is_past($submissionPeriodEnd)) {
            flash('error', 'Submission closure date cannot be in the past. Please select a current or future date.');
            redirect("/admin/programs/$uuid/setup");
        }
    }

    // Validate closure date is at least 1 hour after start date
    if ($submissionPeriodStart && $submissionPeriodEnd) {
        $startTime = to_timestamp($submissionPeriodStart);
        $endTime = to_timestamp($submissionPeriodEnd);
        $oneHourInSeconds = 3600;

        if ($endTime < ($startTime + $oneHourInSeconds)) {
            flash('error', 'Submission closure date must be at least 1 hour after the opening date.');
            redirect("/admin/programs/$uuid/setup");
        }
    }

    $settings = [
        'max_submissions_per_user' => max(1, (int)($_POST['max_submissions_per_user'] ?? 1)),
        'submission_period_start' => $submissionPeriodStart,
        'submission_period_end' => $submissionPeriodEnd,
        'rules' => trim($_POST['rules'] ?? ''),
        'instructions' => trim($_POST['instructions'] ?? ''),
        'batch_notifications' => isset($_POST['batch_notifications']),
    ];

    // Build review stages
    if ($hasSubmissions) {
        // Only read reviewer reassignments — stage structure is preserved in the model layer
        $stages = [];
        if (!empty($_POST['stage_reviewers'])) {
            foreach ($_POST['stage_reviewers'] as $order => $reviewerId) {
                $stages[] = [
                    'order' => (int)$order,
                    'reviewer_id' => (int)$reviewerId,
                ];
            }
        }
    } else {
        $stages = [];
        if (!empty($_POST['stages'])) {
            foreach ($_POST['stages'] as $stageData) {
                $stages[] = [
                    'order' => (int)$stageData['order'],
                    'name' => trim($stageData['name']),
                    'reviewer_id' => (int)$stageData['reviewer_id'],
                    'action' => $stageData['action'] ?? 'approve_reject',
                    'pass_threshold' => !empty($stageData['pass_threshold']) ? (int)$stageData['pass_threshold'] : null,
                ];
            }
            usort($stages, fn($a, $b) => $a['order'] - $b['order']);
        }
    }

    // Validation
    if (empty($name)) {
        flash('error', 'Program name is required.');
        redirect("/admin/programs/$uuid/setup");
    }
    if (empty($formIds)) {
        flash('error', 'Please select at least one form.');
        redirect("/admin/programs/$uuid/setup");
    }
    if (empty($stages)) {
        flash('error', 'Please add at least one review stage before saving.');
        redirect("/admin/programs/$uuid/setup");
    }

    try {
        // Detect reviewer changes for audit logging (before update)
        $reviewerChanges = [];
        if ($hasSubmissions) {
            foreach ($program['review_stages'] as $existingStage) {
                $order = (int)$existingStage['order'];
                foreach ($stages as $submitted) {
                    if ((int)$submitted['order'] === $order && (int)$submitted['reviewer_id'] !== (int)$existingStage['reviewer_id']) {
                        $oldRef = db_one('SELECT uuid FROM users WHERE id = ?', [(int)$existingStage['reviewer_id']]);
                        $newRef = db_one('SELECT uuid FROM users WHERE id = ?', [(int)$submitted['reviewer_id']]);
                        $reviewerChanges[] = [
                            'stage' => $order,
                            'stage_name' => $existingStage['name'],
                            'old_reviewer_uuid' => $oldRef['uuid'] ?? null,
                            'new_reviewer_uuid' => $newRef['uuid'] ?? null,
                        ];
                    }
                }
            }
        }

        update_program($program['id'], $name, $description, $formIds, $stages, $settings);
        log_audit('updated', 'program', $program['id'], ['name' => $name, 'stages' => count($stages)], $program['uuid']);

        // Log each reviewer reassignment individually for clear audit trail
        foreach ($reviewerChanges as $change) {
            log_audit('reviewer_changed', 'program', $program['id'], $change, $program['uuid']);
        }
        
        if (!$hasSubmissions) {
            flash('success', 'Program saved successfully.');
        } elseif (!empty($reviewerChanges)) {
            $count = count($reviewerChanges);
            flash('success', "Program updated. {$count} reviewer" . ($count != 1 ? 's' : '') . " reassigned successfully.");
        } else {
            flash('success', 'Program settings updated successfully.');
        }

    } catch (Exception $e) {
        flash('error', 'Failed to save program: ' . $e->getMessage());
    }

    redirect("/admin/programs/$uuid/setup");
}

/**
 * View program submissions list
 */
function program_submissions_list(string $uuid): void {
    require_auth();
    require_role('admin');

    $program = get_program_with_forms_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    $page = (int)($_GET['page'] ?? 1);
    // Allow viewing all non-draft submissions when no status is specified
    $status = !empty($_GET['status']) ? $_GET['status'] : null;

    $result = list_program_submissions($program['id'], $status, $page, 20);
    $submissions = $result['rows'];
    $stats = get_program_stats($program['id']);

    // Batch-load submission data for scoring to avoid N+1 queries
    $submissions_lookup = [];
    if (!empty($submissions)) {
        $all_sub_ids = [];
        foreach ($submissions as $sub) {
            foreach ($sub['submission_ids'] as $sid) {
                $all_sub_ids[] = (int)$sid;
            }
        }
        
        if (!empty($all_sub_ids)) {
            $all_sub_ids = array_values(array_unique($all_sub_ids));
            $placeholders = implode(',', array_fill(0, count($all_sub_ids), '?'));
            $submission_data = db_query(
                "SELECT id, answers, form_version_id FROM submissions WHERE id IN ($placeholders)",
                $all_sub_ids
            );
            
            // Index by ID for O(1) lookup
            foreach ($submission_data as $s) {
                decode_json_fields($s, ['answers']);
                $submissions_lookup[$s['id']] = $s;
            }
        }
    }

    $pageTitle = 'Submissions — ' . sanitize($program['name']);
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Programs', 'url' => '/admin/programs'],
        ['label' => 'Program Details', 'url' => '/admin/programs/' . $program['uuid'] . '/setup'],
        ['label' => 'Submissions']
    ];

    ob_start();
    require __DIR__ . '/views/submissions.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * End / close a program
 */
function program_end(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $program = get_program_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    if ($program['status'] !== 'active') {
        flash('error', 'Only active programs can be closed.');
        redirect("/admin/programs/{$program['uuid']}/setup");
    }

    try {
        end_program($program['id']);
        log_audit('closed', 'program', $program['id'], ['name' => $program['name']], $program['uuid']);
        flash('success', 'Program closed successfully.');
    } catch (Exception $e) {
        flash('error', 'Failed to close program: ' . $e->getMessage());
    }

    redirect('/admin/programs');
}

/**
 * Publish a program (change from draft to active)
 */
function program_publish(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $program = get_program_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    // Validate program has review stages before publishing
    if (empty($program['review_stages'])) {
        flash('error', 'Cannot publish a program without review stages. Please add at least one stage.');
        redirect("/admin/programs/$uuid/setup");
    }

    $unavailableForms = get_program_unavailable_forms_for_activation($program['form_ids'] ?? []);
    if (!empty($unavailableForms)) {
        $formLabels = array_map(
            static fn(array $form): string => $form['name'] . (empty($form['status']) ? '' : ' (' . $form['status'] . ')'),
            $unavailableForms
        );
        flash(
            'error',
            'Cannot publish this program because the following required forms are not currently published: '
            . implode(', ', $formLabels)
            . '. Publish those forms first or remove them from this program.'
        );
        redirect("/admin/programs/$uuid/setup");
    }

    try {
        update_program_status($program['id'], 'active');
        log_audit('published', 'program', $program['id'], ['name' => $program['name']], $program['uuid']);

        // Check for form deadline conflicts and show as warning instead of success
        $programEnd = $program['settings']['submission_period_end'] ?? null;
        $hasConflicts = false;
        if ($programEnd && !empty($program['form_ids'])) {
            $conflicts = get_form_deadline_conflicts($program['form_ids'], $programEnd);
            if (!empty($conflicts)) {
                $hasConflicts = true;
                $names = array_map(fn($c) => $c['name'], $conflicts);
                flash('warning', 'Program published, but some forms have earlier deadlines than the program closing date: '
                    . implode(', ', $names)
                    . '. Check the deadline mismatch warning below for details.');
            }
        }

        if (!$hasConflicts) {
            flash('success', 'Program published successfully and is now visible to users.');
        }
    } catch (Exception $e) {
        flash('error', 'Failed to publish program: ' . $e->getMessage());
    }

    redirect("/admin/programs/$uuid/setup");
}

/**
 * Unpublish a program (change from active to draft)
 */
function program_unpublish(string $uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();

    $program = get_program_by_uuid($uuid);
    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/admin/programs');
    }
    _require_program_ownership($program);

    try {
        update_program_status($program['id'], 'draft');
        log_audit('unpublished', 'program', $program['id'], ['name' => $program['name']], $program['uuid']);
        flash('success', 'Program unpublished successfully and is no longer visible to users.');
    } catch (Exception $e) {
        flash('error', 'Failed to unpublish program: ' . $e->getMessage());
    }

    redirect("/admin/programs/$uuid/setup");
}

// ============================================================================
// USER — HELPERS
// ============================================================================

/**
 * Ensure a program is active and within submission period before user actions.
 *
 * @param array<string, mixed> $program
 */
function _ensure_program_open_for_user(array $program): void {
    if ($program['status'] !== 'active') {
        flash('error', t('program_view.not_available'));
        redirect('/programs');
    }

    $settings = $program['settings'] ?? [];
    if (!empty($settings['submission_period_start']) && is_future($settings['submission_period_start'])) {
        flash('info', t('program_view.not_open_yet'));
        redirect('/programs');
    }
    if (!empty($settings['submission_period_end']) && is_past($settings['submission_period_end'])) {
        flash('info', t('program_view.submission_closed'));
        redirect('/programs');
    }
}


// ============================================================================
// ADMIN — REVIEW QUEUE
// ============================================================================

/**
 * Review queue — pending reviews for current user
 */
function program_review_queue(): void {
    require_auth();
    require_role('reviewer');
    $user = current_user();
    $is_admin = in_array($user['role'], ['admin', 'super_admin']);

    $program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    
    // Admins/super_admins see all pending reviews; reviewers see only their own
    $reviewer_id = $is_admin ? null : $user['id'];
    $allPendingReviews = get_pending_program_reviews($reviewer_id);
    
    // Apply program filter for display
    $filteredReviews = $program_id
        ? array_filter($allPendingReviews, fn($r) => (int)$r['program_id'] === $program_id)
        : $allPendingReviews;
    
    // Paginate filtered results
    $result = paginate_array(array_values($filteredReviews), $page, 20);
    $pendingReviews = $result['rows'];
    
    // Build programs dropdown from all reviews (unfiltered)
    $programsWithReviews = [];
    $programsSeen = [];
    foreach ($allPendingReviews as $review) {
        if (!isset($programsSeen[$review['program_id']])) {
            $programsWithReviews[] = [
                'id' => $review['program_id'],
                'uuid' => $review['program_uuid'],
                'name' => $review['program_name']
            ];
            $programsSeen[$review['program_id']] = true;
        }
    }

    $pageTitle = 'Review Queue';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Review Queue']
    ];

    ob_start();
    require __DIR__ . '/views/review_queue.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Show individual program submission for review
 */
function program_review_submission(string $psUuid): void {
    require_auth();
    require_role('reviewer');
    $user = current_user();

    $ps = get_program_submission_with_details($psUuid);
    if (!$ps) {
        flash('error', 'Program submission not found.');
        redirect('/admin/review-queue');
    }

    // Find the current review stage
    $currentStage = null;
    foreach ($ps['program']['review_stages'] as $stage) {
        if ((int)$stage['order'] === (int)$ps['current_stage']) {
            $currentStage = $stage;
            break;
        }
    }

    // Admins/super_admins can view any submission; reviewers must be assigned
    $is_admin = in_array($user['role'], ['admin', 'super_admin']);
    $is_assigned_reviewer = $currentStage && (int)$currentStage['reviewer_id'] === (int)$user['id'];
    
    if (!$is_admin && !$is_assigned_reviewer) {
        flash('error', 'You are not authorized to review this submission.');
        redirect('/admin/review-queue');
    }

    // Super admins and assigned reviewers can make decisions; regular admins can only view
    $can_decide = $is_assigned_reviewer || $user['role'] === 'super_admin';

    $pageTitle = 'Review — ' . sanitize($ps['user']['name']);
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Review Queue', 'url' => '/admin/review-queue'],
        ['label' => 'Review Submission']
    ];

    ob_start();
    require __DIR__ . '/views/review_submission.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Process review decision
 */
function program_decide(string $psUuid): void {
    require_auth();
    require_role('reviewer');
    csrf_check();

    $user = current_user();
    $ps = get_program_submission_with_details($psUuid);

    if (!$ps) {
        flash('error', 'Program submission not found.');
        redirect('/admin/review-queue');
    }

    // Prevent review on already completed submissions
    if (in_array($ps['status'], ['approved', 'rejected'])) {
        flash('error', 'This submission has already been reviewed and is ' . $ps['status'] . '.');
        redirect('/admin/review-queue');
    }

    $currentStage = null;
    foreach ($ps['program']['review_stages'] as $stage) {
        if ((int)$stage['order'] === (int)$ps['current_stage']) {
            $currentStage = $stage;
            break;
        }
    }

    if (!$currentStage) {
        flash('error', 'Review stage not found.');
        redirect('/admin/review-queue');
    }

    // Super admins can make decisions on any submission; others must be the assigned reviewer
    $is_super_admin = $user['role'] === 'super_admin';
    $is_assigned_reviewer = (int)$currentStage['reviewer_id'] === (int)$user['id'];
    
    if (!$is_super_admin && !$is_assigned_reviewer) {
        flash('error', 'You are not authorized to review this submission.');
        redirect('/admin/review-queue');
    }

    $score = !empty($_POST['score']) ? (int)$_POST['score'] : null;
    $justification = trim($_POST['justification'] ?? '');

    if ($currentStage['action'] === 'score') {
        if ($score === null || $score < 0 || $score > 100) {
            flash('error', 'Please provide a valid score (0–100).');
            redirect("/admin/review-queue/$psUuid");
        }
        $threshold = $currentStage['pass_threshold'] ?? 0;
        $decision = ($score >= $threshold) ? 'approved' : 'rejected';
    } else {
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision, ['approved', 'rejected'])) {
            flash('error', 'Please select a decision.');
            redirect("/admin/review-queue/$psUuid");
        }
    }

    try {
        record_program_decision($ps['id'], $ps['current_stage'], $user['id'], $decision, $score, $justification);

        if ($decision === 'approved') {
            advance_program_stage($ps['id']);
        } else {
            reject_program_submission($ps['id']);
        }

        // Notify user (unless batch mode)
        if (empty($ps['program']['settings']['batch_notifications'])) {
            queue_email_from_template(
                'program_decision',
                $ps['user'],
                [
                    'program_name' => $ps['program']['name'],
                    'decision' => ucfirst($decision),
                    'justification' => $justification,
                    'submission_link' => base_url("/programs/{$ps['program']['uuid']}"),
                ]
            );

            create_notification($ps['user']['id'], 'program_decision', [
                'program_name' => $ps['program']['name'],
                'decision'     => $decision,
                'program_link' => '/my-programs',
            ]);
        }

        log_audit('program_decision', 'program_submission', $ps['id'], [
            'decision' => $decision,
            'stage' => $ps['current_stage'],
            'score' => $score,
        ], $ps['uuid']);

        flash('success', 'Review decision recorded successfully.');
    } catch (Exception $e) {
        flash('error', 'Failed to record decision: ' . $e->getMessage());
    }

    redirect('/admin/review-queue');
}

// ============================================================================
// USER — PROGRAM BROWSING & SUBMISSION
// ============================================================================

/**
 * List programs available to the user (with pagination + search)
 */
function user_programs_list(): void {
    require_auth();

    $user = current_user();
    $search = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));

    $result = browse_available_programs($search ?: null, $page, 12);
    $recently_closed_programs = get_recently_closed_programs($search ?: null);

    // For each program, get user's submission count and forms
    foreach ($result['rows'] as &$prog) {
        $prog['user_submission_count'] = count_user_program_submissions($user['id'], $prog['id']);
        $prog['form_count'] = count($prog['form_ids']);
    }
    unset($prog);

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('common.programs')],
    ];

    $title = t('programs_browse.title');
    require __DIR__ . '/views/user_list.php';
}

/**
 * Show user's own program submissions/enrollments (with pagination + status filter)
 * GET /my-programs
 */
function user_my_programs(): void {
    require_auth();

    $user = current_user();
    $status_filter = $_GET['status'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));

    // Validate status filter — includes 'draft'
    $allowed_statuses = ['draft', 'submitted', 'in_review', 'approved', 'rejected'];
    if ($status_filter && !in_array($status_filter, $allowed_statuses)) {
        $status_filter = null;
    }

    $result = list_user_program_submissions($user['id'], $status_filter, $page);

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.my_programs')],
    ];

    $title = t('my_programs.title');
    require __DIR__ . '/views/my_programs.php';
}

/**
 * View a single program (user side) — see required forms, attach, submit
 */
function user_program_view(string $uuid): void {
    require_auth();

    $user = current_user();
    $program = get_program_with_forms_by_uuid($uuid);

    if (!$program) {
        flash('error', t('program_view.not_available'));
        redirect('/programs');
    }

    _ensure_program_open_for_user($program);

    // Check max submissions
    $settings = $program['settings'] ?? [];
    $maxSubs = (int)($settings['max_submissions_per_user'] ?? 1);
    $existingCount = count_user_program_submissions($user['id'], $program['id']);
    $canSubmitNew = $maxSubs === 0 || $existingCount < $maxSubs; // 0 = unlimited

    // Get existing draft if one exists (don't auto-create on page load)
    $draft = null;
    if ($canSubmitNew) {
        $draft = db_one(
            "SELECT * FROM program_submissions WHERE program_id = ? AND user_id = ? AND status = 'draft' ORDER BY created_at DESC LIMIT 1",
            [$program['id'], $user['id']]
        );
        if ($draft) {
            $draft = _decode_program_submission($draft);
        }
    }

    // For each required form, get user's available submissions
    $formStatuses = [];
    foreach ($program['forms'] as $form) {
        $userSubs = db_query(
            "SELECT id, uuid, submitted_at, status FROM submissions WHERE user_id = ? AND form_id = ? AND status != 'draft' ORDER BY submitted_at DESC",
            [$user['id'], $form['id']]
        );
        $attachedSubId = $draft ? ($draft['submission_ids'][(string)$form['id']] ?? null) : null;

        $formStatuses[] = [
            'form' => $form,
            'user_submissions' => $userSubs,
            'attached_submission_id' => $attachedSubId,
        ];
    }

    // Load user's past program submissions for this program
    $pastSubmissions = db_query(
        "SELECT * FROM program_submissions WHERE user_id = ? AND program_id = ? AND status != 'draft' ORDER BY submitted_at DESC",
        [$user['id'], $program['id']]
    );
    $pastSubmissions = array_map('_decode_program_submission', $pastSubmissions);

    // Batch-load submission details for past program submissions (avoids N+1)
    $pastSubmissionDetails = [];
    if (!empty($pastSubmissions)) {
        $allSubIds = [];
        foreach ($pastSubmissions as $ps) {
            foreach ($ps['submission_ids'] as $sid) {
                $allSubIds[] = (int)$sid;
            }
        }
        if (!empty($allSubIds)) {
            $allSubIds = array_values(array_unique($allSubIds));
            $placeholders = implode(',', array_fill(0, count($allSubIds), '?'));
            $subRows = db_query(
                "SELECT id, uuid, form_id, submitted_at, status FROM submissions WHERE id IN ($placeholders)",
                $allSubIds
            );
            foreach ($subRows as $s) {
                $pastSubmissionDetails[(int)$s['id']] = $s;
            }
        }
    }

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('common.programs'), 'url' => '/programs'],
        ['label' => sanitize($program['name'])],
    ];

    $title = sanitize($program['name']);

    ob_start();
    require __DIR__ . '/views/user_program.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/user.php';
}

/**
 * Attach / detach a submission to a program draft (POST)
 */
function user_program_attach(string $uuid): void {
    require_auth();
    csrf_check();

    $user = current_user();
    $program = get_program_by_uuid($uuid);

    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/programs');
    }

    _ensure_program_open_for_user($program);

    $draft = get_or_create_program_draft($program['id'], $user['id']);
    $formId = (int)($_POST['form_id'] ?? 0);
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['attach_action'] ?? 'attach';

    if ($action === 'detach') {
        detach_submission_from_program($draft['id'], $formId);
        flash('success', t('program_view.submission_detached'));
    } else {
        // Verify user owns the submission
        $sub = db_one("SELECT id, user_id, form_id FROM submissions WHERE id = ? AND user_id = ?", [$submissionId, $user['id']]);
        if (!$sub || (int)$sub['form_id'] !== $formId) {
            flash('error', t('program_view.invalid_submission'));
            redirect("/programs/$uuid");
        }
        attach_submission_to_program($draft['id'], $formId, $submissionId);
        flash('success', t('program_view.submission_attached'));
    }

    redirect("/programs/$uuid");
}

/**
 * Discard program draft (POST)
 */
function user_program_discard_draft(string $uuid): void {
    require_auth();
    csrf_check();

    $user = current_user();
    $program = get_program_by_uuid($uuid);

    if (!$program) {
        flash('error', 'Program not found.');
        redirect('/programs');
    }

    _ensure_program_open_for_user($program);

    $discarded = discard_program_draft($program['id'], $user['id']);

    if ($discarded) {
        log_audit('discarded', 'program_submission_draft', 0, ['program_uuid' => $program['uuid'], 'user_uuid' => $user['uuid']], null);
        flash('success', t('program_view.draft_discarded'));
    } else {
        flash('info', t('program_view.no_draft_to_discard'));
    }

    redirect("/programs/$uuid");
}

/**
 * Submit program entry (POST)
 */
function user_program_submit(string $uuid): void {
    require_auth();
    csrf_check();

    $user = current_user();
    $program = get_program_with_forms_by_uuid($uuid);

    if (!$program) {
        flash('error', t('program_view.not_available'));
        redirect('/programs');
    }

    _ensure_program_open_for_user($program);

    // Check max submissions
    $maxSubs = (int)($program['settings']['max_submissions_per_user'] ?? 1);
    $existingCount = count_user_program_submissions($user['id'], $program['id']);
    if ($maxSubs !== 0 && $existingCount >= $maxSubs) {
        flash('error', t('program_view.max_submissions_reached'));
        redirect("/programs/$uuid");
    }

    // Safety-net rate limit: when admin hasn't set a cap (unlimited), prevent rapid-fire abuse
    if ($maxSubs === 0) {
        $rate_error = check_program_submission_rate_limit($user['id'], $program['id']);
        if ($rate_error) {
            flash('warning', $rate_error);
            redirect("/programs/$uuid");
        }
    }

    $draft = db_one(
        "SELECT * FROM program_submissions WHERE program_id = ? AND user_id = ? AND status = 'draft' ORDER BY created_at DESC LIMIT 1",
        [$program['id'], $user['id']]
    );

    if (!$draft) {
        flash('error', t('program_view.no_draft'));
        redirect("/programs/$uuid");
    }

    $draft = _decode_program_submission($draft);

    // Verify all required forms have attached submissions
    $missingForms = [];
    foreach ($program['forms'] as $form) {
        if (empty($draft['submission_ids'][(string)$form['id']])) {
            $missingForms[] = $form['name'];
        }
    }

    if (!empty($missingForms)) {
        flash('error', t('program_view.attach_all_required_inline') . ' ' . implode(', ', $missingForms));
        redirect("/programs/$uuid");
    }

    $success = submit_program_entry($draft['id']);
    if (!$success) {
        flash('error', t('program_view.max_submissions_reached'));
        redirect("/programs/$uuid");
    }

    $submittedPs = db_one('SELECT uuid FROM program_submissions WHERE id = ?', [$draft['id']]);
    log_audit('submitted', 'program_submission', $draft['id'], ['program_uuid' => $program['uuid']], $submittedPs['uuid'] ?? null);

    flash('success', t('program_view.submission_received'));
    redirect("/programs/$uuid");
}
