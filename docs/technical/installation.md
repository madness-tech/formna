# Installation and Deployment

## Table of contents

1. [Requirements](#requirements)
2. [Installation workflow](#installation-workflow)
3. [Configuration](#configuration)
4. [Database setup](#database-setup)
5. [Web server setup](#web-server-setup)
6. [Cron setup](#cron-setup)
7. [Permissions and verification](#permissions-and-verification)

---

## Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2+ |
| PHP extensions | `pdo_mysql`, `openssl`, `mbstring`, `json`, `fileinfo`, `curl` |
| Database | MySQL 8.0+ or MariaDB 10.6+ |
| Web server | Apache 2.4+ with `mod_rewrite` or Nginx |
| Composer | 2.x |
| Node.js | 18+ for local CSS work only |

---

## Installation workflow

### Bare-metal installation

#### 1. Install PHP dependencies

```bash
composer install
```

#### 2. Create application configuration

```bash
cp .env.example .env
```

Edit `.env` and update:

- `DB_HOST=localhost` (change from default `db` for bare-metal)
- `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `APP_KEY` (generate using step 3)
- `MAIL_*` SMTP settings
- `APP_URL`, `APP_ENV`, session settings

#### 3. Generate encryption key

```bash
php bin/formna generate-key
```

Copy the output into `.env` as `APP_KEY`.

#### 4. Create the database

```sql
CREATE DATABASE formna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON formna.* TO 'formna_user'@'localhost' IDENTIFIED BY 'secure_password';
FLUSH PRIVILEGES;
```

#### 5. Run first-time setup

```bash
php bin/formna install
```

This command will:

- Verify environment and dependencies
- Run database migrations
- Prompt to create your first super admin user

### Docker installation

#### 1. Create application configuration

```bash
cp .env.example .env
```

The defaults work with Docker out of the box:
- `DB_HOST=db` (required for Docker)
- `DB_USER=formna`
- `DB_PASSWORD=formna_secret`

You can customize the database name, user, and password in `.env` — Docker Compose will use these values to create the database.

#### 2. Start containers

```bash
docker compose up -d
```

#### 3. Run first-time setup

```bash
docker compose exec app php bin/formna install
```

This replaces the placeholder `APP_KEY` with a secure random key, runs migrations, and creates your first admin user.

#### 4. Access the application

Visit `http://localhost:8888`

---

## Configuration

FORMNA uses a `.env` file for all configuration. Key settings include:

| Setting | Description | Default |
|---------|-------------|---------|
| `APP_NAME` | Organization name | FORMNA |
| `APP_ENV` | Environment (development/production) | development |
| `APP_URL` | Base URL of the application | http://localhost:8888 |
| `APP_KEY` | 32-character encryption key | (must generate) |
| `DB_HOST` | Database host (`db` for Docker, `localhost` for bare-metal) | db |
| `DB_PORT` | Database port | 3306 |
| `DB_NAME` | Database name | formna |
| `DB_USER` | Database user | formna |
| `DB_PASSWORD` | Database password | formna_secret |
| `MAIL_HOST` | SMTP server hostname | smtp.example.com |
| `MAIL_PORT` | SMTP port | 587 |
| `MAIL_USERNAME` | SMTP username | - |
| `MAIL_PASSWORD` | SMTP password | - |
| `MAIL_ENCRYPTION` | SMTP encryption (tls/ssl) | tls |
| `SESSION_SECURE` | Require HTTPS for sessions | false |
| `SESSION_HTTPONLY` | HttpOnly cookie flag | true |
| `SESSION_SAMESITE` | SameSite cookie policy | Lax |

For production deployments:
- Set `APP_ENV=production`
- Generate a strong `APP_KEY`
- Set `SESSION_SECURE=true` (HTTPS only)
- Configure actual SMTP credentials

---

## Database setup

Database migrations are managed with Phinx. The `bin/formna install` command runs migrations automatically.

To run migrations manually:

```bash
php bin/formna migrate
```

To create additional super admin users:

```bash
php bin/formna create-admin
```

For schema extension work, create new Phinx migrations in `database/migrations/`.

---

## Web server setup

### Apache

Set Apache `DocumentRoot` to the `public/` directory.

```apache
<VirtualHost *:443>
    ServerName forms.example.com
    DocumentRoot /var/www/formna/public

    <Directory /var/www/formna/public>
        AllowOverride All
        Require all granted
    </Directory>

    # SSL configuration
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
</VirtualHost>
```

Ensure `mod_rewrite` is enabled. The included `.htaccess` handles URL rewriting.

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name forms.example.com;
    root /var/www/formna/public;
    index index.php;

    # SSL configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### Production recommendations

- Serve over HTTPS only (set `SESSION_SECURE=true` in `.env`)
- Ensure `storage/` and `public/uploads/` are writable by the web server user
- Keep `.env` secure (never commit to version control)
- Regularly back up the database and `storage/uploads/`

---

## Cron setup

FORMNA uses a **single cron router**. Only one crontab entry is required.

```cron
* * * * * cd /var/www/formna && php src/cron/router.php >> storage/logs/cron.log 2>&1
```

The router dispatches scheduled jobs such as:

- Email queue processing
- Webhook queue processing
- Token cleanup
- Rate-limit cleanup
- Orphaned upload cleanup
- Reminder notifications
- Audit and notification retention purges

Check queue status:

```bash
php bin/formna queue-status
```

See [Operations and maintenance](./operations.md) for job details.

---

## Permissions and verification

### Typical directory permissions

```bash
chmod -R 775 storage/cache
chmod -R 775 storage/logs
chmod -R 775 storage/uploads
chmod -R 775 storage/reports
chmod -R 775 public/uploads/branding
```

### Typical ownership

```bash
chown -R www-data:www-data storage
chown -R www-data:www-data public/uploads
```

### Environment verification

Run the built-in environment checker:

```bash
php bin/formna check
```

This verifies:
- PHP version and extensions
- Composer dependencies
- `.env` file existence
- Directory permissions

### Verification checklist

1. Visit the login page at your `APP_URL`
2. Log in with super admin credentials
3. Open the admin dashboard
4. Create a sample form and test submission
5. Test email delivery from the system
6. Verify cron jobs are running (`php bin/formna queue-status`)
