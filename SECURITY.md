# Security Policy

FORMNA takes security seriously. We appreciate the security research community's efforts to help keep our users safe.

## 🔒 Supported Versions

Security updates are provided for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | ✅ Current release |
| < 1.0   | ❌ Not supported   |

**Recommendation:** Always run the latest stable version for the best security and features.

---

## 🚨 Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in FORMNA, please report it responsibly:

### How to Report

📧 **Email:** [hello@madness.tech](mailto:hello@madness.tech)

Please include:

1. **Description** — Clear explanation of the vulnerability
2. **Impact** — Potential security impact and severity
3. **Reproduction** — Step-by-step instructions to reproduce
4. **Environment** — PHP version, database, OS, web server
5. **Suggested fix** — If you have one (optional)
6. **Your contact info** — For follow-up questions

### What to Expect

- **Acknowledgment** — We'll respond within **48 hours**
- **Updates** — We'll keep you informed of our progress
- **Resolution** — We aim to fix critical issues within **7 days**
- **Credit** — We'll credit you in release notes (if you wish)
- **Public disclosure** — After a fix is released, typically within 30 days

### Confidentiality

Please:
- **Do not** disclose the vulnerability publicly until we've released a fix
- **Do not** exploit the vulnerability beyond what's necessary to demonstrate it
- Give us reasonable time to address the issue before disclosure

---

## 🛡️ Security Best Practices

When deploying FORMNA, follow these security guidelines:

### 1. Environment Configuration

✅ **Use strong encryption keys**
```bash
php bin/formna generate-key
# Copy the generated 32-character key to .env as APP_KEY
```

✅ **Set production environment**
```env
APP_ENV=production
```

✅ **Enable secure sessions over HTTPS**
```env
SESSION_SECURE=true    # Only if using HTTPS
SESSION_HTTPONLY=true
SESSION_SAMESITE=Lax
```

✅ **Configure trusted proxies** (if behind a proxy/load balancer)
```env
APP_TRUSTED_PROXIES=127.0.0.1,173.245.48.0
```

### 2. Database Security

✅ **Use strong database credentials**
- Avoid default usernames like 'root' or 'admin'
- Use complex passwords (20+ characters)
- Grant only necessary privileges

✅ **Restrict database access**
- Bind MySQL to localhost if not using remote connections
- Use firewall rules to limit database access

### 3. File Permissions

Set appropriate file permissions on your server:

```bash
# Application files (read-only for web server)
chmod -R 755 /path/to/formna
chmod -R 644 /path/to/formna/public/*.php

# Writable directories
chmod -R 775 /path/to/formna/storage
chmod -R 775 /path/to/formna/public/uploads

# Protect sensitive files
chmod 600 /path/to/formna/.env
```

### 4. Web Server Configuration

✅ **Point document root to public/ directory**
- Never expose the application root to the web
- Only `public/` should be web-accessible

✅ **Disable directory listing**
```apache
# Apache (.htaccess in public/)
Options -Indexes
```

```nginx
# Nginx
autoindex off;
```

✅ **Block access to sensitive files**
```nginx
# Nginx example
location ~ /\. {
    deny all;
}

location ~ \.env {
    deny all;
}
```

### 5. HTTPS/TLS

✅ **Use HTTPS in production**
- Obtain a free SSL certificate from [Let's Encrypt](https://letsencrypt.org/)
- Redirect all HTTP traffic to HTTPS
- Use HSTS headers

✅ **Use secure session cookies**
```env
SESSION_SECURE=true  # Cookies only sent over HTTPS
```

### 6. Updates & Patches

✅ **Stay updated**
- Monitor [FORMNA releases](https://github.com/madness-tech/formna/releases)
- Subscribe to security announcements
- Test updates in a staging environment first

✅ **Keep dependencies current**
```bash
composer update
npm update
```

### 7. Backups

✅ **Regular backups**
- Database backups (daily or more frequent)
- Uploaded files (`storage/uploads/`, `public/uploads/`)
- Configuration files (`.env`)
- Test restoration procedures

### 8. Monitoring

✅ **Review logs regularly**
- Check `storage/logs/` for suspicious activity
- Monitor audit log data through the admin panel for unauthorized access attempts
- Set up log rotation to prevent disk space issues

✅ **Monitor failed login attempts**
- FORMNA automatically rate-limits login attempts
- Review patterns in audit logs

### 9. User Management

✅ **Strong password policies**
- Enforce minimum password requirements (configured in system settings)
- Enable MFA/TOTP for admin accounts
- Regularly review user accounts and remove inactive users

✅ **Principle of least privilege**
- Grant users only the permissions they need
- Use Reviewer role instead of Admin when full access isn't required
- Regularly audit user permissions

### 10. Rate Limiting

FORMNA includes built-in rate limiting for:
- Login attempts (5 per 15 minutes per IP)
- API endpoints
- Password reset requests

Rate limits are configurable and logged.

---

## 🔐 Security Features

FORMNA includes several security features out of the box:

### Authentication & Authorization
- ✅ Secure password hashing (bcrypt)
- ✅ Multi-factor authentication (TOTP)
- ✅ Email verification for new accounts
- ✅ Rate limiting on authentication endpoints
- ✅ Recovery codes for MFA backup
- ✅ Fine-grained role-based access control (RBAC)

### Data Protection
- ✅ Encryption of sensitive data at rest
- ✅ Prepared statements (SQL injection protection)
- ✅ CSRF protection on all forms
- ✅ XSS protection via output escaping
- ✅ File upload validation and sanitization

### Audit & Compliance
- ✅ Comprehensive audit logging
- ✅ IP address tracking
- ✅ User action history
- ✅ Configurable data retention policies

### Session Security
- ✅ Secure session management
- ✅ Session regeneration on login
- ✅ Configurable session timeouts
- ✅ HttpOnly and Secure cookie flags

---

## 🔍 Known Issues

Current known security considerations:

### None at this time

We'll list any known security issues here until they're resolved. Check back regularly or watch the repository for updates.

---

## 📋 Security Checklist

Use this checklist when deploying FORMNA:

- [ ] Generated strong APP_KEY
- [ ] Set APP_ENV=production
- [ ] Using HTTPS with valid SSL certificate
- [ ] SESSION_SECURE=true (if using HTTPS)
- [ ] Strong database credentials
- [ ] File permissions properly set
- [ ] Web server document root points to public/
- [ ] Directory listing disabled
- [ ] .env file not web-accessible
- [ ] Firewall configured
- [ ] Regular backups scheduled
- [ ] Log monitoring in place
- [ ] Dependencies up to date
- [ ] MFA enabled for admin accounts

---

## 📚 Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)

---

## 📞 Contact

For security-related questions or concerns:

📧 **Email:** [hello@madness.tech](mailto:hello@madness.tech)

For non-security issues, use [GitHub Issues](https://github.com/madness-tech/formna/issues).

---

Thank you for helping keep FORMNA and our users safe! 🙏
