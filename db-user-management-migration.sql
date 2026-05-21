-- ============================================================
-- FeedbackFlow — Company User Management Migration
-- Run after install.sql
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. Extend ff_users with invite / status columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `ff_users`
  ADD COLUMN IF NOT EXISTS `status`         enum('active','invited','disabled') NOT NULL DEFAULT 'active' AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `invite_token`   varchar(64)  DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `invite_expires` datetime     DEFAULT NULL AFTER `invite_token`,
  ADD COLUMN IF NOT EXISTS `invited_by`     int(11)      DEFAULT NULL AFTER `invite_expires`;

-- Mark existing active users as 'active'
UPDATE `ff_users` SET `status` = 'active' WHERE `is_active` = 1 AND `status` = 'active';
UPDATE `ff_users` SET `status` = 'disabled' WHERE `is_active` = 0 AND `status` = 'active';

-- ────────────────────────────────────────────────────────────
-- 2. MySQL 5.7-compatible fallback (stored procedure)
-- ────────────────────────────────────────────────────────────
-- If ADD COLUMN IF NOT EXISTS fails on your version, run this
-- block instead (copy the procedure from db-fix-mysql57.sql):
--
-- CALL ff_add_col('ff_users', 'status',         "enum('active','invited','disabled') NOT NULL DEFAULT 'active'");
-- CALL ff_add_col('ff_users', 'invite_token',   'varchar(64) DEFAULT NULL');
-- CALL ff_add_col('ff_users', 'invite_expires', 'datetime DEFAULT NULL');
-- CALL ff_add_col('ff_users', 'invited_by',     'int(11) DEFAULT NULL');
