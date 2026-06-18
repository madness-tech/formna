# Getting Started

## Table of contents

1. [First login](#first-login)
2. [Role overview](#role-overview)
3. [Admin dashboard](#admin-dashboard)
4. [Recommended first-day setup](#recommended-first-day-setup)

---

## First login

After deployment:

1. open the platform login page
2. sign in with your administrator credentials
3. complete MFA if required
4. confirm you can access the admin dashboard

---

## Role overview

| Role | Primary responsibility |
|---|---|
| Super Admin | full system access and global configuration |
| Admin | form and program management for owned areas |
| Reviewer | review assigned submissions and applications |
| User | applicant and end-user access only |

Higher roles inherit lower-role abilities, but form-specific permissions may also be used to grant narrower access without broader role elevation.

---

## Admin dashboard

The admin dashboard is the control center for day-to-day operations. Depending on role, it may show:

- form, program, and submission counts
- pending reviews
- active clarification requests
- quick links for creation and review tasks
- recent activity and alerts

Typical navigation areas include:

- dashboard
- forms
- programs
- submissions or review queue
- reports
- users
- settings

---

## Recommended first-day setup

For a new deployment, complete these actions first:

1. verify site branding and organization name
2. confirm SMTP works using a test email
3. configure user roles and invite internal staff
4. create a pilot form
5. create a pilot program if applicable
6. review notification templates
7. verify audit logging and cron-driven background jobs
