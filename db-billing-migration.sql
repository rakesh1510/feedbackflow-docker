-- ============================================================
-- FeedbackFlow — Billing System Migration
-- Run this AFTER install.sql to upgrade to the full pricing system
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. EXTEND ff_billing_plans with new limit columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_billing_plans`
  ADD COLUMN IF NOT EXISTS `max_users`          int(11) NOT NULL DEFAULT 3        AFTER `max_projects`,
  ADD COLUMN IF NOT EXISTS `max_emails`         int(11) NOT NULL DEFAULT 500       AFTER `max_users`,
  ADD COLUMN IF NOT EXISTS `max_whatsapp`       int(11) NOT NULL DEFAULT 0         AFTER `max_emails`,
  ADD COLUMN IF NOT EXISTS `max_sms`            int(11) NOT NULL DEFAULT 0         AFTER `max_whatsapp`,
  ADD COLUMN IF NOT EXISTS `allow_ai`           tinyint(1) NOT NULL DEFAULT 0      AFTER `max_sms`,
  ADD COLUMN IF NOT EXISTS `allow_white_label`  tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_ai`,
  ADD COLUMN IF NOT EXISTS `allow_api`          tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_white_label`,
  ADD COLUMN IF NOT EXISTS `allow_export`       tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_api`,
  ADD COLUMN IF NOT EXISTS `allow_automations`  tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_export`,
  ADD COLUMN IF NOT EXISTS `allow_audit_logs`   tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_automations`,
  ADD COLUMN IF NOT EXISTS `allow_sso`          tinyint(1) NOT NULL DEFAULT 0      AFTER `allow_audit_logs`,
  ADD COLUMN IF NOT EXISTS `description`        varchar(255) DEFAULT NULL           AFTER `name`,
  ADD COLUMN IF NOT EXISTS `highlight_color`    varchar(7)   NOT NULL DEFAULT '#6366f1' AFTER `description`;

-- ────────────────────────────────────────────────────────────
-- 2. UPDATE plan seeds to match pricing spec
-- ────────────────────────────────────────────────────────────
-- Starter: €19/mo  |  Growth: €49/mo  |  Pro: €99/mo  |  Enterprise: €299/mo
DELETE FROM `ff_billing_plans`;

INSERT INTO `ff_billing_plans`
  (`name`, `slug`, `description`, `price_monthly`, `price_yearly`, `currency`,
   `max_projects`, `max_users`, `max_feedback_per_month`, `max_campaigns_per_month`,
   `max_emails`, `max_whatsapp`, `max_sms`, `max_team_members`,
   `allow_ai`, `allow_white_label`, `allow_api`, `allow_export`,
   `allow_automations`, `allow_audit_logs`, `allow_sso`,
   `highlight_color`, `features`, `is_active`, `sort_order`)
VALUES
  ('Starter',    'starter',    'Perfect for small teams getting started',
   19.00, 182.00, 'EUR',  2,  10,   500,  3,   1000,  100,  50,  10,
   0, 0, 0, 0, 0, 0, 0, '#6366f1',
   '["2 projects","10 users","500 feedback/mo","1,000 emails/mo","100 WhatsApp/mo","50 SMS/mo","Public roadmap","Changelog","QR codes","Feedback widget"]',
   1, 1),

  ('Growth',     'growth',     'For growing teams that need more power',
   49.00, 470.00, 'EUR',  5,  25,  2000, 10,   5000,  500, 200,  25,
   1, 0, 1, 1, 1, 1, 0, '#8b5cf6',
   '["5 projects","25 users","2,000 feedback/mo","5,000 emails/mo","500 WhatsApp/mo","200 SMS/mo","AI insights","Automations","Audit logs","API access","Bulk export","Review booster"]',
   1, 2),

  ('Pro',        'pro',        'Advanced tools for professional teams',
   99.00, 950.00, 'EUR', 15, 100, 10000, 50,  20000, 2000,1000, 100,
   1, 1, 1, 1, 1, 1, 0, '#a855f7',
   '["15 projects","100 users","10,000 feedback/mo","20,000 emails/mo","2,000 WhatsApp/mo","1,000 SMS/mo","AI copilot","White-label","Priority support","Custom domain","SSO ready"]',
   1, 3),

  ('Enterprise', 'enterprise', 'Unlimited scale with dedicated support',
   299.00, 2870.00, 'EUR', -1, -1, -1, -1, -1, -1, -1, -1,
   1, 1, 1, 1, 1, 1, 1, '#ec4899',
   '["Unlimited projects","Unlimited users","Unlimited feedback","Unlimited emails","Unlimited WhatsApp & SMS","AI copilot","SSO / SAML","White-label","SLA","Dedicated support","Custom contract"]',
   1, 4);

-- ────────────────────────────────────────────────────────────
-- 3. ff_addons  — add-on product catalog
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_addons` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `slug`          varchar(80) NOT NULL UNIQUE,
  `name`          varchar(120) NOT NULL,
  `description`   varchar(255) NOT NULL DEFAULT '',
  `type`          enum('quantity','boolean') NOT NULL DEFAULT 'quantity',
  `resource`      varchar(80) DEFAULT NULL COMMENT 'limit column this add-on extends, e.g. max_emails',
  `unit_label`    varchar(60) NOT NULL DEFAULT 'unit',
  `units_per_qty` int(11) NOT NULL DEFAULT 1   COMMENT 'how many resource units per 1 qty purchased',
  `price_per_qty` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'price per qty/mo',
  `min_qty`       int(11) NOT NULL DEFAULT 1,
  `max_qty`       int(11) NOT NULL DEFAULT 100,
  `icon`          varchar(60) NOT NULL DEFAULT 'fa-puzzle-piece',
  `is_active`     tinyint(1) NOT NULL DEFAULT 1,
  `sort_order`    int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ff_addons`
  (`slug`,`name`,`description`,`type`,`resource`,`unit_label`,`units_per_qty`,`price_per_qty`,`min_qty`,`max_qty`,`icon`,`sort_order`)
VALUES
  ('extra-projects',   'Extra Projects',       'Add 5 more projects per slot',               'quantity','max_projects',  '5 projects',  5,  15.00, 1, 20, 'fa-folder-plus',   1),
  ('extra-users',      'Extra Users',          'Add 10 more team members per slot',          'quantity','max_users',     '10 users',   10,  25.00, 1, 20, 'fa-users-plus',    2),
  ('extra-emails',     'Email Credits',        'Extra 5,000 emails per slot/mo',             'quantity','max_emails',    '5k emails', 5000, 10.00, 1, 50, 'fa-envelope-open', 3),
  ('extra-whatsapp',   'WhatsApp Credits',     'Extra 500 WhatsApp messages per slot/mo',   'quantity','max_whatsapp',  '500 msgs',  500,  15.00, 1, 50, 'fa-whatsapp',      4),
  ('extra-sms',        'SMS Credits',          'Extra 500 SMS messages per slot/mo',        'quantity','max_sms',       '500 msgs',  500,  20.00, 1, 50, 'fa-comment-sms',   5),
  ('ai-copilot',       'AI Copilot',           'Full AI analysis, drafts & auto-replies',   'boolean','allow_ai',       'feature',     1,  25.00, 1,  1, 'fa-robot',         6),
  ('white-label',      'White-label',          'Remove FeedbackFlow branding entirely',     'boolean','allow_white_label','feature',  1,  35.00, 1,  1, 'fa-tag',           7);

-- ────────────────────────────────────────────────────────────
-- 4. ff_company_addons  — purchased add-ons per company
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_company_addons` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `company_id`    int(11) NOT NULL,
  `addon_id`      int(11) NOT NULL,
  `quantity`      int(11) NOT NULL DEFAULT 1,
  `activated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`    datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_addon` (`company_id`,`addon_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 5. ff_admin_overrides  — per-company custom limit overrides
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_admin_overrides` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `company_id`    int(11) NOT NULL,
  `resource`      varchar(80) NOT NULL COMMENT 'e.g. max_emails, allow_ai',
  `override_value` int(11) NOT NULL DEFAULT 0 COMMENT '-1 = unlimited',
  `note`          varchar(255) DEFAULT NULL,
  `set_by`        int(11) DEFAULT NULL COMMENT 'super-admin user id',
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_resource` (`company_id`,`resource`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 6. Extend ff_invoices with VAT and billing detail columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_invoices`
  ADD COLUMN IF NOT EXISTS `subtotal`        decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `vat_rate`        decimal(5,2)  NOT NULL DEFAULT 0.00 AFTER `subtotal`,
  ADD COLUMN IF NOT EXISTS `vat_amount`      decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `vat_rate`,
  ADD COLUMN IF NOT EXISTS `billing_name`    varchar(191) DEFAULT NULL            AFTER `vat_amount`,
  ADD COLUMN IF NOT EXISTS `billing_address` text DEFAULT NULL                    AFTER `billing_name`,
  ADD COLUMN IF NOT EXISTS `vat_number`      varchar(50) DEFAULT NULL             AFTER `billing_address`,
  ADD COLUMN IF NOT EXISTS `line_items`      text DEFAULT NULL COMMENT 'JSON'    AFTER `vat_number`,
  ADD COLUMN IF NOT EXISTS `plan_slug`       varchar(80) DEFAULT NULL             AFTER `line_items`,
  ADD COLUMN IF NOT EXISTS `period_start`    date DEFAULT NULL                    AFTER `plan_slug`,
  ADD COLUMN IF NOT EXISTS `period_end`      date DEFAULT NULL                    AFTER `period_start`;

-- ────────────────────────────────────────────────────────────
-- 7. Extend ff_companies with billing/VAT fields
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_companies`
  ADD COLUMN IF NOT EXISTS `billing_name`    varchar(191) DEFAULT NULL AFTER `billing_email`,
  ADD COLUMN IF NOT EXISTS `billing_address` text DEFAULT NULL         AFTER `billing_name`,
  ADD COLUMN IF NOT EXISTS `billing_city`    varchar(100) DEFAULT NULL AFTER `billing_address`,
  ADD COLUMN IF NOT EXISTS `billing_zip`     varchar(20) DEFAULT NULL  AFTER `billing_city`,
  ADD COLUMN IF NOT EXISTS `billing_country` varchar(100) DEFAULT NULL AFTER `billing_zip`,
  ADD COLUMN IF NOT EXISTS `vat_rate`        decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `vat_number`,
  ADD COLUMN IF NOT EXISTS `plan_expires_at` datetime DEFAULT NULL     AFTER `plan`,
  ADD COLUMN IF NOT EXISTS `billing_cycle`   enum('monthly','yearly') NOT NULL DEFAULT 'monthly' AFTER `plan_expires_at`;

-- ────────────────────────────────────────────────────────────
-- 8. Add company_id to ff_campaigns (needed for usage tracking)
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_campaigns`
  ADD COLUMN IF NOT EXISTS `company_id` int(11) DEFAULT NULL AFTER `id`;

-- Backfill: link existing campaigns to their creator's company
UPDATE `ff_campaigns` c
  JOIN `ff_users` u ON u.id = c.created_by
  SET c.company_id = u.company_id
  WHERE c.company_id IS NULL AND u.company_id IS NOT NULL;

-- ────────────────────────────────────────────────────────────
-- 9. Add company_id to ff_sms_log (if table exists)
-- ────────────────────────────────────────────────────────────
-- NOTE: Only run if you have an ff_sms_log table.
-- If the table does not exist yet this line is safe to skip.
-- ALTER TABLE `ff_sms_log`
--   ADD COLUMN IF NOT EXISTS `company_id` int(11) DEFAULT NULL AFTER `id`;
