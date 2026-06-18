<?php

/**
 * Audit helpers - utility functions for audit logging
 */

/**
 * Fetch entity UUID given entity type and ID
 * This helper fetches the UUID from the appropriate table based on entity type
 *
 * @param string $entity_type The entity type (user, form, submission, etc.)
 * @param int $entity_id The entity ID
 * @return string|null The entity UUID or null if not found
 */
function get_entity_uuid(string $entity_type, int $entity_id): ?string {
    $table_map = [
        'user' => 'users',
        'form' => 'forms',
        'submission' => 'submissions',
        'clarification_request' => 'clarification_requests',
        'program' => 'programs',
        'program_submission' => 'program_submissions',
    ];
    
    $table = $table_map[$entity_type] ?? null;
    if (!$table) {
        return null;
    }
    
    $row = db_one("SELECT uuid FROM {$table} WHERE id = ?", [$entity_id]);
    return $row['uuid'] ?? null;
}

/**
 * Log audit with automatic UUID lookup
 * Wrapper around log_audit that automatically fetches the entity UUID
 *
 * @param string $action
 * @param string $entity_type
 * @param int $entity_id
 * @param array<string, mixed>|null $details
 * @param string|null $entity_uuid If provided, use this; otherwise auto-fetch
 */
function log_audit_auto(string $action, string $entity_type, int $entity_id, ?array $details = null, ?string $entity_uuid = null): void {
    if ($entity_uuid === null) {
        $entity_uuid = get_entity_uuid($entity_type, $entity_id);
    }
    
    log_audit($action, $entity_type, $entity_id, $details, $entity_uuid);
}
