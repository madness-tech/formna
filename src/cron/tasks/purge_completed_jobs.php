#!/usr/bin/env php
<?php

/**
 * Purge old completed and permanently-failed job queue entries.
 *
 * Completed jobs (run_at in past, not locked, not failed) older than 30 days
 * and permanently failed jobs older than 30 days are removed.
 *
 * Managed by the cron router — runs daily at 2:10 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$days = 30;

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int) str_replace('--days=', '', $arg);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Purging job queue entries older than {$days} days...\n";

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

// Completed jobs: locked_at IS NULL, failed_at IS NULL, run_at in the past, and old enough
$completedDeleted = db_exec(
    "DELETE FROM job_queue
     WHERE failed_at IS NULL
       AND locked_at IS NULL
       AND run_at < ?
       AND created_at < ?",
    [$cutoff, $cutoff]
);

// Permanently failed jobs older than retention period
$failedDeleted = db_exec(
    "DELETE FROM job_queue
     WHERE failed_at IS NOT NULL
       AND failed_at < ?",
    [$cutoff]
);

$total = $completedDeleted + $failedDeleted;
echo "[" . date('Y-m-d H:i:s') . "] Purged {$total} job(s) (completed={$completedDeleted}, failed={$failedDeleted})\n";
