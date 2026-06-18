# Technical Documentation

Developer and operator documentation for FORMNA.

This section explains how the platform is built, how to deploy it, how to extend it safely, and how to operate it in production.

---

## Who this section is for

- backend developers
- frontend developers working on the existing UI stack
- maintainers and implementers
- DevOps and infrastructure engineers
- technical auditors reviewing architecture or security

---

## Table of contents

1. [Architecture](./architecture.md)
2. [Installation and deployment](./installation.md)
3. [Database reference](./database.md)
4. [Development and extension](./development.md)
5. [Integrations and APIs](./integrations.md)
6. [Operations and maintenance](./operations.md)
7. [Security](./security.md)
8. [Design system and UI customization](./design-system.md)

---

## What FORMNA is

FORMNA is a self-hosted form and application management platform built with plain PHP 8.2+, MySQL, compiled Tailwind CSS, and vanilla JavaScript. It supports:

- dynamic forms with conditional logic
- multi-form programs and staged review workflows
- applicant clarifications and notification flows
- role-based administration
- queued email and webhook processing
- branding, language, and UI customization

---

## Recommended reading order

1. [Architecture](./architecture.md)
2. [Installation and deployment](./installation.md)
3. [Development and extension](./development.md)
4. [Operations and maintenance](./operations.md)
5. [Security](./security.md)

---

## Quick links

| Task | Document |
|---|---|
| Understand project structure | [Architecture](./architecture.md) |
| Install a new environment | [Installation and deployment](./installation.md) |
| Add a new module | [Development and extension](./development.md) |
| Review schema and core entities | [Database reference](./database.md) |
| Work with AJAX APIs or webhooks | [Integrations and APIs](./integrations.md) |
| Configure cron and production jobs | [Operations and maintenance](./operations.md) |
| Review security controls | [Security](./security.md) |
| Update CSS or UI components | [Design system and UI customization](./design-system.md) |
