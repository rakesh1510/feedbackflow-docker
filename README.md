# FeedbackFlow — Complete LAMP Edition (37 Modules)

A production-ready multi-tenant SaaS feedback management platform.  
Stack: **PHP 8+ · MySQL · Apache · Tailwind CSS · Alpine.js · Chart.js**

---

## Quick Start

### Requirements
- PHP 8.0+ with `pdo`, `pdo_mysql`, `curl`, `openssl`
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with `mod_rewrite`

### Installation

1. **Upload files** to your server (e.g. `/var/www/html/feedbackflow/`)

2. **Create database**
```sql
CREATE DATABASE feedbackflow CHARACTER SET utf8mb4;
CREATE USER 'ffuser'@'localhost' IDENTIFIED BY 'yourpassword';
GRANT ALL PRIVILEGES ON feedbackflow.* TO 'ffuser'@'localhost';
```

3. **Import schema**
```bash
mysql -u ffuser -p feedbackflow < install.sql
```

4. **Edit `config.php`**
```php
define('DB_NAME', 'feedbackflow');
define('DB_USER', 'ffuser');
define('DB_PASS', 'yourpassword');
define('APP_URL', 'https://yourdomain.com');
define('SECRET_KEY', 'your-64-char-random-string');
```

5. **Set permissions**
```bash
chmod 777 uploads/
```

6. **Add cron job** (background jobs)
```bash
* * * * * php /var/www/html/feedbackflow/jobs/worker.php
```

### Login
- URL: `/admin/`
- Email: `admin@demo.com`
- Password: `Admin1234!`
- **⚠️ Change immediately!**

---

## All 37 Modules

| Module | Admin Page |
|--------|-----------|
| Dashboard & Stats | admin/index.php |
| Feedback Inbox | admin/feedback.php |
| Projects | admin/projects.php |
| Channels & Widget | admin/channels.php, admin/widget.php |
| Suppression List (07) | admin/suppression.php |
| Tasks (08) | admin/tasks.php |
| Analytics (09) | admin/analytics.php |
| AI Features (10) | admin/ai-insights.php, admin/ai-copilot.php |
| Review Booster (11) | admin/review-booster.php |
| Automation Workflows (12) | admin/automations.php |
| Public Board & Roadmap (13) | public/board.php, admin/roadmap.php |
| Usage & Limits (14) | admin/billing.php |
| Pricing Plans (15) | admin/billing.php |
| Billing & Payments (16) | admin/billing.php |
| Invoices (17) | admin/billing.php |
| GDPR / Legal (18) | admin/export.php |
| Multi-Language (19) | lang/en.php, lang/es.php, lang/fr.php |
| Timezone (20) | config.php |
| Security / CSRF (21) | includes/functions.php |
| Apache Routing (22) | .htaccess |
| Super Admin (23) | admin/super-admin.php |
| Reports & Metrics (24) | admin/reports.php |
| Notifications (25) | admin/notifications.php |
| Audit Logs (26) | admin/audit-logs.php |
| Export & Import (27) | admin/export.php |
| Background Jobs (28) | jobs/worker.php |
| API Keys (29) | admin/api-keys.php |
| Webhooks (30) | admin/integrations.php |
| Status Pages (31) | admin/status.php, public/status.php |
| Team & Roles | admin/team.php |
| Changelog | admin/changelog.php |
| Email Campaigns | admin/email-campaigns.php |

---

## Optional Configuration

```php
// AI (OpenAI)
define('OPENAI_API_KEY', 'sk-...');

// Stripe Billing
define('STRIPE_SECRET_KEY', 'sk_live_...');

// SMTP Email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'you@gmail.com');
define('SMTP_PASS', 'app-password');

// Slack
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/...');
```

---

## License
MIT — free to use, modify, and resell.
