#!/usr/bin/env php
<?php

/**
 * Purge old read notifications.
 *
 * Removes notifications that have been read and are older than
 * a configurable retention period. Unread notifications are never removed.
 * Default: 60 days.
 *
 * Usage:
 *   php app/src/cron/tasks/purge_old_notifications.php [--days=60] [--dry-run]
 *
 * Managed by the cron router — runs daily at 2:30 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$days   = 60;
$dryRun = in_array('--dry-run', $argv, true);

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int) str_replace('--days=', '', $arg);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Purging read notifications older than {$days} days" . ($dryRun ? " (DRY RUN)" : "") . "\n";

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

if ($dryRun) {
    $count = db_one(
        "SELECT COUNT(*) as count FROM notifications WHERE read_at IS NOT NULL AND created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Would delete {$count['count']} notification(s)\n";
} else {
    $deleted = db_exec(
        "DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?",
        [$cutoff]
    );
    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} read notification(s)\n";
}
