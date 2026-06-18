#!/usr/bin/env php
<?php
/**
 * Clean up orphaned file uploads (files with no submission_id).
 *
 * This script removes file records and files that are:
 * - Not linked to a submission (submission_id IS NULL)
 * - Older than a configurable number of hours
 *
 * Usage:
 *   php app/src/cron/tasks/cleanup_orphaned_uploads.php [--dry-run] [--hours=24]
 *
 * Managed by the cron router — runs hourly.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$hours = 24;

foreach ($argv as $arg) {
    if (strpos($arg, '--hours=') === 0) {
        $hours = (int)str_replace('--hours=', '', $arg);
    }
}

echo "Orphaned Upload Cleanup Script\n";
echo "================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "LIVE") . "\n";
echo "Threshold: Files older than {$hours} hours\n\n";

$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

// Collect file IDs referenced by active drafts so they are not deleted
$draftFileIds = [];
$drafts = db_query("SELECT data FROM drafts", []);
foreach ($drafts as $draft) {
    $data = json_decode($draft['data'], true);
    if (!is_array($data)) continue;
    foreach ($data as $val) {
        if (is_numeric($val)) {
            $draftFileIds[(int)$val] = true;
        }
    }
}

$excludeClause = '';
$params = [$cutoffDate];
if (!empty($draftFileIds)) {
    $placeholders = implode(',', array_fill(0, count($draftFileIds), '?'));
    $excludeClause = " AND id NOT IN ($placeholders)";
    $params = array_merge($params, array_keys($draftFileIds));
}

$files = db_query(
    "SELECT id, stored_path, size_bytes, created_at FROM files WHERE submission_id IS NULL AND created_at < ?" . $excludeClause . " ORDER BY created_at ASC",
    $params
);

if (!empty($draftFileIds)) {
    echo "Excluding " . count($draftFileIds) . " file(s) referenced by active drafts\n";
}

echo "Found " . count($files) . " orphaned file(s) older than {$hours} hours\n\n";

if (empty($files)) {
    echo "No files to clean up. Exiting.\n";
    exit(0);
}

global $config;
$storageRoot = realpath($config['storage']['local_path']);

if ($storageRoot === false) {
    $configuredPath = $config['storage']['local_path'] ?? '(not configured)';
    echo "FATAL: Storage root does not exist or is not accessible: {$configuredPath}\n";
    echo "No files will be cleaned up. Check your storage configuration.\n";
    exit(1);
}

echo "Storage root: {$storageRoot}\n";

$totalBytes = array_sum(array_map(fn($file) => (int)($file['size_bytes'] ?? 0), $files));

echo "Total size: " . number_format($totalBytes) . " bytes\n\n";

foreach ($files as $file) {
    echo sprintf(
        "ID: %d | Path: %s | Size: %d bytes | Created: %s\n",
        $file['id'],
        $file['stored_path'],
        (int)$file['size_bytes'],
        $file['created_at']
    );
}

echo "\n";

if ($dryRun) {
    echo "DRY RUN: No files or records deleted.\n";
    exit(0);
}

$deletedCount = 0;
$missingCount = 0;
$idsToDelete = [];

foreach ($files as $file) {
    $filename = basename((string)$file['stored_path']);
    
    if ($filename === '' || $filename === '.' || $filename === '..') {
        echo "Skipping invalid stored path: {$file['stored_path']}\n";
        $idsToDelete[] = $file['id'];
        continue;
    }
    
    $candidatePath = $storageRoot . DIRECTORY_SEPARATOR . $filename;

    if (file_exists($candidatePath)) {
        $resolved = realpath($candidatePath);
        if ($resolved === false || !str_starts_with($resolved, $storageRoot . DIRECTORY_SEPARATOR)) {
            echo "SECURITY: Skipping file with unexpected resolved path: {$candidatePath}\n";
            continue;
        }
        
        if (unlink($resolved)) {
            $deletedCount++;
        } else {
            echo "Failed to delete file: {$resolved}\n";
        }
    } else {
        $missingCount++;
        echo "File missing on disk, will remove record: {$candidatePath}\n";
    }

    $idsToDelete[] = $file['id'];
}

if (!empty($idsToDelete)) {
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    db_exec("DELETE FROM files WHERE id IN ($placeholders)", $idsToDelete);
}

echo "\nCleanup complete.\n";
echo "Deleted files: {$deletedCount}\n";
echo "Missing files removed from DB: {$missingCount}\n";
echo "Records deleted: " . count($idsToDelete) . "\n";
