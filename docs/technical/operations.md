# Operations and Maintenance

## Table of contents

1. [Cron architecture](#cron-architecture)
2. [Scheduled tasks](#scheduled-tasks)
3. [Monitoring](#monitoring)
4. [Manual execution](#manual-execution)
5. [Performance notes](#performance-notes)
6. [Troubleshooting](#troubleshooting)

---

## Cron architecture

FORMNA uses a **single cron router** located at `src/cron/router.php`.

How it works:

1. system cron invokes the router every minute
2. the router acquires a lock to prevent overlap
3. the router reads task configuration and state
4. matching due tasks are spawned as background PHP processes
5. individual tasks manage their own locks when necessary
6. dispatch state is saved to prevent duplicate same-minute runs

---

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `process_email_queue` | every minute | send queued emails |
| `process_webhook_queue` | every minute | deliver queued webhooks |
| `cleanup_orphaned_uploads` | hourly at `:00` | remove orphaned uploads |
| `cleanup_expired_tokens` | hourly at `:15` | purge verification and reset tokens |
| `cleanup_expired_rate_limits` | hourly at `:15` | remove stale throttle records |
| `cleanup_abandoned_program_drafts` | daily at 2:00 AM | remove empty stale drafts |
| `cleanup_old_resend_logs` | daily at 2:05 AM | purge email resend log entries |
| `purge_completed_jobs` | daily at 2:10 AM | clean old job queue records |
| `purge_old_audit_logs` | daily at 2:20 AM | purge audit records by retention policy |
| `purge_old_notifications` | daily at 2:30 AM | purge old read notifications |
| `purge_old_webhook_logs` | daily at 2:40 AM | purge old webhook logs |
| `purge_old_report_cache` | daily at 2:50 AM | purge cached report files |
| `warm_report_cache` | every 6 hours | pre-generate report caches |
| `send_deadline_reminders` | daily at 8:00 AM | remind users about upcoming deadlines |
| `send_draft_reminders` | daily at 8:00 AM | remind users about unfinished drafts |
| `send_expiry_warnings` | daily at 8:00 AM | warn about expiring submissions |

---

## Monitoring

### Core logs

| File | Purpose |
|---|---|
| `storage/logs/cron.log` | router activity |
| `storage/logs/process_email_queue.log` | email queue processing |
| `storage/logs/process_webhook_queue.log` | webhook processing |
| `storage/logs/email_errors.log` | email failures |
| `storage/logs/*.log` | task-specific logs |

### Useful commands

```bash
tail -f storage/logs/cron.log
tail -f storage/logs/process_email_queue.log
php bin/formna queue-status
```

---

## Manual execution

Tasks can be run manually when debugging or validating changes.

```bash
php src/cron/tasks/process_email_queue.php
php src/cron/tasks/cleanup_orphaned_uploads.php --dry-run
php src/cron/tasks/purge_old_audit_logs.php --days=30 --dry-run
```

Documented optional flags include `--dry-run`, `--days=N`, and task-specific retention overrides.

---

## Performance notes

### Database

- index frequently queried columns
- paginate large datasets
- use JSON strategically rather than excessively

### Files

- keep uploads outside the web root
- stream large files when possible
- rely on cleanup jobs for abandoned attachments

### Email and webhook queues

- delivery is asynchronous
- email and webhook retries use backoff behavior
- slow endpoints affect webhook throughput

### Router overhead

The cron router is intentionally lightweight and primarily dispatches work rather than performing long-running business logic itself.

---

## Troubleshooting

### Cron tasks not running

1. verify the crontab entry exists
2. inspect `cron.log`
3. inspect `cron_state.json`
4. verify the task is enabled in cron config
5. inspect the task-specific log

### Emails not sending

1. check queue status
2. verify SMTP configuration
3. inspect `email_errors.log`
4. inspect queue worker logs

### Lock issues

Lock files use `flock()` and should release automatically on exit. If a process is stuck, terminate the process rather than deleting lock files blindly.

### File uploads failing

Check:

- PHP `upload_max_filesize`
- PHP `post_max_size`
- writable storage directories
- MIME and extension validation rules
