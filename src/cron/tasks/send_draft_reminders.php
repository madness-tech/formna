#!/usr/bin/env php
<?php

/**
 * Send Draft Reminders
 *
 * Finds users who have saved a draft submission but haven't submitted,
 * and sends a reminder email. Uses the configurable delay_days from the
 * 'draft_reminder' email template (default 3 days after draft creation).
 *
 * Only sends one reminder per draft (tracked via job queue metadata).
 *
 * Managed by the cron router — runs daily at 8:00 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/emails/sender.php';
require_once __DIR__ . '/../../modules/emails/models.php';
require_once __DIR__ . '/../../modules/forms/models.php';

echo "[" . date('Y-m-d H:i:s') . "] Checking for stale drafts needing reminders...\n";

$template = get_email_template('draft_reminder');

if (!$template) {
    echo "[" . date('Y-m-d H:i:s') . "] Draft reminder template is disabled or missing. Exiting.\n";
    exit(0);
}

$delayDays = ($template['delay_days'] ?? 3);

// Find drafts older than delay_days that haven't been reminded yet
// Only include drafts for forms that have no deadline OR deadline hasn't passed
$cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$delayDays} days"));

$drafts = db_query(
    "SELECT d.id, d.form_id, d.user_id, d.updated_at,
            f.uuid AS form_uuid, f.name AS form_name,
            u.name AS user_name, u.email AS user_email
     FROM drafts d
     JOIN forms f ON f.id = d.form_id AND f.deleted_at IS NULL AND f.status = 'published'
     JOIN users u ON u.id = d.user_id AND u.status = 'active' AND u.email_verified_at IS NOT NULL
     WHERE d.updated_at < ?
       AND (
           JSON_UNQUOTE(JSON_EXTRACT(f.settings, '$.submission_deadline')) IS NULL
           OR JSON_UNQUOTE(JSON_EXTRACT(f.settings, '$.submission_deadline')) > UTC_TIMESTAMP()
       )
       AND d.user_id NOT IN (
           SELECT s.user_id FROM submissions s
           WHERE s.form_id = d.form_id AND s.status != 'draft'
       )",
    [$cutoff]
);

if (empty($drafts)) {
    echo "[" . date('Y-m-d H:i:s') . "] No stale drafts found.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($drafts) . " stale draft(s).\n";

$emailsSent = 0;

foreach ($drafts as $draft) {
    // Check if we already sent a reminder for this draft recently
    $alreadySent = db_one(
        "SELECT COUNT(*) as count FROM job_queue
         WHERE handler = 'send_email'
           AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.meta.trigger')) = 'draft_reminder'
           AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.meta.draft_id')) = ?
           AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
        [(string) $draft['id'], $delayDays]
    );

    if (($alreadySent['count'] ?? 0) > 0) {
        continue;
    }

    $queued = queue_email_from_template('draft_reminder', [
        'email' => $draft['user_email'],
        'name'  => $draft['user_name'],
    ], [
        'form_name'      => $draft['form_name'],
        'form_link'      => base_url('/forms/' . $draft['form_uuid']),
        'dashboard_link' => base_url('/dashboard'),
    ], 0, [
        'trigger'  => 'draft_reminder',
        'draft_id' => (string) $draft['id'],
        'user_id'  => (string) $draft['user_id'],
        'form_id'  => (string) $draft['form_id'],
    ]);

    if ($queued) {
        $emailsSent++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Queued {$emailsSent} draft reminder email(s)\n";
