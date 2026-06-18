<div align="center">

# 📋 FORMNA

**Self-hosted form management platform for organizations**

Build forms, manage submissions, run review workflows, and generate reports — all under your control.

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)

[📖 Documentation](docs/) • [🚀 Quick Start](#-quick-start) • [💬 Support](https://github.com/madness-tech/formna/issues)

</div>

---

## ✨ Features

### 📝 Form Management
- **Drag & Drop Builder** — Create forms with 15+ field types (text, file uploads, scoring, conditionals, etc.)
- **Smart Validation** — Built-in file validation, scoring logic, and custom field rules
- **Multi-language** — Full i18n support (English, Arabic, French, Spanish, German, Danish)

### 🏢 Program & Workflow Management
- **Programs** — Organize forms into programs with deadlines, eligibility rules, and custom permissions
- **Review Workflows** — Multi-stage review with scoring rubrics and collaborative evaluation
- **Clarifications** — Request additional information from applicants mid-review

### 📊 Reports & Analytics
- **Rich Reporting** — Generate exportable reports with automatic caching for performance
- **Audit Logs** — Track every action with comprehensive audit trails
- **Data Exports** — Export submissions in CSV format

### 🔐 Security & Access Control
- **Role-based Permissions** — Fine-grained RBAC for super-admins, admins, reviewers, and users
- **MFA / TOTP** — Two-factor authentication with recovery codes
- **Data Encryption** — Sensitive data encryption at rest

### 🎨 Branding & Customization
- **White-label** — Custom logos, colors, and CSS overrides
- **Email Templates** — Customizable SMTP email notifications with queued delivery
- **Webhook Integrations** — Real-time event notifications to external systems

---

## 🚀 Quick Start

### Option 1: Docker (Recommended for Testing)

```bash
# 1. Clone and configure
git clone https://github.com/madness-tech/formna.git
cd formna
cp .env.example .env

# 2. Start services
docker compose up -d

# 3. Run setup (replaces placeholder APP_KEY, migrates DB, creates admin)
docker compose exec app php bin/formna install

# 4. Visit http://localhost:8888 🎉
```

### Option 2: Bare Metal

```bash
# 1. Clone repository
git clone https://github.com/madness-tech/formna.git
cd formna

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env: set DB credentials, change DB_HOST=localhost (not 'db')

# 4. Generate encryption key
php bin/formna generate-key
# Copy output to .env as APP_KEY

# 5. Run setup
php bin/formna install

# 6. Configure web server (point to public/ directory)
# Visit your APP_URL and log in!
```

---

## 📋 Requirements

| Component | Requirement |
|-----------|-------------|
| **PHP** | 8.2 or higher |
| **Database** | MySQL 8.0+ or MariaDB 10.6+ |
| **Web Server** | Apache (with mod_rewrite) or Nginx |
| **PHP Extensions** | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`, `curl`, `fileinfo` |

---

## 🛠️ Configuration

### Web Server Setup

<details>
<summary><strong>Apache</strong></summary>

The included `public/.htaccess` handles URL rewriting automatically. Ensure:
- `mod_rewrite` is enabled
- `AllowOverride All` is set for the public directory

</details>

<details>
<summary><strong>Nginx</strong></summary>

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/formna/public;
    index index.php;

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

</details>

### Cron Jobs

For background processing (email queue, webhooks, reminders):

```bash
* * * * * cd /path/to/formna && php src/cron/router.php >> storage/logs/cron.log 2>&1
```

---

## 🧰 CLI Tools

FORMNA includes a powerful CLI for administration and maintenance:

```bash
php bin/formna install         # First-time setup (check + migrate + create admin)
php bin/formna migrate         # Run pending database migrations
php bin/formna create-admin    # Create a super-admin user
php bin/formna generate-key    # Generate a random 32-char APP_KEY
php bin/formna queue-status    # Show email/webhook queue stats
php bin/formna check           # Verify environment & dependencies
```

---

## 🏗️ Project Structure

```
formna/
├── bin/                    # CLI tools (install, migrate, diagnostics)
├── database/migrations/    # Database migrations (Phinx)
├── docker/                 # Docker configuration
├── docs/                   # Comprehensive documentation
├── public/                 # Web root (index.php, assets, uploads)
├── resources/css/          # Tailwind CSS source files
├── src/
│   ├── config/             # Route definitions
│   ├── core/               # Framework core (auth, db, helpers, i18n)
│   ├── cron/               # Background task scheduler
│   ├── lang/               # Translation files (UI + help content)
│   ├── layouts/            # PHP layout templates
│   └── modules/            # Feature modules (forms, users, reports, etc.)
├── storage/                # Runtime data (logs, cache, uploads, reports)
└── tests/                  # PHPUnit test suites
```

---

## 📚 Documentation

FORMNA includes comprehensive documentation for all audiences:

| Guide | Audience | Contents |
|-------|----------|----------|
| **[📖 Technical Guide](docs/technical/)** | Developers, DevOps | Architecture, installation, database schema, APIs, security |
| **[⚙️ Administration Guide](docs/administration/)** | Admins | Forms, programs, users, permissions, workflows, reporting |
| **[👤 User Manual](docs/user-manual/)** | End Users | Registration, form filling, submission tracking, clarifications |

**Quick links:**
- [Installation & Deployment](docs/technical/installation.md)
- [Architecture Overview](docs/technical/architecture.md)
- [Administrator Quick Start](docs/administration/getting-started.md)
- [Security Model](docs/technical/security.md)

---

## 🧪 Development

```bash
# Install dependencies with dev tools
composer install

# Run tests
./vendor/bin/phpunit

# Build CSS (requires Node.js)
npm install
npm run css:build

# Watch CSS changes during development
npm run css:watch
```

---

## 📄 License & Commercial Use

FORMNA is open-source software, licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

### ✅ Free Use

Free use is granted to:
- ✅ Non-profit organizations
- ✅ Registered charities
- ✅ Academic and research institutions
- ✅ Government entities (for internal, non-commercial purposes)
- ✅ For-profit companies with annual revenue under **$1M USD**

### 💼 Commercial License Required

A commercial license is required for:
- ❌ Enterprises and large organizations (revenue ≥ $1M USD)
- ❌ Healthcare providers
- ❌ Financial services firms
- ❌ Government entities using FORMNA for public/commercial purposes
- ❌ SaaS providers deploying FORMNA for external clients

**Commercial licensing includes:**
- 🎯 Implementation assistance and onboarding
- 🛟 Priority support and maintenance
- 📈 Custom feature development options

📧 **Contact:** [hello@madness.tech](mailto:hello@madness.tech)

---

## ⚠️ Disclaimer

FORMNA is provided **"as is"**, without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose, and non-infringement. 

In no event shall MADNESS (FZE), its principals, employees, or contributors be liable for any claim, damages, or other liability — whether in an action of contract, tort, or otherwise — arising from, out of, or in connection with the software or the use or other dealings in the software.

**Your Responsibilities:**
- Evaluate FORMNA's suitability for your intended use
- Ensure compliance with applicable laws and regulations in your jurisdiction
- Maintain the security and integrity of your deployment

---

## 🙏 Acknowledgments

FORMNA is built with:
- [Bramus Router](https://github.com/bramus/router) — Fast PHP routing
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) — Email sending
- [Phinx](https://phinx.org/) — Database migrations
- [Parsedown](https://parsedown.org/) — Markdown parsing
- [Tailwind CSS](https://tailwindcss.com/) — Utility-first CSS framework

---

<div align="center">

**Made with ❤️ by [MADNESS (FZE)](https://madness.tech)**

[⭐ Star on GitHub](https://github.com/madness-tech/formna) • [🐛 Report a Bug](https://github.com/madness-tech/formna/issues) • [🔒 Security Policy](SECURITY.md)

</div>
