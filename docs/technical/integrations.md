# Integrations and APIs

## Table of contents

1. [Internal APIs](#internal-apis)
2. [Webhook model](#webhook-model)
3. [Webhook payload](#webhook-payload)
4. [Authentication](#authentication)
5. [Integration guidance](#integration-guidance)

---

## Internal APIs

FORMNA includes internal authenticated JSON endpoints for browser-driven interactions.

### Questions

```text
POST   /api/questions
GET    /api/questions/{uid}
POST   /api/questions/{uid}
DELETE /api/questions/{uid}
POST   /api/questions/reorder
```

### Drafts

```text
POST   /api/drafts/{form_uuid}
GET    /api/drafts/{form_uuid}
DELETE /api/drafts/{form_uuid}
```

### Files

```text
POST   /api/files/upload
DELETE /api/files/{id}
```

### Notifications

```text
GET    /api/notifications
POST   /api/notifications/{id}/read
POST   /api/notifications/read-all
POST   /api/notifications/clear-all
```

### Clarifications

```text
POST   /api/clarifications/{uuid}/resolve
POST   /api/clarifications/{uuid}/reject
POST   /api/clarifications/{uuid}/cancel
```

### Admin-specific APIs

```text
POST   /api/admin/ai/summarize
GET    /api/admin/ai/summary/{type}/{uuid}
GET    /api/admin/forms/{uuid}/admins
POST   /api/admin/forms/{uuid}/admins
PATCH  /api/admin/forms/{uuid}/admins/{id}
DELETE /api/admin/forms/{uuid}/admins/{id}
GET    /api/admin/users/search
```

---

## Webhook model

Webhooks are configured per form and triggered when a form submission event occurs.

Current documented behavior includes:

- one webhook per form
- Bearer token authentication
- retry attempts with backoff
- delivery logs and manual testing support

---

## Webhook payload

```json
{
  "event": "form.submitted",
  "timestamp": "2026-06-18T15:00:00Z",
  "form": {
    "id": "form-uuid",
    "name": "Application Form",
    "version": 2
  },
  "submission": {
    "id": "submission-uuid",
    "submitted_at": "2026-06-18T15:00:00Z",
    "user": {
      "id": 123,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe"
    },
    "answers": {
      "question_uid": "answer value"
    },
    "score": 85
  }
}
```

File-answer payloads may include file IDs, filenames, and authenticated download URLs.

---

## Authentication

Webhooks use **Bearer token authentication**. Each webhook includes a configurable bearer token that is sent in the `Authorization` header.

```http
POST /your-endpoint HTTP/1.1
Host: example.com
Content-Type: application/json
Authorization: Bearer your_webhook_bearer_token_here
User-Agent: FORMNA-Webhook/1.0
```

Your webhook endpoint should validate the Bearer token:

```php
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$expectedToken = 'your_webhook_bearer_token_here';

if ($authHeader !== "Bearer {$expectedToken}") {
    http_response_code(401);
    exit('Unauthorized');
}

$payload = file_get_contents('php://input');
// Process webhook...
```

---

## Integration guidance

### For outgoing integrations

- keep destination endpoints fast
- return 2xx status codes on success
- log payloads on the receiving side
- verify Bearer tokens before processing

### For AI integration

The platform can optionally use OpenAI-backed summaries for reviewers. Documented settings include:

- API key
- model selection
- token limits
- feature enablement
- connection testing

### For email integration

All email delivery is SMTP-based and queue-driven. Test template rendering and delivery before production rollout.
