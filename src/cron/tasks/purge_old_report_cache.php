#!/usr/bin/env php
<?php

/**
 * Purge old report cache files.
 *
 * Removes cached report JSON files older than a configurable retention period.
 * Default: 30 days.
 *
 * Usage:
 *   php app/src/cron/tasks/purge_old_report_cache.php [--days=30] [--dry-run]
 *
 * Managed by the cron router — runs daily at 2:50 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$days   = 30;
$dryRun = in_array('--dry-run', $argv, true);

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int) str_replace('--days=', '', $arg);
    }
}

$cacheDir = __DIR__ . '/../../../storage/reports';

echo "[" . date('Y-m-d H:i:s') . "] Purging report cache files older than {$days} days" . ($dryRun ? " (DRY RUN)" : "") . "\n";

if (!is_dir($cacheDir)) {
    echo "[" . date('Y-m-d H:i:s') . "] Cache directory does not exist, nothing to purge\n";
    exit(0);
}

$cutoff  = time() - ($days * 86400);
$deleted = 0;
$skipped = 0;

$files = glob($cacheDir . '/*.json');
if ($files === false) {
    $files = [];
}

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }

    $mtime = filemtime($file);
    if ($mtime === false || $mtime >= $cutoff) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "  Would delete: " . basename($file) . " (modified " . date('Y-m-d H:i:s', $mtime) . ")\n";
        $deleted++;
    } else {
        if (unlink($file)) {
            $deleted++;
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] " . ($dryRun ? "Would delete" : "Deleted") . " {$deleted} cache file(s), skipped {$skipped}\n";
