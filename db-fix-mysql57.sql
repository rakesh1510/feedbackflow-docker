-- ============================================================
-- FeedbackFlow — MySQL 5.7 / XAMPP Column Fix
-- ============================================================
-- Use this file INSTEAD of db-billing-migration.sql if you
-- are on MySQL 5.7 (XAMPP default) and get errors about
-- "ADD COLUMN IF NOT EXISTS" not being supported.
--
-- Safe to run multiple times: each ALTER TABLE is wrapped in
-- a stored procedure that checks column existence first.
-- ============================================================

DELIMITER $$

-- Helper: add a column only if it does not already exist
DROP PROCEDURE IF EXISTS ff_add_col$$
CREATE PROCEDURE ff_add_col(
    tbl  VARCHAR(64),
    col  VARCHAR(64),
    def  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl
          AND COLUMN_NAME  = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ────────────────────────────────────────────────────────────
-- ff_billing_plans  — new limit / feature columns
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_billing_plans', 'max_users',         'int(11) NOT NULL DEFAULT 3');
CALL ff_add_col('ff_billing_plans', 'max_emails',        'int(11) NOT NULL DEFAULT 500');
CALL ff_add_col('ff_billing_plans', 'max_whatsapp',      'int(11) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'max_sms',           'int(11) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_ai',          'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_white_label', 'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_api',         'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_export',      'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_automations', 'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_audit_logs',  'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'allow_sso',         'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_billing_plans', 'description',       'varchar(255) DEFAULT NULL');
CALL ff_add_col('ff_billing_plans', 'highlight_color',   "varchar(7) NOT NULL DEFAULT '#6366f1'");

-- ────────────────────────────────────────────────────────────
-- ff_invoices  — VAT + billing detail columns
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_invoices', 'subtotal',        'decimal(10,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('ff_invoices', 'vat_rate',        'decimal(5,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('ff_invoices', 'vat_amount',      'decimal(10,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('ff_invoices', 'billing_name',    'varchar(191) DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'billing_address', 'text DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'vat_number',      'varchar(50) DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'line_items',      'text DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'plan_slug',       'varchar(80) DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'period_start',    'date DEFAULT NULL');
CALL ff_add_col('ff_invoices', 'period_end',      'date DEFAULT NULL');

-- ────────────────────────────────────────────────────────────
-- ff_companies  — billing / VAT fields
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_companies', 'billing_name',    'varchar(191) DEFAULT NULL');
CALL ff_add_col('ff_companies', 'billing_address', 'text DEFAULT NULL');
CALL ff_add_col('ff_companies', 'billing_city',    'varchar(100) DEFAULT NULL');
CALL ff_add_col('ff_companies', 'billing_zip',     'varchar(20) DEFAULT NULL');
CALL ff_add_col('ff_companies', 'billing_country', 'varchar(100) DEFAULT NULL');
CALL ff_add_col('ff_companies', 'vat_rate',        'decimal(5,2) NOT NULL DEFAULT 0.00');
CALL ff_add_col('ff_companies', 'plan_expires_at', 'datetime DEFAULT NULL');
CALL ff_add_col('ff_companies', 'billing_cycle',   "enum('monthly','yearly') NOT NULL DEFAULT 'monthly'");

-- ────────────────────────────────────────────────────────────
-- ff_campaigns  — company_id for usage tracking (ROOT CAUSE FIX)
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_campaigns', 'company_id', 'int(11) DEFAULT NULL');

-- Backfill existing campaigns to their creator's company
UPDATE `ff_campaigns` c
  JOIN `ff_users` u ON u.id = c.created_by
  SET c.company_id = u.company_id
  WHERE c.company_id IS NULL AND u.company_id IS NOT NULL;

-- ────────────────────────────────────────────────────────────
-- ff_sms_log  — company_id (only if table exists)
-- ────────────────────────────────────────────────────────────
-- Uncomment if you have an ff_sms_log table:
-- CALL ff_add_col('ff_sms_log', 'company_id', 'int(11) DEFAULT NULL');

-- ────────────────────────────────────────────────────────────
-- New tables: ff_addons, ff_company_addons, ff_admin_overrides
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_addons` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `slug`          varchar(80) NOT NULL,
  `name`          varchar(120) NOT NULL,
  `description`   varchar(255) NOT NULL DEFAULT '',
  `type`          enum('quantity','boolean') NOT NULL DEFAULT 'quantity',
  `resource`      varchar(80) DEFAULT NULL,
  `unit_label`    varchar(60) NOT NULL DEFAULT 'unit',
  `units_per_qty` int(11) NOT NULL DEFAULT 1,
  `price_per_qty` decimal(8,2) NOT NULL DEFAULT 0.00,
  `min_qty`       int(11) NOT NULL DEFAULT 1,
  `max_qty`       int(11) NOT NULL DEFAULT 100,
  `icon`          varchar(60) NOT NULL DEFAULT 'fa-puzzle-piece',
  `is_active`     tinyint(1) NOT NULL DEFAULT 1,
  `sort_order`    int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ff_addons`
  (`slug`,`name`,`description`,`type`,`resource`,`unit_label`,`units_per_qty`,`price_per_qty`,`min_qty`,`max_qty`,`icon`,`sort_order`)
VALUES
  ('extra-projects',  'Extra Projects',     'Add 5 more projects per slot',            'quantity','max_projects',    '5 projects', 5,    15.00, 1, 20, 'fa-folder-plus',   1),
  ('extra-users',     'Extra Users',        'Add 10 more team members per slot',       'quantity','max_users',       '10 users',   10,   25.00, 1, 20, 'fa-users-plus',    2),
  ('extra-emails',    'Email Credits',      'Extra 5,000 emails per slot/mo',          'quantity','max_emails',      '5k emails',  5000, 10.00, 1, 50, 'fa-envelope-open', 3),
  ('extra-whatsapp',  'WhatsApp Credits',   'Extra 500 WhatsApp messages per slot/mo', 'quantity','max_whatsapp',    '500 msgs',   500,  15.00, 1, 50, 'fa-whatsapp',      4),
  ('extra-sms',       'SMS Credits',        'Extra 500 SMS messages per slot/mo',      'quantity','max_sms',         '500 msgs',   500,  20.00, 1, 50, 'fa-comment-sms',   5),
  ('ai-copilot',      'AI Copilot',         'Full AI analysis, drafts & auto-replies', 'boolean', 'allow_ai',        'feature',    1,    25.00, 1,  1, 'fa-robot',         6),
  ('white-label',     'White-label',        'Remove FeedbackFlow branding entirely',   'boolean', 'allow_white_label','feature',   1,    35.00, 1,  1, 'fa-tag',           7);

CREATE TABLE IF NOT EXISTS `ff_company_addons` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `company_id`   int(11) NOT NULL,
  `addon_id`     int(11) NOT NULL,
  `quantity`     int(11) NOT NULL DEFAULT 1,
  `activated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`   datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_addon` (`company_id`,`addon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_admin_overrides` (
  `id`             int(11) NOT NULL AUTO_INCREMENT,
  `company_id`     int(11) NOT NULL,
  `resource`       varchar(80) NOT NULL,
  `override_value` int(11) NOT NULL DEFAULT 0,
  `note`           varchar(255) DEFAULT NULL,
  `set_by`         int(11) DEFAULT NULL,
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_resource` (`company_id`,`resource`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- Update plan seeds to correct EUR pricing
-- ────────────────────────────────────────────────────────────
UPDATE `ff_billing_plans` SET
  price_monthly=19.00, price_yearly=182.00, currency='EUR',
  max_projects=2,  max_users=10,  max_emails=1000, max_whatsapp=100, max_sms=50,
  max_feedback_per_month=500, max_campaigns_per_month=3,
  allow_ai=0, allow_white_label=0, allow_api=0, allow_export=0,
  allow_automations=0, allow_audit_logs=0, allow_sso=0,
  description='Perfect for small teams getting started', highlight_color='#6366f1'
WHERE slug='starter';

UPDATE `ff_billing_plans` SET
  price_monthly=49.00, price_yearly=470.00, currency='EUR',
  max_projects=5,  max_users=25,  max_emails=5000, max_whatsapp=500, max_sms=200,
  max_feedback_per_month=2000, max_campaigns_per_month=10,
  allow_ai=1, allow_white_label=0, allow_api=1, allow_export=1,
  allow_automations=1, allow_audit_logs=1, allow_sso=0,
  description='For growing teams that need more power', highlight_color='#8b5cf6'
WHERE slug='growth';

UPDATE `ff_billing_plans` SET
  price_monthly=99.00, price_yearly=950.00, currency='EUR',
  max_projects=15, max_users=100, max_emails=20000, max_whatsapp=2000, max_sms=1000,
  max_feedback_per_month=10000, max_campaigns_per_month=50,
  allow_ai=1, allow_white_label=1, allow_api=1, allow_export=1,
  allow_automations=1, allow_audit_logs=1, allow_sso=0,
  description='Advanced tools for professional teams', highlight_color='#a855f7'
WHERE slug='pro';

UPDATE `ff_billing_plans` SET
  price_monthly=299.00, price_yearly=2870.00, currency='EUR',
  max_projects=-1, max_users=-1, max_emails=-1, max_whatsapp=-1, max_sms=-1,
  max_feedback_per_month=-1, max_campaigns_per_month=-1,
  allow_ai=1, allow_white_label=1, allow_api=1, allow_export=1,
  allow_automations=1, allow_audit_logs=1, allow_sso=1,
  description='Unlimited scale with dedicated support', highlight_color='#ec4899'
WHERE slug='enterprise';

-- ────────────────────────────────────────────────────────────
-- ff_users  — invite / status columns (user management)
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_users', 'status',         "enum('active','invited','disabled') NOT NULL DEFAULT 'active'");
CALL ff_add_col('ff_users', 'invite_token',   'varchar(64) DEFAULT NULL');
CALL ff_add_col('ff_users', 'invite_expires', 'datetime DEFAULT NULL');
CALL ff_add_col('ff_users', 'invited_by',     'int(11) DEFAULT NULL');

-- Backfill status for existing users
UPDATE `ff_users` SET `status` = 'active'   WHERE `is_active` = 1  AND `status` = 'active';
UPDATE `ff_users` SET `status` = 'disabled' WHERE `is_active` = 0  AND `status` = 'active';

-- ────────────────────────────────────────────────────────────
-- ff_companies  — onboarding tracking columns
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_companies', 'onboarding_complete', 'tinyint(1) NOT NULL DEFAULT 0');
CALL ff_add_col('ff_companies', 'trial_ends_at',       'datetime DEFAULT NULL');
CALL ff_add_col('ff_companies', 'signup_source',       'varchar(80) DEFAULT NULL');
CALL ff_add_col('ff_companies', 'signup_ip',           'varchar(45) DEFAULT NULL');

-- Mark all existing companies as already onboarded
UPDATE `ff_companies` SET `onboarding_complete` = 1 WHERE `onboarding_complete` = 0;

-- Free plan seed (MySQL 5.7 safe)
INSERT IGNORE INTO `ff_billing_plans`
  (`name`,`slug`,`description`,`price_monthly`,`price_yearly`,`currency`,
   `max_projects`,`max_users`,`max_feedback_per_month`,`max_campaigns_per_month`,
   `max_emails`,`max_whatsapp`,`max_sms`,`max_team_members`,
   `allow_ai`,`allow_white_label`,`allow_api`,`allow_export`,
   `allow_automations`,`allow_audit_logs`,`allow_sso`,
   `highlight_color`,`features`,`is_active`,`sort_order`)
VALUES
  ('Free','free','Perfect to get started for free',
   0.00,0.00,'EUR',1,3,50,1,100,0,0,3,
   0,0,0,0,0,0,0,'#6b7280',
   '["1 project","3 users","50 feedback/mo","Basic dashboard","Public board"]',
   1,0);

-- ────────────────────────────────────────────────────────────
-- ff_onboarding_log  — audit trail
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_onboarding_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `company_id` int(11)      DEFAULT NULL,
  `action`     varchar(80)  NOT NULL,
  `flow`       enum('company_signup','invited_user','other') NOT NULL DEFAULT 'other',
  `meta`       text         DEFAULT NULL,
  `ip`         varchar(45)  DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`    (`user_id`),
  KEY `company_id` (`company_id`),
  KEY `action`     (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- ff_companies — add is_active column for suspend/activate
-- ────────────────────────────────────────────────────────────
CALL ff_add_col('ff_companies', 'is_active', 'tinyint(1) NOT NULL DEFAULT 1');
UPDATE `ff_companies` SET `is_active` = 1 WHERE `is_active` IS NULL;

-- ────────────────────────────────────────────────────────────
-- Master DB extensions — hybrid multi-tenant architecture
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_company_databases` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `company_id`     int(11)      NOT NULL,
  `db_host`        varchar(255) NOT NULL DEFAULT 'localhost',
  `db_port`        smallint(5)  NOT NULL DEFAULT 3306,
  `db_name`        varchar(64)  NOT NULL DEFAULT '',
  `db_user`        varchar(64)  NOT NULL DEFAULT '',
  `db_pass_enc`    text         DEFAULT NULL,
  `db_status`      enum('pending','active','failed','suspended') NOT NULL DEFAULT 'pending',
  `error_msg`      varchar(500) DEFAULT NULL,
  `provisioned_at` datetime     DEFAULT NULL,
  `updated_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company` (`company_id`),
  KEY `db_status` (`db_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_provisioning_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `company_id` int(11)      NOT NULL,
  `action`     varchar(80)  NOT NULL,
  `status`     enum('success','failed','skipped','warning','pending') NOT NULL DEFAULT 'pending',
  `detail`     varchar(1000) DEFAULT NULL,
  `created_by` int(11)      DEFAULT NULL,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `action`     (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_super_admin_log` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`          int(11)      NOT NULL,
  `action`            varchar(80)  NOT NULL,
  `target_company_id` int(11)      DEFAULT NULL,
  `target_user_id`    int(11)      DEFAULT NULL,
  `meta`              text         DEFAULT NULL,
  `ip`                varchar(45)  DEFAULT NULL,
  `created_at`        datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id`          (`admin_id`),
  KEY `target_company_id` (`target_company_id`),
  KEY `action`            (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleanup
DROP PROCEDURE IF EXISTS ff_add_col;
