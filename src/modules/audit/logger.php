<?php

/**
 * Valid entity types for audit log
 */
const AUDIT_VALID_ENTITY_TYPES = [
    'ai_summary',
    'clarification_request',
    'email_template',
    'form',
    'form_version',
    'languages',
    'program',
    'program_submission',
    'program_submission_draft',
    'question',
    'questions',
    'site_settings',
    'submission',
    'user',
];

/**
 * Log audit trail event
 *
 * @param array<string, mixed>|null $details
 */
function log_audit(string $action, string $entity_type, int $entity_id, ?array $details, ?string $entity_uuid): void {
    // Validate action format: lowercase letters, underscores, 3-64 chars
    if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $action)) {
        error_log("log_audit: invalid action '{$action}' — skipped");
        return;
    }

    // Validate entity type against allowlist
    if (!in_array($entity_type, AUDIT_VALID_ENTITY_TYPES, true)) {
        error_log("log_audit: unknown entity_type '{$entity_type}' — skipped");
        return;
    }

    $user = current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    db_insert('audit_log', [
        'user_id' => $user['id'] ?? null,
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'entity_uuid' => $entity_uuid,
        'details' => $details ? json_encode($details) : null,
        'ip' => $ip,
        'created_at' => now(),
    ]);
}
