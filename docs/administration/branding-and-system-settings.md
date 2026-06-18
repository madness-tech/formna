# Branding and System Settings

## Table of contents

1. [Branding basics](#branding-basics)
2. [Custom CSS override](#custom-css-override)
3. [Language management](#language-management)
4. [Security settings](#security-settings)
5. [Audit logs](#audit-logs)

---

## Branding basics

Super admins can usually configure:

- site name
- primary color
- light and dark logos

This covers standard brand alignment for most deployments.

---

## Custom CSS override

For advanced visual customization, FORMNA supports a file-based custom CSS override mode. This is primarily a technical customization feature, but administrators should understand its operational effect:

- if the override file is active, branding UI changes are effectively disabled
- the custom stylesheet becomes the controlling layer for appearance
- this mode should be coordinated with the technical team

Technical implementation details and the sample override file are documented in [Technical → Design system and UI customization](../technical/design-system.md).

---

## Language management

Administrators can manage languages by:

- adding new languages
- editing translations
- enabling or disabling languages
- setting the default language
- supporting RTL languages where applicable

Translation governance tips:

- keep terminology consistent across forms and emails
- validate changes in both admin and user flows
- verify help text separately from UI labels

---

## Security settings

Administrative settings may include:

- MFA enforcement decisions
- AI integration settings
- email configuration
- password and session policies

Changes here have system-wide effect and should be documented internally.

---

## Audit logs

Audit logs provide visibility into administrative activity such as:

- user changes
- form and program updates
- permission modifications
- system configuration changes

Use audit records for support, governance, and compliance reviews.
