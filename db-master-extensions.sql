-- ============================================================
-- FeedbackFlow — Master Database Extensions
-- Hybrid Multi-Tenant Architecture
--
-- Run ONCE on the MASTER database (feedbackflow).
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. ff_company_databases
--    Stores encrypted connection details for each tenant DB.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_company_databases` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `company_id`     int(11)      NOT NULL,
  `db_host`        varchar(255) NOT NULL DEFAULT 'localhost',
  `db_port`        smallint(5)  NOT NULL DEFAULT 3306,
  `db_name`        varchar(64)  NOT NULL DEFAULT '',
  `db_user`        varchar(64)  NOT NULL DEFAULT '',
  `db_pass_enc`    text         DEFAULT NULL    COMMENT 'AES-256-CBC encrypted, base64-stored',
  `db_status`      enum('pending','active','failed','suspended') NOT NULL DEFAULT 'pending',
  `error_msg`      varchar(500) DEFAULT NULL,
  `provisioned_at` datetime     DEFAULT NULL,
  `updated_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company` (`company_id`),
  KEY `db_status` (`db_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tenant DB connection pool — managed by DBManager';

-- ────────────────────────────────────────────────────────────
-- 2. ff_provisioning_log
--    Audit trail for all tenant DB provisioning actions.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_provisioning_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `company_id` int(11)      NOT NULL,
  `action`     varchar(80)  NOT NULL  COMMENT 'e.g. create_database, apply_schema, store_connection, run_migration',
  `status`     enum('success','failed','skipped','warning','pending') NOT NULL DEFAULT 'pending',
  `detail`     varchar(1000) DEFAULT NULL,
  `created_by` int(11)      DEFAULT NULL  COMMENT 'Super admin user ID if triggered manually',
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `action`     (`action`),
  KEY `status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Provisioning audit trail — written by TenantProvisioner';

-- ────────────────────────────────────────────────────────────
-- 3. ff_super_admin_log
--    Audit trail for all super-admin actions
--    (impersonate, toggle company, export data, etc.)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_super_admin_log` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`          int(11)      NOT NULL  COMMENT 'Super admin who performed the action',
  `action`            varchar(80)  NOT NULL  COMMENT 'e.g. view_company, impersonate, toggle_company, export_data',
  `target_company_id` int(11)      DEFAULT NULL,
  `target_user_id`    int(11)      DEFAULT NULL,
  `meta`              text         DEFAULT NULL  COMMENT 'JSON extra context',
  `ip`                varchar(45)  DEFAULT NULL,
  `created_at`        datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id`          (`admin_id`),
  KEY `target_company_id` (`target_company_id`),
  KEY `action`            (`action`),
  KEY `created_at`        (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Super admin action audit trail';

-- ────────────────────────────────────────────────────────────
-- 4. Add is_active to ff_companies if missing
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_companies`
  ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `name`;

-- Mark all existing companies as active (they were active before this column existed)
UPDATE `ff_companies` SET `is_active` = 1 WHERE `is_active` = 0 AND `onboarding_complete` = 1;
