<?php

/**
 * Submissions Module - Controllers
 *
 * Handles user form filling, submission, and viewing.
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../forms/models.php';
require_once __DIR__ . '/../forms/validation.php';

/**
 * User dashboard - shows available forms, submissions, and drafts
 */
function dashboard(): void {
    require_auth();
    
    $user = current_user();
    
    // Generate time-of-day greeting
    $greeting = 'Hello';
    if (file_exists(__DIR__ . '/../dashboard/models.php')) {
        require_once __DIR__ . '/../dashboard/models.php';
        $greeting = get_greeting($user['timezone'] ?? 'UTC');
    }
    
    // Get user's recent submissions and actual total count
    $submissions = get_user_submissions($user['id'], 5);
    $total_submissions = count_all_user_submissions($user['id']);
    
    // Get form drafts
    $drafts = get_user_drafts($user['id']);
    
    // Get program drafts
    require_once __DIR__ . '/../programs/models.php';
    $program_drafts = get_user_program_drafts($user['id']);
    
    // Get program submissions (latest 5)
    $program_submissions = get_user_program_submissions($user['id']);
    
    // Pending clarifications
    require_once __DIR__ . '/../clarifications/models.php';
    $pending_clarifications = get_pending_clarifications($user['id']);
    
    // Get available forms (latest 5, excluding those past submission deadline)
    $available_forms = db_query("
        SELECT f.id, f.uuid, f.name, f.description, f.status, f.settings, f.created_at
        FROM forms f
        WHERE f.status = 'published' AND f.deleted_at IS NULL
        ORDER BY f.updated_at DESC
    ");
    // Filter out closed forms (past deadline) in PHP since deadline is in JSON settings
    $open_forms = [];
    foreach ($available_forms as $f) {
        decode_json_fields($f, ['settings']);
        if (!is_form_closed($f)) {
            $open_forms[] = $f;
        }
    }
    $available_forms = array_slice($open_forms, 0, 5);
    
    // Get latest active program (not submission)
    $latest_program = db_one("
        SELECT p.id, p.uuid, p.name, p.description, p.status
        FROM programs p
        WHERE p.id = (
            SELECT ps.program_id
            FROM program_submissions ps
            WHERE ps.user_id = ?
            ORDER BY ps.updated_at DESC
            LIMIT 1
        )
        AND p.status IN ('active', 'closed')
    ", [$user['id']]);
    
    $breadcrumbs = [
        ['label' => t('common.dashboard')],
    ];

    require __DIR__ . '/views/dashboard.php';
}

/**
 * User submissions list - shows all user's submissions with filtering and pagination
 * GET /submissions
 */
function user_submissions_list(): void {
    require_auth();

    $user = current_user();
    $status_filter = $_GET['status'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));

    // Validate status filter — only statuses that are actually set in code
    $allowed_statuses = ['draft', 'submitted', 'clarification_requested'];
    if ($status_filter && !in_array($status_filter, $allowed_statuses)) {
        $status_filter = null;
    }

    $result = list_user_submissions($user['id'], $status_filter, $page);

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.my_submissions')],
    ];

    $title = t('my_submissions.title');
    require __DIR__ . '/views/user_submissions.php';
}

/**
 * Browse available forms with pagination and search
 * GET /forms
 */
function browse_forms(): void {
    require_auth();

    $user = current_user();
    $search = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));

    $result = browse_available_forms($search ?: null, $page, 12, $user['id']);
    $recently_closed_forms = get_recently_closed_forms($search ?: null);

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('common.forms')],
    ];

    require __DIR__ . '/views/forms_browse.php';
}

/**
 * Display form for filling
 * GET /forms/{uuid}
 */
function fill_form(string $uuid): void {
    require_auth();
    
    $user = current_user();
    
    // Get form by UUID
    $form = get_form_by_uuid($uuid);
    if (!$form || $form['status'] !== 'published') {
        flash('error', t('form_fill.form_unavailable'));
        redirect('/dashboard');
    }

    // Check deadline
    check_form_deadline($form);
    
    // Check prerequisites — render gate page instead of redirecting
    $prereq_ids = $form['settings']['prerequisite_form_ids'] ?? [];
    if (!empty($prereq_ids)) {
        $prerequisite_status = get_prerequisite_status($user['id'], $prereq_ids);
        if (!$prerequisite_status['met']) {
            $breadcrumbs = [
                ['label' => t('common.dashboard'), 'url' => '/dashboard'],
                ['label' => t('common.forms'), 'url' => '/forms'],
                ['label' => sanitize($form['name'])],
            ];
            $title = $form['name'];
            require __DIR__ . '/views/prerequisites_gate.php';
            return;
        }
    }
    
    // Get active version
    $version = get_active_version($form['id']);
    if (!$version) {
        flash('error', t('form_fill.no_active_version'));
        redirect('/dashboard');
    }
    
    // Get questions
    $questions = get_questions($version['id']);
    
    // Determine submission logic
    $limit = (int)($form['settings']['submission_limit'] ?? 1);
    $submitted_count = count_user_submissions($user['id'], $form['id']);
    $allows_multiple = ($limit > 1 || $limit === 0);
    $can_submit_new = ($limit === 0 || $submitted_count < $limit);
    $allow_edits = !empty($form['settings']['is_editable_after_submit']);
    
    // For single-submission forms (limit = 1)
    if (!$allows_multiple) {
        $existing_submission = get_user_submission($user['id'], $form['id'], 'submitted');
        if ($existing_submission) {
            // User has already submitted - redirect to view (edit button shown if editable)
            flash('info', t('form_fill.already_submitted'));
            redirect('/submissions/' . $existing_submission['uuid']);
        }
        // No submission yet - show form
        $can_submit_new = true;
        $past_submissions = null;
    } else {
        // Multi-submission forms (limit > 1 or unlimited)
        $past_submissions_page = max(1, (int)($_GET['submissions_page'] ?? 1));
        $past_submissions = get_user_submissions_for_form($user['id'], $form['id'], $past_submissions_page, 15);
    }
    
    // Load draft if exists (for new submission)
    $draft = get_draft($form['id'], $user['id']);
    if ($draft) {
        // Version mismatch: draft was saved against an older version whose
        // question IDs no longer match. Delete the stale draft and inform the user.
        if ((int)$draft['form_version_id'] !== (int)$version['id']) {
            delete_draft($form['id'], $user['id']);
            $draft = null;
            $draft_was_cleared = true;
            flash('info', t('form_fill.draft_cleared_version_update'));
        } else {
            decode_json_fields($draft, ['data']);
        }
    }
    $draft_data = $draft ? $draft['data'] : [];
    
    // Resolve file metadata so the view can render file indicators base on draft data
    $edit_files = resolve_file_metadata_for_answers($questions, $draft_data);

    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('common.forms'), 'url' => '/forms'],
        ['label' => sanitize($form['name'])],
    ];

    $title = $form['name'];
    require __DIR__ . '/views/fill.php';
}

/**
 * Submit form
 * POST /forms/{uuid}/submit
 */
function submit_form(string $uuid): void {
    require_auth();
    csrf_check();
    
    $user = current_user();
    
    // Get form
    $form = get_form_by_uuid($uuid);
    if (!$form || $form['status'] !== 'published') {
        flash('error', t('form_fill.not_found'));
        redirect('/dashboard');
    }
    
    // Check deadline
    check_form_deadline($form);
    
    // Check prerequisites
    check_prerequisites($user['id'], $form);

    // Get active version
    $version = get_active_version($form['id']);
    
    // Get questions for visibility calculation
    $questions = get_questions($version['id']);
    
    // Validate submission
    $answers = $_POST['answers'] ?? [];
    // Note: Third parameter is deprecated and ignored - visibility is now calculated server-side
    $errors = validate_submission($version['id'], $answers, null);
    
    if (!empty($errors)) {
        // Re-render form with errors
        $draft_data = $answers;
        $title = $form['name'];
        require __DIR__ . '/views/fill.php';
        return;
    }
    
    // SECURITY FIX: Calculate visible questions server-side instead of trusting client input
    // This prevents attackers from submitting answers for hidden questions
    require_once __DIR__ . '/../forms/validation.php';
    $visible_question_uids = calculate_visible_questions($questions, $answers);
    
    // Filter answers to only include visible questions
    $visible_question_ids = [];
    foreach ($questions as $q) {
        if (in_array($q['uid'], $visible_question_uids)) {
            $visible_question_ids[] = $q['id'];
        }
    }
    
    // Filter answers to only visible questions
    $filtered_answers = [];
    foreach ($answers as $question_id => $answer) {
        if (in_array($question_id, $visible_question_ids)) {
            $filtered_answers[(string)$question_id] = $answer;
        }
    }
    $answers = $filtered_answers;
    
    // Convert datetime answers from user's local timezone to UTC for consistent storage
    foreach ($questions as $q) {
        $qid = (string)$q['id'];
        if ($q['type'] === 'datetime' && isset($answers[$qid]) && $answers[$qid] !== '') {
            $utc_value = from_datetime_local($answers[$qid]);
            if ($utc_value !== null) {
                $answers[$qid] = $utc_value;
            }
        }
    }
    
    // Capture metadata
    $metadata = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'submitted_from' => 'web'
    ];
    
    // Enforce submission limit (per user) with row-level locking
    // This prevents concurrent requests from bypassing the limit.
    $limit = (int)($form['settings']['submission_limit'] ?? 1);

    $rate_error = check_submission_rate_limit($user['id'], $form['id']);
    if ($rate_error) {
        flash('warning', $rate_error);
        redirect('/forms/' . $uuid);
    }
    $submission_result = db_transaction(function() use ($form, $version, $user, $answers, $metadata, $limit) {
        $lock_name = null;

        try {
            if ($limit !== 0) { // 0 means unlimited
                $lock_name = 'submission_limit:' . $form['id'] . ':' . $user['id'];
                $lock_row = db_one('SELECT GET_LOCK(?, 5) as acquired', [$lock_name]);
                if (empty($lock_row) || (int)$lock_row['acquired'] !== 1) {
                    return ['allowed' => false, 'submission_id' => null, 'lock_failed' => true];
                }
            }

            $locked_form = db_one('SELECT id FROM forms WHERE id = ? FOR UPDATE', [$form['id']]);
            if (!$locked_form) {
                return ['allowed' => false, 'submission_id' => null, 'form_missing' => true];
            }

            if ($limit !== 0) { // 0 means unlimited
                $count_row = db_one(
                    'SELECT COUNT(*) as count FROM submissions WHERE user_id = ? AND form_id = ? AND status != ?',
                    [$user['id'], $form['id'], 'draft']
                );
                $submitted_count = (int)($count_row['count'] ?? 0);
                if ($submitted_count >= $limit) {
                    return ['allowed' => false, 'submission_id' => null];
                }
            }

            $submission_id = create_submission(
                $form['id'],
                $version['id'],
                $user['id'],
                $answers,
                $metadata
            );

            return ['allowed' => true, 'submission_id' => $submission_id];
        } finally {
            if ($lock_name !== null) {
                db_one('SELECT RELEASE_LOCK(?) as released', [$lock_name]);
            }
        }
    });

    if (empty($submission_result['allowed'])) {
        if (!empty($submission_result['form_missing'])) {
            flash('error', t('form_fill.not_found'));
            redirect('/dashboard');
        }

        if (!empty($submission_result['lock_failed'])) {
            flash('warning', t('form_fill.form_busy'));
            redirect('/forms/' . $uuid);
        }

        flash('warning', t('program_view.max_submissions_reached'));
        $latest = get_user_submission($user['id'], $form['id']);
        if ($latest) {
            redirect('/submissions/' . $latest['uuid']);
        }
        redirect('/dashboard');
    }

    $submission_id = $submission_result['submission_id'];

    // Associate any files uploaded during form-filling with this submission.
    // Files are uploaded via AJAX before the submission exists, leaving
    // submission_id = null until this point.
    $file_ids = [];
    foreach ($questions as $q) {
        if ($q['type'] === 'file' && !empty($answers[(string)$q['id']])) {
            $file_ids[] = (int)$answers[(string)$q['id']];
        }
    }
    if (!empty($file_ids)) {
        associate_files_with_submission($file_ids, $submission_id, $user['id']);
    }
    
    // Trigger webhook if configured for this form
    require_once __DIR__ . '/../webhooks/processor.php';
    trigger_webhook_for_form($form['id'], $submission_id);
    
    // Delete draft
    delete_draft($form['id'], $user['id']);
    
    // Get submission for redirect
    $submission = get_submission_by_id($submission_id);
    
    // Safety check: ensure submission was retrieved
    if (!$submission) {
        error_log("Failed to retrieve submission after creation. ID: $submission_id");
        flash('success', t('submission.received_message'));
        redirect('/dashboard');
    }
    
    // Queue submission confirmation email
    require_once __DIR__ . '/../emails/sender.php';
    queue_email_from_template('submission_confirmed', $user, [
        'form_name' => $form['name'],
        'submission_id' => $submission['uuid'],
        'submission_link' => base_url('/submissions/' . $submission['uuid']),
        'submitted_at' => $submission['created_at']
    ]);
    
    redirect('/submissions/' . $submission['uuid']);
}

/**
 * View submission
 * GET /submissions/{uuid}
 */
function view_submission(string $uuid): void {
    require_auth();
    
    $user = current_user();
    
    // Get submission
    $submission = get_submission_by_uuid($uuid);
    if (!$submission) {
        flash('error', t('submission.not_found'));
        redirect('/dashboard');
    }
    
    // Check ownership — admins and super_admins can view any submission
    $is_admin = in_array($user['role'], ['admin', 'super_admin']);
    if (!$is_admin && $submission['user_id'] !== $user['id']) {
        flash('error', t('submission.access_denied'));
        redirect('/dashboard');
    }
    
    // Get form (allow viewing even if form is deleted)
    $form = db_one('SELECT * FROM forms WHERE id = ?', [$submission['form_id']]);
    if ($form) {
        decode_json_fields($form, ['settings']);
    } else {
        // Form was hard-deleted, show error
        flash('error', t('submission.form_missing'));
        redirect('/dashboard');
    }
    
    $version = get_version($submission['form_version_id']);
    $questions = get_questions($version['id']);
    
    // Get files
    $files = get_files($submission['id']);
    
    // Check if user came from form page or submissions list (for better navigation)
    $from_form_uuid = $_GET['from_form'] ?? null;
    
    // Build return URL for submissions list (preserves page and filter state)
    $back_to_submissions_url = null;
    if (($_GET['from'] ?? null) === 'list') {
        $return_params = [];
        $return_page = (int)($_GET['list_page'] ?? 1);
        if ($return_page > 1) {
            $return_params['page'] = $return_page;
        }
        $return_status = $_GET['list_status'] ?? null;
        if ($return_status && in_array($return_status, ['draft', 'submitted', 'clarification_requested'], true)) {
            $return_params['status'] = $return_status;
        }
        $back_to_submissions_url = '/submissions' . (!empty($return_params) ? '?' . http_build_query($return_params) : '');
    }
    
    // Determine if form allows multiple submissions and if user can still submit
    $limit = (int)($form['settings']['submission_limit'] ?? 1);
    $submitted_count = count_user_submissions($user['id'], $form['id']);
    $allows_multiple = ($limit > 1 || $limit === 0);
    $can_submit_new = ($limit === 0 || $submitted_count < $limit);
    $is_editable = is_submission_editable($submission, $form);
    
    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.my_submissions'), 'url' => '/submissions'],
        ['label' => t('submission.your_responses')],
    ];

    $title = sanitize($form['name']);
    require __DIR__ . '/views/confirmation.php';
}

/**
 * Edit submission
 * GET /submissions/{uuid}/edit
 */
function edit_submission(string $uuid): void {
    require_auth();
    
    $user = current_user();
    
    // Get submission
    $submission = get_submission_by_uuid($uuid);
    if (!$submission) {
        flash('error', t('submission.not_found'));
        redirect('/dashboard');
    }
    
    // Check ownership
    if ($submission['user_id'] !== $user['id']) {
        flash('error', t('submission.access_denied'));
        redirect('/dashboard');
    }
    
    // Get form
    $form = get_form_by_id($submission['form_id']);

    // Enforce deadline and prerequisites on edits
    check_form_deadline($form);
    check_prerequisites($user['id'], $form);
    
    // Check if editable
    check_editable($submission, $form);
    
    // Get version and questions
    $version = get_version($submission['form_version_id']);
    $questions = get_questions($version['id']);
    
    // Determine submission logic for multi-submission forms
    $limit = $form['settings']['submission_limit'] ?? 1;
    $submitted_count = count_user_submissions($user['id'], $form['id']);
    $allows_multiple = ($limit > 1 || $limit === 0);
    // Always allow the form to render when editing an existing submission
    $can_submit_new = true;
    $allow_edits = !empty($form['settings']['is_editable_after_submit']);
    
    // Get past submissions for multi-submission forms
    $past_submissions = null;
    $past_submissions_page = max(1, (int)($_GET['submissions_page'] ?? 1));
    if ($allows_multiple) {
        $past_submissions = get_user_submissions_for_form($user['id'], $form['id'], $past_submissions_page, 15);
    }
    
    // Use submission answers as draft data
    $draft_data = $submission['answers'];
    
    // Convert datetime answers from UTC back to datetime-local format for the input fields
    foreach ($questions as $q) {
        $qid = (string)$q['id'];
        if ($q['type'] === 'datetime' && isset($draft_data[$qid]) && $draft_data[$qid] !== '') {
            $draft_data[$qid] = to_datetime_local($draft_data[$qid]);
        }
    }
    
    // Resolve file metadata so the view can render file indicators server-side
    $edit_files = resolve_file_metadata_for_answers($questions, $draft_data);
    
    $is_editing = true;
    
    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.my_submissions'), 'url' => '/submissions'],
        ['label' => t('submission.edit_submission')],
    ];

    $title = sanitize($form['name']);
    require __DIR__ . '/views/fill.php';
}

/**
 * Update submission
 * POST /submissions/{uuid}/update
 */
function update_submission(string $uuid): void {
    require_auth();
    csrf_check();
    
    $user = current_user();
    
    // Get submission
    $submission = get_submission_by_uuid($uuid);
    if (!$submission) {
        flash('error', t('submission.not_found'));
        redirect('/dashboard');
    }
    
    // Check ownership
    if ($submission['user_id'] !== $user['id']) {
        flash('error', t('submission.access_denied'));
        redirect('/dashboard');
    }
    
    // Get form
    $form = get_form_by_id($submission['form_id']);

    // Enforce deadline and prerequisites on edits
    check_form_deadline($form);
    check_prerequisites($user['id'], $form);
    
    // Check if editable
    check_editable($submission, $form);
    
    // Get questions for visibility calculation
    $questions = get_questions($submission['form_version_id']);
    
    // Validate (pass submission ID so existing file references are accepted)
    $answers = $_POST['answers'] ?? [];
    $errors = validate_submission($submission['form_version_id'], $answers, null, $submission['id']);
    
    if (!empty($errors)) {
        // Re-render form with errors — resolve file metadata so indicators display
        $version = get_version($submission['form_version_id']);
        $draft_data = $answers;
        $is_editing = true;
        $edit_files = resolve_file_metadata_for_answers($questions, $draft_data);
        $title = sanitize($form['name']);
        require __DIR__ . '/views/fill.php';
        return;
    }
    
    // SECURITY FIX: Calculate visible questions server-side instead of trusting client input
    // This prevents attackers from submitting answers for hidden questions
    require_once __DIR__ . '/../forms/validation.php';
    $visible_question_uids = calculate_visible_questions($questions, $answers);
    
    // Filter answers to only include visible questions
    $visible_question_ids = [];
    foreach ($questions as $q) {
        if (in_array($q['uid'], $visible_question_uids)) {
            $visible_question_ids[] = $q['id'];
        }
    }
    
    // Filter answers to only visible questions
    $filtered_answers = [];
    foreach ($answers as $question_id => $answer) {
        if (in_array($question_id, $visible_question_ids)) {
            $filtered_answers[(string)$question_id] = $answer;
        }
    }
    $answers = $filtered_answers;
    
    // Convert datetime answers from user's local timezone to UTC for consistent storage
    foreach ($questions as $q) {
        $qid = (string)$q['id'];
        if ($q['type'] === 'datetime' && isset($answers[$qid]) && $answers[$qid] !== '') {
            $utc_value = from_datetime_local($answers[$qid]);
            if ($utc_value !== null) {
                $answers[$qid] = $utc_value;
            }
        }
    }
    
    // Remove files that were deleted/replaced while editing
    cleanup_removed_file_answers(
        $questions,
        is_array($submission['answers'] ?? null) ? $submission['answers'] : [],
        $answers,
        (int)$user['id'],
        (int)$submission['id']
    );

    // Update submission
    update_submission_answers($submission['id'], $answers);

    // Associate any files uploaded during editing (submission_id = null) with
    // this submission. Files may have been re-uploaded as part of the edit.
    $file_ids = [];
    foreach ($questions as $q) {
        if ($q['type'] === 'file' && !empty($answers[(string)$q['id']])) {
            $file_ids[] = (int)$answers[(string)$q['id']];
        }
    }
    if (!empty($file_ids)) {
        associate_files_with_submission($file_ids, $submission['id'], $user['id']);
    }
    
    // Delete any draft that might have been created during editing
    delete_draft($form['id'], $user['id']);
    
    redirect('/submissions/' . $submission['uuid']);
}

// ============================================================================
// ADMIN: ALL SUBMISSIONS MANAGEMENT
// ============================================================================

/**
 * Admin view of all submissions across all forms
 *
 * SECURITY:
 * - Requires admin role
 * - Super admins see all submissions
 * - Regular admins only see submissions from forms they created
 * - Includes submissions from soft-deleted forms (if admin owned the form)
 */
function admin_submissions_list(): void {
    require_auth();
    require_role('admin');
    
    $user = current_user();
    $page = (int)($_GET['page'] ?? 1);
    
    // Build filters array from GET parameters
    $filters = [];
    
    // Validate status against allowlist (defense-in-depth — matches user-side validation)
    $allowed_statuses = ['submitted', 'clarification_requested'];
    if (!empty($_GET['status']) && in_array($_GET['status'], $allowed_statuses, true)) {
        $filters['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['form_id'])) {
        $filters['form_id'] = (int)$_GET['form_id'];
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
    
    if (!empty($_GET['include_deleted'])) {
        $filters['include_deleted'] = true;
    }
    
    if (!empty($_GET['search'])) {
        $filters['search'] = trim($_GET['search']);
    }
    
    if (!empty($_GET['sort'])) {
        $filters['sort'] = $_GET['sort'];
    }
    
    if (!empty($_GET['clarification_status'])) {
        $allowed_clarification_statuses = ['open', 'responded'];
        if (in_array($_GET['clarification_status'], $allowed_clarification_statuses)) {
            $filters['clarification_status'] = $_GET['clarification_status'];
        }
    }
    
    // SECURITY: Super admins see all submissions, regular admins see only their own
    $created_by = $user['role'] === 'super_admin' ? null : $user['id'];
    
    // Get submissions with filters
    $result = list_all_submissions($created_by, $filters, $page, 20);
    
    // Get forms list for filter dropdown (respects ownership)
    $forms_list = get_forms_for_filter($created_by);
    
    // Calculate statistics from database totals (not just current page)
    $stats = get_all_submissions_stats($created_by, $filters);
    $stats['total'] = $stats['total'] ?? $result['total'];
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'All Submissions']
    ];
    
    $title = 'All Submissions';
    
    ob_start();
    require __DIR__ . '/views/admin_list.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Resolve and validate a file for serving.
 * Returns [file_record, full_path, sanitized_name] or exits with HTTP error.
 *
 * @return array{0: array<string, mixed>, 1: string, 2: string}
 */
function resolve_file_for_serving(int $file_id): array {
    $file = get_file($file_id);
    if (!$file) {
        http_response_code(404);
        exit('File not found');
    }

    // Submission is required for permission checks
    $submission = get_submission_by_id($file['submission_id']);
    if (!$submission) {
        http_response_code(404);
        exit('File not found');
    }

    $user = current_user();

    // Access rules: owner, admin/super_admin, or form permission to view results
    $is_owner = $submission['user_id'] === $user['id'];
    $is_admin = in_array($user['role'], ['admin', 'super_admin']);
    $has_results_access = false;
    if (!$is_owner && !$is_admin) {
        // Check form permissions for viewers/reviewers
        $has_results_access = has_form_permission($submission['form_id'], 'view_results');
    }

    if (!$is_owner && !$is_admin && !$has_results_access) {
        http_response_code(403);
        exit('Access denied');
    }

    // Resolve path safely to prevent path traversal
    // basename() handles both legacy records (runtime/storage/uploads/file.ext) and new records (file.ext)
    global $config;
    $storage_root = realpath($config['storage']['local_path']);
    $full_path = $storage_root ? realpath($storage_root . '/' . basename($file['stored_path'])) : false;

    if ($storage_root === false || $full_path === false || !str_starts_with($full_path, $storage_root)) {
        http_response_code(404);
        exit('File not found');
    }

    if (!is_file($full_path)) {
        http_response_code(404);
        exit('File not found');
    }

    // SECURITY: Remove all non-printable ASCII characters including newlines and carriage returns
    // to prevent HTTP Response Splitting / Header Injection attacks
    $safe_name = basename($file['original_name']);
    $safe_name = preg_replace('/[^\x20-\x7E]/', '', $safe_name);
    $safe_name = str_replace('"', '', $safe_name);

    return [$file, $full_path, $safe_name];
}

/**
 * Secure file download
 * GET /files/{id}/download
 */
function download_file(int $file_id): void {
    require_auth();

    [$file, $full_path, $safe_name] = resolve_file_for_serving($file_id);

    $mime = isset(MIME_TYPE_MAPPING[$file['mime_type']]) ? $file['mime_type'] : 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)$file['size_bytes']);
    header('Content-Disposition: attachment; filename="' . $safe_name . '"');
    header('X-Content-Type-Options: nosniff');

    readfile($full_path);
    exit;
}

/**
 * Inline file preview (images and PDFs rendered in browser)
 * GET /files/{id}/preview
 */
function preview_file(int $file_id): void {
    require_auth();

    [$file, $full_path, $safe_name] = resolve_file_for_serving($file_id);

    // Only allow inline preview for safe, browser-renderable types
    $previewable_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
    ];

    if (!in_array($file['mime_type'], $previewable_types, true)) {
        // Fall back to download for non-previewable types
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . (string)$file['size_bytes']);
        header('Content-Disposition: attachment; filename="' . $safe_name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($full_path);
        exit;
    }

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . (string)$file['size_bytes']);
    header('Content-Disposition: inline; filename="' . $safe_name . '"');
    // Prevent the browser from sniffing a different MIME type
    header('X-Content-Type-Options: nosniff');

    readfile($full_path);
    exit;
}
