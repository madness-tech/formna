<?php

/**
 * Clarification & Rectification Controllers
 * 
 * Handles the request/response flow for submission clarifications
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../submissions/models.php';
require_once __DIR__ . '/../forms/models.php';
require_once __DIR__ . '/../emails/sender.php';

/**
 * Check if current user can act on a clarification (resolve/cancel/reject).
 * Allowed if: original requester, super_admin, or has manage permission on the form.
 *
 * @param array<string, mixed> $clarification
 * @param array<string, mixed>|null $submission
 */
function can_manage_clarification(array $clarification, ?array $submission = null): bool {
    $user = current_user();
    if (!$user) return false;

    // Original requester always can
    if ((int)$clarification['requested_by'] === (int)$user['id']) return true;

    // Super admin always can
    if ($user['role'] === 'super_admin') return true;

    // Admin with manage permission on the form can
    if (!$submission) {
        $submission = get_submission_by_id($clarification['submission_id']);
    }
    
    if ($submission && has_form_permission($submission['form_id'], 'manage')) return true;

    return false;
}

/**
 * Show form to create clarification request (Admin or Program Reviewer)
 */
function clarification_create_form(string $submission_uuid): void {
    require_auth();
    require_role('reviewer');
    
    $submission = get_submission_by_uuid($submission_uuid);
    
    if (!$submission) {
        flash('error', 'Submission not found.');
        redirect('/admin/dashboard');
    }
    
    // Check if user has permission to view this submission
    // Either through form ownership/permissions or through program reviewer access
    $form = get_form_by_id($submission['form_id']);
    $has_access = has_form_permission($submission['form_id'], 'view_results')
        || ($form && (int)$form['created_by'] === (int)current_user()['id']);
    
    // Check program reviewer access if no direct form access
    if (!$has_access) {
        $ps_uuid = $_GET['ps'] ?? null;
        if ($ps_uuid) {
            require_once __DIR__ . '/../forms/controllers.php';
            $has_access = _verify_reviewer_access_to_submission($ps_uuid, $submission['form_id'], $submission['id']);
        }
    }
    
    if (!$has_access) {
        flash('error', 'You do not have permission to manage this submission.');
        redirect('/admin/dashboard');
    }
    
    // Get form version and questions
    $version = get_version($submission['form_version_id']);
    $questions = get_questions($submission['form_version_id']);
    $answers = $submission['answers'];
    
    // Get existing clarifications (use numeric ID)
    $existing_clarifications = get_clarifications_for_submission($submission['id']);
    
    $title = 'Request Clarification';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Submissions', 'url' => '/admin/forms/' . $form['uuid'] . '/submissions'],
        ['label' => 'Submission', 'url' => '/admin/forms/' . $form['uuid'] . '/submissions/' . $submission['uuid']],
        ['label' => 'Request Clarification']
    ];
    
    ob_start();
    include __DIR__ . '/views/request.php';
    $content = ob_get_clean();
    
    include __DIR__ . '/../../layouts/admin.php';
}

/**
 * Store clarification request (Admin or Program Reviewer)
 */
function clarification_store(string $submission_uuid): void {
    require_auth();
    require_role('reviewer');
    csrf_check();
    
    $submission = get_submission_by_uuid($submission_uuid);
    
    if (!$submission) {
        flash('error', 'Submission not found.');
        redirect('/admin/dashboard');
    }
    
    // Check if user has permission — form ownership/permissions or program reviewer access
    $form = get_form_by_id($submission['form_id']);
    $has_access = has_form_permission($submission['form_id'], 'view_results')
        || ($form && (int)$form['created_by'] === (int)current_user()['id']);
    
    $ps_uuid = $_POST['ps'] ?? $_GET['ps'] ?? null;
    
    if (!$has_access) {
        if ($ps_uuid) {
            require_once __DIR__ . '/../forms/controllers.php';
            $has_access = _verify_reviewer_access_to_submission($ps_uuid, $submission['form_id'], $submission['id']);
        }
    }
    
    if (!$has_access) {
        flash('error', 'You do not have permission to manage this submission.');
        redirect('/admin/dashboard');
    }
    
    $clarify_url = '/admin/submissions/' . $submission['uuid'] . '/clarify' . ($ps_uuid ? '?ps=' . urlencode($ps_uuid) : '');
    
    $message = trim($_POST['message'] ?? '');
    $selected_questions = $_POST['questions'] ?? [];
    
    if (empty($selected_questions)) {
        flash('error', 'Please select at least one question to request clarification on.');
        redirect($clarify_url);
    }
    
    // Build question type and label lookups
    $version_questions = get_questions($submission['form_version_id']);
    $question_type_map = [];
    $question_label_map = [];
    foreach ($version_questions as $vq) {
        $question_type_map[$vq['id']] = $vq['type'];
        $question_label_map[$vq['id']] = $vq['config']['label'] ?? '';
    }
    
    // Build items array
    $items = [];
    foreach ($selected_questions as $question_id) {
        $type = $_POST['type_' . $question_id] ?? 'clarification';
        $guidance = trim($_POST['guidance_' . $question_id] ?? '');
        
        if (empty($guidance)) {
            flash('error', 'Please provide guidance for all selected questions.');
            redirect($clarify_url);
        }
        
        // Server-side enforcement: file questions cannot be rectified
        $q_type = $question_type_map[(int)$question_id] ?? null;
        if ($q_type === 'file' && $type === 'rectification') {
            $type = 'clarification';
        }
        
        $items[] = [
            'question_id' => (int)$question_id,
            'type' => $type,
            'reason' => $guidance
        ];
    }
    
    // Create clarification request (with protection against race conditions)
    try {
        $clarification_id = create_clarification(
            $submission['id'],
            current_user()['id'],
            $message,
            $items
        );
    } catch (Exception $e) {
        // Handle duplicate clarification race condition
        flash('error', 'A clarification request already exists for this submission. Please wait for the user to respond before creating another.');
        $detail_url = '/admin/forms/' . $form['uuid'] . '/submissions/' . $submission['uuid'] . ($ps_uuid ? '?from=review&ps=' . urlencode($ps_uuid) : '');
        redirect($detail_url);
    }
    
    // Get the UUID for the link
    $clarification = get_clarification($clarification_id);
    
    // Log audit
    log_audit('clarification_requested', 'submission', $submission['id'], [
        'clarification_uuid' => $clarification['uuid'],
        'items_count' => count($items)
    ], $submission['uuid']);
    
    // Build admin message for email including per-question guidance
    $email_parts = [];
    if ($message !== '') {
        $email_parts[] = $message;
    }
    foreach ($items as $item) {
        $label = $question_label_map[$item['question_id']] ?? '';
        $email_parts[] = '- ' . $label . ': ' . $item['reason'];
    }
    $email_message = implode("\n", $email_parts);

    // Queue notification email and in-app notification
    $submitter = db_one("SELECT * FROM users WHERE id = ?", [$submission['user_id']]);
    if ($submitter) {
        queue_email_from_template(
            'clarification_request',
            $submitter,
            [
                'form_name' => $form['name'],
                'submission_id' => $submission['uuid'],
                'clarification_link' => base_url('/requests/' . $clarification['uuid']),
                'admin_message' => $email_message
            ]
        );
        
        // Create in-app notification
        require_once __DIR__ . '/../notifications/models.php';
        create_notification(
            $submitter['id'],
            'clarification_request',
            [
                'form_name' => $form['name'],
                'clarification_link' => '/requests/' . $clarification['uuid']
            ]
        );
    }
    
    flash('success', 'Clarification request sent successfully.');
    $detail_url = '/admin/forms/' . $form['uuid'] . '/submissions/' . $submission['uuid'] . ($ps_uuid ? '?from=review&ps=' . urlencode($ps_uuid) : '');
    redirect($detail_url);
}

/**
 * List clarification requests for user
 */
function clarification_user_list(): void {
    require_auth();
    
    $user = current_user();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $all_clarifications = get_pending_clarifications($user['id']);
    $result = paginate_array($all_clarifications, $page, 15);
    
    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.clarification_requests')],
    ];

    $title = t('clarifications.title');

    ob_start();
    include __DIR__ . '/views/user_list.php';
    $content = ob_get_clean();

    include __DIR__ . '/../../layouts/user.php';
}

/**
 * Show clarification response form (User)
 */
function clarification_respond_form(string $uuid): void {
    require_auth();
    
    $clarification = get_clarification_by_uuid($uuid);
    
    if (!$clarification) {
        flash('error', 'Clarification request not found.');
        redirect('/requests');
    }
    
    // Get submission and verify ownership
    $submission = get_submission_by_id($clarification['submission_id']);
    if ((int)$submission['user_id'] !== (int)current_user()['id']) {
        flash('error', 'You do not have permission to view this request.');
        redirect('/requests');
    }
    
    // Only open clarifications can be responded to
    if ($clarification['status'] !== 'open') {
        flash('info', 'This clarification request has already been responded to.');
        redirect('/requests');
    }
    
    // Get form and questions
    $form = get_form_by_id($submission['form_id']);
    $questions = get_clarification_questions($clarification['id']);
    $answers = $submission['answers'];
    
    // Get requester info
    $requester = db_one("SELECT name FROM users WHERE id = ?", [$clarification['requested_by']]);
    
    $breadcrumbs = [
        ['label' => t('common.dashboard'), 'url' => '/dashboard'],
        ['label' => t('nav.clarification_requests'), 'url' => '/requests'],
        ['label' => t('clarifications.respond_heading')],
    ];

    $title = t('clarifications.respond_heading');

    ob_start();
    include __DIR__ . '/views/respond.php';
    $content = ob_get_clean();

    include __DIR__ . '/../../layouts/user.php';
}

/**
 * Store clarification response (User)
 */
function clarification_respond_submit(string $uuid): void {
    require_auth();
    csrf_check();
    
    $clarification = get_clarification_by_uuid($uuid);
    
    if (!$clarification) {
        flash('error', 'Clarification request not found.');
        redirect('/requests');
    }
    
    // Get submission and verify ownership
    $submission = get_submission_by_id($clarification['submission_id']);
    if ((int)$submission['user_id'] !== (int)current_user()['id']) {
        flash('error', 'You do not have permission to respond to this request.');
        redirect('/requests');
    }
    
    // Only open clarifications can be responded to
    if ($clarification['status'] !== 'open') {
        flash('info', 'This clarification request has already been responded to.');
        redirect('/requests');
    }
    
    // Load questions for type-aware response collection & validation
    $questions = get_clarification_questions($clarification['id']);
    
    $responses = [];
    
    // Collect and validate responses
    foreach ($clarification['items'] as $item) {
        $question_id = $item['question_id'];
        $question = $questions[$question_id] ?? null;
        $q_type = $question ? $question['type'] : 'text';
        $config = $question ? $question['config'] : [];
        
        if ($item['type'] === 'rectification' && $question) {
            // Rectification: collect and validate the value matching the original field type
            $raw_value = $_POST['q_' . $question_id] ?? '';
            $result = validate_rectification_value($raw_value, $q_type, $config);
            
            if (!$result['valid']) {
                flash('error', $result['error']);
                redirect('/requests/' . $uuid);
            }
            
            $responses['q_' . $question_id] = $result['value'];
        } else {
            // Clarification: free-text response (always a string)
            $response = trim($_POST['q_' . $question_id] ?? '');
            if ($response === '') {
                flash('error', 'Please provide a response for all questions.');
                redirect('/requests/' . $uuid);
            }
            $responses['q_' . $question_id] = $response;
        }
    }
    
    // Save responses (pass questions map so model can determine array types)
    respond_to_clarification($clarification['id'], $responses, $questions);
    
    // Log audit
    log_audit('clarification_responded', 'clarification_request', $clarification['id'], [
        'submission_uuid' => $submission['uuid']
    ], $clarification['uuid']);
    
    // Queue notification email to admin
    $form = get_form_by_id($submission['form_id']);
    $admin = db_one("SELECT * FROM users WHERE id = ?", [$clarification['requested_by']]);
    if ($admin) {
        $admin_submission_url = '/admin/forms/' . $form['uuid'] . '/submissions/' . $submission['uuid'];
        
        queue_email_from_template(
            'clarification_response',
            $admin,
            [
                'form_name' => $form['name'],
                'respondent_name' => current_user()['name'],
                'submission_link' => base_url($admin_submission_url)
            ]
        );
        
        // Create in-app notification
        require_once __DIR__ . '/../notifications/models.php';
        create_notification(
            $admin['id'],
            'clarification_response',
            [
                'form_name' => $form['name'],
                'respondent_name' => current_user()['name'],
                'submission_link' => $admin_submission_url
            ]
        );
    }
    
    flash('success', 'Your response has been submitted successfully.');
    redirect('/requests');
}

/**
 * Resolve clarification (Admin marks as resolved after reviewing response)
 */
function clarification_resolve(string $uuid): never {
    require_auth();
    require_role('reviewer');
    csrf_check();
    
    $clarification = get_clarification_by_uuid($uuid);
    
    if (!$clarification) {
        json_response(['success' => false, 'error' => 'Clarification not found.'], 404);
    }
    
    $submission = get_submission_by_id($clarification['submission_id']);
    
    if (!can_manage_clarification($clarification, $submission)) {
        json_response(['success' => false, 'error' => 'Permission denied.'], 403);
    }
    
    resolve_clarification($clarification['id']);
    
    // Log audit
    log_audit('clarification_resolved', 'clarification_request', $clarification['id'], null, $clarification['uuid']);
    
    // Notify the user that their clarification has been resolved
    $form = get_form_by_id($submission['form_id']);
    $submitter = db_one("SELECT * FROM users WHERE id = ?", [$submission['user_id']]);
    
    if ($submitter) {
        queue_email_from_template(
            'clarification_resolved',
            $submitter,
            [
                'form_name' => $form['name']
            ]
        );
        
        // Create in-app notification
        require_once __DIR__ . '/../notifications/models.php';
        create_notification(
            $submitter['id'],
            'clarification_resolved',
            [
                'form_name' => $form['name'],
                'submission_link' => '/submissions/' . $submission['uuid']
            ]
        );
    }
    
    json_response(['success' => true]);
}

/**
 * Cancel (delete) a clarification request (Admin withdraws the request)
 */
function clarification_cancel(string $uuid): never {
    require_auth();
    require_role('reviewer');
    csrf_check();
    
    $clarification = get_clarification_by_uuid($uuid);
    
    if (!$clarification) {
        json_response(['success' => false, 'error' => 'Clarification not found.'], 404);
    }
    
    // Only open clarifications can be cancelled
    if ($clarification['status'] !== 'open') {
        json_response(['success' => false, 'error' => 'Only open clarification requests can be cancelled.'], 400);
    }
    
    $submission = get_submission_by_id($clarification['submission_id']);
    
    if (!can_manage_clarification($clarification, $submission)) {
        json_response(['success' => false, 'error' => 'Permission denied.'], 403);
    }
    
    // Log audit
    log_audit('clarification_cancelled', 'submission', $clarification['submission_id'], [
        'clarification_uuid' => $clarification['uuid'],
        'items_count' => count($clarification['items'])
    ], $submission['uuid'] ?? null);
    
    cancel_clarification($clarification['id']);
    
    json_response(['success' => true]);
}

/**
 * Reject clarification response (Admin sends it back for revision)
 */
function clarification_reject(string $uuid): never {
    require_auth();
    require_role('reviewer');
    csrf_check();
    
    $clarification = get_clarification_by_uuid($uuid);
    
    if (!$clarification) {
        json_response(['success' => false, 'error' => 'Clarification not found.'], 404);
    }
    
    // Only responded clarifications can be rejected
    if ($clarification['status'] !== 'responded') {
        json_response(['success' => false, 'error' => 'Only responded clarifications can be rejected.'], 400);
    }
    
    $submission = get_submission_by_id($clarification['submission_id']);
    
    if (!can_manage_clarification($clarification, $submission)) {
        json_response(['success' => false, 'error' => 'Permission denied.'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $feedback = trim($input['feedback'] ?? '');
    
    if ($feedback === '') {
        json_response(['success' => false, 'error' => 'Please provide feedback explaining why the response is inadequate.'], 422);
    }
    
    reject_clarification($clarification['id'], $feedback);
    
    // Log audit
    log_audit('clarification_rejected', 'clarification_request', $clarification['id'], [
        'feedback' => $feedback
    ], $clarification['uuid']);
    
    // Notify the user that their response was rejected
    $form = get_form_by_id($submission['form_id']);
    $submitter = db_one("SELECT * FROM users WHERE id = ?", [$submission['user_id']]);
    
    if ($submitter) {
        queue_email_from_template(
            'clarification_rejected',
            $submitter,
            [
                'form_name' => $form['name'],
                'admin_feedback' => $feedback,
                'clarification_link' => base_url('/requests/' . $clarification['uuid'])
            ]
        );
        
        // Create in-app notification
        require_once __DIR__ . '/../notifications/models.php';
        create_notification(
            $submitter['id'],
            'clarification_rejected',
            [
                'form_name' => $form['name'],
                'admin_feedback' => $feedback,
                'clarification_link' => '/requests/' . $clarification['uuid']
            ]
        );
    }
    
    json_response(['success' => true]);
}

/**
 * Admin: View clarification request details
 */
function admin_view_clarification(string $uuid): void {
    require_auth();
    require_role('reviewer');
    
    $clarification = get_clarification_by_uuid($uuid);
    if (!$clarification) {
        flash('error', 'Clarification request not found.');
        redirect('/admin/dashboard');
    }
    
    $submission = get_submission_by_id($clarification['submission_id']);
    if (!$submission) {
        flash('error', 'Submission not found.');
        redirect('/admin/dashboard');
    }
    
    $form = get_form_by_id($submission['form_id']);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/dashboard');
    }
    
    $has_access = has_form_permission($submission['form_id'], 'view_results')
        || ((int)$form['created_by'] === (int)current_user()['id']);
    
    // Check program reviewer access if no direct form access
    if (!$has_access) {
        $ps_uuid = $_GET['ps'] ?? null;
        if ($ps_uuid) {
            require_once __DIR__ . '/../forms/controllers.php';
            $has_access = _verify_reviewer_access_to_submission($ps_uuid, $submission['form_id'], $submission['id']);
        }
    }
    
    if (!$has_access) {
        flash('error', 'You do not have permission to view this clarification.');
        redirect('/admin/dashboard');
    }
    
    // Get questions for this clarification
    $questions = get_clarification_questions($clarification['id']);
    
    // Get submission answers
    $answers = $submission['answers'] ?? [];
    
    // Get submitter info
    $submitter = db_one("SELECT * FROM users WHERE id = ?", [$submission['user_id']]);
    
    // Get requester info (person who created the clarification request)
    $requester = db_one("SELECT name FROM users WHERE id = ?", [$clarification['requested_by']]);
    
    $title = 'Clarification Request Details';
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Submissions', 'url' => '/admin/forms/' . $form['uuid'] . '/submissions'],
        ['label' => 'Clarification Details']
    ];
    
    ob_start();
    include __DIR__ . '/views/admin_view.php';
    $content = ob_get_clean();
    
    include __DIR__ . '/../../layouts/admin.php';
}
