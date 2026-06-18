# Architecture

## Table of contents

1. [Platform overview](#platform-overview)
2. [Technology stack](#technology-stack)
3. [Design principles](#design-principles)
4. [Project structure](#project-structure)
5. [Module structure](#module-structure)
6. [Request lifecycle](#request-lifecycle)
7. [Core capabilities](#core-capabilities)

---

## Platform overview

FORMNA is a self-hosted application platform for forms, submissions, program applications, reviews, and clarifications. The codebase favors simplicity over framework abstraction and uses mostly functional PHP with small, explicit building blocks.

Key platform capabilities include:

- form creation and versioning
- configurable submission rules
- multi-form programs
- reviewer assignment and staged decisions
- clarification workflows
- audit logging
- webhook delivery and email automation
- multi-language and branding support

---

## Technology stack

### Server-side

| Concern | Technology |
|---|---|
| Language | PHP 8.2+ |
| Routing | Bramus Router |
| Database access | PDO with parameterized queries |
| Email | PHPMailer |
| Migrations | Phinx |
| Encryption | OpenSSL AES-256-CBC |
| Sessions | Native PHP sessions |

### Client-side

| Concern | Technology |
|---|---|
| Styling | Tailwind CSS v4, compiled |
| JavaScript | Vanilla ES6 modules |
| Drag-and-drop ordering | SortableJS |
| Date picker | Flatpickr |
| MFA QR setup | QRCode.js |
| Font | Inter, self-hosted |

---

## Design principles

### Functional-first PHP

The application uses a functional style for core business logic and module code. Classes are generally limited to migrations and third-party packages.

### Explicit boundaries

Routes, controllers, models, validation, and views are separated by responsibility. This keeps changes localized and reduces hidden behavior.

### Security-first defaults

Security is built into the request lifecycle through CSRF protection, parameterized queries, sanitization, secure session settings, role checks, and file validation.

### Operational simplicity

Background work is handled by a single cron router and task scripts rather than a separate queue service. This keeps deployment requirements small and predictable.

---

## Project structure

```text
formna/
├── public/                      # web root
│   ├── index.php                # single entry point
│   ├── .htaccess                # rewrite rules
│   ├── css/app.css              # compiled CSS
│   ├── js/                      # browser scripts
│   └── uploads/                 # public branding assets
├── src/
│   ├── config/                  # app config and routes
│   ├── core/                    # shared framework utilities
│   ├── cron/                    # router, schedule config, tasks
│   ├── layouts/                 # shared HTML layouts
│   ├── lang/                    # translations
│   └── modules/                 # feature modules
├── storage/                     # secure file storage
│   ├── cache/                   # application cache
│   ├── logs/                    # app and cron logs
│   ├── reports/                 # generated reports
│   └── uploads/                 # secure file uploads
├── vendor/                      # Composer dependencies
├── resources/css/               # Tailwind source files
├── database/migrations/         # Phinx migrations
├── bin/formna                   # CLI helper tool
└── docs/                        # this documentation set
```

---

## Module structure

Each feature lives under `src/modules/{module}/`.

| File | Purpose |
|---|---|
| `controllers.php` | Route handlers and page orchestration |
| `models.php` | Database access and module queries |
| `views/` | Templates rendered by layouts |
| `api.php` | JSON endpoints when needed |
| `validation.php` | Input validation rules |
| extra helpers | Module-specific behavior such as scoring or processing |

Representative modules include:

- `forms`
- `submissions`
- `programs`
- `clarifications`
- `users`
- `webhooks`
- `emails`
- `notifications`
- `branding`
- `languages`
- `audit`
- `ai`

---

## Request lifecycle

```text
Browser
  → Web server (Apache/Nginx)
  → public/index.php
  → Composer autoload and core bootstrap
  → route match in src/config/routes.php
  → module controller
  → module model functions and helpers
  → view rendering
  → layout wrapping
  → HTTP response
```

Key bootstrap responsibilities include:

- session initialization
- error handling
- CSRF token setup
- shared helper availability
- authentication and authorization helpers

---

## Core capabilities

### Forms

- multiple question types
- conditional logic
- help text and validation
- draft and published versions
- scoring support

### Programs

- multi-form application packages
- required and optional forms
- stage-based review workflows
- reviewer assignments

### Reviews and clarifications

- queue-based reviewer flows
- per-stage decisions
- applicant clarification requests and response handling

### Administration and operations

- user and role management
- audit logs
- branding and language management
- email templates and webhooks
- background task processing
