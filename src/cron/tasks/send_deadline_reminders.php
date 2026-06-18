#!/usr/bin/env php
<?php

/**
 * Send Deadline Reminders
 *
 * Finds published forms with upcoming submission deadlines and emails users
 * who have an active relationship with the form (draft saved, or enrolled in
 * a program containing the form) but have not yet submitted.
 *
 * Uses the configurable delay_days from the 'deadline_reminder' email template
 * (default 3 days before deadline).
 *
 * Only sends one reminder per user per form per deadline window.
 *
 * Managed by the cron router — runs daily at 8:00 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/emails/sender.php';
require_once __DIR__ . '/../../modules/emails/models.php';
require_once __DIR__ . '/../../modules/forms/models.php';

echo "[" . date('Y-m-d H:i:s') . "] Checking for upcoming submission deadlines...\n";

// Get the reminder lead time from template config (default 3 days)
$template = get_email_template('deadline_reminder');

if (!$template) {
    echo "[" . date('Y-m-d H:i:s') . "] Deadline reminder template is disabled or missing. Exiting.\n";
    exit(0);
}

$leadDays = ($template['delay_days'] ?? 3);

$now = gmdate('Y-m-d H:i:s');
$windowEnd = gmdate('Y-m-d H:i:s', strtotime("+{$leadDays} days"));

// Find published forms with deadlines within the reminder window
$forms = db_query(
    "SELECT f.id, f.uuid, f.name, f.settings
     FROM forms f
     WHERE f.status = 'published'
       AND f.deleted_at IS NULL
       AND JSON_UNQUOTE(JSON_EXTRACT(f.settings, '$.submission_deadline')) IS NOT NULL
       AND JSON_UNQUOTE(JSON_EXTRACT(f.settings, '$.submission_deadline')) > ?
       AND JSON_UNQUOTE(JSON_EXTRACT(f.settings, '$.submission_deadline')) <= ?",
    [$now, $windowEnd]
);

if (empty($forms)) {
    echo "[" . date('Y-m-d H:i:s') . "] No forms with upcoming deadlines found.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($forms) . " form(s) with upcoming deadlines.\n";

$emailsSent = 0;

foreach ($forms as $form) {
    decode_json_fields($form, ['settings']);
    $deadline = $form['settings']['submission_deadline'] ?? null;

    if (!$deadline) {
        continue;
    }

    // Find active, verified users who have a relationship with this form
    // (have a draft OR are enrolled in a program containing this form but haven't submitted it yet)
    // but have NOT yet submitted, and have NOT already been reminded recently.
    $users = db_query(
        "SELECT DISTINCT u.id, u.name, u.email
         FROM users u
         WHERE u.status = 'active'
           AND u.email_verified_at IS NOT NULL
           AND (
               u.id IN (
                   SELECT d.user_id FROM drafts d WHERE d.form_id = ?
               )
               OR u.id IN (
                   SELECT ps.user_id
                   FROM program_submissions ps
                   JOIN programs p ON p.id = ps.program_id
                   WHERE JSON_CONTAINS(p.form_ids, CAST(? AS JSON))
                     AND ps.status = 'draft'
                     AND NOT EXISTS (
                         SELECT 1 FROM program_submission_entries pse
                         WHERE pse.program_submission_id = ps.id
                           AND pse.form_id = ?
                     )
               )
           )
           AND u.id NOT IN (
               SELECT s.user_id
               FROM submissions s
               WHERE s.form_id = ?
                 AND s.status != 'draft'
           )
           AND u.id NOT IN (
               SELECT jq_inner.user_id FROM (
                   SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(jq.payload, '$.meta.user_id')) AS UNSIGNED) AS user_id
                   FROM job_queue jq
                   WHERE jq.handler = 'send_email'
                     AND JSON_UNQUOTE(JSON_EXTRACT(jq.payload, '$.meta.trigger')) = 'deadline_reminder'
                     AND JSON_UNQUOTE(JSON_EXTRACT(jq.payload, '$.meta.form_id')) = ?
                     AND jq.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)
               ) jq_inner
           )",
        [$form['id'], (string) $form['id'], $form['id'], $form['id'], (string) $form['id'], $leadDays]
    );

    if (empty($users)) {
        continue;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Sending deadline reminders for '{$form['name']}' to " . count($users) . " user(s)\n";

    foreach ($users as $user) {
        $queued = queue_email_from_template('deadline_reminder', [
            'email' => $user['email'],
            'name'  => $user['name'],
        ], [
            'form_name'     => $form['name'],
            'deadline_date' => $deadline,
            'form_link'     => base_url('/forms/' . $form['uuid']),
        ], 0, [
            'trigger' => 'deadline_reminder',
            'form_id' => (string) $form['id'],
            'user_id' => (string) $user['id'],
        ]);

        if ($queued) {
            $emailsSent++;
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Queued {$emailsSent} deadline reminder email(s)\n";
