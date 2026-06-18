#!/usr/bin/env php
<?php
/**
 * Clean up abandoned program submission drafts
 * 
 * This script removes program drafts that are:
 * - Empty (no forms attached)
 * - Older than 30 days
 * - Haven't been updated in 30 days
 * 
 * Usage:
 *   php app/src/cron/tasks/cleanup_abandoned_program_drafts.php [--dry-run] [--days=30]
 *
 * Managed by the cron router — runs daily at 2:00 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/programs/models.php';
require_once __DIR__ . '/../../modules/audit/logger.php';

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$days = 30;

foreach ($argv as $arg) {
    if (strpos($arg, '--days=') === 0) {
        $days = (int)str_replace('--days=', '', $arg);
    }
}

echo "Abandoned Program Draft Cleanup Script\n";
echo "======================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "LIVE") . "\n";
echo "Threshold: Drafts older than {$days} days\n\n";

// Find abandoned drafts
$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

$sql = "
    SELECT 
        ps.id,
        ps.uuid,
        ps.created_at,
        ps.updated_at,
        p.name as program_name,
        u.email as user_email
    FROM program_submissions ps
    JOIN programs p ON ps.program_id = p.id
    JOIN users u ON ps.user_id = u.id
    WHERE ps.status = 'draft'
      AND ps.updated_at < ?
    ORDER BY ps.updated_at ASC
";

$drafts = db_query($sql, [$cutoffDate]);

echo "Found " . count($drafts) . " draft(s) older than {$days} days\n\n";

if (empty($drafts)) {
    echo "No drafts to clean up. Exiting.\n";
    exit(0);
}

$emptyDrafts = [];
$partialDrafts = [];

foreach ($drafts as $draft) {
    $submissionIds = get_submission_ids_map((int)$draft['id']);
    $draft['_submission_count'] = count($submissionIds);
    
    if (empty($submissionIds)) {
        $emptyDrafts[] = $draft;
    } else {
        $partialDrafts[] = $draft;
    }
}

echo "Breakdown:\n";
echo "- Empty drafts (no forms attached): " . count($emptyDrafts) . "\n";
echo "- Partial drafts (some forms attached): " . count($partialDrafts) . "\n\n";

// Clean up empty drafts
if (!empty($emptyDrafts)) {
    echo "Empty Drafts to Remove:\n";
    echo "=======================\n";
    foreach ($emptyDrafts as $draft) {
        echo sprintf(
            "ID: %d | UUID: %s | Program: %s | User: %s | Created: %s | Updated: %s\n",
            $draft['id'],
            $draft['uuid'],
            $draft['program_name'],
            $draft['user_email'],
            $draft['created_at'],
            $draft['updated_at']
        );
    }
    echo "\n";
    
    if (!$dryRun) {
        $ids = array_column($emptyDrafts, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        db_exec("DELETE FROM program_submissions WHERE id IN ($placeholders)", $ids);
        echo "✓ Deleted " . count($emptyDrafts) . " empty draft(s)\n\n";
    } else {
        echo "DRY RUN: Would delete " . count($emptyDrafts) . " empty draft(s)\n\n";
    }
}

// Report on partial drafts (don't delete these - user might come back)
if (!empty($partialDrafts)) {
    echo "Partial Drafts (NOT removed - user might return):\n";
    echo "=================================================\n";
    foreach ($partialDrafts as $draft) {
        $submissionCount = $draft['_submission_count'];
        echo sprintf(
            "ID: %d | UUID: %s | Program: %s | User: %s | Forms Attached: %d | Updated: %s\n",
            $draft['id'],
            $draft['uuid'],
            $draft['program_name'],
            $draft['user_email'],
            $submissionCount,
            $draft['updated_at']
        );
    }
    echo "\nThese drafts have forms attached and should be reviewed manually.\n";
    echo "Consider contacting users or setting a longer threshold (e.g., --days=90).\n\n";
}

echo "Done.\n";
