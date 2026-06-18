<?php

/**
 * Forms Module - Controllers
 * 
 * Handles all admin form management actions.
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../ai/models.php';

// ============================================================================
// FORM LISTING & DASHBOARD
// ============================================================================

/**
 * Admin forms dashboard
 */
function forms_index(): void {
    require_auth();
    require_role('admin');
    
    $user = current_user();
    $page = $_GET['page'] ?? 1;
    $status = $_GET['status'] ?? null;
    
    // Super admins see all forms, admins see their own
    $created_by = $user['role'] === 'super_admin' ? null : $user['id'];
    
    $result = list_forms($created_by, $status, $page, 20);
    
    // Add permission info to each form for conditional UI
    foreach ($result['rows'] as &$form) {
        $form['can_manage'] = has_form_permission($form['id'], 'manage');
        $form['can_view_results'] = has_form_permission($form['id'], 'view_results');
    }
    unset($form); // Break reference
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms']
    ];
    
    ob_start();
    require __DIR__ . '/views/index.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Show create form page
 */
function forms_create(): void {
    require_auth();
    require_role('admin');
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Create New Form']
    ];
    
    ob_start();
    require __DIR__ . '/views/create.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Store new form
 */
function forms_store(): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    // Validation
    if (empty($name)) {
        flash('error', 'Form name is required.');
        redirect('/admin/forms/create');
    }
    
    $user = current_user();
    $result = create_form($name, $description, $instructions, $user['id']);
    
    // Get the form to retrieve its UUID
    $form = get_form_by_id($result['form_id']);
    
    flash('success', 'Form created successfully. Start adding questions below.');
    redirect('/admin/forms/' . $form['uuid'] . '/builder');
}

// ============================================================================
// FORM BUILDER
// ============================================================================

/**
 * Show form builder interface
 *
 * SECURITY: This is a GET endpoint and must be idempotent.
 * No database writes are performed here to prevent unintended
 * version creation from crawlers, pre-fetching, or repeated access.
 */
function forms_builder(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    // Get current draft version (read-only, no auto-creation)
    $version = db_one('SELECT * FROM form_versions WHERE form_id = ? AND status = ? ORDER BY version_number DESC LIMIT 1',
        [$form['id'], 'draft']);
    
    // Get active version for reference
    $active_version = get_active_version($form['id']);
    
    // If no draft exists, we'll show a prompt in the view for explicit creation
    $questions = $version ? get_questions($version['id']) : [];
    
    // Count active user drafts (shown as warning on publish button)
    $user_draft_count = 0;
    if ($version && $version['status'] === 'draft' && $active_version) {
        $draft_count_row = db_one(
            'SELECT COUNT(*) as count FROM drafts WHERE form_id = ? AND form_version_id = ?',
            [$form['id'], $active_version['id']]
        );
        $user_draft_count = (int)($draft_count_row['count'] ?? 0);
    }
    
    // Pre-process questions for visibility configuration (performance optimization)
    // Instead of O(N²) loops in the view, we do O(N) processing here once
    $questions_by_uid = [];
    $visibility_eligible_questions = [];
    
    foreach ($questions as $q) {
        $questions_by_uid[$q['uid']] = $q;
        
        // Only certain question types can be used as visibility triggers
        if (in_array($q['type'], ['select', 'radio', 'checkbox_group', 'multiselect'])) {
            $visibility_eligible_questions[] = [
                'uid' => $q['uid'],
                'label' => $q['config']['label'] ?? 'Untitled Question',
                'options' => $q['config']['options'] ?? []
            ];
        }
    }
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details']
    ];

    ob_start();
    require __DIR__ . '/views/builder.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

// ============================================================================
// FORM SETTINGS
// ============================================================================

/**
 * Show form settings page
 */
function forms_settings(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    // Get all forms for prerequisites dropdown
    $all_forms = db_query('SELECT id, name FROM forms WHERE deleted_at IS NULL AND status = ? AND id != ? ORDER BY name', ['published', $form['id']]);

    $active_program_links = [];
    $can_unpublish_form = true;
    if (($form['status'] ?? '') === 'published') {
        $active_program_links = get_programs_using_form($form['id']);
        $can_unpublish_form = empty($active_program_links);
    }
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Settings']
    ];
    
    ob_start();
    require __DIR__ . '/views/settings.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Save form settings
 */
function forms_save_settings(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to modify this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to modify this form.');
        redirect('/admin/forms');
    }
    
    // Validate and sanitize prerequisite form IDs
    $prerequisite_form_ids = $_POST['prerequisite_form_ids'] ?? [];
    
    // SECURITY: Prevent direct circular dependency - form cannot require itself
    if (in_array($form['id'], $prerequisite_form_ids)) {
        flash('error', 'Invalid prerequisite: A form cannot require itself.');
        redirect('/admin/forms/' . $form['uuid'] . '/settings');
    }
    
    // SECURITY: Prevent indirect circular dependencies
    if (!empty($prerequisite_form_ids)) {
        $circular_chain = detect_circular_prerequisite($form['id'], $prerequisite_form_ids);
        if ($circular_chain !== false) {
            flash('error', 'Invalid prerequisite: Circular dependency detected. ' . $circular_chain);
            redirect('/admin/forms/' . $form['uuid'] . '/settings');
        }
    }
    
    // Ensure all prerequisite IDs are valid integers and forms exist
    $prerequisite_form_ids = array_filter(array_map('intval', $prerequisite_form_ids), function($id) {
        return $id > 0 && get_form_by_id($id) !== null;
    });
    
    // Validate submission deadline is sufficiently in the future (only when setting a new value)
    $submitted_deadline_raw = !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : null;
    $submitted_deadline_db = $submitted_deadline_raw ? to_db_datetime($submitted_deadline_raw) : null;
    $existing_deadline_db = $form['settings']['submission_deadline'] ?? null;
    
    if ($submitted_deadline_db !== null && $submitted_deadline_db !== $existing_deadline_db) {
        // Deadline must be at least 1 hour from now to prevent near-instant expiry
        $deadline_utc = new DateTimeImmutable($submitted_deadline_db, new DateTimeZone('UTC'));
        $minimum_utc = new DateTimeImmutable('+1 hour', new DateTimeZone('UTC'));
        if ($deadline_utc < $minimum_utc) {
            flash('error', 'Submission deadline must be at least 1 hour from now.');
            redirect('/admin/forms/' . $form['uuid'] . '/settings');
        }
    }
    
    // Build only the settings keys this page controls
    $settings = [
        'is_editable_after_submit' => isset($_POST['is_editable_after_submit']),
        'editable_days' => !empty($_POST['editable_days']) ? intval($_POST['editable_days']) : null,
        'editable_until_date' => !empty($_POST['editable_until_date'])
            ? to_db_datetime($_POST['editable_until_date'] . 'T23:59')
            : null,
        'submission_deadline' => $submitted_deadline_db,
        'expiry_days' => !empty($_POST['expiry_days']) ? intval($_POST['expiry_days']) : null,
        'submission_limit' => isset($_POST['submission_limit']) && $_POST['submission_limit'] !== ''
            ? max(0, intval($_POST['submission_limit']))
            : 1,
        'prerequisite_form_ids' => array_values($prerequisite_form_ids),
    ];
    
    // Update form basic info
    update_form($form['id'], [
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'instructions' => trim($_POST['instructions'] ?? '')
    ]);
    
    // Merge into existing settings (preserves keys managed by other pages like scoring_enabled)
    patch_form_settings($form['id'], $settings);
    
    flash('success', 'Form settings saved successfully.');
    redirect('/admin/forms/' . $form['uuid'] . '/settings');
}

// ============================================================================
// FORM PREVIEW & PUBLISHING
// ============================================================================

/**
 * Preview form
 */
function forms_preview(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    // Get draft or active version
    $version = db_one('SELECT * FROM form_versions WHERE form_id = ? AND status IN (?, ?) ORDER BY version_number DESC LIMIT 1',
        [$form['id'], 'draft', 'active']);
    
    if (!$version) {
        flash('error', 'No version found for this form.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    $questions = get_questions($version['id']);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Preview']
    ];
    
    ob_start();
    require __DIR__ . '/views/preview.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Publish form version
 */
function forms_publish(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to publish this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to publish this form.');
        redirect('/admin/forms');
    }
    
    // Get draft version
    $version = db_one('SELECT * FROM form_versions WHERE form_id = ? AND status = ? ORDER BY version_number DESC LIMIT 1',
        [$form['id'], 'draft']);
    
    if (!$version) {
        flash('error', 'No draft version found to publish.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    // Check if form has questions
    $question_count = db_one('SELECT COUNT(*) as count FROM questions WHERE form_version_id = ?', [$version['id']]);
    if ($question_count['count'] == 0) {
        flash('error', 'Cannot publish form with no questions.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    // Prevent publishing if draft is identical to the current active version
    $active_version = get_active_version($form['id']);
    if ($active_version && versions_are_identical($version['id'], $active_version['id'])) {
        flash('error', 'No changes detected. Please make changes before publishing a new version.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    publish_version($form['id'], $version['id']);
    
    flash('success', 'Version published successfully! The form is now live.');
    redirect('/admin/forms/' . $form['uuid'] . '/builder');
}

/**
 * Create new version (duplicate current active)
 */
function forms_new_version(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to modify this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to modify this form.');
        redirect('/admin/forms');
    }
    
    $active = get_active_version($form['id']);
    if (!$active) {
        flash('error', 'No active version to copy from.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    $new_version_id = create_version($form['id'], $active['id']);
    
    flash('success', 'New draft version created. Make your changes and publish when ready.');
    redirect('/admin/forms/' . $form['uuid'] . '/builder');
}

/**
 * Create a draft version (explicit POST action)
 *
 * This endpoint handles the creation of draft versions when none exists.
 * It can create either:
 * 1. A new draft from an active version (copying questions)
 * 2. An initial version if no versions exist yet
 *
 * SECURITY: This is a POST-only endpoint to prevent unintended version
 * creation from GET requests (crawlers, pre-fetching, etc.)
 */
function forms_create_draft(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to modify this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to modify this form.');
        redirect('/admin/forms');
    }
    
    // Check if draft already exists
    $existing_draft = db_one('SELECT * FROM form_versions WHERE form_id = ? AND status = ? ORDER BY version_number DESC LIMIT 1',
        [$form['id'], 'draft']);
    
    if ($existing_draft) {
        flash('info', 'A draft version already exists.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    // Check if there's an active version to copy from
    $active_version = get_active_version($form['id']);
    
    if ($active_version) {
        // Create new draft from active version
        $version_id = create_version($form['id'], $active_version['id']);
        flash('info', 'A new draft version has been created for editing. The published form remains unchanged until you publish this draft.');
    } else {
        // No versions exist at all - create initial version
        $version_id = create_version($form['id']);
        flash('success', 'Initial version created. Start building your form below.');
    }
    
    redirect('/admin/forms/' . $form['uuid'] . '/builder');
}

/**
 * Discard a draft version
 * Only allows discarding draft versions with version_number > 1
 */
function forms_discard_draft(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to modify this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to modify this form.');
        redirect('/admin/forms');
    }
    
    // Get draft version
    $draft = get_draft_version($form['id']);
    
    if (!$draft) {
        flash('error', 'No draft version found to discard.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    // Verify version number > 1
    if ($draft['version_number'] <= 1) {
        flash('error', 'Cannot discard the initial version.');
        redirect('/admin/forms/' . $form['uuid'] . '/builder');
    }
    
    try {
        discard_draft_version($form['id'], $draft['id']);
        flash('success', 'Draft version discarded successfully.');
    } catch (Exception $e) {
        flash('error', 'Failed to discard draft: ' . $e->getMessage());
    }
    
    redirect('/admin/forms/' . $form['uuid'] . '/builder');
}

// ============================================================================
// FORM SCORING
// ============================================================================

/**
 * Show scoring configuration page
 *
 * Scoring is form-level configuration that applies to whichever version exists.
 * Unlike builder which requires drafts, scoring can be configured on active versions too.
 */
function forms_scoring(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    // Get draft version if exists, otherwise use active version
    // Scoring configuration works on whichever version exists
    $version = get_draft_version($form['id']) ?: get_active_version($form['id']);
    
    // Keep active version for reference in UI
    $active_version = get_active_version($form['id']);
    
    $questions = $version ? get_questions($version['id']) : [];
    
    // Check if scoring toggle is locked (form has submissions)
    $scoring_locked = form_has_submissions($form['id']);
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Scoring']
    ];
    
    ob_start();
    require __DIR__ . '/views/scoring.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * Save scoring configuration
 *
 * Merges scoring data into each question's existing config JSON.
 * Also toggles the form-level scoring_enabled setting.
 * Works on whichever version exists (draft or active).
 */
function forms_save_scoring(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to modify this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to modify this form.');
        redirect('/admin/forms');
    }
    
    // Get draft version if exists, otherwise use active version
    // Scoring works on whichever version exists
    $version = get_draft_version($form['id']) ?: get_active_version($form['id']);
    
    if (!$version) {
        flash('error', 'No form version found. Please create a version first.');
        redirect('/admin/forms/' . $form['uuid'] . '/scoring');
    }
    
    // Check if scoring toggle is locked (form has submissions)
    $scoring_locked = form_has_submissions($form['id']);
    
    // Update scoring_enabled in form settings (only if not locked)
    if (!$scoring_locked) {
        patch_form_settings($form['id'], ['scoring_enabled' => isset($_POST['scoring_enabled'])]);
    }
    
    $questions = get_questions($version['id']);
    $scoring_data = $_POST['scoring'] ?? [];
    
    // Validate submitted keys against known question UIDs
    $valid_question_uids = array_column($questions, 'uid');
    $scoring_data = array_intersect_key($scoring_data, array_flip($valid_question_uids));
    
    // Update each question's config with scoring data
    foreach ($questions as $question) {
        $q_uid = $question['uid'];
        
        if (!isset($scoring_data[$q_uid])) {
            // If no scoring data submitted for this question, remove any existing scoring
            if (isset($question['config']['scoring'])) {
                $config = $question['config'];
                unset($config['scoring']);
                update_question_by_uid($q_uid, $config);
            }
            continue;
        }
        
        // Cast all scoring values to integers
        $score_values = [];
        foreach ($scoring_data[$q_uid] as $key => $value) {
            $score_values[$key] = (int) $value;
        }
        
        // Check if all scores are zero — treat as "no scoring configured"
        $has_nonzero = false;
        foreach ($score_values as $v) {
            if ($v !== 0) {
                $has_nonzero = true;
                break;
            }
        }
        
        $config = $question['config'];
        if ($has_nonzero) {
            $config['scoring'] = $score_values;
        } else {
            // All zeros — remove scoring config to keep things clean
            unset($config['scoring']);
        }
        
        update_question_by_uid($q_uid, $config);
    }

    log_audit('updated', 'form', $form['id'], ['action' => 'scoring_updated'], $form['uuid']);

    flash('success', 'Scoring configuration saved.');
    redirect('/admin/forms/' . $form['uuid'] . '/scoring');
}

// ============================================================================
// FORM DELETION
// ============================================================================

/**
 * Delete form (soft delete)
 *
 * Soft deletion allows removing forms while preserving all historical data
 * including submissions, versions, and questions. Forms can be deleted
 * regardless of whether they have submissions.
 */
function forms_delete(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to delete this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to delete this form.');
        redirect('/admin/forms');
    }
    
    // Check if form is associated with any active programs
    $programs = get_programs_using_form($form['id']);
    
    if (!empty($programs)) {
        // Remove form from all programs
        $program_names = [];
        foreach ($programs as $program) {
            remove_form_from_program($program['id'], $form['id']);
            $program_names[] = $program['name'];
        }
        
        // Delete the form (soft delete preserves all submissions and data)
        soft_delete_form($form['id']);
        
        // Notify user which programs were affected
        $programs_list = implode(', ', $program_names);
        flash('success', 'Form deleted successfully. It has been removed from the following program(s): ' . $programs_list . '. All submissions have been preserved.');
    } else {
        // No programs affected, just delete
        soft_delete_form($form['id']);
        
        // Check if form has submissions to inform the user
        $submission_count = db_one('SELECT COUNT(*) as count FROM submissions WHERE form_id = ?', [$form['id']]);
        $has_submissions = ($submission_count['count'] ?? 0) > 0;
        
        if ($has_submissions) {
            flash('success', 'Form deleted successfully. All submissions have been preserved and remain accessible.');
        } else {
            flash('success', 'Form deleted successfully.');
        }
    }
    
    redirect('/admin/forms');
}

/**
 * Unpublish a published form
 * Changes form status from published to draft, hiding it from users
 * Matches the Programs module pattern for consistency
 */
function forms_unpublish(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to manage this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to manage this form.');
        redirect('/admin/forms');
    }
    
    // Verify form is published
    if ($form['status'] !== 'published') {
        flash('error', 'Only published forms can be unpublished.');
        redirect('/admin/forms/' . $form_uuid . '/settings');
    }

    // Prevent unpublishing forms linked to active programs
    $active_programs = get_programs_using_form($form['id']);
    if (!empty($active_programs)) {
        flash('error', 'This form cannot be unpublished while it is linked to an active program.');
        redirect('/admin/forms/' . $form_uuid . '/settings');
    }
    
    if (unpublish_form($form['id'])) {
        flash('success', 'Form unpublished successfully. It has been returned to draft status and is no longer visible to users.');
    } else {
        flash('error', 'Failed to unpublish form.');
    }
    
    redirect('/admin/forms/' . $form_uuid . '/settings');
}

/**
 * Republish a previously unpublished form
 * Changes form status from draft back to published (requires existing active version)
 * Matches the Programs module pattern for consistency
 */
function forms_republish(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to manage this form
    if (!has_form_permission($form['id'], 'manage')) {
        flash('error', 'You do not have permission to manage this form.');
        redirect('/admin/forms');
    }
    
    // Verify form is draft
    if ($form['status'] !== 'draft') {
        flash('error', 'Only draft forms can be published.');
        redirect('/admin/forms/' . $form_uuid . '/settings');
    }
    
    // Verify form has an active version (was previously published)
    if (!$form['active_version_id']) {
        flash('error', 'This form has no published version. Use the builder to publish a version first.');
        redirect('/admin/forms/' . $form_uuid . '/builder');
    }
    
    if (republish_form($form['id'])) {
        flash('success', 'Form published successfully. It is now visible to users again.');
    } else {
        flash('error', 'Failed to publish form.');
    }
    
    redirect('/admin/forms/' . $form_uuid . '/settings');
}

// ============================================================================
// SUBMISSIONS MANAGEMENT
// ============================================================================

/**
 * List submissions for a form
 */
function forms_submissions_list(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    // Use including_deleted so submissions remain viewable even for soft-deleted forms
    $form = get_form_by_uuid_including_deleted($form_uuid);
    if (!$form) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    if (!has_form_permission($form['id'], 'view_results')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    $page = $_GET['page'] ?? 1;
    // Validate status filter against allowlist; empty string or invalid values fall back to null (all statuses)
    $allowed_statuses = ['submitted', 'clarification_requested'];
    $status = (!empty($_GET['status']) && in_array($_GET['status'], $allowed_statuses, true))
        ? $_GET['status']
        : null;
    
    require_once __DIR__ . '/../submissions/models.php';
    $submissions = list_submissions($form['id'], $status, $page, 20);
    
    // Map stats to the keys expected by the view
    $raw_stats = get_form_stats($form['id']);
    $stats = [
        'total' => $raw_stats['total_submissions'] ?? 0,
        'submitted' => $raw_stats['submitted'] ?? 0,
        'clarification_requested' => $raw_stats['clarification_requested'] ?? 0,
        'drafts' => $raw_stats['drafts'] ?? 0,
    ];
    
    // Expose the selected filter for the view
    $status_filter = $status;
    
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Forms', 'url' => '/admin/forms'],
        ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
        ['label' => 'Submissions']
    ];
    
    ob_start();
    require __DIR__ . '/../submissions/views/list.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * View single submission detail
 *
 * SECURITY: Allows viewing submissions even from deleted forms
 * (admin must still own the form)
 */
function forms_submission_detail(string $form_uuid, string $submission_uuid): void {
    require_auth();
    // Reviewers accessing from program review queue need at least 'reviewer' role;
    // all other access requires 'admin'. Role is verified further below with
    // _verify_reviewer_access_to_submission() for the reviewer path.
    $from_review_early = $_GET['from'] ?? null;
    $min_role = ($from_review_early === 'review') ? 'reviewer' : 'admin';
    require_role($min_role);
    
    require_once __DIR__ . '/../submissions/models.php';
    $submission = get_submission_by_uuid($submission_uuid);
    
    if (!$submission) {
        flash('error', 'Submission not found.');
        redirect('/admin/forms');
    }
    
    // Get form - including soft-deleted forms (submissions must remain viewable)
    if ($form_uuid) {
        $form = get_form_by_uuid_including_deleted($form_uuid);
    } else {
        $form = get_form_by_id_including_deleted($submission['form_id']);
    }
    
    if (!$form || $submission['form_id'] != $form['id']) {
        flash('error', 'Form not found.');
        redirect('/admin/forms');
    }
    
    // SECURITY: Verify admin has permission to access this form
    // Even deleted forms respect ownership rules.
    // Exception: program reviewers can view form submissions attached to their review queue.
    $from_review = $_GET['from'] ?? null;
    $review_ps_uuid = $_GET['ps'] ?? null;
    $has_review_access = false;

    if ($from_review === 'review' && $review_ps_uuid) {
        $has_review_access = _verify_reviewer_access_to_submission($review_ps_uuid, $form['id'], $submission['id']);
    }

    if (!$has_review_access && !has_form_permission($form['id'], 'view_results')) {
        flash('error', 'You do not have permission to access this form.');
        redirect('/admin/forms');
    }
    
    // Get version and questions
    // Questions are hard-deleted from drafts (no submissions reference them) and
    // cannot be deleted from active versions (API enforces draft-only deletion).
    // Therefore get_questions() is always correct for viewing submissions.
    $version = get_version($submission['form_version_id']);
    $questions = get_questions($version['id']);

    // Fetch uploaded files for rendering file answers
    $files = get_files($submission['id']);
    
    // Get submitter info
    $submitter = db_one('SELECT name, email FROM users WHERE id = ?', [$submission['user_id']]);
    
    // Load program context and audit trail for this submission
    require_once __DIR__ . '/../programs/models.php';
    $program_contexts = get_programs_for_submission($submission['id']);
    $audit_trail = get_submission_audit_trail($submission['id']);

    // Load clarifications and scoring data for the view
    require_once __DIR__ . '/../clarifications/models.php';
    $clarifications = get_clarifications_for_submission($submission['id']);

    $clarification_items_by_question = [];
    foreach ($clarifications as $clarification) {
        foreach ($clarification['items'] as $item) {
            $question_id = $item['question_id'];
            if (!isset($clarification_items_by_question[$question_id])) {
                $clarification_items_by_question[$question_id] = [];
            }
            $clarification_items_by_question[$question_id][] = [
                'clarification_uuid' => $clarification['uuid'],
                'clarification_status' => $clarification['status'],
                'requester_name' => $clarification['requester_name'],
                'created_at' => $clarification['created_at'],
                'type' => $item['type'],
                'guidance' => $item['reason'] ?? '',
                'response' => $item['response'] ?? null,
                'original_value' => $item['original_value'] ?? null,
                'status' => $item['status'] ?? 'open',
                'responded_at' => $item['responded_at'] ?? null
            ];
        }
    }

    $pending_response_count = 0;
    $has_active_clarification = false;
    $responded_clarification = null;
    foreach ($clarifications as $clarification) {
        if ($clarification['status'] === 'responded') {
            $pending_response_count++;
            $responded_clarification = $clarification;
            $has_active_clarification = true;
        } elseif ($clarification['status'] === 'open') {
            $has_active_clarification = true;
        }
    }

    require_once __DIR__ . '/../forms/scoring.php';
    $scoring_enabled = is_scoring_enabled($form);
    $form_score = $scoring_enabled
        ? calculate_form_score($questions, $submission['answers'] ?? [])
        : null;

    if ($from_review === 'review' && $review_ps_uuid) {
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
            ['label' => 'Review Queue', 'url' => '/admin/review-queue'],
            ['label' => 'Review Submission', 'url' => '/admin/review-queue/' . urlencode($review_ps_uuid)],
            ['label' => 'Submission Details']
        ];
    } else {
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
            ['label' => 'Forms', 'url' => '/admin/forms'],
            ['label' => 'Form Details', 'url' => '/admin/forms/' . $form['uuid'] . '/builder'],
            ['label' => 'Submissions', 'url' => '/admin/forms/' . $form['uuid'] . '/submissions'],
            ['label' => 'Submission Details']
        ];
    }
    
    ob_start();
    require __DIR__ . '/../submissions/views/detail.php';
    $content = ob_get_clean();
    
    require __DIR__ . '/../../layouts/admin.php';
}

// ============================================================================
// INTERNAL HELPERS
// ============================================================================

/**
 * Verify that the current user has reviewer access to a specific form submission
 * through a program review assignment.
 *
 * SECURITY: This performs three checks to prevent URL parameter manipulation:
 * 1. The program submission exists and is in a reviewable state
 * 2. The current user is the assigned reviewer for the current stage
 * 3. The form submission being viewed is actually attached to that program submission
 *
 * @param string $ps_uuid   Program submission UUID from the query parameter
 * @param int    $form_id   The form ID being accessed
 * @param int    $sub_id    The submission ID being accessed
 */
function _verify_reviewer_access_to_submission(string $ps_uuid, int $form_id, int $sub_id): bool {
    require_once __DIR__ . '/../programs/models.php';

    $user = current_user();
    if (!$user) {
        return false;
    }

    // Load the program submission
    $ps = get_program_submission_by_uuid($ps_uuid);
    if (!$ps) {
        return false;
    }

    // Must be in a reviewable state
    if (!in_array($ps['status'], ['submitted', 'in_review', 'approved', 'rejected'])) {
        return false;
    }

    // Load the program to get review stages
    $program = get_program($ps['program_id']);
    if (!$program) {
        return false;
    }

    // Super admins can always review program submissions
    if ($user['role'] === 'super_admin') {
        $is_assigned_reviewer = true;
    } else {
        // Verify the current user is the reviewer for the current stage only
        $current_stage = (int)$ps['current_stage'];
        $current_stage_config = null;
        foreach ($program['review_stages'] as $stage) {
            if ((int)$stage['order'] === $current_stage) {
                $current_stage_config = $stage;
                break;
            }
        }

        if (!$current_stage_config) {
            return false;
        }

        $is_assigned_reviewer = (int)($current_stage_config['reviewer_id'] ?? 0) === (int)$user['id'];
    }

    if (!$is_assigned_reviewer) {
        return false;
    }

    // Verify the form submission is actually attached to this program submission
    foreach ($ps['submission_ids'] as $attached_form_id => $attached_sub_id) {
        if ((int)$attached_form_id === $form_id && (int)$attached_sub_id === $sub_id) {
            return true;
        }
    }

    return false;
}
