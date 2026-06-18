#!/usr/bin/env php
<?php

/**
 * Send Expiry Warnings
 *
 * Finds submitted submissions that will expire soon based on the form's
 * expiry_days setting, and emails the submitter. Uses the configurable
 * delay_days from the 'expiry_warning' email template to determine how
 * many days before expiry to send the warning (default 7 days).
 *
 * Only sends one warning per submission per expiry window.
 *
 * Managed by the cron router — runs daily at 8:00 AM.
 */

require_once __DIR__ . '/../../core/cron_bootstrap.php';
require_once __DIR__ . '/../../modules/emails/sender.php';
require_once __DIR__ . '/../../modules/emails/models.php';

echo "[" . date('Y-m-d H:i:s') . "] Checking for submissions expiring soon...\n";

$template = get_email_template('expiry_warning');

if (!$template) {
    echo "[" . date('Y-m-d H:i:s') . "] Expiry warning template is disabled or missing. Exiting.\n";
    exit(0);
}

$warningDays = ($template['delay_days'] ?? 7);

// Find forms that have expiry_days configured
$forms = db_query(
    "SELECT f.id, f.uuid, f.name, f.settings
     FROM forms f
     WHERE f.deleted_at IS NULL
       AND JSON_EXTRACT(f.settings, '$.expiry_days') IS NOT NULL
       AND CAST(JSON_EXTRACT(f.settings, '$.expiry_days') AS UNSIGNED) > 0"
);

if (empty($forms)) {
    echo "[" . date('Y-m-d H:i:s') . "] No forms with expiry configured.\n";
    exit(0);
}

$emailsSent = 0;

foreach ($forms as $form) {
    decode_json_fields($form, ['settings']);
    $expiryDays = (int) ($form['settings']['expiry_days'] ?? 0);
    $submissionLimit = (int) ($form['settings']['submission_limit'] ?? 1);

    if ($expiryDays <= 0) {
        continue;
    }

    // Calculate the window: submissions that will expire within warningDays days
    // A submission expires at: submitted_at + expiry_days
    // We want expiry to be AFTER today: submitted_at + expiry_days > NOW()
    // And within warning window: submitted_at + expiry_days <= NOW() + warningDays
    // Which means: submitted_at > NOW() - expiry_days AND submitted_at <= NOW() - expiry_days + warningDays
    $windowStart = gmdate('Y-m-d H:i:s', strtotime("-{$expiryDays} days"));
    $windowEnd   = gmdate('Y-m-d H:i:s', strtotime("-{$expiryDays} days +{$warningDays} days"));

    $submissions = db_query(
        "SELECT s.id, s.uuid, s.submitted_at, s.user_id,
                u.name AS user_name, u.email AS user_email
         FROM submissions s
         JOIN users u ON u.id = s.user_id AND u.status = 'active'
         WHERE s.form_id = ?
           AND s.status = 'submitted'
           AND s.submitted_at > ?
           AND s.submitted_at <= ?",
        [$form['id'], $windowStart, $windowEnd]
    );

    if (empty($submissions)) {
        continue;
    }

    foreach ($submissions as $sub) {
        // Check if already warned for this submission
        $alreadySent = db_one(
            "SELECT COUNT(*) as count FROM job_queue
             WHERE handler = 'send_email'
               AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.meta.trigger')) = 'expiry_warning'
               AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.meta.submission_id')) = ?
               AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [(string) $sub['id'], $warningDays]
        );

        if (($alreadySent['count'] ?? 0) > 0) {
            continue;
        }

        $expiryDate = gmdate('Y-m-d H:i:s', strtotime($sub['submitted_at'] . " +{$expiryDays} days"));

        // Provide context-aware messaging based on submission limits
        if ($submissionLimit === 1) {
            $expiryAction = "After this date, you will no longer be able to access this submission.";
        } else {
            $expiryAction = "You may submit a new entry before or after this date if needed.";
        }

        $queued = queue_email_from_template('expiry_warning', [
            'email' => $sub['user_email'],
            'name'  => $sub['user_name'],
        ], [
            'form_name'       => $form['name'],
            'expiry_date'     => $expiryDate,
            'expiry_action'   => $expiryAction,
            'submission_link' => base_url('/submissions/' . $sub['uuid']),
        ], 0, [
            'trigger'       => 'expiry_warning',
            'submission_id' => (string) $sub['id'],
            'user_id'       => (string) $sub['user_id'],
            'form_id'       => (string) $form['id'],
        ]);

        if ($queued) {
            $emailsSent++;
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Queued {$emailsSent} expiry warning email(s)\n";
