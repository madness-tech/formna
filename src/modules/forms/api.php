<?php

/**
 * Forms Module - API Endpoints
 * 
 * Handles AJAX requests for question management.
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../emails/sender.php';

/**
 * Add new question to a form version
 * POST /api/questions
 */
function api_add_question(): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    $input = json_input();
    
    $version_id = $input['version_id'] ?? null;
    $form_id = $input['form_id'] ?? null; // Optional: for integrity checking
    $type = $input['type'] ?? null;
    $sort_order = $input['sort_order'] ?? 1;
    $config = $input['config'] ?? [];
    
    if (!$version_id || !$type) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Validate version exists and get associated form
    $version = get_version($version_id);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        return;
    }
    
    // SECURITY: If form_id is provided in request, verify version belongs to that form
    // This prevents accidentally editing the wrong form's draft when admin has multiple forms open
    if ($form_id !== null && $version['form_id'] !== $form_id) {
        error_log("INTEGRITY WARNING: Version $version_id does not belong to form $form_id (belongs to {$version['form_id']})");
        http_response_code(400);
        echo json_encode(['error' => 'Version does not belong to the specified form']);
        return;
    }
    
    // Verify user has permission to edit this form
    if (!has_form_permission($version['form_id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit this form']);
        return;
    }
    
    if ($version['status'] !== 'draft') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify a published form version']);
        return;
    }

    $question_count = get_question_count($version_id);
    if ($question_count >= MAX_QUESTIONS_PER_VERSION) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Question limit reached',
            'details' => 'Draft versions are limited to ' . MAX_QUESTIONS_PER_VERSION . ' questions.'
        ]);
        return;
    }
    
    // Validate config structure for the question type
    $validation_result = validate_question_config($type, $config);
    if (!$validation_result['valid']) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid question configuration',
            'details' => $validation_result['errors']
        ]);
        return;
    }
    
    // Use the normalized config and sanitize it
    $config = sanitize_config($validation_result['config']);
    
    try {
        $question_id = add_question($version_id, $type, $sort_order, $config);
        $question = get_question_by_id($question_id);
        
        echo json_encode([
            'success' => true,
            'question' => $question
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add question']);
    }
}

/**
 * Get question by ID
 * GET /api/questions/:id
 */
function api_get_question(string $uid): void {
    require_auth();
    require_role('admin');
    
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    $question = get_question_by_uid($uid);
    
    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
        return;
    }
    
    // Get version to check permissions
    $version = get_version($question['form_version_id']);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        return;
    }
    
    // Verify user has permission to view this form
    if (!has_form_permission($version['form_id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to view this form']);
        return;
    }
    
    // For option-based questions, include which option values are locked by conditional logic
    if (in_array($question['type'], ['select', 'radio', 'checkbox_group', 'multiselect'])) {
        $dependents = get_questions_depending_on($question['uid'], $question['form_version_id']);
        $referenced_values = [];
        foreach ($dependents as $dep) {
            $dep_values = $dep['config']['visibility']['values'] ?? [];
            $referenced_values = array_merge($referenced_values, $dep_values);
        }
        $question['referenced_option_values'] = array_values(array_unique($referenced_values));
    }
    
    echo json_encode([
        'success' => true,
        'question' => $question
    ]);
}

/**
 * Update question configuration
 * POST /api/questions/:id
 */
function api_update_question(string $uid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    $input = json_input();
    $config = $input['config'] ?? null;
    
    if (!$config) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing config']);
        return;
    }
    
    // Get question and validate version status
    $question = get_question_by_uid($uid);
    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
        return;
    }
    
    $version = get_version($question['form_version_id']);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        return;
    }
    
    // Verify user has permission to edit this form
    if (!has_form_permission($version['form_id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit this form']);
        return;
    }
    
    if ($version['status'] !== 'draft') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify a published form version']);
        return;
    }
    
    // Validate config structure for the question type
    $validation_result = validate_question_config($question['type'], $config);
    if (!$validation_result['valid']) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid question configuration',
            'details' => $validation_result['errors']
        ]);
        return;
    }
    
    // Use the normalized config and sanitize it
    $config = sanitize_config($validation_result['config']);
    
    // Block option changes that would break existing conditional visibility references
    if (in_array($question['type'], ['select', 'radio', 'checkbox_group', 'multiselect'])) {
        $dependents = get_questions_depending_on($uid, $question['form_version_id']);
        
        if (!empty($dependents)) {
            $old_options = $question['config']['options'] ?? [];
            $new_options = $config['options'] ?? [];
            
            // Build value→label maps for old and new options
            $old_map = array_column($old_options, 'label', 'value');
            $new_map = array_column($new_options, 'label', 'value');
            
            foreach ($dependents as $dep) {
                $ref_values = $dep['config']['visibility']['values'] ?? [];
                $dep_label = $dep['config']['label'] ?: 'Untitled';
                
                foreach ($ref_values as $ref_value) {
                    $old_label = $old_map[$ref_value] ?? null;
                    if ($old_label === null) continue;
                    
                    // Referenced option value no longer exists or its label changed
                    // (label change means either direct edit or positional shift from removal above)
                    if (!isset($new_map[$ref_value]) || $new_map[$ref_value] !== $old_label) {
                        http_response_code(409);
                        echo json_encode([
                            'error' => 'The option "' . $old_label . '" is used in conditional logic by "' . $dep_label . '" and cannot be modified. Please remove the conditional relationship first.',
                        ]);
                        return;
                    }
                }
            }
        }
    }
    
    try {
        update_question_by_uid($uid, $config);
        
        echo json_encode([
            'success' => true,
            'message' => 'Question updated successfully'
        ]);
    } catch (Exception $e) {
        error_log("API Error - update_question: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update question: ' . $e->getMessage()]);
    }
}

/**
 * Delete question
 * DELETE /api/questions/:id
 */
function api_delete_question(string $uid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    // Get question and validate version status
    $question = get_question_by_uid($uid);
    if (!$question) {
        http_response_code(404);
        echo json_encode(['error' => 'Question not found']);
        return;
    }
    
    $version = get_version($question['form_version_id']);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        return;
    }
    
    // Verify user has permission to edit this form
    if (!has_form_permission($version['form_id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit this form']);
        return;
    }
    
    if ($version['status'] !== 'draft') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify a published form version']);
        return;
    }
    
    // Block deletion if other questions depend on this one via conditional visibility
    $dependents = get_questions_depending_on($uid, $question['form_version_id']);
    if (!empty($dependents)) {
        $dependent_labels = array_map(
            fn($q) => $q['config']['label'] ?: 'Untitled',
            $dependents
        );
        http_response_code(409);
        echo json_encode([
            'error' => 'This question cannot be deleted because it is used as a conditional visibility source. Please remove the conditional relationship from: ' . implode(', ', $dependent_labels),
        ]);
        return;
    }
    
    try {
        $result = delete_question_by_uid($uid);
        
        if (!$result) {
            http_response_code(404);
            echo json_encode(['error' => 'Question not found or already deleted']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Question deleted successfully'
        ]);
    } catch (Exception $e) {
        error_log("API Error - delete_question: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete question: ' . $e->getMessage()]);
    }
}

/**
 * Reorder questions
 * POST /api/questions/reorder
 */
function api_reorder_questions(): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    // Clean any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    $input = json_input();
    
    $version_id = $input['version_id'] ?? null;
    $form_id = $input['form_id'] ?? null; // Optional: for integrity checking
    $question_ids = $input['question_ids'] ?? [];
    
    if (!$version_id || empty($question_ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Validate version exists and get associated form
    $version = get_version($version_id);
    if (!$version) {
        http_response_code(404);
        echo json_encode(['error' => 'Version not found']);
        return;
    }
    
    // SECURITY: If form_id is provided in request, verify version belongs to that form
    // This prevents accidentally editing the wrong form's draft when admin has multiple forms open
    if ($form_id !== null && $version['form_id'] !== $form_id) {
        error_log("INTEGRITY WARNING: Version $version_id does not belong to form $form_id (belongs to {$version['form_id']})");
        http_response_code(400);
        echo json_encode(['error' => 'Version does not belong to the specified form']);
        return;
    }
    
    // Verify user has permission to edit this form
    if (!has_form_permission($version['form_id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit this form']);
        return;
    }
    
    if ($version['status'] !== 'draft') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify a published form version']);
        return;
    }
    
    try {
        reorder_questions($version_id, $question_ids);
        
        echo json_encode([
            'success' => true,
            'message' => 'Questions reordered successfully'
        ]);
    } catch (Exception $e) {
        error_log("API Error - reorder_questions: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reorder questions: ' . $e->getMessage()]);
    }
}

/**
 * Sanitize configuration data recursively
 * Normalizes data types and cleans string values
 *
 * Performs normalization operations:
 * - Trims whitespace from string values
 * - Normalizes boolean values ONLY for whitelisted keys that expect booleans
 * - Preserves null, numeric, and array types
 *
 * IMPORTANT: Boolean casting is restricted to known boolean fields to prevent
 * converting user content like label="true" into actual boolean values.
 *
 * Note: Does NOT apply HTML encoding - data is stored raw in the database.
 * SQL injection is prevented by prepared statements in the model layer.
 * HTML escaping is applied at output time in views using sanitize().
 *
 * @param mixed $data Data to sanitize
 * @param string|null $key Current key being processed (for context-aware sanitization)
 * @return mixed Sanitized data
 */
function sanitize_config($data, $key = null) {
    // Whitelist of config keys that should have boolean casting applied
    // These are keys where "true"/"false" strings should become actual booleans
    $boolean_keys = [
        'required',           // Question required flag
        'include_other',      // Include "Other" option in select/radio
        'allow_multiple',     // Allow multiple file uploads
        // Visibility-related booleans would be nested under 'visibility' key
    ];
    
    if (is_array($data)) {
        $sanitized = [];
        foreach ($data as $k => $value) {
            // Sanitize array keys (trim whitespace)
            $clean_key = is_string($k) ? trim($k) : $k;
            // Pass the key context down for boolean detection
            $sanitized[$clean_key] = sanitize_config($value, $clean_key);
        }
        return $sanitized;
    } elseif (is_string($data)) {
        // Trim whitespace and strip null bytes from string values
        $trimmed = str_replace("\0", '', trim($data));
        
        // SECURITY FIX: Only normalize string representations of booleans
        // for whitelisted keys that are known to expect boolean values.
        // This prevents user content like label="true" from being converted to boolean.
        $should_cast_boolean = ($key !== null && in_array($key, $boolean_keys));
        
        if ($should_cast_boolean) {
            if ($trimmed === 'true') {
                return true;
            } elseif ($trimmed === 'false') {
                return false;
            }
            // Note: We don't cast 'null' string to null for boolean fields
            // as that would be ambiguous - use actual null or false instead
        }
        
        // Return cleaned string (preserves user content like "true", "false", "null")
        return $trimmed;
    } elseif (is_bool($data) || is_null($data) || is_numeric($data)) {
        // Keep booleans, null, and numbers as-is
        return $data;
    } else {
        // For any other type, return as-is (should not occur with validated config)
        return $data;
    }
}

// ============================================================================
// FORM ADMIN MANAGEMENT API
// ============================================================================

/**
 * Search users for adding as form admins
 * GET /api/admin/users/search
 */
function api_admin_users_search(): void {
    require_auth();
    require_role('admin');
    
    header('Content-Type: application/json');
    
    $user = current_user();
    $rl_error = check_user_action_rate_limit(
        $user['id'], 'user_search', USER_SEARCH_RATE_LIMIT_PER_MINUTE, 1, true
    );
    if ($rl_error) {
        http_response_code(429);
        echo json_encode(['error' => $rl_error]);
        return;
    }
    
    $query = trim($_GET['q'] ?? '');
    $form_uuid = trim($_GET['form_uuid'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    
    if (strlen($query) < 3) {
        echo json_encode(['users' => []]);
        return;
    }
    
    // Only search for admin role users (exclude super_admins, reviewers, and regular users)
    // Super admins already have access to everything
    // Reviewers get access through programs, not form-level permissions
    // Regular users shouldn't be form admins
    $search_pattern = '%' . $query . '%';
    
    $sql = "SELECT u.id, u.uuid, u.name, u.email, u.role
            FROM users u
            WHERE u.status = 'active'
            AND u.role = 'admin'
            AND (u.name LIKE ? OR u.email LIKE ?)";
    
    $params = [$search_pattern, $search_pattern];
    
    // Exclude users who already have permission on this form (including owner)
    if (!empty($form_uuid)) {
        $sql .= " AND u.id NOT IN (
            SELECT p.user_id
            FROM permissions p
            JOIN forms f ON p.form_id = f.id
            WHERE f.uuid = ?
            UNION
            SELECT created_by
            FROM forms
            WHERE uuid = ?
        )";
        $params[] = $form_uuid;
        $params[] = $form_uuid;
    }
    
    $sql .= " ORDER BY u.name LIMIT ?";
    $params[] = $limit;
    
    $users = db_query($sql, $params);
    
    echo json_encode(['users' => $users]);
}

/**
 * Get all admins for a form
 * GET /api/admin/forms/{uuid}/admins
 */
function api_form_admins_list(string $form_uuid): void {
    require_auth();
    require_role('admin');
    
    header('Content-Type: application/json');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => 'Form not found']);
        return;
    }
    
    // SECURITY: Verify user has manage permission
    if (!has_form_permission($form['id'], 'manage')) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    $admins = get_form_admins($form['id']);
    
    echo json_encode($admins);
}

/**
 * Add an admin to a form
 * POST /api/admin/forms/{uuid}/admins
 */
function api_form_admins_add(string $form_uuid): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    header('Content-Type: application/json');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => 'Form not found']);
        return;
    }
    
    $current_user = current_user();
    
    // SECURITY: Only owner or super_admin can add admins
    if (!is_form_owner($form['id'], $current_user['id']) && $current_user['role'] !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only the form owner can add administrators']);
        return;
    }
    
    $input = json_input();
    $user_id = (int)($input['user_id'] ?? 0);
    $access = $input['access'] ?? 'view_results';
    
    // Validate access level
    if (!in_array($access, ['view_results', 'manage'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid access level']);
        return;
    }
    
    // Verify user exists and has appropriate role
    $user = db_one(
        'SELECT id, uuid, name, email, role, status FROM users WHERE id = ?',
        [$user_id]
    );
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    if ($user['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['error' => 'User is not active']);
        return;
    }
    
    if (!in_array($user['role'], ['admin', 'reviewer', 'super_admin'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User does not have appropriate role']);
        return;
    }
    
    // Add admin
    $success = add_form_admin($form['id'], $user_id, $access);
    
    if (!$success) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not add admin. User may already have access.']);
        return;
    }
    
    // Determine link based on access level
    $form_link = $access === 'manage'
        ? '/admin/forms/' . $form['uuid'] . '/builder'
        : '/admin/forms/' . $form['uuid'] . '/submissions';
    
    // Queue notification email
    require_once __DIR__ . '/../emails/models.php';
    queue_email_from_template(
        'form_admin_added',
        $user,
        [
            'form_name' => $form['name'],
            'access_level' => $access === 'manage' ? 'Manage Form and Settings' : 'Manage Submissions',
            'granted_by' => $current_user['name'] ?? $current_user['email'],
            'form_link' => base_url($form_link)
        ]
    );
    
    // Create in-app notification
    require_once __DIR__ . '/../notifications/models.php';
    create_notification(
        $user['id'],
        'form_admin_added',
        [
            'form_name' => $form['name'],
            'form_link' => $form_link
        ]
    );
    
    echo json_encode([
        'success' => true,
        'admin' => [
            'id' => $user['id'],
            'uuid' => $user['uuid'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'access' => $access,
            'granted_at' => now()
        ]
    ]);
}

/**
 * Update admin access level
 * PATCH /api/admin/forms/{uuid}/admins/{user_id}
 */
function api_form_admins_update(string $form_uuid, int $user_id): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    header('Content-Type: application/json');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => 'Form not found']);
        return;
    }
    
    $current_user = current_user();
    
    // SECURITY: Only owner or super_admin can update admins
    if (!is_form_owner($form['id'], $current_user['id']) && $current_user['role'] !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Only the form owner can update administrators']);
        return;
    }
    
    $input = json_input();
    $access = $input['access'] ?? '';
    
    // Validate access level
    if (!in_array($access, ['view_results', 'manage'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid access level']);
        return;
    }
    
    // Update admin
    $success = update_form_admin_access($form['id'], $user_id, $access);
    
    if (!$success) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not update admin access']);
        return;
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Remove an admin from a form
 * DELETE /api/admin/forms/{uuid}/admins/{user_id}
 */
function api_form_admins_remove(string $form_uuid, int $user_id): void {
    require_auth();
    require_role('admin');
    csrf_check();
    
    header('Content-Type: application/json');
    
    $form = get_form_by_uuid($form_uuid);
    if (!$form) {
        http_response_code(404);
        echo json_encode(['error' => 'Form not found']);
        return;
    }
    
    $current_user = current_user();
    
    // SECURITY: Owner or super_admin can remove any admin, or user can remove themselves
    $is_owner = is_form_owner($form['id'], $current_user['id']);
    $is_super_admin = $current_user['role'] === 'super_admin';
    $is_self_removal = $user_id === $current_user['id'];
    
    if (!$is_owner && !$is_super_admin && !$is_self_removal) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    // Remove admin
    $success = remove_form_admin($form['id'], $user_id);
    
    if (!$success) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not remove admin. User may not have access or is the owner.']);
        return;
    }
    
    echo json_encode(['success' => true]);
}
