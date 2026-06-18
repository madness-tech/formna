#!/usr/bin/env php
<?php

/**
 * Clean up stale login rate-limit records.
 *
 * Removes rate_limits rows that:
 *   - Have zero attempts and are older than 1 hour, OR
 *   - Have a lock that has expired and the record is older than 24 hours
 *
 * Managed by the cron router — runs hourly.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Cleaning up expired rate-limit records...\n";

// Remove records with no attempts older than 1 hour
$staleIdle = db_exec(
    "DELETE FROM rate_limits
     WHERE attempts = 0
       AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);

// Remove records whose lock has expired and are older than 24 hours
$staleExpired = db_exec(
    "DELETE FROM rate_limits
     WHERE (locked_until IS NULL OR locked_until < NOW())
       AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);

$total = $staleIdle + $staleExpired;
echo "[" . date('Y-m-d H:i:s') . "] Deleted {$total} stale rate-limit record(s) (idle={$staleIdle}, expired={$staleExpired})\n";
