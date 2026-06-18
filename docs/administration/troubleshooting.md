# Administration Troubleshooting

## Table of contents

1. [Forms and visibility](#forms-and-visibility)
2. [Permissions and access](#permissions-and-access)
3. [Notifications and delivery](#notifications-and-delivery)
4. [Webhooks and integrations](#webhooks-and-integrations)
5. [Escalation guidance](#escalation-guidance)

---

## Forms and visibility

### Problem: a form is not visible to users

Check:

- form status is published
- deadline has not passed
- prerequisite rules are satisfied
- the user has the right access context

### Problem: a program is not accepting applications

Check:

- program status
- attached form requirements
- deadline and closure settings

---

## Permissions and access

### Problem: a staff member cannot access expected features

Check:

- global role assignment
- form-specific permissions
- account active status
- whether the user needs to sign out and back in after a role change

---

## Notifications and delivery

### Problem: emails are not arriving

Check:

- SMTP configuration
- email template active state
- queue processing status
- email-related logs

### Problem: reminders or automated messages are delayed

Check that cron is active and background tasks are processing normally.

---

## Webhooks and integrations

### Problem: webhook not firing or failing

Check:

- webhook is active
- form-to-webhook assignment exists
- destination URL is valid and reachable
- receiving endpoint returns a 2xx response
- signature verification logic matches the configured secret

---

## Escalation guidance

Escalate to the technical team when the issue appears to involve:

- deployment or server configuration
- cron execution failure
- storage permissions
- persistent email queue failures
- code defects or broken UI behavior
