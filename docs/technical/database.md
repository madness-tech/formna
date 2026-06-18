# Database Reference

## Table of contents

1. [Data model principles](#data-model-principles)
2. [Core tables](#core-tables)
3. [System tables](#system-tables)
4. [Schema conventions](#schema-conventions)

---

## Data model principles

- UUIDs are used for public identifiers.
- Numeric IDs are used internally for joins and foreign keys.
- JSON columns are used for flexible configuration and answer payloads.
- UTC is used for stored timestamps.
- auditability and retention are first-class concerns.

---

## Core tables

### `users`

Stores account profile, authentication, role, status, and timezone information.

### `forms`

Stores form metadata including name, instructions, status, settings, and author.

### `form_versions`

Tracks published and draft revisions for forms.

### `questions`

Stores question definitions, type, configuration, ordering, and soft-delete state.

### `submissions`

Stores user responses, status, score, timestamps, and the form version used for the submission.

### `programs`

Stores multi-form program definitions, configuration, and publication status.

### `program_submissions`

Stores application-level records for programs, including stage and review status.

### `program_submission_entries`

Links program applications to the form submissions included in them.

### `clarification_requests`

Stores reviewer clarification requests, item-level messages, status, and feedback.

### `files`

Stores uploaded file metadata and the secure storage path for submission attachments.

### `webhooks`, `form_webhooks`, `webhook_log`

Store webhook configuration, form associations, delivery attempts, and retry state.

---

## System tables

### `audit_log`
Administrative and system activity history.

### `notifications`
In-app notification records.

### `email_templates`
Configurable email message templates.

### `job_queue`
Background job records for email and related asynchronous work.

### `languages`
Language metadata and translation storage.

### `user_mfa`
TOTP secrets and MFA-related account data.

### `rate_limits`
Temporary records used for throttling operations such as login and email resend.

### `verification_tokens`
Email verification and password reset tokens.

### `ai_summaries`
Generated AI summaries for supported entities.

### `site_settings`
Singleton-style global platform settings such as branding and configuration flags.

---

## Schema conventions

### JSON-heavy configuration

The schema intentionally uses JSON for:

- question configuration
- form settings
- answer payloads
- program decisions
- translation data

This keeps schema evolution easier for configurable product behavior while retaining relational anchors for the major domain entities.

### Foreign keys and retention

Foreign keys are used where appropriate, with cleanup and purge routines handling long-term maintenance of logs, notifications, and queue-related data.

### Practical next step

When adding features:

1. model public-facing records with UUIDs
2. use migrations for schema change
3. keep flexible config in JSON only when it improves maintainability
4. update documentation when new entities or states are introduced
