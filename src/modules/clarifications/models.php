<?php

/**
 * Clarification & Rectification Models
 * 
 * Handles admin requests for submission clarifications and user responses
 */

/**
 * Validate and normalize a single rectification response value based on question type.
 *
 * Returns an array with 'valid' (bool), 'value' (cleaned value on success),
 * and 'error' (string description on failure).
 *
 * @param mixed $value The raw response value
 * @param string $q_type The question type (e.g. 'text', 'select', 'number')
 * @param array<string, mixed> $config The question config (must include 'options' for select types)
 * @return array{valid: bool, value?: mixed, error?: string}
 */
function validate_rectification_value(mixed $value, string $q_type, array $config): array {
    $label = $config['label'] ?? 'Field';

    if (in_array($q_type, ['checkbox_group', 'multiselect'], true)) {
        $values = is_array($value) ? $value : [$value];
        $values = array_map('trim', $values);
        $values = array_filter($values, fn($v) => $v !== '');

        if (empty($values)) {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }

        $options = $config['options'] ?? [];
        $valid_values = array_map(fn($o) => (string)$o['value'], $options);
        foreach ($values as $val) {
            if (!in_array((string)$val, $valid_values, true)) {
                return ['valid' => false, 'error' => "\"$label\" contains an invalid selection."];
            }
        }

        return ['valid' => true, 'value' => array_values($values)];
    }

    if (in_array($q_type, ['select', 'radio'], true)) {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }

        $options = $config['options'] ?? [];
        $valid_values = array_map(fn($o) => (string)$o['value'], $options);
        if (!in_array((string)$value, $valid_values, true)) {
            return ['valid' => false, 'error' => "\"$label\" contains an invalid selection."];
        }

        return ['valid' => true, 'value' => $value];
    }

    if ($q_type === 'number') {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => "\"$label\" must be a valid number."];
        }
        return ['valid' => true, 'value' => $value];
    }

    if ($q_type === 'email') {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => "\"$label\" must be a valid email address."];
        }
        return ['valid' => true, 'value' => $value];
    }

    if ($q_type === 'url') {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => "\"$label\" must be a valid URL."];
        }
        return ['valid' => true, 'value' => $value];
    }

    if ($q_type === 'date') {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return ['valid' => false, 'error' => "\"$label\" must be a valid date."];
        }
        return ['valid' => true, 'value' => $value];
    }

    if ($q_type === 'datetime') {
        $value = trim((string)$value);
        if ($value === '') {
            return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
        }
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
        if (!$dt || $dt->format('Y-m-d\TH:i') !== $value) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $value);
            if (!$dt || $dt->format('Y-m-d\TH:i:s') !== $value) {
                return ['valid' => false, 'error' => "\"$label\" must be a valid date and time."];
            }
        }
        return ['valid' => true, 'value' => $value];
    }

    // text / textarea / fallback
    $value = trim((string)$value);
    if ($value === '') {
        return ['valid' => false, 'error' => "Please provide a response for \"$label\"."];
    }
    return ['valid' => true, 'value' => $value];
}

/**
 * Create a new clarification request
 * @param array<array{question_id: int, type: string, reason: string}> $items
 */
function create_clarification(int $submission_id, int $requested_by, string $message, array $items): int {
    return db_transaction(function() use ($submission_id, $requested_by, $message, $items) {
        // Check if there's already an open clarification to prevent race condition duplicates
        $existing = db_one(
            "SELECT id FROM clarification_requests WHERE submission_id = ? AND status = 'open' FOR UPDATE",
            [$submission_id]
        );
        
        if ($existing) {
            throw new Exception('An open clarification request already exists for this submission.');
        }
        
        $id = db_insert('clarification_requests', [
            'uuid' => uuid(),
            'submission_id' => $submission_id,
            'requested_by' => $requested_by,
            'status' => 'open',
            'message' => $message,
            'items' => json_encode($items),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Update submission status
        db_exec(
            "UPDATE submissions SET status = 'clarification_requested', updated_at = NOW() WHERE id = ?",
            [$submission_id]
        );
        
        return $id;
    });
}

/**
 * Get a clarification request by ID
 * @return array<string, mixed>|null
 */
function get_clarification(int $id): ?array {
    $clarification = db_one("SELECT * FROM clarification_requests WHERE id = ?", [$id]);
    
    if ($clarification) {
        decode_json_fields($clarification, ['items']);
    }
    
    return $clarification;
}

/**
 * Get a clarification request by UUID
 * @return array<string, mixed>|null
 */
function get_clarification_by_uuid(string $uuid): ?array {
    $clarification = db_one("SELECT * FROM clarification_requests WHERE uuid = ?", [$uuid]);
    
    if ($clarification) {
        decode_json_fields($clarification, ['items']);
    }
    
    return $clarification;
}

/**
 * Get all clarification requests for a submission
 * @return array<array<string, mixed>>
 */
function get_clarifications_for_submission(int $submission_id): array {
    $clarifications = db_query(
        "SELECT cr.*, u.name as requester_name 
         FROM clarification_requests cr
         LEFT JOIN users u ON cr.requested_by = u.id
         WHERE cr.submission_id = ?
         ORDER BY cr.created_at DESC",
        [$submission_id]
    );
    
    foreach ($clarifications as &$clarification) {
        decode_json_fields($clarification, ['items']);
    }
    
    return $clarifications;
}

/**
 * Get pending clarification requests for a user
 * @return array<array<string, mixed>>
 */
function get_pending_clarifications(int $user_id): array {
    $clarifications = db_query(
        "SELECT cr.*, 
                s.uuid as submission_uuid,
                f.name as form_name,
                f.uuid as form_uuid,
                u.name as requester_name
         FROM clarification_requests cr
         INNER JOIN submissions s ON cr.submission_id = s.id
         INNER JOIN forms f ON s.form_id = f.id
         LEFT JOIN users u ON cr.requested_by = u.id
         WHERE s.user_id = ? AND cr.status IN ('open', 'responded')
         ORDER BY cr.created_at DESC",
        [$user_id]
    );
    
    foreach ($clarifications as &$clarification) {
        decode_json_fields($clarification, ['items']);
    }
    
    return $clarifications;
}

/**
 * Respond to a clarification item
 * @param array<int, array<string, mixed>> $questions Question data indexed by ID (for type-aware storage)
 */
function respond_to_item(int $clarification_id, int $question_id, mixed $response, array $questions = []): bool {
    return db_transaction(function() use ($clarification_id, $question_id, $response, $questions) {
        // Lock the clarification row to prevent race conditions
        $clarification = db_one(
            "SELECT * FROM clarification_requests WHERE id = ? FOR UPDATE",
            [$clarification_id]
        );
        
        if (!$clarification) {
            return false;
        }
        
        decode_json_fields($clarification, ['items']);
        $items = $clarification['items'];
        $all_responded = true;
        
        // Update the specific item
        foreach ($items as &$item) {
            if ($item['question_id'] == $question_id) {
                $response_value = $response;
                $item['status'] = 'responded';
                $item['responded_at'] = now();

                if ($item['type'] === 'rectification') {
                    $question = $questions[$question_id] ?? null;
                    if (!$question) {
                        return false;
                    }

                    $result = validate_rectification_value($response_value, $question['type'] ?? 'text', $question['config'] ?? []);
                    if (!$result['valid']) {
                        return false;
                    }
                    $response_value = $result['value'];

                    // Convert datetime values from user's local timezone to UTC
                    if (($question['type'] ?? '') === 'datetime') {
                        $utc = from_datetime_local($response_value);
                        if ($utc !== null) {
                            $response_value = $utc;
                        }
                    }
                }

                $item['response'] = $response_value;
            }
            
            // Check if all items have been responded to
            if ($item['status'] !== 'responded') {
                $all_responded = false;
            }
        }
        
        // Update the clarification request
        $new_status = $all_responded ? 'responded' : 'open';
        
        db_update('clarification_requests', [
            'items' => json_encode($items),
            'status' => $new_status,
            'updated_at' => now()
        ], 'id = ?', [$clarification_id]);
        
        // If all items responded, update submission status back to submitted
        if ($all_responded) {
            db_exec(
                "UPDATE submissions SET status = 'submitted', updated_at = NOW()
                 WHERE id = ?",
                [$clarification['submission_id']]
            );
        }
        
        return true;
    });
}

/**
 * Respond to all items in a clarification request at once
 * @param array<string, string|array<int, string>> $responses
 * @param array<int, array<string, mixed>> $questions Question data indexed by ID (for type-aware storage)
 */
function respond_to_clarification(int $clarification_id, array $responses, array $questions = []): bool {
    return db_transaction(function() use ($clarification_id, $responses, $questions) {
        // Lock the clarification row to prevent race conditions
        $clarification = db_one(
            "SELECT * FROM clarification_requests WHERE id = ? FOR UPDATE",
            [$clarification_id]
        );
        
        if (!$clarification) {
            return false;
        }
        
        decode_json_fields($clarification, ['items']);
        $items = $clarification['items'];
        
        // Update all items with responses
        foreach ($items as &$item) {
            if (isset($responses['q_' . $item['question_id']])) {
                $response_value = $responses['q_' . $item['question_id']];
                $item['status'] = 'responded';
                $item['responded_at'] = now();

                if ($item['type'] === 'rectification') {
                    $question = $questions[$item['question_id']] ?? null;
                    if (!$question) {
                        return false;
                    }

                    $result = validate_rectification_value($response_value, $question['type'] ?? 'text', $question['config'] ?? []);
                    if (!$result['valid']) {
                        return false;
                    }
                    $response_value = $result['value'];

                    // Convert datetime values from user's local timezone to UTC
                    if (($question['type'] ?? '') === 'datetime') {
                        $utc = from_datetime_local($response_value);
                        if ($utc !== null) {
                            $response_value = $utc;
                        }
                    }
                }

                $item['response'] = $response_value;
            }
        }
        
        // Update the clarification request
        db_update('clarification_requests', [
            'items' => json_encode($items),
            'status' => 'responded',
            'updated_at' => now()
        ], 'id = ?', [$clarification_id]);
        
        // Update submission status to submitted (but don't apply rectification answers yet)
        db_update('submissions', [
            'status' => 'submitted',
            'updated_at' => now()
        ], 'id = ?', [$clarification['submission_id']]);
        
        return true;
    });
}

/**
 * Resolve a clarification request (admin marks as resolved)
 * For rectifications, this applies the corrected answers to the submission
 * and stores the original value in the clarification item for audit display.
 */
function resolve_clarification(int $id): bool {
    return db_transaction(function() use ($id) {
        $clarification = db_one(
            "SELECT * FROM clarification_requests WHERE id = ? FOR UPDATE",
            [$id]
        );
        
        if (!$clarification) {
            return false;
        }
        
        decode_json_fields($clarification, ['items']);
        
        // Apply rectification answers to the submission
        $submission = db_one(
            "SELECT * FROM submissions WHERE id = ? FOR UPDATE",
            [$clarification['submission_id']]
        );
        
        if ($submission) {
            decode_json_fields($submission, ['answers']);
            $answers = $submission['answers'];
            $modified = false;
            
            foreach ($clarification['items'] as &$item) {
                if ($item['type'] === 'rectification' && isset($item['response'])) {
                    $original = $answers[$item['question_id']] ?? null;
                    $item['original_value'] = $original;
                    $answers[$item['question_id']] = $item['response'];
                    $modified = true;
                    
                    $q_ref = db_one('SELECT uid FROM questions WHERE id = ?', [$item['question_id']]);
                    log_audit('rectification_applied', 'submission', $clarification['submission_id'], [
                        'clarification_uuid' => $clarification['uuid'],
                        'question_id' => $item['question_id'],
                        'question_uid' => $q_ref['uid'] ?? null,
                        'original_value' => $original,
                        'new_value' => $item['response']
                    ], $submission['uuid']);
                }
            }
            unset($item);
            
            if ($modified) {
                db_update('submissions', [
                    'answers' => json_encode($answers),
                    'updated_at' => now()
                ], 'id = ?', [$clarification['submission_id']]);
            }
        }
        
        db_update('clarification_requests', [
            'status' => 'resolved',
            'items' => json_encode($clarification['items']),
            'updated_at' => now()
        ], 'id = ?', [$id]);
        
        return true;
    });
}

/**
 * Reject a clarification response (admin sends it back for revision)
 * Resets status to 'open' so the user can revise their responses.
 */
function reject_clarification(int $id, string $feedback): bool {
    return db_transaction(function() use ($id, $feedback) {
        $clarification = db_one(
            "SELECT * FROM clarification_requests WHERE id = ? FOR UPDATE",
            [$id]
        );

        if (!$clarification) {
            return false;
        }

        // Reset all item statuses back to 'open' so the user can revise
        decode_json_fields($clarification, ['items']);
        $items = $clarification['items'];
        foreach ($items as &$item) {
            $item['status'] = 'open';
        }

        db_update('clarification_requests', [
            'status' => 'open',
            'admin_feedback' => $feedback,
            'items' => json_encode($items),
            'updated_at' => now()
        ], 'id = ?', [$id]);

        // Set submission status back to clarification_requested
        db_exec(
            "UPDATE submissions SET status = 'clarification_requested', updated_at = NOW() WHERE id = ?",
            [$clarification['submission_id']]
        );

        return true;
    });
}

/**
 * Cancel (hard-delete) a clarification request.
 * Reverts the submission status to 'submitted' if it was in 'clarification_requested'.
 */
function cancel_clarification(int $id): bool {
    return db_transaction(function() use ($id) {
        $clarification = db_one(
            "SELECT * FROM clarification_requests WHERE id = ? FOR UPDATE",
            [$id]
        );

        if (!$clarification) {
            return false;
        }

        // Get submission to find the user_id for notification cleanup
        $submission = db_one(
            "SELECT user_id FROM submissions WHERE id = ?",
            [$clarification['submission_id']]
        );

        // Revert submission status if it was set to clarification_requested
        db_exec(
            "UPDATE submissions SET status = 'submitted', updated_at = NOW()
             WHERE id = ? AND status = 'clarification_requested'",
            [$clarification['submission_id']]
        );

        // Delete associated notification for the user
        if ($submission) {
            db_exec(
                "DELETE FROM notifications 
                 WHERE user_id = ? 
                 AND type = 'clarification_request' 
                 AND JSON_EXTRACT(data, '$.clarification_link') LIKE ?",
                [$submission['user_id'], '%/requests/' . $clarification['uuid'] . '%']
            );
        }

        // Hard-delete the clarification request
        db_exec("DELETE FROM clarification_requests WHERE id = ?", [$id]);

        return true;
    });
}

/**
 * Get statistics about clarification requests
 * @return array<string, mixed>|null
 */
function get_clarification_stats(?int $submission_id = null): ?array {
    if ($submission_id) {
        $stats = db_one("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM clarification_requests
            WHERE submission_id = ?
        ", [$submission_id]);
    } else {
        $stats = db_one("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM clarification_requests
        ");
    }
    
    return $stats;
}

/**
 * Get question details for clarification items
 * @return array<int, array<string, mixed>>
 */
function get_clarification_questions(int $clarification_id): array {
    $clarification = get_clarification($clarification_id);
    
    if (!$clarification || empty($clarification['items'])) {
        return [];
    }
    
    $question_ids = array_column($clarification['items'], 'question_id');
    
    if (empty($question_ids)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    
    $questions = db_query(
        "SELECT * FROM questions WHERE id IN ($placeholders)",
        $question_ids
    );
    
    // Decode config for each question
    foreach ($questions as &$question) {
        decode_json_fields($question, ['config']);
    }
    unset($question); // Break the reference to prevent issues in subsequent loops
    
    // Index by question ID for easy lookup
    $indexed = [];
    foreach ($questions as $question) {
        $indexed[$question['id']] = $question;
    }
    
    return $indexed;
}
