<?php

/**
 * AI Module — Controllers
 *
 * Admin settings page (super_admin only) and JSON API endpoints
 * for generating/fetching AI summaries (admin/reviewer).
 */

require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../../core/llm.php';
require_once __DIR__ . '/../audit/logger.php';
require_once __DIR__ . '/../programs/models.php';

/** Rate limit: max AI summarize requests per user per 10-minute window */
const AI_SUMMARIZE_MAX_PER_WINDOW = 10;
const AI_SUMMARIZE_WINDOW_MINUTES = 10;

// ============================================================================
// ADMIN SETTINGS PAGE (super_admin only)
// ============================================================================

/**
 * GET /admin/ai-settings — Show AI configuration page
 */
function ai_settings_page(): void
{
    require_auth();
    require_role('super_admin');

    $settings = get_ai_settings();

    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'AI Settings'],
    ];

    ob_start();
    require __DIR__ . '/views/settings.php';
    $content = ob_get_clean();

    require __DIR__ . '/../../layouts/admin.php';
}

/**
 * POST /admin/ai-settings — Save AI configuration
 */
function ai_settings_save(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    $user = current_user();

    $data = [
        'enabled'    => isset($_POST['ai_enabled']),
        'provider'   => trim($_POST['ai_provider'] ?? 'openrouter'),
        'base_url'   => trim($_POST['ai_base_url'] ?? ''),
        'api_key'    => $_POST['ai_api_key'] ?? '',
        'model'      => trim($_POST['ai_model'] ?? ''),
        'max_tokens' => $_POST['ai_max_tokens'] ?? '',
        'site_url'   => trim($_POST['ai_site_url'] ?? ''),
        'site_name'  => trim($_POST['ai_site_name'] ?? ''),
    ];

    // Validate base_url format if provided
    if ($data['base_url'] !== '' && !filter_var($data['base_url'], FILTER_VALIDATE_URL)) {
        // Allow localhost URLs for Ollama (not caught by FILTER_VALIDATE_URL ports)
        if (!preg_match('#^https?://[a-zA-Z0-9.\-]+(:\d+)?(/.*)?$#', $data['base_url'])) {
            flash('error', 'Base URL must be a valid HTTP/HTTPS URL.');
            redirect('/admin/ai-settings');
        }
    }

    // Handle "clear API key" action
    if (isset($_POST['clear_api_key'])) {
        clear_ai_api_key($user['id']);
        log_audit('ai_api_key_cleared', 'site_settings', 1, null, null);
        flash('success', 'API key cleared.');
        redirect('/admin/ai-settings');
    }

    save_ai_settings($data, $user['id']);

    log_audit('ai_settings_changed', 'site_settings', 1, [
        'provider'   => $data['provider'],
        'model'      => $data['model'],
        'enabled'    => $data['enabled'],
        'changed_by' => $user['uuid'],
    ], null);

    flash('success', 'AI settings saved.');
    redirect('/admin/ai-settings');
}

/**
 * POST /admin/ai-settings/test — Test LLM connection
 * Returns JSON.
 */
function ai_settings_test(): void
{
    require_auth();
    require_role('super_admin');
    csrf_check();

    // Read values from request body so the user can test before saving.
    // Falls back to saved DB values for fields not provided (e.g. API key).
    // Ensure $input is an array (json_input returns null on empty/invalid body).
    $input = json_input() ?? [];
    $saved = get_ai_settings();

    $provider  = trim($input['provider'] ?? $saved['provider']);
    $base_url  = trim($input['base_url'] ?? $saved['base_url']);
    $model     = trim($input['model'] ?? $saved['model']);
    $site_url  = trim($input['site_url'] ?? $saved['site_url']);
    $site_name = trim($input['site_name'] ?? $saved['site_name']);

    // API key: use the provided one if non-empty, otherwise fall back to saved
    $api_key = trim($input['api_key'] ?? '');
    if ($api_key === '') {
        $api_key = trim($saved['api_key']);
    }

    if ($base_url === '') {
        json_response(['success' => false, 'error' => 'Base URL is not configured.'], 400);
    }

    if ($model === '') {
        json_response(['success' => false, 'error' => 'Model is not configured.'], 400);
    }

    // Ollama may not need a key; others do
    if ($api_key === '' && $provider !== 'ollama') {
        json_response(['success' => false, 'error' => 'API key is not configured.'], 400);
    }

    // Build a settings-like array for build_llm_options
    $test_settings = [
        'provider'  => $provider,
        'base_url'  => $base_url,
        'api_key'   => $api_key,
        'model'     => $model,
        'site_url'  => $site_url,
        'site_name' => $site_name,
    ];

    // Send a minimal test message
    $messages = [
        ['role' => 'user', 'content' => 'Respond with exactly: Connection successful.'],
    ];

    $options = build_llm_options($test_settings);
    $options['max_tokens'] = 150;
    $options['timeout'] = 30;

    $result = llm_chat($base_url, $api_key, $model, $messages, $options);

    if ($result['success']) {
        json_response([
            'success' => true,
            'message' => 'Connection successful.',
            'model'   => $result['model'] ?? $model,
        ]);
    } else {
        json_response(['success' => false, 'error' => $result['error']], 200);
    }
}

// ============================================================================
// API ENDPOINTS (admin/reviewer)
// ============================================================================

/** Supported entity types for AI summaries */
const AI_SUPPORTED_ENTITY_TYPES = ['program_submission', 'submission'];

/**
 * POST /api/admin/ai/summarize — Generate (or regenerate) a summary
 *
 * Expects JSON body: { "entity_type": "program_submission"|"submission", "entity_uuid": "..." }
 *
 * Security:
 * - Requires reviewer+ role
 * - AI must be enabled
 * - Rate-limited per user
 * - CSRF via X-CSRF-Token header
 *
 * Race condition mitigation:
 * - Uses SELECT ... FOR UPDATE inside a transaction to serialize concurrent
 *   requests. The first request to acquire the lock writes a placeholder row
 *   (empty summary) into ai_summaries, claiming generation. Subsequent
 *   requests see the placeholder and return 409 instead of calling the LLM.
 *   On failure the placeholder is deleted; on success it is overwritten via
 *   upsert. Placeholders older than 2 minutes are considered stale and may
 *   be reclaimed. The per-entity 60-second cooldown further limits
 *   regeneration spam.
 */
function api_ai_summarize(): void
{
    require_auth();
    require_role('reviewer');
    csrf_check();

    $user = current_user();

    // ── Check AI enabled ─────────────────────────────────────────────────
    if (!is_ai_enabled()) {
        json_response(['success' => false, 'error' => 'AI features are disabled.'], 403);
    }

    // ── Parse input ──────────────────────────────────────────────────────
    $input = json_input() ?? [];
    $entity_type = $input['entity_type'] ?? '';
    $entity_uuid = $input['entity_uuid'] ?? '';
    $regenerate  = !empty($input['regenerate']);

    if (!in_array($entity_type, AI_SUPPORTED_ENTITY_TYPES, true) || $entity_uuid === '') {
        json_response(['success' => false, 'error' => 'Invalid request.'], 400);
    }

    // ── Rate limit (check only — recording deferred until after authorization) ──
    $rl = check_user_action_rate_limit(
        $user['id'],
        'ai_summarize',
        AI_SUMMARIZE_MAX_PER_WINDOW,
        AI_SUMMARIZE_WINDOW_MINUTES,
        false
    );
    if ($rl) {
        json_response(['success' => false, 'error' => $rl], 429);
    }

    // ── Load entity based on type ────────────────────────────────────────
    $entity = null;
    $lock_table = '';
    $prompt_builder = '';

    if ($entity_type === 'program_submission') {
        $entity = get_program_submission_with_details($entity_uuid);
        $lock_table = 'program_submissions';
        $prompt_builder = 'build_summary_prompt';
    } elseif ($entity_type === 'submission') {
        $entity = get_submission_with_details($entity_uuid);
        $lock_table = 'submissions';
        $prompt_builder = 'build_submission_summary_prompt';
    }

    if (!$entity) {
        json_response(['success' => false, 'error' => 'Submission not found.'], 404);
    }

    // ── Verify reviewer is authorized for this specific entity ───────────
    if (!in_array($user['role'], ['admin', 'super_admin'])) {
        if ($entity_type === 'program_submission') {
            $currentStage = null;
            foreach ($entity['program']['review_stages'] as $stage) {
                if ((int)$stage['order'] === (int)$entity['current_stage']) {
                    $currentStage = $stage;
                    break;
                }
            }
            if (!$currentStage || (int)$currentStage['reviewer_id'] !== (int)$user['id']) {
                json_response(['success' => false, 'error' => 'Access denied.'], 403);
            }
        } elseif ($entity_type === 'submission') {
            if (!has_form_permission($entity['form']['id'], 'view_results')) {
                json_response(['success' => false, 'error' => 'Access denied.'], 403);
            }
        }
    }

    // ── Record rate limit now that authorization has passed ───────────────
    record_user_action_attempt($user['id'], 'ai_summarize');

    // ── Per-entity regeneration cooldown ─────────────────────────────────
    // Check before the transaction so we never write a placeholder for a
    // request that would be rejected by the cooldown anyway.
    if ($regenerate) {
        $recent = get_ai_summary($entity_type, $entity['id']);
        if ($recent && $recent['summary'] !== '' && (time() - strtotime($recent['created_at'])) < 60) {
            json_response(['success' => false, 'error' => 'Please wait before regenerating this summary.'], 429);
        }
    }

    // ── Serialize concurrent requests for same entity ────────────────────
    // Lock the entity row inside a transaction and insert a placeholder
    // (empty summary) to claim generation. This prevents a second concurrent
    // request from also calling the LLM. The placeholder is cleaned up on
    // failure; on success it is overwritten by the real summary via upsert.
    $needs_generation = true;
    $existing_summary = null;
    $generation_in_progress = false;

    db_transaction(function () use ($entity, $entity_type, $lock_table, $regenerate, $user, &$needs_generation, &$existing_summary, &$generation_in_progress) {
        $locked = db_one(
            "SELECT id FROM {$lock_table} WHERE id = ? FOR UPDATE",
            [$entity['id']]
        );
        if (!$locked) {
            $needs_generation = false;
            return;
        }

        $existing = get_ai_summary($entity_type, $entity['id']);

        // Check for an in-flight placeholder left by another request
        if ($existing && $existing['summary'] === '') {
            if ((time() - strtotime($existing['created_at'])) < 120) {
                $needs_generation = false;
                $generation_in_progress = true;
                return;
            }
            // Stale placeholder (>2 min) — reclaim it below
        }

        if (!$regenerate && $existing && $existing['summary'] !== '') {
            // A real summary already exists — return it
            $needs_generation = false;
            $existing_summary = $existing;
            return;
        }

        // Claim generation by writing a placeholder (empty summary).
        // For first-time: creates the row. For regeneration: overwrites via upsert.
        save_ai_summary($entity_type, $entity['id'], '', '', null, $user['id']);
    });

    if ($generation_in_progress) {
        json_response(['success' => false, 'error' => 'Summary generation is already in progress. Please wait.'], 409);
    }

    if (!$needs_generation && $existing_summary) {
        json_response([
            'success'       => true,
            'summary'       => $existing_summary['summary'],
            'summary_html'  => render_summary_html($existing_summary['summary']),
            'model'         => $existing_summary['model_used'],
            'tokens_used'   => $existing_summary['tokens_used'],
            'generated_by'  => $existing_summary['generated_by_name'],
            'created_at'    => format_datetime($existing_summary['created_at'], 'short'),
        ]);
    }

    if (!$needs_generation) {
        json_response(['success' => false, 'error' => 'Submission not found.'], 404);
    }

    // ── Build prompt and call LLM ────────────────────────────────────────
    $settings = get_ai_settings();
    if (!$settings['enabled']) {
        delete_ai_summary($entity_type, $entity['id']);
        json_response(['success' => false, 'error' => 'AI features are disabled.'], 403);
    }

    $messages = $prompt_builder($entity);
    $options = build_llm_options($settings);

    $result = llm_chat($settings['base_url'], $settings['api_key'], $settings['model'], $messages, $options);

    if (!$result['success']) {
        delete_ai_summary($entity_type, $entity['id']);
        json_response(['success' => false, 'error' => $result['error']], 502);
    }

    if (mb_strlen($result['content']) > 100000) {
        delete_ai_summary($entity_type, $entity['id']);
        json_response(['success' => false, 'error' => 'Response too large.'], 502);
    }

    // ── Store result ─────────────────────────────────────────────────────
    save_ai_summary(
        $entity_type,
        $entity['id'],
        $result['content'],
        $result['model'] ?? $settings['model'],
        $result['tokens_used'],
        $user['id']
    );

    log_audit('ai_summary_generated', 'ai_summary', $entity['id'], [
        'entity_type' => $entity_type,
        'model'       => $result['model'] ?? $settings['model'],
        'tokens'      => $result['tokens_used'],
    ], $entity['uuid']);

    // ── Return response ──────────────────────────────────────────────────
    json_response([
        'success'       => true,
        'summary'       => $result['content'],
        'summary_html'  => render_summary_html($result['content']),
        'model'         => $result['model'] ?? $settings['model'],
        'tokens_used'   => $result['tokens_used'],
        'generated_by'  => $user['name'],
        'created_at'    => format_datetime(now(), 'short'),
    ]);
}

/**
 * GET /api/admin/ai/summary/{entity_type}/{uuid} — Fetch cached summary for an entity
 *
 * @param string $entity_type 'program_submission' or 'submission'
 * @param string $entity_uuid UUID of the entity
 *
 * Returns JSON. Returns null summary (not an error) if no summary exists yet.
 */
function api_ai_get_summary(string $entity_type, string $entity_uuid): void
{
    require_auth();
    require_role('reviewer');

    $user = current_user();

    // Quick bail if AI is off — don't even query
    if (!is_ai_enabled()) {
        json_response(['success' => true, 'summary' => null]);
    }

    // Validate entity type
    if (!in_array($entity_type, AI_SUPPORTED_ENTITY_TYPES, true)) {
        json_response(['success' => false, 'error' => 'Invalid entity type.'], 400);
    }

    // Load entity and verify reviewer access
    $entity_id = null;

    if ($entity_type === 'program_submission') {
        $ps = get_program_submission_with_details($entity_uuid);
        if (!$ps) {
            json_response(['success' => false, 'error' => 'Submission not found.'], 404);
        }
        $entity_id = $ps['id'];

        if (!in_array($user['role'], ['admin', 'super_admin'])) {
            $currentStage = null;
            foreach ($ps['program']['review_stages'] as $stage) {
                if ((int)$stage['order'] === (int)$ps['current_stage']) {
                    $currentStage = $stage;
                    break;
                }
            }
            if (!$currentStage || (int)$currentStage['reviewer_id'] !== (int)$user['id']) {
                json_response(['success' => false, 'error' => 'Access denied.'], 403);
            }
        }
    } elseif ($entity_type === 'submission') {
        require_once __DIR__ . '/../submissions/models.php';
        $sub = get_submission_by_uuid($entity_uuid);
        if (!$sub) {
            json_response(['success' => false, 'error' => 'Submission not found.'], 404);
        }
        $entity_id = $sub['id'];

        if (!in_array($user['role'], ['admin', 'super_admin']) && !has_form_permission($sub['form_id'], 'view_results')) {
            json_response(['success' => false, 'error' => 'Access denied.'], 403);
        }
    }

    $summary = get_ai_summary($entity_type, $entity_id);

    if (!$summary || $summary['summary'] === '') {
        json_response(['success' => true, 'summary' => null]);
    }

    json_response([
        'success'      => true,
        'summary'      => $summary['summary'],
        'summary_html' => render_summary_html($summary['summary']),
        'model'        => $summary['model_used'],
        'tokens_used'  => $summary['tokens_used'],
        'generated_by' => $summary['generated_by_name'],
        'created_at'   => format_datetime($summary['created_at'], 'short'),
    ]);
}
