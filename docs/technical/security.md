# Security

## Table of contents

1. [Authentication and authorization](#authentication-and-authorization)
2. [Data protection](#data-protection)
3. [Network and integration security](#network-and-integration-security)
4. [Audit and compliance](#audit-and-compliance)
5. [Operational recommendations](#operational-recommendations)

---

## Authentication and authorization

Documented security controls include:

- bcrypt password hashing
- secure session flags
- session regeneration on login
- session versioning for forced logout
- email verification workflows
- optional or enforced TOTP MFA
- role hierarchy: `super_admin > admin > reviewer > user`
- form-specific administrative permissions
- rate limiting for login and email-related actions

---

## Data protection

- CSRF protection on state-changing actions
- parameterized SQL queries through PDO
- output sanitization for rendered content
- AES-256-CBC encryption for sensitive stored secrets
- secure file storage outside the public web root
- MIME-aware upload validation and file-size controls

---

## Network and integration security

- HTTPS should be enforced in production
- webhook payloads use Bearer token authentication
- production integrations should use secure endpoints
- audit trails include IP logging where supported

---

## Audit and compliance

The platform documents support for:

- comprehensive audit logging
- change tracking on administrative actions
- user-linked activity records
- configurable data retention behavior
- privacy and compliance workflows such as account deletion and data export support

---

## Operational recommendations

1. run the platform only behind HTTPS
2. keep encryption keys out of version control
3. verify background workers are active
4. test email and webhook integrations after configuration changes
5. periodically review admin roles and form permissions
6. monitor audit logs for unexpected changes
