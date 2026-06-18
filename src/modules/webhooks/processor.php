<?php

/**
 * Webhooks Module - Processing Logic
 *
 * Handles webhook queueing, firing, and payload building
 */

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../forms/models.php';

/**
 * Trigger webhook for a form submission (main entry point)
 * 
 * Called after a form submission is created. Checks if form has an active webhook,
 * builds payload, and queues it for asynchronous delivery.
 * 
 * @param int $form_id Form ID
 * @param int $submission_id Submission ID
 * @return bool True if webhook was queued, false if no webhook exists
 */
function trigger_webhook_for_form(int $form_id, int $submission_id): bool {
    // Get webhook associated with this form
    $webhook = get_webhook_for_form($form_id);
    
    // No webhook attached or webhook is inactive
    if (!$webhook || !$webhook['is_active']) {
        return false;
    }
    
    // Get submission data
    $submission = get_webhook_submission($submission_id);
    if (!$submission) {
        error_log("Webhook trigger failed: Submission {$submission_id} not found");
        return false;
    }
    
    // Get form data
    $form = get_form_by_id($form_id);
    if (!$form) {
        error_log("Webhook trigger failed: Form {$form_id} not found");
        return false;
    }

    // Load questions from the version captured at submission time
    $questions = [];
    if (!empty($submission['form_version_id'])) {
        $questions = get_questions((int)$submission['form_version_id']);
    }
    
    // Build webhook payload
    $payload = build_webhook_payload($submission, $form, $webhook, $questions);
    
    // Queue the webhook for async delivery
    return queue_webhook($webhook['id'], $submission_id, $payload);
}

/**
 * Queue a webhook for asynchronous delivery
 *
 * Creates a pending entry in webhook_log table. The cron worker will pick it up.
 *
 * @param int $webhook_id Webhook ID
 * @param int $submission_id Submission ID
 * @param array<string, mixed> $payload Webhook payload data
 * @return bool Success status
 */
function queue_webhook(int $webhook_id, int $submission_id, array $payload): bool {
    try {
        $stmt = db()->prepare("
            INSERT INTO webhook_log (webhook_id, submission_id, request_payload, status, attempts, created_at)
            VALUES (?, ?, ?, 'pending', 0, NOW())
        ");
        
        return $stmt->execute([
            $webhook_id,
            $submission_id,
            json_encode($payload)
        ]);
    } catch (Exception $e) {
        error_log("Failed to queue webhook {$webhook_id} for submission {$submission_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Build webhook payload from submission data
 *
 * Creates standardized JSON payload with form, submission, and user data.
 * Respects the include_user_data setting for privacy.
 * Answer keys are mapped to question labels (from config) instead of IDs.
 *
 * @param array<string, mixed> $submission Submission data with user info
 * @param array<string, mixed> $form Form data
 * @param array<string, mixed> $webhook Webhook configuration
 * @param array<int, array<string, mixed>> $questions Questions for this form version
 * @return array<string, mixed> Payload structure
 */
function build_webhook_payload(array $submission, array $form, array $webhook, array $questions = []): array {
    // Build question ID → label map (skip display-only types which have no answers)
    $display_only = ['heading', 'paragraph', 'divider'];
    $label_map = [];
    foreach ($questions as $q) {
        if (!in_array($q['type'], $display_only)) {
            $label = trim($q['config']['label'] ?? '');
            $label_map[(string)$q['id']] = $label !== '' ? $label : 'question_' . $q['id'];
        }
    }

    // Remap answer keys from question IDs to question labels
    $raw_answers = json_decode($submission['answers'] ?? '{}', true) ?? [];
    $answers = [];
    foreach ($raw_answers as $question_id => $value) {
        $key = $label_map[(string)$question_id] ?? 'question_' . $question_id;
        $answers[$key] = $value;
    }

    // Base payload structure
    $payload = [
        'event' => 'submitted',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'webhook_name' => $webhook['name'],
        'form' => [
            'uuid' => $form['uuid'],
            'name' => $form['name']
        ],
        'submission' => [
            'uuid' => $submission['uuid'],
            'submitted_at' => $submission['submitted_at'],
            'user' => [
                'uuid' => $submission['user_uuid']
            ],
            'answers' => $answers
        ]
    ];
    
    // Include full user data if configured
    if ($webhook['include_user_data']) {
        $payload['submission']['user']['name'] = $submission['user_name'] ?? '';
        $payload['submission']['user']['email'] = $submission['user_email'] ?? '';
    }
    
    return $payload;
}

/**
 * Fire a webhook (make HTTP POST request)
 *
 * Called by the cron worker to deliver queued webhooks.
 * Makes HTTP POST request with Bearer token authentication.
 *
 * @param array<string, mixed> $webhook_log_entry Webhook log entry from database
 * @return array<string, mixed> Result with success status, HTTP code, and response
 */
function fire_webhook(array $webhook_log_entry): array {
    // Get webhook details
    $webhook = get_webhook($webhook_log_entry['webhook_id'], true);
    
    if (!$webhook) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'Webhook not found'
        ];
    }
    
    // Check if webhook is still active
    if (!$webhook['is_active']) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'Webhook is inactive'
        ];
    }
    
    // Prepare HTTP request
    $url = $webhook['url'];
    $payload = $webhook_log_entry['request_payload'];
    $bearer_token = $webhook['bearer_token'];

    require_once __DIR__ . '/../../core/env.php';
    $config = load_config();
    $is_dev = ($config['app']['environment'] ?? 'production') === 'development';

    // Runtime SSRF protection: re-resolve the hostname immediately before firing
    // to defend against DNS rebinding attacks (where validation-time DNS differs
    // from request-time DNS). Pin cURL to the validated IP via CURLOPT_RESOLVE.
    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https://') ? 443 : 80);
    $ips  = resolve_host_ips(strtolower(trim($host, '[]')));

    $pinned_ip = null;
    foreach ($ips as $ip) {
        if (!is_safe_webhook_ip($ip)) {
            return [
                'success' => false,
                'status'  => 0,
                'error'   => 'Webhook URL resolves to a private or reserved IP address',
                'body'    => ''
            ];
        }
        if ($pinned_ip === null) {
            $pinned_ip = $ip;
        }
    }

    if ($pinned_ip === null) {
        return [
            'success' => false,
            'status'  => 0,
            'error'   => 'Could not resolve webhook URL hostname',
            'body'    => ''
        ];
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bearer_token,
            'User-Agent: ' . ($config['app']['name'] ?? 'FORMNA') . '-Webhook/1.0'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => !$is_dev,
        CURLOPT_SSL_VERIFYHOST => $is_dev ? 0 : 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned_ip}"],
    ]);
    
    // Execute request
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    // Check for cURL errors
    if ($response_body === false) {
        return [
            'success' => false,
            'status' => 0,
            'error' => 'cURL error: ' . $curl_error,
            'body' => ''
        ];
    }
    
    // Consider 2xx status codes as success
    $is_success = $http_code >= 200 && $http_code < 300;
    
    return [
        'success' => $is_success,
        'status' => $http_code,
        'body' => substr($response_body, 0, 5000), // Limit response body to 5KB
        'error' => $is_success ? '' : "HTTP {$http_code}"
    ];
}

/**
 * Get submission with user details for webhook payload
 *
 * @param int $submission_id Submission ID
 * @return array{id: int, uuid: string, form_id: int, form_version_id: int, user_id: int, answers: string, submitted_at: string, user_uuid: string, user_name: string, user_email: string}|null
 */
function get_webhook_submission(int $submission_id): ?array {
    $stmt = db()->prepare("
        SELECT 
            s.*,
            u.uuid as user_uuid,
            u.name as user_name,
            u.email as user_email
        FROM submissions s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    
    $stmt->execute([$submission_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

