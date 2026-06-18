#!/usr/bin/env php
<?php

/**
 * Purge old webhook log entries.
 *
 * Removes webhook_log rows older than a configurable retention period.
 * Only purges entries with a terminal status (success or failed).
 * Pending/retrying entries are never removed.
 * Default: 60 days.
 *
 * Usage:
 *   php app/src/cron/tasks/purge_old_webhook_logs.php [--days=60] [--dry-run]
 *
 * Managed by the cron router — runs daily at 2:40 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$days   = 60;
$dryRun = in_array('--dry-run', $argv, true);

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int) str_replace('--days=', '', $arg);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Purging webhook logs older than {$days} days" . ($dryRun ? " (DRY RUN)" : "") . "\n";

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

if ($dryRun) {
    $count = db_one(
        "SELECT COUNT(*) as count FROM webhook_log WHERE status IN ('success', 'failed') AND created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Would delete {$count['count']} webhook log(s)\n";
} else {
    $deleted = db_exec(
        "DELETE FROM webhook_log WHERE status IN ('success', 'failed') AND created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} webhook log(s)\n";
}
