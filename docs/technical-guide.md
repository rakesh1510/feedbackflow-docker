# FeedbackFlow — Technical Documentation

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation](#installation)
3. [File Structure](#file-structure)
4. [Configuration (config.php)](#configuration)
5. [Database Schema](#database-schema)
6. [Collection Channels](#collection-channels)
7. [REST API](#rest-api)
8. [Widget JavaScript API](#widget-javascript-api)
9. [Embeddable Inline Form](#embeddable-inline-form)
10. [Email Campaigns](#email-campaigns)
11. [Webhooks](#webhooks)
12. [Security](#security)
13. [Upgrading](#upgrading)
14. [Troubleshooting](#troubleshooting)

---

## System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP       | 8.0     | 8.2+        |
| MySQL     | 5.7     | 8.0+        |
| MariaDB   | 10.3    | 10.6+       |
| Apache    | 2.4     | 2.4+        |
| mod_rewrite | Required | — |
| PHP Extensions | pdo, pdo_mysql, json, mbstring, openssl | + curl, fileinfo |
| Disk space | 50 MB | 500 MB+ (for attachments) |

---

## Installation

### Step 1 — Upload files
Upload the `feedbackflow-lamp/` directory to your web server. Common paths:
```
/var/www/html/feedbackflow/
/home/youruser/public_html/feedbackflow/
```

### Step 2 — Create database
```sql
CREATE DATABASE feedbackflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ffuser'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON feedbackflow.* TO 'ffuser'@'localhost';
FLUSH PRIVILEGES;
```

### Step 3 — Configure
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'feedbackflow');
define('DB_USER', 'ffuser');
define('DB_PASS', 'strong_password');
define('APP_URL',  'https://feedback.yourcompany.com');
define('APP_NAME', 'FeedbackFlow');
```

### Step 4 — Run the web installer
Navigate to:
```
https://yourdomain.com/feedbackflow/install.php
```
The installer will:
- Check all system requirements
- Create all database tables
- Create your admin account
- Seed demo data (optional)

### Step 5 — Delete install.php
```bash
rm /var/www/html/feedbackflow/install.php
```

### Existing database — import manually
```bash
mysql -u ffuser -p feedbackflow < install.sql
```

---

## File Structure

```
feedbackflow-lamp/
├── config.php                  ← Main configuration file
├── install.php                 ← Web installer (delete after setup)
├── install.sql                 ← Full database schema
├── db-channels-migration.sql  ← Migration for channels feature
├── index.php                   ← Login page / redirect
├── .htaccess                   ← Apache security rules
│
├── includes/
│   ├── db.php                  ← PDO database wrapper (DB class)
│   ├── auth.php                ← Session authentication (Auth class)
│   ├── functions.php           ← Helpers, CSRF, rate limiting, AI, webhooks
│   ├── header.php              ← HTML head + CSS
│   ├── sidebar.php             ← Navigation sidebar
│   └── footer.php              ← Closing HTML
│
├── admin/
│   ├── index.php               ← Dashboard
│   ├── feedback.php            ← Feedback inbox + detail view
│   ├── analytics.php           ← Charts and stats
│   ├── roadmap.php             ← Kanban roadmap
│   ├── changelog.php           ← Publish changelog entries
│   ├── projects.php            ← Multi-project management
│   ├── team.php                ← User/role management
│   ├── channels.php            ← Collection channels overview
│   ├── email-campaigns.php     ← Email campaign builder & sender
│   ├── widget.php              ← Widget embed code
│   ├── integrations.php        ← Slack, Jira, Zapier, webhooks
│   └── settings.php            ← Profile, password, GDPR, system
│
├── public/
│   ├── board.php               ← Public feedback board (voting + submit)
│   ├── roadmap.php             ← Public roadmap
│   ├── changelog.php           ← Public changelog
│   └── feedback-link.php       ← Universal form (email/QR/SMS/WhatsApp)
│
├── api/
│   ├── feedback.php            ← REST: list + submit feedback
│   └── vote.php                ← REST: vote on feedback
│
├── widget/
│   ├── widget.js               ← Floating widget script
│   ├── embed-form.js           ← Inline embedded form script
│   ├── config.php              ← Widget config API endpoint
│   └── submit.php              ← Widget form submission handler
│
├── uploads/
│   ├── .htaccess               ← Blocks PHP execution in uploads
│   ├── attachments/            ← Feedback file attachments
│   └── avatars/                ← User avatar images
│
└── docs/
    ├── user-guide.md           ← This file's companion
    └── technical-guide.md      ← This file
```

---

## Configuration

All settings live in `config.php`. Below is a full reference:

```php
// ── Database ────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'feedbackflow');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ── Application ─────────────────────────────────
define('APP_NAME',    'FeedbackFlow');
define('APP_URL',     'http://localhost/feedbackflow');  // No trailing slash
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE','UTC');

// ── Security ─────────────────────────────────────
define('APP_SECRET',  'change-this-to-random-32-char-string');
define('SESSION_LIFETIME', 86400);  // seconds (24h)

// ── Email / SMTP ─────────────────────────────────
define('MAIL_FROM',   'noreply@yourcompany.com');
define('SMTP_HOST',   '');   // Leave empty to use PHP mail()
define('SMTP_PORT',   587);
define('SMTP_USER',   '');
define('SMTP_PASS',   '');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// ── AI Features (optional) ───────────────────────
define('OPENAI_API_KEY', '');  // Leave empty to disable AI

// ── File Uploads ─────────────────────────────────
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);  // 5 MB
define('UPLOAD_ALLOWED',  ['jpg','jpeg','png','gif','webp','pdf','txt','csv']);

// ── Integrations (optional) ──────────────────────
define('SLACK_WEBHOOK_URL', '');
```

---

## Database Schema

### Core tables

| Table | Purpose |
|-------|---------|
| `ff_users` | Admin users and team members |
| `ff_projects` | Projects (one per product/website) |
| `ff_project_members` | Role assignments per project |
| `ff_feedback` | All submitted feedback |
| `ff_categories` | Feedback categories per project |
| `ff_tags` | Tags per project |
| `ff_feedback_tags` | Many-to-many: feedback ↔ tags |
| `ff_votes` | User votes on feedback |
| `ff_comments` | Comments and internal notes |
| `ff_attachments` | File attachments |
| `ff_roadmap` | Roadmap items |
| `ff_changelog` | Changelog entries |
| `ff_notifications` | In-app notifications |
| `ff_settings` | Key-value settings per project |
| `ff_activity` | Audit log |
| `ff_webhooks` | Webhook endpoint configuration |
| `ff_rate_limits` | Rate limiting for spam protection |
| `ff_sessions` | User sessions |

### Channels tables (added in v1.1)

| Table | Purpose |
|-------|---------|
| `ff_email_campaigns` | Email feedback campaign definitions |
| `ff_campaign_recipients` | Individual email recipients + tracking |
| `ff_feedback_links` | Shareable links for QR/WhatsApp/SMS |

### `ff_feedback` — key columns

| Column | Type | Description |
|--------|------|-------------|
| `source` | varchar(30) | `widget`, `email`, `qr`, `sms`, `whatsapp`, `embedded`, `public`, `in-app` |
| `rating` | tinyint | Star rating 1–5 (null if not provided) |
| `campaign_id` | int | Links to `ff_email_campaigns.id` |
| `status` | enum | `new`, `under_review`, `planned`, `in_progress`, `done`, `declined`, `duplicate` |
| `priority` | enum | `critical`, `high`, `medium`, `low` |
| `ai_sentiment` | enum | `positive`, `neutral`, `negative` |
| `ai_sentiment_score` | decimal | 0.000–1.000 |

---

## Collection Channels

FeedbackFlow collects feedback from 8 channels. All feedback is stored in one `ff_feedback` table with a `source` field to differentiate.

### 1. Website Widget (`source = 'widget'`)
Floating button embedded with one `<script>` tag.
```html
<script src="https://yourfeedbackflow.com/widget/widget.js"
        data-key="PROJECT_WIDGET_KEY" defer></script>
```
Handled by: `widget/widget.js` → `widget/submit.php`

### 2. Email Campaign (`source = 'email'`)
HTML email with star rating buttons. Each recipient gets a unique token URL.
- Managed via: `admin/email-campaigns.php`
- Submission form: `public/feedback-link.php?token=RECIPIENT_TOKEN`
- Star click in email pre-selects rating and opens the form: `?token=TOKEN&r=4`

### 3. QR Code (`source = 'qr'`)
QR code generated via `api.qrserver.com` pointing to:
```
/public/feedback-link.php?slug=PROJECT_SLUG&source=qr
```
Or a unique token link from `ff_feedback_links` for per-location tracking.

### 4. WhatsApp / SMS (`source = 'whatsapp'` / `'sms'`)
Pre-built message templates in `admin/channels.php` → WhatsApp & SMS tab.
Opens the same `feedback-link.php` page with `?source=whatsapp` or `?source=sms`.

### 5. Embedded Inline Form (`source = 'embedded'`)
Static form rendered inline on your page using `embed-form.js`.
```html
<div id="ff-embed-form"></div>
<script>
  window.FFConfig = {
    key: "PROJECT_WIDGET_KEY",
    baseUrl: "https://yourfeedbackflow.com",
    source: "embedded"
  };
</script>
<script src="https://yourfeedbackflow.com/widget/embed-form.js" defer></script>
```

### 6. Public Board (`source = 'public'`)
Users submit directly at:
```
/public/board.php?slug=PROJECT_SLUG
```

### 7. Shareable Direct Links (`source = 'direct'`)
Generic feedback link, trackable, created in `admin/channels.php` → Shareable Links.

### 8. In-App (`source = 'in-app'`)
Use the widget JS API to trigger the widget programmatically inside your app:
```javascript
FeedbackFlow.open();
FeedbackFlow.identify('user@email.com', 'John Doe');
```

---

## REST API

Base URL: `https://yourfeedbackflow.com/api/`

Authentication: Pass `key` (widget key) as a query parameter or POST field.

### GET /api/feedback.php
Returns project info and recent feedback.

**Request:**
```
GET /api/feedback.php?key=PROJECT_KEY&page=1&per_page=20&status=new
```

**Response:**
```json
{
  "success": true,
  "project_color": "#6366f1",
  "categories": [
    { "id": 1, "name": "Bug Report", "color": "#ef4444" }
  ],
  "feedback": [
    {
      "id": 42,
      "title": "Checkout is broken",
      "status": "new",
      "vote_count": 7,
      "created_at": "2026-03-17 10:00:00"
    }
  ],
  "total": 1,
  "page": 1
}
```

### POST /api/feedback.php
Submit new feedback.

**Request fields:**
| Field | Required | Description |
|-------|----------|-------------|
| `key` | Yes | Project widget key |
| `title` | Yes* | Short title |
| `description` | Yes* | Full feedback text |
| `category_id` | No | Category ID |
| `submitter_name` | No | Submitter name |
| `submitter_email` | No | Submitter email |
| `rating` | No | 1–5 star rating |
| `source` | No | Channel source |

*At least one of `title` or `description` required.

**Response:**
```json
{ "success": true, "id": 43 }
```

### POST /api/vote.php
Vote on a feedback item.

**Request fields:**
| Field | Required |
|-------|----------|
| `feedback_id` | Yes |
| `project_key` | Yes |
| `voter_email` | No |

---

## Widget JavaScript API

After the widget script loads, you can control it programmatically:

```javascript
// Open the feedback widget
FeedbackFlow.open();

// Close the widget
FeedbackFlow.close();

// Pre-fill user information
FeedbackFlow.identify('user@example.com', 'John Doe');

// Listen for events
document.addEventListener('ff:submitted', function(e) {
  console.log('Feedback submitted:', e.detail);
});
```

---

## Embeddable Inline Form

The `embed-form.js` script renders a complete feedback form inside any `<div>` on your page.

### Configuration options

```javascript
window.FFConfig = {
  key:            "PROJECT_WIDGET_KEY",  // Required
  baseUrl:        "https://yourfeedbackflow.com", // Required
  theme:          "light",      // "light", "dark", "auto"
  showRating:     true,         // Show star rating
  ratingQuestion: "How was your experience?",
  source:         "embedded",   // Logged as feedback source
  targetId:       "ff-embed-form"  // ID of target div
};
```

The form submits to `/api/feedback.php` via AJAX — no page reload.

---

## Email Campaigns

### Flow
1. Create campaign in `admin/email-campaigns.php` (name, subject, intro, rating question)
2. Add recipients (paste list in `Name, email` format)
3. Preview the rendered email
4. Click **Send** — FeedbackFlow sends one personalised email per recipient
5. Each email contains clickable star buttons + a full feedback link
6. Track opens, clicks, and responses in the **Results** tab

### Email delivery
FeedbackFlow uses PHP's built-in `mail()` function by default.  
For production, configure an SMTP relay in `config.php` or use a service like:
- **Amazon SES** — set SMTP credentials
- **SendGrid** — set SMTP credentials  
- **Mailgun** — set SMTP credentials
- **Postfix** on the same server

### Recipient tracking
Each recipient gets a unique 48-character token. When they open the email or click a star:
1. Their `opened_at` timestamp is recorded
2. Star click pre-selects the rating on the feedback form (`?r=4`)
3. On submission, `submitted_at` and `feedback_id` are linked to their record

---

## Webhooks

Configure outgoing webhooks in `admin/integrations.php`.

Every new feedback submission sends a POST request to your URL:

```json
{
  "event": "feedback.created",
  "project": "my-app",
  "feedback": {
    "id": 42,
    "title": "Checkout is broken",
    "description": "...",
    "category": "Bug Report",
    "status": "new",
    "priority": "high",
    "source": "email",
    "rating": 2,
    "submitter_name": "John",
    "submitter_email": "john@example.com",
    "created_at": "2026-03-17T10:00:00Z"
  }
}
```

**Signature verification** — Each request includes an `X-FeedbackFlow-Signature` header:
```
X-FeedbackFlow-Signature: sha256=HMAC_SHA256(secret, body)
```

Verify in your receiver:
```php
$sig = hash_hmac('sha256', $rawBody, $webhookSecret);
if (!hash_equals($sig, $receivedSig)) { http_response_code(401); exit; }
```

---

## Security

### Built-in protections
- **CSRF tokens** — every POST form includes a validated token
- **Password hashing** — bcrypt via `password_hash()`
- **Rate limiting** — `ff_rate_limits` table prevents submission flooding (10 submissions per IP per hour)
- **File upload protection** — whitelist of allowed extensions; PHP execution blocked in `uploads/` via `.htaccess`
- **SQL injection** — all queries use PDO prepared statements
- **XSS** — all output escaped with `htmlspecialchars()` via `h()` helper
- **Secure headers** — set in `.htaccess` (X-Frame-Options, X-Content-Type-Options, etc.)
- **Session security** — HttpOnly, SameSite=Lax cookies; session regeneration on login

### Recommended hardening
1. **Delete `install.php`** immediately after setup
2. Set `APP_SECRET` to a random 32+ character string
3. Restrict database user to minimum required privileges
4. Enable HTTPS (required for modern browser APIs used by the widget)
5. Set `session.cookie_secure = 1` in `php.ini` when on HTTPS
6. Configure SMTP instead of `mail()` in production

---

## Upgrading

When a new version is released:

1. Back up your database:
   ```bash
   mysqldump -u ffuser -p feedbackflow > backup_$(date +%Y%m%d).sql
   ```
2. Upload new files (overwrite everything except `config.php`)
3. Run any migration SQL files included in the release (e.g. `db-channels-migration.sql`)
4. Clear any opcode cache (`opcache_reset()` or restart PHP-FPM)

### Running the channels migration on an existing install
If you already had FeedbackFlow installed before the channels feature:
```bash
mysql -u ffuser -p feedbackflow < db-channels-migration.sql
```
The migration uses `ADD COLUMN IF NOT EXISTS` and `CREATE TABLE IF NOT EXISTS` — it is safe to run multiple times.

---

## Troubleshooting

### Blank page or 500 error
- Enable PHP error display temporarily: add `ini_set('display_errors', 1);` at the top of `config.php`
- Check Apache error log: `tail -f /var/log/apache2/error.log`
- Verify all `include` paths are correct for your directory structure

### Feedback widget not appearing
- Check the browser console for JavaScript errors
- Verify the widget key in the `data-key` attribute matches your project key in admin
- Ensure `APP_URL` in `config.php` has no trailing slash and matches the actual URL
- CORS: if your website is on a different domain, add it to the allowed origins in `widget/widget.js`

### Emails not sending
- Test `mail()` works on your server: `php -r "mail('you@example.com','Test','Test');"`
- Check server spam filters — use a proper SMTP relay instead of `mail()`
- Ensure `MAIL_FROM` is set to a domain that matches your server's hostname

### QR code not loading
- QR codes use the `api.qrserver.com` service — requires internet access from the browser
- For offline/intranet use, install `chillerlan/php-qrcode` via Composer and generate server-side

### Database connection failed
- Verify credentials in `config.php`
- Check MySQL is running: `systemctl status mysql`
- Test connection: `mysql -u ffuser -p feedbackflow -e "SELECT 1"`

### File uploads failing
- Check `uploads/` directory permissions: `chmod 755 uploads/ uploads/attachments/ uploads/avatars/`
- Check `upload_max_filesize` and `post_max_size` in `php.ini`
- Verify `UPLOAD_MAX_SIZE` in `config.php` does not exceed `php.ini` limits

---

*FeedbackFlow v1.1 — Technical Documentation*
