#!/usr/bin/env php
<?php

/**
 * Clean up old email resend log entries.
 *
 * Removes email_resend_log entries older than 7 days.
 * These are only used for rate limiting, so we don't need to keep them long-term.
 *
 * Managed by the cron router — runs daily.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old email resend logs...\n";

$pdo = db();

$stmt = $pdo->prepare("
    DELETE FROM email_resend_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");

$stmt->execute();
$deleted = $stmt->rowCount();

echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} old resend log entrie(s)\n";
