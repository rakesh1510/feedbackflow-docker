-- ============================================================
-- FeedbackFlow — Onboarding & Signup Migration
-- Run after install.sql + db-billing-migration.sql
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. Extend ff_companies with onboarding tracking
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_companies`
  ADD COLUMN IF NOT EXISTS `onboarding_complete` tinyint(1)  NOT NULL DEFAULT 0   AFTER `plan`,
  ADD COLUMN IF NOT EXISTS `trial_ends_at`       datetime    DEFAULT NULL          AFTER `onboarding_complete`,
  ADD COLUMN IF NOT EXISTS `signup_source`       varchar(80) DEFAULT NULL          AFTER `trial_ends_at`,
  ADD COLUMN IF NOT EXISTS `signup_ip`           varchar(45) DEFAULT NULL          AFTER `signup_source`;

-- Mark existing companies as onboarding complete (they pre-date this feature)
UPDATE `ff_companies` SET `onboarding_complete` = 1 WHERE `onboarding_complete` = 0;

-- Add free plan seed if missing
INSERT IGNORE INTO `ff_billing_plans`
  (`name`, `slug`, `description`, `price_monthly`, `price_yearly`, `currency`,
   `max_projects`, `max_users`, `max_feedback_per_month`, `max_campaigns_per_month`,
   `max_emails`, `max_whatsapp`, `max_sms`, `max_team_members`,
   `allow_ai`, `allow_white_label`, `allow_api`, `allow_export`,
   `allow_automations`, `allow_audit_logs`, `allow_sso`,
   `highlight_color`, `features`, `is_active`, `sort_order`)
VALUES
  ('Free', 'free', 'Perfect to get started for free',
   0.00, 0.00, 'EUR', 1, 3, 50, 1, 100, 0, 0, 3,
   0, 0, 0, 0, 0, 0, 0, '#6b7280',
   '["1 project","3 users","50 feedback/mo","100 emails/mo","Basic dashboard","Public board"]',
   1, 0);

-- ────────────────────────────────────────────────────────────
-- 2. ff_onboarding_log — audit trail for every onboarding action
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_onboarding_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `company_id` int(11)      DEFAULT NULL,
  `action`     varchar(80)  NOT NULL COMMENT 'e.g. register, select_plan, create_project, invite_sent, invite_accepted',
  `flow`       enum('company_signup','invited_user','other') NOT NULL DEFAULT 'other',
  `meta`       text         DEFAULT NULL COMMENT 'JSON extra data',
  `ip`         varchar(45)  DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`    (`user_id`),
  KEY `company_id` (`company_id`),
  KEY `action`     (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 3. MySQL 5.7 fallback (use ff_add_col from db-fix-mysql57.sql)
-- ────────────────────────────────────────────────────────────
-- CALL ff_add_col('ff_companies', 'onboarding_complete', 'tinyint(1) NOT NULL DEFAULT 0');
-- CALL ff_add_col('ff_companies', 'trial_ends_at',       'datetime DEFAULT NULL');
-- CALL ff_add_col('ff_companies', 'signup_source',       'varchar(80) DEFAULT NULL');
-- CALL ff_add_col('ff_companies', 'signup_ip',           'varchar(45) DEFAULT NULL');
