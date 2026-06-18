<?php

/**
 * Forms Module - Validation
 * 
 * Handles form submission validation based on question configuration.
 */

/**
 * Build validation rules from form version questions
 * @return array<int, array<string, mixed>>
 */
function build_validation_rules(int $version_id): array {
    $questions = get_questions($version_id);
    $rules = [];
    
    foreach ($questions as $question) {
        $config = $question['config'];
        $rules[$question['id']] = [
            'type' => $question['type'],
            'label' => $config['label'] ?? 'Field',
            'required' => $config['required'] ?? false,
            'validation' => $config['validation'] ?? [],
            'options' => $config['options'] ?? null  // Include options for validation
        ];
    }
    
    return $rules;
}

/**
 * Calculate which questions should be visible based on submitted answers and visibility rules
 * SECURITY: This must be evaluated server-side, never trust client input
 *
 * CRITICAL FIX: Handles cascading visibility properly by unsetting answers for hidden questions.
 * This prevents validation bypass where:
 *   - Question A hides Question B
 *   - Question B controls Question C
 *   - Malicious user submits value for hidden B to make C appear visible
 *   - Without this fix, C would be validated using the "ghost" value from hidden B
 *
 * @param array<int, array<string, mixed>> $questions All questions for the form version
 * @param array<int, mixed> &$answers Submitted answers (keyed by question_id) - MODIFIED by reference to unset hidden values
 * @return array<int, string> Array of visible question UIDs
 */
function calculate_visible_questions(array $questions, array &$answers): array {
    $questions_by_uid = [];
    foreach ($questions as $q) {
        $questions_by_uid[$q['uid']] = $q;
    }

    $max_iterations = count($questions) + 1;
    $iteration = 0;
    $hidden_ids = [];

    do {
        $changed = false;
        $iteration++;

        foreach ($questions as $question) {
            $config         = $question['config'];
            $already_hidden = isset($hidden_ids[$question['id']]);

            if (empty($config['visibility']['question_uid'])) {
                if ($already_hidden) {
                    unset($hidden_ids[$question['id']]);
                    $changed = true;
                }
                continue;
            }

            $visibility      = $config['visibility'];
            $source_uid      = $visibility['question_uid'];
            $required_values = array_map('strval', $visibility['values'] ?? []);

            $source_question = $questions_by_uid[$source_uid] ?? null;

            if (!$source_question || !empty($hidden_ids[$source_question['id']])) {
                if (!$already_hidden) {
                    $hidden_ids[$question['id']] = true;
                    $changed = true;
                }
                continue;
            }

            if (empty($required_values)) {
                if ($already_hidden) {
                    unset($hidden_ids[$question['id']]);
                    $changed = true;
                }
                continue;
            }

            $source_answer = $answers[$source_question['id']] ?? null;
            $source_answer = is_array($source_answer) ? $source_answer : ($source_answer !== null ? [$source_answer] : []);
            $source_answer = array_map('strval', $source_answer);

            $is_visible = count(array_intersect($source_answer, $required_values)) > 0;

            if (!$is_visible && !$already_hidden) {
                $hidden_ids[$question['id']] = true;
                $changed = true;
            } elseif ($is_visible && $already_hidden) {
                unset($hidden_ids[$question['id']]);
                $changed = true;
            }
        }

        foreach ($hidden_ids as $q_id => $_) {
            unset($answers[$q_id]);
        }

    } while ($changed && $iteration < $max_iterations);

    $visible_uids = [];
    foreach ($questions as $q) {
        if (!isset($hidden_ids[$q['id']])) {
            $visible_uids[] = $q['uid'];
        }
    }

    return $visible_uids;
}

/**
 * Validate submission answers against rules
 * Returns array of errors (empty if valid)
 *
 * @param int $version_id Form version ID
 * @param array<int, mixed> $answers Question answers (keyed by question_id)
 * @param array<int, string>|null $visible_question_uids DEPRECATED - No longer used, calculated server-side for security
 * @param int|null $submission_id When editing, the submission being updated (allows its own file references)
 * @return array<int, string>
 */
function validate_submission(int $version_id, array $answers, ?array $visible_question_uids = null, ?int $submission_id = null): array {
    $questions = get_questions($version_id);
    $rules = build_validation_rules($version_id);
    $errors = [];
    
    // SECURITY FIX: Calculate visible questions server-side instead of trusting client input
    // This prevents attackers from claiming required fields were "hidden" to bypass validation
    $server_visible_uids = calculate_visible_questions($questions, $answers);
    
    // Build a map of question_id => uid for filtering
    $question_uid_map = [];
    foreach ($questions as $question) {
        $question_uid_map[$question['id']] = $question['uid'];
    }
    
    foreach ($rules as $question_id => $rule) {
        // Skip validation for questions that aren't visible based on server-side calculation
        $question_uid = $question_uid_map[$question_id] ?? null;
        if ($question_uid && !in_array($question_uid, $server_visible_uids)) {
            continue; // Skip validation for hidden questions
        }
        
        $answer = $answers[$question_id] ?? null;
        $label = $rule['label'];
        $validation = $rule['validation'];
        
        // Check required
        if ($rule['required'] && empty_answer($answer, $rule['type'])) {
            $errors[$question_id] = t('validation.required_field');
            continue;
        }
        
        // Skip further validation if empty and not required
        if (empty_answer($answer, $rule['type'])) {
            continue;
        }
        
        // Type-specific validation
        switch ($rule['type']) {
            case 'text':
            case 'textarea':
                $errors = array_merge($errors, validate_text($question_id, $answer, $validation));
                break;
                
            case 'number':
                $errors = array_merge($errors, validate_number($question_id, $answer, $validation));
                break;
                
            case 'email':
                if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    $errors[$question_id] = t('validation.invalid_email');
                }
                break;
                
            case 'url':
                if (!filter_var($answer, FILTER_VALIDATE_URL)) {
                    $errors[$question_id] = t('validation.invalid_url');
                }
                break;
                
            case 'date':
                // Validate HTML5 date format: YYYY-MM-DD
                $date = DateTime::createFromFormat('Y-m-d', $answer);
                if (!$date || $date->format('Y-m-d') !== $answer) {
                    $errors[$question_id] = t('validation.invalid_date');
                }
                break;
                
            case 'datetime':
                // Validate HTML5 datetime-local formats: YYYY-MM-DDTHH:MM or YYYY-MM-DDTHH:MM:SS
                $datetime = DateTime::createFromFormat('Y-m-d\TH:i:s', $answer);
                if (!$datetime || $datetime->format('Y-m-d\TH:i:s') !== $answer) {
                    // Try without seconds
                    $datetime = DateTime::createFromFormat('Y-m-d\TH:i', $answer);
                    if (!$datetime || $datetime->format('Y-m-d\TH:i') !== $answer) {
                        $errors[$question_id] = t('validation.invalid_datetime');
                    }
                }
                break;
                
            case 'select':
            case 'radio':
                // SECURITY: Validate that submitted value is one of the allowed options
                $errors = array_merge($errors, validate_single_select($question_id, $answer, $label, $rule['options']));
                break;
                
            case 'checkbox_group':
            case 'multiselect':
                // SECURITY: Validate that submitted values are from allowed options
                $errors = array_merge($errors, validate_multi_select($question_id, $answer, $label, $validation, $rule['options']));
                break;
                
            case 'file':
                // SECURITY: Validate file reference during submission
                // Even though files are validated during upload, we must verify:
                // 1. The file_id exists
                // 2. The file belongs to the current user
                // 3. The file matches the question being answered
                $errors = array_merge($errors, validate_file_reference($question_id, $answer, $label, $submission_id));
                break;
        }
    }
    
    return $errors;
}

/**
 * Check if answer is considered empty for its type
 */
function empty_answer(mixed $answer, string $type): bool {
    if ($answer === null || $answer === '') {
        return true;
    }
    
    if (in_array($type, ['checkbox_group', 'multiselect']) && (!is_array($answer) || count($answer) === 0)) {
        return true;
    }
    
    return false;
}

/**
 * Validate text/textarea fields
 * @param array<string, mixed> $validation
 * @return array<int, string>
 */
function validate_text(int $question_id, string $answer, array $validation): array {
    $errors = [];

    $max_pattern_length = 500;
    
    if (isset($validation['min_length']) && strlen($answer) < $validation['min_length']) {
        $errors[$question_id] = t('validation.min_length', ['min' => $validation['min_length']]);
    }
    
    if (isset($validation['max_length']) && strlen($answer) > $validation['max_length']) {
        $errors[$question_id] = t('validation.max_length', ['max' => $validation['max_length']]);
    }
    
    if (isset($validation['pattern']) && !empty($validation['pattern'])) {
        if (strlen($validation['pattern']) > $max_pattern_length) {
            $errors[$question_id] = t('validation.invalid_format');
            return $errors;
        }
        ini_set('pcre.backtrack_limit', 100000);
        $delimiter = '~';
        $pattern = str_replace($delimiter, '\\' . $delimiter, $validation['pattern']);
        $match_result = preg_match($delimiter . $pattern . $delimiter . 'u', $answer);
        if ($match_result === false) {
            error_log("PCRE error in pattern validation for question $question_id: " . preg_last_error_msg());
            $errors[$question_id] = t('validation.invalid_format');
        } elseif ($match_result === 0) {
            $errors[$question_id] = t('validation.invalid_format');
        }
    }
    
    return $errors;
}

/**
 * Validate number fields
 * @param array<string, mixed> $validation
 * @return array<int, string>
 */
function validate_number(int $question_id, string $answer, array $validation): array {
    $errors = [];
    
    if (!is_numeric($answer)) {
        $errors[$question_id] = t('validation.invalid_number');
        return $errors;
    }
    
    $number = floatval($answer);
    
    if (isset($validation['min']) && $number < $validation['min']) {
        $errors[$question_id] = t('validation.min_value', ['min' => $validation['min']]);
    }
    
    if (isset($validation['max']) && $number > $validation['max']) {
        $errors[$question_id] = t('validation.max_value', ['max' => $validation['max']]);
    }
    
    return $errors;
}

/**
 * Validate single-select fields (select, radio)
 * SECURITY: Ensures submitted value is from the allowed options
 * @param array<int, array<string, mixed>>|null $options
 * @return array<int, string>
 */
function validate_single_select(int $question_id, mixed $answer, string $label, ?array $options): array {
    $errors = [];
    
    // If no options provided, cannot validate (should not happen in normal operation)
    if (!is_array($options) || empty($options)) {
        error_log("SECURITY WARNING: validate_single_select called without valid options for question $question_id");
        $errors[$question_id] = "$label has invalid configuration.";
        return $errors;
    }
    
    // Extract valid option values and normalize to strings
    // HTML form submissions send all values as strings, but JSON config may use integers
    $valid_values = [];
    foreach ($options as $option) {
        if (isset($option['value'])) {
            // Convert to string for comparison (handles both string and numeric values)
            $valid_values[] = strval($option['value']);
        }
    }
    
    // CRITICAL SECURITY CHECK: Ensure submitted value is in allowed options
    // Convert answer to string to match normalized valid_values
    if (!in_array(strval($answer), $valid_values, true)) {
        error_log("SECURITY WARNING: Invalid option submitted for question $question_id. Submitted: " . var_export($answer, true));
        $errors[$question_id] = "$label contains an invalid selection.";
    }
    
    return $errors;
}

/**
 * Validate multi-select fields (checkbox group, multiselect)
 * SECURITY: Ensures all submitted values are from the allowed options
 * @param array<string, mixed> $validation
 * @param array<int, array<string, mixed>>|null $options
 * @return array<int, string>
 */
function validate_multi_select(int $question_id, mixed $answer, string $label, array $validation, ?array $options = null): array {
    $errors = [];
    
    if (!is_array($answer)) {
        $answer = [$answer];
    }
    
    // CRITICAL SECURITY CHECK: Validate against allowed options if provided
    if (is_array($options) && !empty($options)) {
        // Extract valid option values and normalize to strings
        // HTML form submissions send all values as strings, but JSON config may use integers
        $valid_values = [];
        foreach ($options as $option) {
            if (isset($option['value'])) {
                // Convert to string for comparison (handles both string and numeric values)
                $valid_values[] = strval($option['value']);
            }
        }
        
        // Check each submitted value is in allowed options
        foreach ($answer as $submitted_value) {
            // Convert submitted value to string to match normalized valid_values
            if (!in_array(strval($submitted_value), $valid_values, true)) {
                error_log("SECURITY WARNING: Invalid option submitted for question $question_id. Submitted value: " . var_export($submitted_value, true));
                $errors[$question_id] = "$label contains one or more invalid selections.";
                break; // One error is enough
            }
        }
        
        // If we found invalid options, return early
        if (!empty($errors)) {
            return $errors;
        }
    }
    
    // Check count constraints
    $count = count($answer);
    
    if (isset($validation['min_selected']) && $count < $validation['min_selected']) {
        $errors[$question_id] = t('validation.min_selections', ['min' => $validation['min_selected']]);
    }
    
    if (isset($validation['max_selected']) && $count > $validation['max_selected']) {
        $errors[$question_id] = t('validation.max_selections', ['max' => $validation['max_selected']]);
    }
    
    return $errors;
}

/**
 * Validate file reference during submission
 * SECURITY: Ensures the file_id is valid and belongs to the current user
 *
 * @param int $question_id Question being answered
 * @param mixed $answer File ID submitted
 * @param string $label Question label for error messages
 * @param int|null $current_submission_id When editing, the submission being updated (allows its own files)
 * @return array<int, string> Array of error messages (empty if valid)
 */
function validate_file_reference(int $question_id, mixed $answer, string $label, ?int $current_submission_id = null): array {
    $errors = [];
    
    // If empty, validation is handled by required check earlier
    if (empty($answer)) {
        return $errors;
    }
    
    // File answer should be a numeric file_id
    if (!is_numeric($answer)) {
        error_log("SECURITY WARNING: Non-numeric file_id submitted for question $question_id: " . var_export($answer, true));
        $errors[$question_id] = "$label has an invalid file reference.";
        return $errors;
    }
    
    $file_id = intval($answer);
    
    // CRITICAL SECURITY CHECK: Verify file exists and belongs to current user
    // We need to check the files table to ensure:
    // 1. File exists
    // 2. File belongs to the current authenticated user
    // 3. File is for the correct question
    $file = db_one(
        'SELECT id, user_id, question_id, submission_id FROM files WHERE id = ?',
        [$file_id]
    );
    
    if (!$file) {
        error_log("SECURITY WARNING: Non-existent file_id submitted for question $question_id: $file_id");
        $errors[$question_id] = "$label references a file that does not exist.";
        return $errors;
    }
    
    // Verify file belongs to current user
    $current_user = current_user();
    if (!$current_user || $file['user_id'] !== $current_user['id']) {
        error_log("SECURITY ALERT: User " . ($current_user['id'] ?? 'unknown') . " attempted to submit file_id $file_id owned by user {$file['user_id']} for question $question_id");
        $errors[$question_id] = "$label references a file you do not own.";
        return $errors;
    }
    
    // Verify file is for the correct question
    if ($file['question_id'] !== $question_id) {
        error_log("SECURITY WARNING: File $file_id for question {$file['question_id']} submitted for question $question_id");
        $errors[$question_id] = "$label references a file for a different question.";
        return $errors;
    }
    
    // Verify file is not already linked to a different submission.
    // During edit, the file is legitimately linked to the submission being updated,
    // so we allow that specific case.
    if ($file['submission_id'] !== null) {
        if ($current_submission_id !== null && (int)$file['submission_id'] === $current_submission_id) {
            // File belongs to the submission being edited — this is valid
        } else {
            error_log("SECURITY WARNING: File $file_id already linked to submission {$file['submission_id']}, attempted reuse for question $question_id");
            $errors[$question_id] = "$label references a file that is already in use.";
            return $errors;
        }
    }
    
    return $errors;
}

// Constants SAFE_FILE_EXTENSIONS, DANGEROUS_FILE_EXTENSIONS, MIME_TYPE_MAPPING
// and normalize_file_extension() are defined centrally in core/helpers.php
// (autoloaded via Composer) so every module shares the same lists.

/**
 * Validate file upload with comprehensive security checks
 *
 * @param array<string, mixed> $file $_FILES array entry
 * @param array<string, mixed> $validation Validation configuration
 * @return array<int, string> Array of error messages (empty if valid)
 */
function validate_file_upload(array $file, array $validation): array {
    $errors = [];
    
    // Check file size
    if (isset($validation['max_file_size_mb'])) {
        $max_bytes = $validation['max_file_size_mb'] * 1024 * 1024;
        if ($file['size'] > $max_bytes) {
            $errors[] = t('validation.file_too_large', ['size' => $validation['max_file_size_mb'] . ' MB']);
        }
    }
    
    // SECURITY: Extract and normalise extension from filename
    // Normalisation maps alternative spellings to a canonical form (e.g. jpeg → jpg)
    // so per-question allowed_extensions lists work regardless of which variant the user uploads.
    $raw_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext = normalize_file_extension($raw_ext);
    
    // CRITICAL SECURITY CHECK 1: Block dangerous extensions (absolute blacklist)
    if (in_array($ext, DANGEROUS_FILE_EXTENSIONS)) {
        $errors[] = "File type '$ext' is not permitted for security reasons.";
        return $errors; // Immediately fail - do not continue validation
    }
    
    // CRITICAL SECURITY CHECK 2: Enforce hardcoded whitelist
    // Configuration can only restrict, never expand beyond this safe list
    if (!in_array($ext, SAFE_FILE_EXTENSIONS)) {
        $errors[] = "File type '$ext' is not permitted. Allowed types: " . implode(', ', SAFE_FILE_EXTENSIONS);
        return $errors;
    }
    
    // Check configured allowed extensions (subset of safe list)
    if (isset($validation['allowed_extensions']) && is_array($validation['allowed_extensions'])) {
        $normalised_allowed = array_map('normalize_file_extension', $validation['allowed_extensions']);
        if (!in_array($ext, $normalised_allowed)) {
            $allowed = implode(', ', $validation['allowed_extensions']);
            $errors[] = "File type must be one of: $allowed.";
        }
    }
    
    // SECURITY CHECK 3: Validate MIME type matches extension
    $detected_mime = mime_content_type($file['tmp_name']);
    
    // Handle empty files (0 bytes) - they have a special MIME type
    // Empty files can be legitimate and will report as inode/x-empty or application/x-empty
    $is_empty_file = ($file['size'] === 0 && in_array($detected_mime, ['inode/x-empty', 'application/x-empty']));
    
    // Check if the detected MIME type is in our mapping
    $mime_valid = false;
    if (isset(MIME_TYPE_MAPPING[$detected_mime])) {
        // Check if the extension matches the expected extensions for this MIME type
        if (in_array($ext, MIME_TYPE_MAPPING[$detected_mime])) {
            $mime_valid = true;
        }
    }
    
    // SECURITY FIX: Check if the extension has a MIME type mapping defined
    // If it does, we MUST enforce strict validation for that extension
    $has_mime_mapping = false;
    foreach (MIME_TYPE_MAPPING as $mime => $extensions) {
        if (in_array($ext, $extensions)) {
            $has_mime_mapping = true;
            break;
        }
    }
    
    // CRITICAL: Enforce MIME validation for ALL extensions with defined mappings
    // This prevents attackers from uploading malicious files with image/archive extensions
    // Empty files are allowed to bypass this check since they have a legitimate special MIME type
    if ($has_mime_mapping && !$mime_valid && !$is_empty_file) {
        $errors[] = "File content does not match its extension. Expected content type for '$ext' files.";
    }
    
    // SECURITY CHECK 4: Read magic bytes for common executable types
    // This catches files disguised with safe extensions
    $file_handle = fopen($file['tmp_name'], 'rb');
    if ($file_handle) {
        $magic_bytes = fread($file_handle, 8);
        fclose($file_handle);
        
        // Check for executable signatures (magic bytes)
        $dangerous_signatures = [
            "\x4D\x5A" => 'Windows executable (PE/EXE)',           // MZ (PE header)
            "\x7F\x45\x4C\x46" => 'Linux executable (ELF)',        // ELF
            "\x23\x21" => 'Script file (shebang)',                 // #! (shell script)
            "\x3C\x3F\x70\x68\x70" => 'PHP script',               // <?php
            "\x50\x4B\x03\x04" => null,                           // ZIP (allowed but monitored)
        ];
        
        foreach ($dangerous_signatures as $signature => $description) {
            if (strpos($magic_bytes, $signature) === 0) {
                if ($description !== null) {
                    $errors[] = "File rejected: detected $description";
                    break;
                }
            }
        }
    }
    
    return $errors;
}

/**
 * Get client-side validation attributes for a question
 * Returns HTML attribute string
 * @param array<string, mixed> $question
 */
function get_client_validation_attrs(array $question): string {
    $config = $question['config'];
    $validation = $config['validation'] ?? [];
    $attrs = [];
    
    if ($config['required'] ?? false) {
        $attrs[] = 'required';
    }
    
    if (isset($validation['min_length'])) {
        $attrs[] = 'minlength="' . $validation['min_length'] . '"';
    }
    
    if (isset($validation['max_length'])) {
        $attrs[] = 'maxlength="' . $validation['max_length'] . '"';
    }
    
    if (isset($validation['min'])) {
        $attrs[] = 'min="' . $validation['min'] . '"';
    }
    
    if (isset($validation['max'])) {
        $attrs[] = 'max="' . $validation['max'] . '"';
    }
    
    if (isset($validation['pattern']) && !empty($validation['pattern'])) {
        $attrs[] = 'pattern="' . htmlspecialchars($validation['pattern']) . '"';
    }
    
    // Add data attributes for JavaScript validation
    $attrs[] = 'data-required="' . (($config['required'] ?? false) ? 'true' : 'false') . '"';
    $attrs[] = 'data-type="' . $question['type'] . '"';
    
    return implode(' ', $attrs);
}

/**
 * Validate question config structure based on type
 * Ensures all required fields are present with appropriate defaults
 *
 * @param string $type Question type
 * @param array<string, mixed> $config Configuration array
 * @return array<string, mixed> Array with 'valid' (bool) and 'errors' (array) or 'config' (normalized array)
 */
function validate_question_config(string $type, array $config): array {
    $errors = [];
    $max_pattern_length = 500;
    
    // Define required fields for each type
    $required_fields = [
        'text' => ['label'],
        'textarea' => ['label'],
        'number' => ['label'],
        'email' => ['label'],
        'url' => ['label'],
        'date' => ['label'],
        'datetime' => ['label'],
        'select' => ['label', 'options'],
        'radio' => ['label', 'options'],
        'checkbox_group' => ['label', 'options'],
        'multiselect' => ['label', 'options'],
        'file' => ['label'],
        'heading' => ['label'],
        'paragraph' => ['description'],
        'divider' => []
    ];
    
    // Check if type is valid
    if (!isset($required_fields[$type])) {
        return ['valid' => false, 'errors' => ['type' => "Invalid question type: $type"]];
    }
    
    // Validate required fields
    foreach ($required_fields[$type] as $field) {
        if (!isset($config[$field]) || (is_string($config[$field]) && trim($config[$field]) === '')) {
            $errors[] = "Missing required field: $field";
        }
    }

    // Enforce length limits on label and description
    $label = $config['label'] ?? '';
    if (is_string($label) && mb_strlen($label) > MAX_LABEL_LENGTH) {
        $errors[] = "Label exceeds maximum length of " . MAX_LABEL_LENGTH . " characters.";
    }
    $description = $config['description'] ?? '';
    if (is_string($description) && mb_strlen($description) > MAX_DESCRIPTION_LENGTH) {
        $errors[] = "Description exceeds maximum length of " . MAX_DESCRIPTION_LENGTH . " characters.";
    }
    
    // Validate regex pattern for text/textarea types
    if (in_array($type, ['text', 'textarea'])) {
        $pattern = $config['validation']['pattern'] ?? null;
        if ($pattern !== null && $pattern !== '') {
            if (strlen($pattern) > $max_pattern_length) {
                $errors[] = "Pattern exceeds maximum length of {$max_pattern_length} characters.";
            }
            if (@preg_match('/' . $pattern . '/', '') === false) {
                $errors[] = "Pattern is not a valid regular expression.";
            }
        }
    }

    // Special validation for option-based fields
    if (in_array($type, ['select', 'radio', 'checkbox_group', 'multiselect'])) {
        if (isset($config['options'])) {
            if (!is_array($config['options'])) {
                $errors[] = "Options must be an array";
            } elseif (empty($config['options'])) {
                $errors[] = "At least one option is required";
            } else {
                // Validate each option has required structure
                foreach ($config['options'] as $index => $option) {
                    if (!is_array($option)) {
                        $errors[] = "Option at index $index must be an array";
                        continue;
                    }
                    // Label is required (what users see), value is optional (auto-filled from label if missing)
                    if (!isset($option['label'])) {
                        $errors[] = "Option at index $index must have a 'label' key";
                    } elseif (trim($option['label']) === '') {
                        $errors[] = "Option at index $index has empty label";
                    }
                    // If value is explicitly provided, ensure it's not empty
                    if (isset($option['value']) && trim($option['value']) === '') {
                        $errors[] = "Option at index $index has empty value";
                    }
                    if (isset($option['value'])) {
                        $value = trim((string) $option['value']);
                        if ($value !== '' && preg_match('/[\[\]]/', $value)) {
                            $errors[] = "Option at index $index has an invalid value. Brackets are not allowed.";
                        }
                    }
                }
            }
        }
    }
    
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Normalize config with defaults
    $normalized_config = normalize_question_config($type, $config);
    
    return ['valid' => true, 'config' => $normalized_config];
}

/** Absolute ceiling for file upload size (MB) */
const MAX_FILE_SIZE_MB_LIMIT = 50;

/** Maximum characters allowed for question labels */
const MAX_LABEL_LENGTH = 200;

/** Maximum characters allowed for question descriptions / help text */
const MAX_DESCRIPTION_LENGTH = 500;

/**
 * Whitelist of accepted top-level config keys per question type.
 * Any key not listed here is silently dropped during normalization,
 * preventing arbitrary data from being persisted in the config JSON.
 *
 * 'visibility' and 'scoring' are appended for every type that carries them.
 *
 * @return array<string, list<string>>
 */
function allowed_config_keys(): array {
    $common = ['label', 'description', 'required', 'validation', 'visibility', 'scoring'];
    return [
        'text'           => $common,
        'textarea'       => $common,
        'number'         => $common,
        'email'          => $common,
        'url'            => $common,
        'date'           => $common,
        'datetime'       => $common,
        'select'         => array_merge($common, ['options']),
        'radio'          => array_merge($common, ['options']),
        'checkbox_group' => array_merge($common, ['options']),
        'multiselect'    => array_merge($common, ['options']),
        'file'           => $common,
        'heading'        => ['label', 'description', 'visibility', 'scoring'],
        'paragraph'      => ['label', 'description', 'visibility', 'scoring'],
        'divider'        => ['label', 'description'],
    ];
}

/**
 * Normalize question config by ensuring all expected fields exist with defaults.
 *
 * SECURITY: Only whitelisted keys (per question type) are accepted from user input.
 * This prevents arbitrary key injection into the stored config JSON and caps
 * dangerous values like max_file_size_mb to a sane ceiling.
 *
 * @param string $type Question type
 * @param array<string, mixed> $config Configuration array
 * @return array<string, mixed> Normalized configuration
 */
function normalize_question_config(string $type, array $config): array {
    // Common defaults (enforce length ceilings as a safety net)
    $normalized = [
        'label' => mb_substr($config['label'] ?? '', 0, MAX_LABEL_LENGTH),
        'description' => mb_substr($config['description'] ?? '', 0, MAX_DESCRIPTION_LENGTH),
        'required' => $config['required'] ?? false,
        'validation' => [],
    ];

    $max_pattern_length = 500;

    // --- Type-specific defaults & validation sub-key handling ---
    switch ($type) {
        case 'text':
        case 'textarea':
            $pattern = $config['validation']['pattern'] ?? null;
            if ($pattern !== null && $pattern !== '') {
                if (strlen($pattern) > $max_pattern_length) {
                    error_log("WARNING: Regex pattern exceeded max length and was discarded for question config.");
                    $config['validation']['pattern'] = null;
                }
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    error_log("WARNING: Invalid regex pattern discarded for question config: " . substr($pattern, 0, 100));
                    $config['validation']['pattern'] = null;
                }
            }
            $normalized['validation'] = [
                'min_length' => $config['validation']['min_length'] ?? null,
                'max_length' => $config['validation']['max_length'] ?? null,
                'pattern'    => $config['validation']['pattern'] ?? null,
            ];
            break;

        case 'number':
            $normalized['validation'] = [
                'min' => $config['validation']['min'] ?? null,
                'max' => $config['validation']['max'] ?? null,
            ];
            break;

        case 'select':
        case 'radio':
        case 'checkbox_group':
        case 'multiselect':
            $normalized['options'] = $config['options'] ?? [];
            foreach ($normalized['options'] as $index => &$option) {
                if (!isset($option['value']) || trim((string) $option['value']) === '') {
                    $option['value'] = 'option' . ($index + 1);
                }
            }
            unset($option);
            if (in_array($type, ['checkbox_group', 'multiselect'])) {
                $normalized['validation'] = [
                    'min_selected' => $config['validation']['min_selected'] ?? null,
                    'max_selected' => $config['validation']['max_selected'] ?? null,
                ];
            }
            break;

        case 'file':
            // SECURITY: Sanitize allowed_extensions to prevent RCE
            $provided_extensions = [];
            if (isset($config['validation']['allowed_extensions']) && is_array($config['validation']['allowed_extensions'])) {
                foreach ($config['validation']['allowed_extensions'] as $ext) {
                    $ext = strtolower(trim($ext));

                    // CRITICAL: Reject dangerous extensions
                    if (in_array($ext, DANGEROUS_FILE_EXTENSIONS)) {
                        error_log("SECURITY WARNING: Attempted to configure dangerous file extension: $ext");
                        continue;
                    }

                    // CRITICAL: Only allow extensions from the safe whitelist
                    if (in_array($ext, SAFE_FILE_EXTENSIONS)) {
                        $provided_extensions[] = $ext;
                    } else {
                        error_log("SECURITY WARNING: Attempted to configure unsafe file extension: $ext");
                    }
                }
            }

            $safe_allowed_extensions = !empty($provided_extensions)
                ? $provided_extensions
                : ['pdf', 'doc', 'docx', 'jpg', 'png'];

            // Determine max_file_size_mb: accept user value only up to the hard ceiling
            $user_max = $config['validation']['max_file_size_mb'] ?? 10;
            $clamped_max = is_numeric($user_max)
                ? min(max((float) $user_max, 0.1), MAX_FILE_SIZE_MB_LIMIT)
                : 10;

            $normalized['validation'] = [
                'max_file_size_mb'    => $clamped_max,
                'allowed_extensions'  => $safe_allowed_extensions,
            ];
            break;

        case 'heading':
            unset($normalized['required']);
            break;

        case 'paragraph':
            $normalized['label'] = '';
            unset($normalized['required']);
            break;

        case 'divider':
            $normalized = [
                'label' => '',
                'description' => '',
                'validation' => [],
            ];
            break;
    }

    // --- SECURITY: Whitelist-based merge ---
    // Only copy keys that are explicitly allowed for this question type.
    // Validation sub-keys are already built above from whitelisted fields;
    // we do NOT re-merge raw user validation on top.
    $top_level_whitelist = allowed_config_keys()[$type] ?? [];

    foreach ($top_level_whitelist as $key) {
        // Skip keys already fully handled above
        if (in_array($key, ['validation', 'options', 'visibility', 'scoring', 'label', 'description'])) {
            continue;
        }
        if (array_key_exists($key, $config)) {
            $normalized[$key] = $config[$key];
        }
    }

    // Add visibility config if present and allowed
    if (in_array('visibility', $top_level_whitelist) && isset($config['visibility'])) {
        $normalized['visibility'] = $config['visibility'];
    }

    // Preserve scoring config if present and allowed
    if (in_array('scoring', $top_level_whitelist) && isset($config['scoring'])) {
        $normalized['scoring'] = $config['scoring'];
    }

    return $normalized;
}
