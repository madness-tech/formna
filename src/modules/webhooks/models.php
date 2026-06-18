<?php

/**
 * Webhooks Module - Data Access Layer
 * 
 * CRUD operations for webhooks, form associations, and webhook logs
 */

require_once __DIR__ . '/../../core/encryption.php';

/**
 * Create a new webhook
 *
 * @param string $name Webhook name
 * @param string $url HTTPS URL endpoint
 * @param string $bearer_token Bearer token for Authorization header (will be encrypted)
 * @param bool $is_active Whether webhook is active
 * @param bool $include_user_data Whether to include full user data in payload
 * @param int $created_by User ID of creator
 * @return int Webhook ID
 */
function create_webhook(
    string $name,
    string $url,
    string $bearer_token,
    bool $is_active = true,
    bool $include_user_data = true,
    ?int $created_by = null
): int {
    // Encrypt the bearer token before storage
    $encrypted_token = encrypt_data($bearer_token);
    
    $stmt = db()->prepare("
        INSERT INTO webhooks (name, url, bearer_token, is_active, include_user_data, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $name,
        $url,
        $encrypted_token,
        $is_active ? 1 : 0,
        $include_user_data ? 1 : 0,
        $created_by
    ]);
    
    return (int)db()->lastInsertId();
}

/**
 * Update an existing webhook
 *
 * @param int $id Webhook ID
 * @param array<string, mixed> $data Associative array of fields to update
 * @return bool Success status
 */
function update_webhook(int $id, array $data): bool {
    // If bearer_token is being updated, encrypt it
    if (isset($data['bearer_token'])) {
        $data['bearer_token'] = encrypt_data($data['bearer_token']);
    }
    
    $allowed_fields = ['name', 'url', 'bearer_token', 'is_active', 'include_user_data'];
    $update_fields = [];
    $params = [];
    
    $bool_fields = ['is_active', 'include_user_data'];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $update_fields[] = "$key = ?";
            $params[] = in_array($key, $bool_fields) ? (int)(bool)$value : $value;
        }
    }
    
    if (empty($update_fields)) {
        return false;
    }
    
    $update_fields[] = "updated_at = NOW()";
    $params[] = $id;
    
    $sql = "UPDATE webhooks SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = db()->prepare($sql);
    
    return $stmt->execute($params);
}

/**
 * Get a webhook by ID (with decrypted token)
 *
 * @param int $id Webhook ID
 * @param bool $decrypt_token Whether to decrypt the bearer token
 * @return array<string, mixed>|null Webhook data or null if not found
 */
function get_webhook(int $id, bool $decrypt_token = true): ?array {
    $stmt = db()->prepare("
        SELECT * FROM webhooks
        WHERE id = ?
    ");
    
    $stmt->execute([$id]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$webhook) {
        return null;
    }
    
    // Decrypt bearer token if requested
    if ($decrypt_token && $webhook['bearer_token']) {
        try {
            $webhook['bearer_token'] = decrypt_data($webhook['bearer_token']);
        } catch (Exception $e) {
            // If decryption fails, keep encrypted value and log error
            error_log("Failed to decrypt webhook token for ID {$id}: " . $e->getMessage());
        }
    }
    
    return $webhook;
}

/**
 * Get webhooks filtered by status
 *
 * @param string $filter 'active' for non-deleted, 'deleted' for soft-deleted only
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
 */
function get_webhooks(string $filter = 'active', int $page = 1, int $per_page = 20): array {
    $sql = "SELECT * FROM webhooks";
    
    if ($filter === 'deleted') {
        $sql .= " WHERE deleted_at IS NOT NULL";
    } else {
        $sql .= " WHERE deleted_at IS NULL";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    return paginate($sql, [], $page, $per_page);
}

/**
 * Get active webhooks only
 *
 * @return array<int, array<string, mixed>> List of active webhooks
 */
function get_active_webhooks(): array {
    $stmt = db()->query("
        SELECT * FROM webhooks
        WHERE is_active = true
          AND deleted_at IS NULL
        ORDER BY created_at DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Delete a webhook (smart delete: hard delete if never fired, soft delete otherwise)
 * 
 * @param int $id Webhook ID
 * @return bool Success status
 */
function delete_webhook(int $id): bool {
    // Check if webhook has ever been fired
    $stmt = db()->prepare("SELECT COUNT(*) as c FROM webhook_log WHERE webhook_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $log_count = (int)$result['c'];
    
    if ($log_count > 0) {
        // SOFT DELETE: Has history
        $stmt = db()->prepare("
            UPDATE webhooks
            SET deleted_at = NOW(),
                is_active = false,
                updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    } else {
        // HARD DELETE: Never used
        // First delete form associations
        $stmt = db()->prepare("DELETE FROM form_webhooks WHERE webhook_id = ?");
        $stmt->execute([$id]);
        
        // Then delete the webhook itself
        $stmt = db()->prepare("DELETE FROM webhooks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

/**
 * Get webhook associated with a specific form
 *
 * @param int $form_id Form ID
 * @return array<string, mixed>|null Webhook data with decrypted token, or null if no webhook attached
 */
function get_webhook_for_form(int $form_id): ?array {
    $stmt = db()->prepare("
        SELECT w.*
        FROM webhooks w
        JOIN form_webhooks fw ON fw.webhook_id = w.id
        WHERE fw.form_id = ?
          AND w.deleted_at IS NULL
    ");
    
    $stmt->execute([$form_id]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$webhook) {
        return null;
    }
    
    // Decrypt bearer token
    try {
        $webhook['bearer_token'] = decrypt_data($webhook['bearer_token']);
    } catch (Exception $e) {
        error_log("Failed to decrypt webhook token for form {$form_id}: " . $e->getMessage());
    }
    
    return $webhook;
}

/**
 * Attach a webhook to a form
 *
 * @param int $webhook_id Webhook ID
 * @param int $form_id Form ID
 * @return bool Success status
 * @throws Exception If form already has a webhook attached
 */
function attach_webhook_to_form(int $webhook_id, int $form_id): bool {
    // Verify webhook exists and is not deleted
    $webhook = get_webhook($webhook_id, false);
    if (!$webhook || $webhook['deleted_at'] !== null) {
        throw new Exception("Webhook not found or has been deleted.");
    }
    
    $stmt = db()->prepare("
        INSERT INTO form_webhooks (form_id, webhook_id)
        VALUES (?, ?)
    ");

    try {
        return $stmt->execute([$form_id, $webhook_id]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            throw new Exception("Form already has a webhook attached. Detach it first.");
        }

        throw $e;
    }
}

/**
 * Detach webhook from a form
 *
 * @param int $form_id Form ID
 * @param int|null $webhook_id Webhook ID to scope the detach (recommended)
 * @return bool Success status
 */
function detach_webhook_from_form(int $form_id, ?int $webhook_id = null): bool {
    if ($webhook_id !== null) {
        $stmt = db()->prepare("DELETE FROM form_webhooks WHERE form_id = ? AND webhook_id = ?");
        return $stmt->execute([$form_id, $webhook_id]);
    }

    $stmt = db()->prepare("DELETE FROM form_webhooks WHERE form_id = ?");
    return $stmt->execute([$form_id]);
}

/**
 * Get form associated with a webhook
 *
 * @param int $webhook_id Webhook ID
 * @return array<string, mixed>|null Form data or null if not attached
 */
function get_form_for_webhook(int $webhook_id): ?array {
    $stmt = db()->prepare("
        SELECT f.*
        FROM forms f
        JOIN form_webhooks fw ON fw.form_id = f.id
        WHERE fw.webhook_id = ?
    ");
    
    $stmt->execute([$webhook_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Toggle webhook active status
 * 
 * @param int $id Webhook ID
 * @return bool New active status
 */
function toggle_webhook_active(int $id): bool {
    $stmt = db()->prepare("
        UPDATE webhooks
        SET is_active = NOT is_active,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$id]);
    
    // Get the new value
    $webhook = get_webhook($id, false);
    return (bool)$webhook['is_active'];
}

/**
 * Get webhook statistics
 *
 * @param int $webhook_id Webhook ID
 * @return array<string, mixed> Statistics including total fires, success rate, etc.
 */
function get_webhook_stats(int $webhook_id): array {
    $stmt = db()->prepare("
        SELECT
            COUNT(*) as total_deliveries,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' OR status = 'retrying' THEN 1 ELSE 0 END) as pending,
            MAX(CASE WHEN status = 'success' THEN created_at ELSE NULL END) as last_success,
            MAX(created_at) as last_attempt
        FROM webhook_log
        WHERE webhook_id = ?
    ");
    
    $stmt->execute([$webhook_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate success rate
    $total = (int)$stats['total_deliveries'];
    $successful = (int)$stats['successful'];
    
    $stats['success_rate'] = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    
    return $stats;
}

/**
 * Get webhook logs with pagination
 *
 * @param int $webhook_id Webhook ID
 * @param int $page Page number (1-based)
 * @param int $per_page Items per page
 * @param string|null $status_filter Filter by status (pending, success, failed, retrying)
 * @return array<string, mixed> Paginated webhook logs
 */
function get_webhook_logs(int $webhook_id, int $page = 1, int $per_page = 50, ?string $status_filter = null): array {
    $offset = ($page - 1) * $per_page;
    
    $where = ["webhook_id = ?"];
    $params = [$webhook_id];
    
    if ($status_filter) {
        $where[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get total count
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM webhook_log WHERE $where_clause");
    $stmt->execute($params);
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get logs
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = db()->prepare("
        SELECT wl.*, s.uuid as submission_uuid, f.name as form_name
        FROM webhook_log wl
        LEFT JOIN submissions s ON s.id = wl.submission_id
        LEFT JOIN forms f ON f.id = s.form_id
        WHERE $where_clause
        ORDER BY wl.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $logs,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => (int) ceil($total / $per_page)
    ];
}

/**
 * Get all recent webhook logs across all webhooks (for dashboard)
 *
 * @param int $limit Number of recent logs to retrieve
 * @return array<int, array<string, mixed>> Recent webhook logs
 */
function get_recent_webhook_logs(int $limit = 20): array {
    $stmt = db()->prepare("
        SELECT wl.*, w.name as webhook_name, s.uuid as submission_uuid, f.name as form_name
        FROM webhook_log wl
        JOIN webhooks w ON w.id = wl.webhook_id
        LEFT JOIN submissions s ON s.id = wl.submission_id
        LEFT JOIN forms f ON f.id = s.form_id
        ORDER BY wl.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get global webhook statistics (for admin dashboard)
 *
 * @return array<string, mixed> Global statistics
 */
function get_global_webhook_stats(): array {
    // Get active webhooks count
    $stmt = db()->query("
        SELECT COUNT(*) as count
        FROM webhooks
        WHERE is_active = true AND deleted_at IS NULL
    ");
    $active_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get pending deliveries
    $stmt = db()->query("
        SELECT COUNT(*) as count
        FROM webhook_log
        WHERE status IN ('pending', 'retrying')
    ");
    $pending_count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get failed in last 24 hours
    $stmt = db()->query("
        SELECT COUNT(*) as count
        FROM webhook_log
        WHERE status = 'failed'
          AND created_at > NOW() - INTERVAL 24 HOUR
    ");
    $failed_24h = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get overall success rate (last 7 days)
    $stmt = db()->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful
        FROM webhook_log
        WHERE created_at > NOW() - INTERVAL 7 DAY
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)$result['total'];
    $successful = (int)$result['successful'];
    $success_rate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    
    return [
        'active_webhooks' => $active_count,
        'pending_deliveries' => $pending_count,
        'failed_last_24h' => $failed_24h,
        'success_rate' => $success_rate
    ];
}

/**
 * Check whether a resolved IP address is safe for outbound webhook requests.
 *
 * Returns false for any private, loopback, link-local, or otherwise reserved
 * address (including 169.254.x.x cloud metadata ranges).
 *
 * @param string $ip IPv4 or IPv6 address
 * @return bool True if the IP is publicly routable
 */
function is_safe_webhook_ip(string $ip): bool {
    // Normalize IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1 → 127.0.0.1)
    // so the NO_PRIV_RANGE / NO_RES_RANGE flags apply to the real address.
    if (stripos($ip, '::ffff:') === 0) {
        $maybe_v4 = substr($ip, 7);
        if (filter_var($maybe_v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $maybe_v4;
        }
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

/**
 * Resolve a hostname to all its IPv4 and IPv6 addresses.
 *
 * @return list<string> Resolved IP addresses (may be empty)
 */
function resolve_host_ips(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [$host];
    }

    $ips = [];

    $a_records = @dns_get_record($host, DNS_A);
    if ($a_records) {
        foreach ($a_records as $record) {
            $ips[] = $record['ip'];
        }
    }

    $aaaa_records = @dns_get_record($host, DNS_AAAA);
    if ($aaaa_records) {
        foreach ($aaaa_records as $record) {
            $ips[] = $record['ipv6'];
        }
    }

    if (empty($ips)) {
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            $ips[] = $ip;
        }
    }

    return $ips;
}

/**
 * Validate webhook URL
 *
 * Enforces HTTPS and blocks SSRF targets (localhost, loopback, private
 * RFC-1918 ranges, link-local, and other reserved ranges) in production.
 * In development mode, local/private URLs are permitted.
 *
 * @param string $url URL to validate
 * @return bool True if valid
 * @throws Exception If URL is invalid or points to a forbidden address
 */
function validate_webhook_url(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid URL format');
    }

    static $is_dev = null;
    if ($is_dev === null) {
        require_once __DIR__ . '/../../core/env.php';
        $config = load_config();
        $is_dev = ($config['app']['environment'] ?? 'production') === 'development';
    }

    if (!$is_dev && !str_starts_with($url, 'https://')) {
        throw new Exception('Webhook URL must use HTTPS in production');
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        throw new Exception('Could not determine host from URL');
    }

    $host_lower = strtolower(trim($host, '[]'));

    if (!$is_dev) {
        if (in_array($host_lower, ['localhost', '::1', '0.0.0.0'])) {
            throw new Exception('Webhook URL cannot point to localhost or loopback addresses');
        }

        $ips = resolve_host_ips($host_lower);
        if (empty($ips)) {
            throw new Exception('Could not resolve webhook URL hostname');
        }

        foreach ($ips as $ip) {
            if (!is_safe_webhook_ip($ip)) {
                throw new Exception('Webhook URL must not point to private or reserved IP ranges');
            }
        }
    }

    return true;
}
