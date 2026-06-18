#!/usr/bin/env php
<?php

/**
 * Warm report caches — Orchestrated with retry and health tracking.
 *
 * Generates all report caches with per-report error handling and retry logic.
 * Writes a _health.json file consumed by the admin UI to surface failures.
 *
 * Usage:
 *   php app/src/cron/tasks/warm_report_cache.php [--dry-run]
 *
 * Managed by the cron router.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/reports/models.php';

const WARM_MAX_RETRIES = 2;
const WARM_HEALTH_FILE = REPORT_CACHE_DIR . '/_health.json';

// Prevent concurrent runs
$LOCK_FILE = __DIR__ . '/../../../storage/warm_report_cache.lock';
$lockFile = fopen($LOCK_FILE, 'c');

if (!$lockFile) {
    error_log('[WARM_CACHE] Failed to open lock file');
    exit(1);
}

if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Another instance is already running. Exiting.\n";
    fclose($lockFile);
    exit(0);
}

$dryRun = in_array('--dry-run', $argv, true);
$startTime = microtime(true);

echo "[" . date('Y-m-d H:i:s') . "] Warming report caches" . ($dryRun ? " [DRY RUN]" : "") . "\n";

if (!is_dir(REPORT_CACHE_DIR)) {
    mkdir(REPORT_CACHE_DIR, 0755, true);
}

$manifest = warm_build_manifest();
echo "  Manifest: " . count($manifest) . " report(s)\n";

$completed = 0;
$failed = 0;
$failedKeys = [];
$results = [];

foreach ($manifest as $key => $item) {
    $success = false;
    $lastError = null;

    for ($attempt = 1; $attempt <= WARM_MAX_RETRIES + 1; $attempt++) {
        $label = ($attempt > 1) ? " (retry {$attempt})" : "";
        echo "  [{$key}]{$label}...";

        if ($dryRun) {
            echo " skip\n";
            $success = true;
            break;
        }

        $itemStart = microtime(true);
        try {
            report_cache_clear($key);
            $item['fn']();
            $ms = round((microtime(true) - $itemStart) * 1000);
            echo " done ({$ms}ms)\n";
            $results[$key] = ['status' => 'completed', 'attempts' => $attempt, 'duration_ms' => $ms];
            $success = true;
            break;
        } catch (Throwable $e) {
            $ms = round((microtime(true) - $itemStart) * 1000);
            $lastError = substr($e->getMessage(), 0, 500);
            echo " error ({$ms}ms): {$lastError}\n";
        }
    }

    if ($success) {
        $completed++;
    } else {
        $failed++;
        $failedKeys[] = $key;
        $results[$key] = [
            'status' => 'failed',
            'attempts' => WARM_MAX_RETRIES + 1,
            'error' => $lastError,
        ];
    }
}

$totalDuration = round(microtime(true) - $startTime, 1);

// Write health file for the admin UI
$health = [
    'last_run' => gmdate('Y-m-d H:i:s'),
    'total_reports' => count($manifest),
    'completed' => $completed,
    'failed' => $failed,
    'failed_reports' => $failedKeys,
    'duration_s' => $totalDuration,
    'status' => $failed === 0 ? 'healthy' : ($completed === 0 ? 'critical' : 'degraded'),
];
file_put_contents(WARM_HEALTH_FILE, json_encode($health, JSON_PRETTY_PRINT), LOCK_EX);

echo "[" . date('Y-m-d H:i:s') . "] Complete: {$completed} ok, {$failed} failed, {$totalDuration}s total\n";

// Release lock
flock($lockFile, LOCK_UN);
fclose($lockFile);

// ── Manifest builder ────────────────────────────────────────────

/**
 * Build the map of report keys to their cache-warming callables.
 *
 * @return array<string, array{fn: callable}>
 */
function warm_build_manifest(): array {
    $global_user = ['id' => 0, 'role' => 'super_admin'];
    $manifest = [];

    // Global reports
    $manifest['summary_global'] = [
        'fn' => function () use ($global_user) { get_reports_summary($global_user); },
    ];
    $manifest['volume_global_30'] = [
        'fn' => function () use ($global_user) { get_submission_volume($global_user, 30); },
    ];

    // Per-form reports
    $forms = db_query("SELECT id, name FROM forms WHERE deleted_at IS NULL AND status = 'published'");
    foreach ($forms as $f) {
        $fid = (int)$f['id'];
        $manifest["form_{$fid}"] = [
            'fn' => function () use ($fid) { get_form_report($fid); },
        ];
        $manifest["answers_{$fid}"] = [
            'fn' => function () use ($fid) { get_answer_distribution($fid); },
        ];
        $manifest["scoring_{$fid}"] = [
            'fn' => function () use ($fid) { get_scoring_distribution($fid); },
        ];
    }

    // Per-program reports
    $programs = db_query("SELECT id, name FROM programs WHERE status IN ('active','closed')");
    foreach ($programs as $p) {
        $pid = (int)$p['id'];
        $manifest["program_{$pid}"] = [
            'fn' => function () use ($pid) { get_program_report($pid); },
        ];
    }

    return $manifest;
}
