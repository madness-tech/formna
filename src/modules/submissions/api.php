<?php

/**
 * Submissions API - AJAX endpoints for draft management and file uploads
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../forms/models.php';

/**
 * Save draft (auto-save endpoint)
 * POST /api/drafts/{form_id}
 */
function api_save_draft(string $form_id): void {
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => t('api.unauthorized')]);
        exit;
    }
    
    // CSRF check for API - check header
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo json_encode(['error' => t('api.csrf_mismatch')]);
        return;
    }
    
    $user = current_user();
    $input = json_input();

    $rate_error = check_user_action_rate_limit(
        $user['id'],
        'draft_save',
        DRAFT_SAVE_RATE_LIMIT_PER_MINUTE,
        1,
        true
    );

    if ($rate_error) {
        http_response_code(429);
        echo json_encode(['error' => $rate_error]);
        exit;
    }
    
    if (!isset($input['data'])) {
        http_response_code(400);
        echo json_encode(['error' => t('api.missing_data')]);
        exit;
    }
    
    // Get form and verify it exists (form_id is actually UUID from URL)
    $form = get_form_by_uuid($form_id);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => t('api.form_not_found')]);
        exit;
    }
    
    // Verify the form is published and accessible
    if ($form['status'] !== 'published') {
        http_response_code(403);
        echo json_encode(['error' => t('api.form_not_accessible')]);
        exit;
    }

    $settings = $form['settings'] ?? [];
    $deadline = $settings['submission_deadline'] ?? null;
    if ($deadline && is_past($deadline)) {
        http_response_code(403);
        echo json_encode(['error' => t('api.form_submissions_closed')]);
        exit;
    }

    // Get active version (need to use numeric ID, not UUID)
    $version = get_active_version($form['id']);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => t('api.no_active_version')]);
        exit;
    }
    
    // Clean up removed/replaced draft file references before persisting new data
    $existing_draft = get_draft($form['id'], $user['id']);
    if ($existing_draft) {
        decode_json_fields($existing_draft, ['data']);
        $questions = get_questions($version['id']);
        cleanup_removed_file_answers(
            $questions,
            is_array($existing_draft['data'] ?? null) ? $existing_draft['data'] : [],
            is_array($input['data']) ? $input['data'] : [],
            (int)$user['id'],
            null
        );
    }

    // Save or update draft (use numeric form ID)
    save_draft($form['id'], $version['id'], $user['id'], $input['data']);
    
    echo json_encode([
        'success' => true,
        'saved_at' => now()
    ]);
}

/**
 * Load draft
 * GET /api/drafts/{form_id}
 */
function api_load_draft(string $form_id): void {
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => t('api.unauthorized')]);
        exit;
    }
    
    // Get form to convert UUID to ID
    $form = get_form_by_uuid($form_id);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => t('api.form_not_found')]);
        exit;
    }
    
    $user = current_user();
    $draft = get_draft($form['id'], $user['id']);
    
    if ($draft) {
        decode_json_fields($draft, ['data']);
        
        // Resolve file metadata for any file_ids stored in the draft data
        // so the client can display "File attached: filename" indicators.
        $file_meta = [];
        if (!empty($draft['data']) && is_array($draft['data'])) {
            $version = get_active_version($form['id']);
            if ($version) {
                $questions = get_questions($version['id']);
                foreach ($questions as $q) {
                    if ($q['type'] === 'file') {
                        $file_id = $draft['data'][(string)$q['id']] ?? null;
                        if ($file_id && is_numeric($file_id)) {
                            $file = get_file((int)$file_id);
                            if ($file) {
                                $file_meta[(string)$file_id] = [
                                    'original_name' => $file['original_name'],
                                    'size_bytes' => (int)$file['size_bytes'],
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'data' => $draft['data'],
            'files' => $file_meta,
            'updated_at' => $draft['updated_at']
        ]);
    } else {
        echo json_encode(['data' => null]);
    }
}

/**
 * Delete draft
 * DELETE /api/drafts/{form_uuid}
 */
function api_delete_draft(string $form_uuid): void {
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => t('api.unauthorized')]);
        exit;
    }
    
    // CSRF check for API - check header
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo json_encode(['error' => t('api.csrf_mismatch')]);
        exit;
    }
    
    // Get form to convert UUID to ID
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => t('api.form_not_found')]);
        exit;
    }
    
    $user = current_user();
    
    // Check if draft exists
    $draft = get_draft($form['id'], $user['id']);
    if (!$draft) {
        http_response_code(404);
        echo json_encode(['error' => t('api.draft_not_found')]);
        exit;
    }
    
    // Clean up uploaded files referenced by this draft
    decode_json_fields($draft, ['data']);
    if (!empty($draft['data']) && is_array($draft['data'])) {
        $file_ids = [];
        foreach ($draft['data'] as $val) {
            if (is_numeric($val)) {
                $file_ids[(int)$val] = true;
            }
        }
        foreach (array_keys($file_ids) as $fid) {
            delete_unlinked_user_file((int)$fid, (int)$user['id']);
        }
    }
    
    // Delete draft
    delete_draft($form['id'], $user['id']);
    
    echo json_encode([
        'success' => true,
        'message' => t('api.draft_deleted')
    ]);
}

/**
 * Delete a pre-submission uploaded file
 * DELETE /api/files/{id}
 */
function api_delete_file(int $file_id): void {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => t('api.unauthorized')]);
        exit;
    }
    
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo json_encode(['error' => t('api.csrf_mismatch')]);
        return;
    }
    
    $user = current_user();
    $file = get_file($file_id);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => t('api.file_not_found')]);
        exit;
    }
    
    // Owner-only delete access
    if ((int)$file['user_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['error' => t('api.access_denied')]);
        exit;
    }

    if (empty($file['submission_id'])) {
        if (!delete_unlinked_user_file($file_id, (int)$user['id'])) {
            http_response_code(403);
            echo json_encode(['error' => t('api.access_denied')]);
            exit;
        }
        echo json_encode(['success' => true]);
        return;
    }

    // For submission-linked files, verify editability but do NOT delete yet.
    // The actual deletion happens when the edit form is submitted (via
    // cleanup_removed_file_answers), so that cancelling an edit leaves the
    // file intact. Return success so the client clears the UI indicator.
    $submission = get_submission_by_id((int)$file['submission_id']);
    if (!$submission || (int)$submission['user_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['error' => t('api.access_denied')]);
        exit;
    }

    $form = get_form_by_id((int)$submission['form_id']);
    if (!$form || !is_submission_editable($submission, $form)) {
        http_response_code(403);
        echo json_encode(['error' => t('api.access_denied')]);
        exit;
    }

    echo json_encode(['success' => true]);
}

/**
 * Upload file
 * POST /api/files/upload
 */
function api_upload_file(): void {
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => t('api.unauthorized')]);
        exit;
    }
    
    csrf_check();
    
    $user = current_user();

    $rate_error = check_user_action_rate_limit(
        $user['id'],
        'file_upload',
        FILE_UPLOAD_RATE_LIMIT_PER_MINUTE,
        1,
        true
    );

    if ($rate_error) {
        http_response_code(429);
        echo json_encode(['error' => $rate_error]);
        exit;
    }
    
    if (!isset($_FILES['file']) || !isset($_POST['question_id'])) {
        http_response_code(400);
        echo json_encode(['error' => t('api.missing_file_or_question')]);
        exit;
    }
    
    $file = $_FILES['file'];
    $question_id = intval($_POST['question_id']);
    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : null;
    
    // Get question to validate file type/size
    $question = get_question_by_id($question_id);
    
    if (!$question || $question['type'] !== 'file') {
        http_response_code(400);
        echo json_encode(['error' => t('api.invalid_question')]);
        exit;
    }
    
    // Verify the question belongs to a published, non-deleted form
    $version = get_version($question['form_version_id']);
    if (!$version) {
        http_response_code(400);
        echo json_encode(['error' => t('api.invalid_question')]);
        exit;
    }
    $form = get_form_by_id($version['form_id']);
    if (!$form) {
        http_response_code(403);
        echo json_encode(['error' => t('api.form_not_accessible')]);
        exit;
    }

    $settings = $form['settings'] ?? [];

    if ($submission_id !== null) {
        $submission = get_submission_by_id($submission_id);
        if (!$submission || (int)$submission['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => t('api.access_denied')]);
            exit;
        }

        if ((int)$submission['form_id'] !== (int)$form['id']) {
            http_response_code(403);
            echo json_encode(['error' => t('api.access_denied')]);
            exit;
        }

        if ((int)$submission['form_version_id'] !== (int)$question['form_version_id']) {
            http_response_code(400);
            echo json_encode(['error' => t('api.invalid_question')]);
            exit;
        }

        if (!($settings['is_editable_after_submit'] ?? false)) {
            http_response_code(403);
            echo json_encode(['error' => t('api.submission_not_editable')]);
            exit;
        }

        $editable_days = $settings['editable_days'] ?? null;
        $editable_until = $settings['editable_until_date'] ?? null;

        if ($editable_days) {
            $deadline_datetime = datetime_add($submission['submitted_at'], "+{$editable_days} days");
            if (is_past($deadline_datetime)) {
                http_response_code(403);
                echo json_encode(['error' => t('api.editing_window_expired')]);
                exit;
            }
        }

        if ($editable_until && is_past($editable_until)) {
            http_response_code(403);
            echo json_encode(['error' => t('api.editing_window_expired')]);
            exit;
        }
    } else {
        if ($form['status'] !== 'published') {
            http_response_code(403);
            echo json_encode(['error' => t('api.form_not_accessible')]);
            exit;
        }

        $active_version = get_active_version($form['id']);
        if (!$active_version || (int)$active_version['id'] !== (int)$version['id']) {
            http_response_code(403);
            echo json_encode(['error' => t('api.form_not_accessible')]);
            exit;
        }

        $deadline = $settings['submission_deadline'] ?? null;
        if ($deadline && is_past($deadline)) {
            http_response_code(403);
            echo json_encode(['error' => t('api.form_submissions_closed')]);
            exit;
        }

        $prerequisites = $settings['prerequisite_form_ids'] ?? [];
        foreach ($prerequisites as $prereq_id) {
            $submission = db_one(
                'SELECT id FROM submissions WHERE user_id = :user_id AND form_id = :form_id AND status IN (:s1, :s2)',
                ['user_id' => $user['id'], 'form_id' => $prereq_id, 's1' => 'submitted', 's2' => 'clarification_requested']
            );

            if (!$submission) {
                http_response_code(403);
                echo json_encode(['error' => t('api.prerequisites_not_met')]);
                exit;
            }
        }

        $conditions = $settings['conditional_access'] ?? [];
        foreach ($conditions as $condition) {
            $submission = db_one(
                'SELECT answers FROM submissions WHERE user_id = :user_id AND form_id = :form_id AND status IN (:s1, :s2)',
                ['user_id' => $user['id'], 'form_id' => $condition['source_form_id'], 's1' => 'submitted', 's2' => 'clarification_requested']
            );

            if (!$submission) {
                http_response_code(403);
                echo json_encode(['error' => t('api.conditions_not_met')]);
                exit;
            }

            decode_json_fields($submission, ['answers']);
            $answers = $submission['answers'] ?? [];
            $answer = $answers['q_' . $condition['question_id']] ?? null;

            $operator = $condition['operator'] ?? 'eq';
            $expected = $condition['value'] ?? null;

            $condition_met = match ($operator) {
                'eq' => $answer == $expected,
                'neq' => $answer != $expected,
                'contains' => is_array($answer) && in_array($expected, $answer, true),
                default => false,
            };

            if (!$condition_met) {
                http_response_code(403);
                echo json_encode(['error' => t('api.conditions_not_met')]);
                exit;
            }
        }

        $limit = (int)($settings['submission_limit'] ?? 1);
        if ($limit !== 0) {
            $submitted_count = count_user_submissions($user['id'], $form['id']);
            if ($submitted_count >= $limit) {
                http_response_code(403);
                echo json_encode(['error' => t('api.submission_limit_reached')]);
                exit;
            }
        }

        $rate_error = check_submission_rate_limit($user['id'], $form['id']);
        if ($rate_error) {
            http_response_code(429);
            echo json_encode(['error' => $rate_error]);
            exit;
        }

        // Guard against orphaned file accumulation (AUTHZ-VULN-01).
        // Limit how many un-submitted files a user can have at any time.
        // The hourly cron cleans up files older than 24 h, but this cap
        // stops short-term abuse within that window.
        $orphaned = db_one(
            'SELECT COUNT(*) AS cnt FROM files WHERE user_id = :uid AND submission_id IS NULL',
            ['uid' => $user['id']]
        );
        if ((int)($orphaned['cnt'] ?? 0) >= 20) {
            http_response_code(429);
            echo json_encode(['error' => t('api.too_many_pending_uploads')]);
            exit;
        }
    }
    
    $config = $question['config'];
    $validation = $config['validation'] ?? [];
    
    // Validate file
    require_once __DIR__ . '/../forms/validation.php';
    $errors = validate_file_upload($file, $validation);
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }
    
    // When editing, upload the new file as unlinked (submission_id = NULL) so the
    // old file remains intact until the edit form is actually submitted. On submit,
    // associate_files_with_submission() links the new file and
    // cleanup_removed_file_answers() deletes the replaced one. If the user cancels
    // the edit, the unlinked file is cleaned up by the orphaned-uploads cron.
    $upload_result = handle_file_upload($file, $question_id, $user['id'], null);
    
    if ($upload_result['success']) {
        echo json_encode($upload_result);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $upload_result['error']]);
    }
}
