-- ============================================================
-- FeedbackFlow — Tenant Database Schema
-- Applied to EACH company's dedicated database by TenantProvisioner.
--
-- Note: No company_id columns — everything in this DB belongs
-- to exactly one company. The company_id is implicit.
-- ============================================================

-- ── Users (company team members) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_users` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `name`           varchar(191) NOT NULL DEFAULT '',
  `email`          varchar(191) NOT NULL,
  `password`       varchar(255) NOT NULL DEFAULT '',
  `role`           enum('owner','admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `is_active`      tinyint(1)   NOT NULL DEFAULT 1,
  `is_super_admin` tinyint(1)   NOT NULL DEFAULT 0,
  `status`         enum('active','invited','disabled') NOT NULL DEFAULT 'active',
  `invite_token`   varchar(64)  DEFAULT NULL,
  `invite_expires` datetime     DEFAULT NULL,
  `invited_by`     int(11)      DEFAULT NULL,
  `avatar`         varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1)   NOT NULL DEFAULT 0,
  `last_login`     datetime     DEFAULT NULL,
  `created_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `role`   (`role`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Projects ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_projects` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `name`            varchar(191) NOT NULL,
  `slug`            varchar(191) NOT NULL,
  `description`     text         DEFAULT NULL,
  `owner_id`        int(11)      NOT NULL,
  `is_public`       tinyint(1)   NOT NULL DEFAULT 1,
  `allow_anonymous` tinyint(1)   NOT NULL DEFAULT 1,
  `widget_key`      varchar(64)  DEFAULT NULL,
  `widget_color`    varchar(7)   NOT NULL DEFAULT '#6366f1',
  `widget_position` varchar(20)  NOT NULL DEFAULT 'bottom-right',
  `logo_url`        varchar(255) DEFAULT NULL,
  `settings`        text         DEFAULT NULL  COMMENT 'JSON extra settings',
  `created_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `owner_id`   (`owner_id`),
  KEY `is_public`  (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Project Channels ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_project_channels` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `project_id` int(11)      NOT NULL,
  `channel`    varchar(40)  NOT NULL  COMMENT 'widget|email|whatsapp|sms|qr_code|in_app',
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  `config`     text         DEFAULT NULL  COMMENT 'JSON channel-specific config',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proj_channel` (`project_id`,`channel`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Feedback ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_feedback` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `project_id`    int(11)      NOT NULL,
  `type`          enum('bug','feature','praise','question','other') NOT NULL DEFAULT 'other',
  `content`       text         NOT NULL,
  `email`         varchar(191) DEFAULT NULL,
  `name`          varchar(191) DEFAULT NULL,
  `status`        enum('open','in_review','planned','closed','rejected') NOT NULL DEFAULT 'open',
  `priority`      enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `channel`       varchar(40)  DEFAULT 'widget',
  `upvotes`       int(11)      NOT NULL DEFAULT 0,
  `tags`          varchar(500) DEFAULT NULL,
  `meta`          text         DEFAULT NULL  COMMENT 'JSON extra data (URL, user agent, etc.)',
  `ip_address`    varchar(45)  DEFAULT NULL,
  `ai_summary`    text         DEFAULT NULL,
  `ai_sentiment`  varchar(20)  DEFAULT NULL,
  `assignee_id`   int(11)      DEFAULT NULL,
  `archived_at`   datetime     DEFAULT NULL,
  `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `status`     (`status`),
  KEY `type`       (`type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Feedback Comments ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_feedback_comments` (
  `id`          int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `user_id`     int(11) DEFAULT NULL,
  `content`     text    NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at`  datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Roadmap / Changelog ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_roadmap` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `project_id`  int(11)      NOT NULL,
  `title`       varchar(255) NOT NULL,
  `description` text         DEFAULT NULL,
  `status`      enum('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `priority`    int(11)      NOT NULL DEFAULT 0,
  `sort_order`  int(11)      NOT NULL DEFAULT 0,
  `created_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Email / SMS Campaigns ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_campaigns` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `name`          varchar(191) NOT NULL,
  `type`          enum('email','sms','whatsapp') NOT NULL DEFAULT 'email',
  `subject`       varchar(255) DEFAULT NULL,
  `content`       text         DEFAULT NULL,
  `status`        enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at`  datetime     DEFAULT NULL,
  `sent_at`       datetime     DEFAULT NULL,
  `recipients`    int(11)      NOT NULL DEFAULT 0,
  `opens`         int(11)      NOT NULL DEFAULT 0,
  `clicks`        int(11)      NOT NULL DEFAULT 0,
  `created_by`    int(11)      DEFAULT NULL,
  `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status`     (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Monthly Usage Tracking ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_billing_usage` (
  `id`                     int(11)    NOT NULL AUTO_INCREMENT,
  `year_month`             char(7)    NOT NULL  COMMENT 'YYYY-MM',
  `feedback_count`         int(11)    NOT NULL DEFAULT 0,
  `campaign_count`         int(11)    NOT NULL DEFAULT 0,
  `email_count`            int(11)    NOT NULL DEFAULT 0,
  `whatsapp_count`         int(11)    NOT NULL DEFAULT 0,
  `sms_count`              int(11)    NOT NULL DEFAULT 0,
  `ai_tokens_used`         int(11)    NOT NULL DEFAULT 0,
  `created_at`             datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_month` (`year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notification Preferences ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_notifications` (
  `id`         int(11)    NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)    NOT NULL,
  `type`       varchar(60) NOT NULL,
  `message`    text        NOT NULL,
  `is_read`    tinyint(1)  NOT NULL DEFAULT 0,
  `link`       varchar(255) DEFAULT NULL,
  `created_at` datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`    (`user_id`),
  KEY `is_read`    (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── API Keys ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_api_keys` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(191) NOT NULL,
  `key_hash`   varchar(255) NOT NULL  COMMENT 'sha256 hash of the actual key',
  `key_prefix` varchar(10)  NOT NULL  COMMENT 'First 8 chars shown in UI',
  `user_id`    int(11)      NOT NULL,
  `scopes`     varchar(255) DEFAULT NULL,
  `last_used`  datetime     DEFAULT NULL,
  `expires_at` datetime     DEFAULT NULL,
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_hash` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Audit Log (company-level events) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_audit_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `action`     varchar(80)  NOT NULL,
  `target`     varchar(80)  DEFAULT NULL,
  `target_id`  int(11)      DEFAULT NULL,
  `meta`       text         DEFAULT NULL,
  `ip`         varchar(45)  DEFAULT NULL,
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`    (`user_id`),
  KEY `action`     (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed: initial usage row for current month ─────────────────────────────
INSERT IGNORE INTO `ff_billing_usage` (`year_month`) VALUES (DATE_FORMAT(NOW(), '%Y-%m'));
