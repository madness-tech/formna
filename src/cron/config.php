<?php

/**
 * Cron Schedule Configuration
 *
 * Each task defines:
 *   - name:     Unique identifier for the task
 *   - script:   Path to the PHP script (relative to tasks/)
 *   - schedule: Cron expression (minute hour day_of_month month day_of_week)
 *   - enabled:  Whether the task is active
 *   - timeout:  Max execution time in seconds (0 = unlimited)
 *   - description: Human-readable description
 *
 * Cron expression supports:
 *   *        = every unit
 *   5        = at exactly 5
 *   1,3,5    = at 1, 3, and 5
 *   1-5      = range from 1 to 5
 *   * /5     = every 5 units (written without space)
 *   1-30/5   = every 5 units within range 1-30
 */

return [

    // ── Queue Processors (every minute) ──────────────────────────────
    [
        'name'        => 'process_email_queue',
        'script'      => 'process_email_queue.php',
        'schedule'    => '* * * * *',
        'enabled'     => true,
        'timeout'     => 300,
        'description' => 'Process pending emails from the job queue',
    ],
    [
        'name'        => 'process_webhook_queue',
        'script'      => 'process_webhook_queue.php',
        'schedule'    => '* * * * *',
        'enabled'     => true,
        'timeout'     => 300,
        'description' => 'Process pending webhook deliveries',
    ],

    // ── Hourly Cleanup Tasks ─────────────────────────────────────────
    [
        'name'        => 'cleanup_orphaned_uploads',
        'script'      => 'cleanup_orphaned_uploads.php',
        'schedule'    => '0 * * * *',
        'enabled'     => true,
        'timeout'     => 120,
        'description' => 'Remove orphaned file uploads older than 24 hours',
    ],
    [
        'name'        => 'cleanup_expired_tokens',
        'script'      => 'cleanup_expired_tokens.php',
        'schedule'    => '15 * * * *',
        'enabled'     => true,
        'timeout'     => 30,
        'description' => 'Purge expired verification tokens',
    ],
    [
        'name'        => 'cleanup_expired_rate_limits',
        'script'      => 'cleanup_expired_rate_limits.php',
        'schedule'    => '15 * * * *',
        'enabled'     => true,
        'timeout'     => 30,
        'description' => 'Purge stale login rate-limit records',
    ],

    // ── Daily Maintenance Tasks (run at 2:00 AM) ─────────────────────
    [
        'name'        => 'cleanup_abandoned_program_drafts',
        'script'      => 'cleanup_abandoned_program_drafts.php',
        'schedule'    => '0 2 * * *',
        'enabled'     => true,
        'timeout'     => 120,
        'description' => 'Remove empty program drafts older than 30 days',
    ],
    [
        'name'        => 'cleanup_old_resend_logs',
        'script'      => 'cleanup_old_resend_logs.php',
        'schedule'    => '5 2 * * *',
        'enabled'     => true,
        'timeout'     => 30,
        'description' => 'Purge email resend log entries older than 7 days',
    ],
    [
        'name'        => 'purge_completed_jobs',
        'script'      => 'purge_completed_jobs.php',
        'schedule'    => '10 2 * * *',
        'enabled'     => true,
        'timeout'     => 60,
        'description' => 'Purge completed/failed job queue entries older than 30 days',
    ],
    [
        'name'        => 'purge_old_audit_logs',
        'script'      => 'purge_old_audit_logs.php',
        'schedule'    => '20 2 * * *',
        'enabled'     => true,
        'timeout'     => 60,
        'description' => 'Purge audit log entries older than 90 days',
    ],
    [
        'name'        => 'purge_old_notifications',
        'script'      => 'purge_old_notifications.php',
        'schedule'    => '30 2 * * *',
        'enabled'     => true,
        'timeout'     => 60,
        'description' => 'Purge read notifications older than 60 days',
    ],
    [
        'name'        => 'purge_old_webhook_logs',
        'script'      => 'purge_old_webhook_logs.php',
        'schedule'    => '40 2 * * *',
        'enabled'     => true,
        'timeout'     => 60,
        'description' => 'Purge webhook log entries older than 60 days',
    ],
    [
        'name'        => 'purge_old_report_cache',
        'script'      => 'purge_old_report_cache.php',
        'schedule'    => '50 2 * * *',
        'enabled'     => true,
        'timeout'     => 30,
        'description' => 'Purge cached report files older than 30 days',
    ],

    // ── Report Cache Warming (matches reports.cache_ttl_hours) ───────
    [
        'name'        => 'warm_report_cache',
        'script'      => 'warm_report_cache.php',
        'schedule'    => '0 */6 * * *',
        'enabled'     => true,
        'timeout'     => 600,
        'description' => 'Pre-generate global, per-form, and per-program report caches',
    ],

    // ── Daily Email Tasks (run at 8:00 AM) ───────────────────────────
    [
        'name'        => 'send_deadline_reminders',
        'script'      => 'send_deadline_reminders.php',
        'schedule'    => '0 8 * * *',
        'enabled'     => true,
        'timeout'     => 120,
        'description' => 'Email users about upcoming submission deadlines',
    ],
    [
        'name'        => 'send_draft_reminders',
        'script'      => 'send_draft_reminders.php',
        'schedule'    => '0 8 * * *',
        'enabled'     => true,
        'timeout'     => 120,
        'description' => 'Remind users about unfinished draft submissions',
    ],
    [
        'name'        => 'send_expiry_warnings',
        'script'      => 'send_expiry_warnings.php',
        'schedule'    => '0 8 * * *',
        'enabled'     => true,
        'timeout'     => 120,
        'description' => 'Warn users about submissions expiring soon',
    ],
];
