# Reporting and Integrations

## Table of contents

1. [Reports and exports](#reports-and-exports)
2. [Email templates](#email-templates)
3. [Webhooks](#webhooks)
4. [Operational checks](#operational-checks)

---

## Reports and exports

Administrators can use reports to monitor:

- submission volume
- status distribution
- application stage progression
- approval and rejection rates
- reviewer activity
- question-level response patterns

CSV export is typically available for forms and programs.

Common export uses:

- offline review
- executive reporting
- archival transfer
- external analytics or BI workflows

---

## Email templates

Administrators, usually super admins, can configure templates for:

- submission confirmations
- application confirmations
- clarification requests and updates
- approval and rejection notifications
- reminders and resets

Common template features include:

- subject and body editing
- placeholders such as user, form, and dashboard values
- enable or disable state
- reset to default
- test send capability

---

## Webhooks

Webhooks allow forms to push submission data into external systems.

Typical administrative tasks:

1. create a webhook endpoint definition
2. set the HTTPS URL and secret
3. assign it to a form
4. test delivery
5. inspect logs and retry failures if needed

When coordinating with technical teams, pair this guide with [Technical → Integrations and APIs](../technical/integrations.md).

---

## Operational checks

Before go-live, confirm:

- reports contain expected data
- exports open cleanly in spreadsheet tools
- email templates render correctly
- SMTP delivery is working
- webhook endpoints verify signatures and return success codes
