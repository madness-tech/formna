#!/usr/bin/env php
<?php

/**
 * Purge old audit log entries.
 *
 * Removes audit_log rows older than a configurable retention period.
 * Default: 90 days.
 *
 * Usage:
 *   php app/src/cron/tasks/purge_old_audit_logs.php [--days=90] [--dry-run]
 *
 * Managed by the cron router — runs daily at 2:20 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$days   = 90;
$dryRun = in_array('--dry-run', $argv, true);

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int) str_replace('--days=', '', $arg);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Purging audit log entries older than {$days} days" . ($dryRun ? " (DRY RUN)" : "") . "\n";

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

if ($dryRun) {
    $count = db_one(
        "SELECT COUNT(*) as count FROM audit_log WHERE created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Would delete {$count['count']} audit log entry(ies)\n";
} else {
    $deleted = db_exec(
        "DELETE FROM audit_log WHERE created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} audit log entry(ies)\n";
}
