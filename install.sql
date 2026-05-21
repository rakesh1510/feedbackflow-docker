-- FeedbackFlow Database Schema
-- Run this file to set up your database
-- Compatible with MySQL 5.7+ and MariaDB 10.3+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Users & Authentication
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('owner','admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verify_token` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Projects
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `allow_anonymous` tinyint(1) NOT NULL DEFAULT 1,
  `widget_key` varchar(64) NOT NULL,
  `widget_color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `widget_position` enum('bottom-right','bottom-left','top-right','top-left') DEFAULT 'bottom-right',
  `widget_theme` enum('light','dark','auto') DEFAULT 'light',
  `widget_title` varchar(100) DEFAULT 'Share your feedback',
  `widget_placeholder` varchar(200) DEFAULT 'Tell us what you think...',
  `custom_domain` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Project Members
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_project_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `invited_by` int(11) DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_user` (`project_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Feedback Categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `icon` varchar(50) DEFAULT 'tag',
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_slug` (`project_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Feedback
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('new','under_review','planned','in_progress','done','declined','duplicate') NOT NULL DEFAULT 'new',
  `priority` enum('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `submitter_name` varchar(100) DEFAULT NULL,
  `submitter_email` varchar(191) DEFAULT NULL,
  `submitter_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `ai_sentiment` enum('positive','neutral','negative') DEFAULT NULL,
  `ai_sentiment_score` decimal(4,3) DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `ai_priority_score` decimal(4,2) DEFAULT NULL,
  `ai_tags` text DEFAULT NULL,
  `duplicate_of` int(11) DEFAULT NULL,
  `impact_score` decimal(4,2) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `browser_info` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Tags
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#94a3b8',
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_name` (`project_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_feedback_tags` (
  `feedback_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`feedback_id`,`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Votes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `voter_ip` varchar(45) DEFAULT NULL,
  `voter_email` varchar(191) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT '👍',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feedback_user` (`feedback_id`,`user_id`),
  KEY `feedback_ip` (`feedback_id`,`voter_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Comments (Internal Notes + Public Comments)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `commenter_name` varchar(100) DEFAULT NULL,
  `commenter_email` varchar(191) DEFAULT NULL,
  `content` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin_reply` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- File Attachments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Roadmap Items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_roadmap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('planned','in_progress','done') NOT NULL DEFAULT 'planned',
  `quarter` varchar(20) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Changelog
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_changelog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('new','improvement','bugfix','breaking') NOT NULL DEFAULT 'new',
  `version` varchar(50) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `author_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_key` (`project_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Activity Log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `meta` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `feedback_id` (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Webhook Endpoints
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `secret` varchar(64) DEFAULT NULL,
  `events` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_triggered` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- AI Weekly Reports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_ai_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `content` longtext DEFAULT NULL,
  `top_themes` text DEFAULT NULL,
  `sentiment_breakdown` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Sessions (for custom session handling)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Spam Protection
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ff_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_action` (`ip`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Multi-Channel Collection (Email, QR, WhatsApp, SMS)
-- --------------------------------------------------------
ALTER TABLE `ff_feedback`
  ADD COLUMN IF NOT EXISTS `source` varchar(30) NOT NULL DEFAULT 'widget' AFTER `project_id`,
  ADD COLUMN IF NOT EXISTS `rating` tinyint(1) DEFAULT NULL AFTER `source`,
  ADD COLUMN IF NOT EXISTS `campaign_id` int(11) DEFAULT NULL AFTER `rating`;

CREATE TABLE IF NOT EXISTS `ff_email_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT 'We''d love your feedback',
  `intro_text` text DEFAULT NULL,
  `rating_question` varchar(255) NOT NULL DEFAULT 'How was your experience?',
  `show_category` tinyint(1) NOT NULL DEFAULT 1,
  `show_message` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('draft','sending','sent') NOT NULL DEFAULT 'draft',
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `open_count` int(11) NOT NULL DEFAULT 0,
  `submit_count` int(11) NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_campaign_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `pre_rating` tinyint(1) DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `campaign_id` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- AI Copilot: Intent + Reply columns on feedback
-- --------------------------------------------------------
ALTER TABLE `ff_feedback`
  ADD COLUMN IF NOT EXISTS `ai_intent` enum('bug','feature','ux','pricing','performance','praise','other') DEFAULT NULL AFTER `ai_tags`,
  ADD COLUMN IF NOT EXISTS `ai_reply` text DEFAULT NULL AFTER `ai_intent`,
  ADD COLUMN IF NOT EXISTS `ai_reply_sent` tinyint(1) NOT NULL DEFAULT 0 AFTER `ai_reply`,
  ADD COLUMN IF NOT EXISTS `ai_reply_sent_at` datetime DEFAULT NULL AFTER `ai_reply_sent`;

-- AI Feedback Clusters
CREATE TABLE IF NOT EXISTS `ff_ai_clusters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `intent` enum('bug','feature','ux','pricing','performance','praise','other') NOT NULL DEFAULT 'other',
  `severity` enum('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `feedback_count` int(11) NOT NULL DEFAULT 0,
  `avg_sentiment` varchar(20) DEFAULT NULL,
  `trend` enum('rising','stable','falling') NOT NULL DEFAULT 'stable',
  `trend_pct` int(11) NOT NULL DEFAULT 0,
  `suggested_action` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maps feedback items to clusters
CREATE TABLE IF NOT EXISTS `ff_cluster_feedback` (
  `cluster_id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  PRIMARY KEY (`cluster_id`,`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI-generated CEO-level insights
CREATE TABLE IF NOT EXISTS `ff_ai_insights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `type` enum('trending','sentiment','release_impact','praise','warning') NOT NULL DEFAULT 'trending',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `metric` varchar(100) DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `generated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_feedback_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Feedback Link',
  `source` varchar(30) NOT NULL DEFAULT 'direct',
  `token` varchar(64) NOT NULL,
  `rating_question` varchar(255) DEFAULT 'How was your experience?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `click_count` int(11) NOT NULL DEFAULT 0,
  `submit_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- EXTENDED SCHEMA — All 37 Modules
-- ============================================================

-- Companies (Multi-tenant)
CREATE TABLE IF NOT EXISTS `ff_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL UNIQUE,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `timezone` varchar(100) NOT NULL DEFAULT 'UTC',
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `plan` enum('free','starter','growth','pro','enterprise') NOT NULL DEFAULT 'free',
  `plan_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `vat_number` varchar(50) DEFAULT NULL,
  `billing_email` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User ↔ Company mapping
ALTER TABLE `ff_users` ADD COLUMN IF NOT EXISTS `company_id` int(11) DEFAULT NULL AFTER `id`;
ALTER TABLE `ff_users` ADD COLUMN IF NOT EXISTS `timezone` varchar(100) DEFAULT 'UTC' AFTER `role`;
ALTER TABLE `ff_users` ADD COLUMN IF NOT EXISTS `language` varchar(10) DEFAULT 'en' AFTER `timezone`;
ALTER TABLE `ff_users` ADD COLUMN IF NOT EXISTS `is_super_admin` tinyint(1) NOT NULL DEFAULT 0 AFTER `language`;

-- Suppression List (Module 07)
CREATE TABLE IF NOT EXISTS `ff_suppression` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `type` enum('email','phone','domain') NOT NULL DEFAULT 'email',
  `value` varchar(255) NOT NULL,
  `reason` enum('unsubscribe','bounce','complaint','manual','gdpr') NOT NULL DEFAULT 'manual',
  `added_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Review Booster (Module 11)
CREATE TABLE IF NOT EXISTS `ff_review_boosters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `platform` enum('google','yelp','tripadvisor','trustpilot','facebook','custom') NOT NULL DEFAULT 'google',
  `review_url` varchar(500) NOT NULL,
  `min_rating` tinyint(1) NOT NULL DEFAULT 4,
  `message_template` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `requests_sent` int(11) NOT NULL DEFAULT 0,
  `requests_clicked` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automations (Module 12)
CREATE TABLE IF NOT EXISTS `ff_automations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `trigger_type` enum('feedback_created','feedback_updated','rating_low','rating_high','keyword_match','daily','weekly') NOT NULL DEFAULT 'feedback_created',
  `trigger_config` text DEFAULT NULL,
  `action_type` enum('send_email','send_webhook','create_task','add_tag','change_status','notify_slack','send_sms') NOT NULL DEFAULT 'send_email',
  `action_config` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `run_count` int(11) NOT NULL DEFAULT 0,
  `last_run_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Billing Plans (Module 14, 15, 16)
CREATE TABLE IF NOT EXISTS `ff_billing_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL UNIQUE,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `max_projects` int(11) NOT NULL DEFAULT 1,
  `max_team_members` int(11) NOT NULL DEFAULT 1,
  `max_feedback_per_month` int(11) NOT NULL DEFAULT 100,
  `max_campaigns_per_month` int(11) NOT NULL DEFAULT 0,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscriptions
CREATE TABLE IF NOT EXISTS `ff_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoices (Module 17)
CREATE TABLE IF NOT EXISTS `ff_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL UNIQUE,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('draft','sent','paid','void','overdue') NOT NULL DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `stripe_payment_intent` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API Keys (Module 29)
CREATE TABLE IF NOT EXISTS `ff_api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL UNIQUE,
  `key_prefix` varchar(12) NOT NULL,
  `scopes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log (Module 26)
CREATE TABLE IF NOT EXISTS `ff_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(191) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Background Jobs (Module 28)
CREATE TABLE IF NOT EXISTS `ff_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(80) NOT NULL,
  `payload` text DEFAULT NULL,
  `status` enum('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) NOT NULL DEFAULT 3,
  `available_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `available_at` (`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usage Tracking (Module 14)
CREATE TABLE IF NOT EXISTS `ff_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `year_month` varchar(7) NOT NULL,
  `feedback_count` int(11) NOT NULL DEFAULT 0,
  `campaign_count` int(11) NOT NULL DEFAULT 0,
  `api_calls` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_month` (`company_id`,`year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status Pages (Module 31)
CREATE TABLE IF NOT EXISTS `ff_status_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `overall_status` enum('operational','degraded','partial_outage','major_outage','maintenance') NOT NULL DEFAULT 'operational',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_status_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('minor','major','critical','maintenance') NOT NULL DEFAULT 'minor',
  `status` enum('investigating','identified','monitoring','resolved') NOT NULL DEFAULT 'investigating',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Export Requests (Module 27)
CREATE TABLE IF NOT EXISTS `ff_export_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('feedback','campaigns','analytics','full_backup') NOT NULL DEFAULT 'feedback',
  `filters` text DEFAULT NULL,
  `status` enum('pending','processing','ready','failed') NOT NULL DEFAULT 'pending',
  `file_path` varchar(255) DEFAULT NULL,
  `row_count` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email Campaigns (enhanced)
CREATE TABLE IF NOT EXISTS `ff_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `from_name` varchar(100) DEFAULT NULL,
  `from_email` varchar(191) DEFAULT NULL,
  `reply_to` varchar(191) DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','paused','cancelled') NOT NULL DEFAULT 'draft',
  `channel` enum('email','sms','whatsapp') NOT NULL DEFAULT 'email',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `recipient_count` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `open_count` int(11) NOT NULL DEFAULT 0,
  `click_count` int(11) NOT NULL DEFAULT 0,
  `bounce_count` int(11) NOT NULL DEFAULT 0,
  `unsubscribe_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ff_campaign_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `token` varchar(64) DEFAULT NULL,
  `status` enum('pending','sent','opened','clicked','bounced','unsubscribed','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  UNIQUE KEY `campaign_email` (`campaign_id`,`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks (Module 08 - processing)
CREATE TABLE IF NOT EXISTS `ff_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  `priority` enum('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Language strings (Module 19)
CREATE TABLE IF NOT EXISTS `ff_translations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lang` varchar(10) NOT NULL,
  `key` varchar(150) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lang_key` (`lang`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- QR codes (Module 05)
CREATE TABLE IF NOT EXISTS `ff_qr_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'QR Code',
  `token` varchar(64) NOT NULL UNIQUE,
  `scan_count` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- ── Extend ff_billing_plans with new limit & feature columns ────────────
ALTER TABLE `ff_billing_plans`
  ADD COLUMN IF NOT EXISTS `max_users`          int(11) NOT NULL DEFAULT 3       AFTER `max_projects`,
  ADD COLUMN IF NOT EXISTS `max_emails`         int(11) NOT NULL DEFAULT 500      AFTER `max_users`,
  ADD COLUMN IF NOT EXISTS `max_whatsapp`       int(11) NOT NULL DEFAULT 0        AFTER `max_emails`,
  ADD COLUMN IF NOT EXISTS `max_sms`            int(11) NOT NULL DEFAULT 0        AFTER `max_whatsapp`,
  ADD COLUMN IF NOT EXISTS `allow_ai`           tinyint(1) NOT NULL DEFAULT 0     AFTER `max_sms`,
  ADD COLUMN IF NOT EXISTS `allow_white_label`  tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_ai`,
  ADD COLUMN IF NOT EXISTS `allow_api`          tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_white_label`,
  ADD COLUMN IF NOT EXISTS `allow_export`       tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_api`,
  ADD COLUMN IF NOT EXISTS `allow_automations`  tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_export`,
  ADD COLUMN IF NOT EXISTS `allow_audit_logs`   tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_automations`,
  ADD COLUMN IF NOT EXISTS `allow_sso`          tinyint(1) NOT NULL DEFAULT 0     AFTER `allow_audit_logs`,
  ADD COLUMN IF NOT EXISTS `description`        varchar(255) DEFAULT NULL          AFTER `name`,
  ADD COLUMN IF NOT EXISTS `highlight_color`    varchar(7) NOT NULL DEFAULT '#6366f1' AFTER `description`;

-- ── Extend ff_companies with billing/VAT fields ──────────────────────────
ALTER TABLE `ff_companies`
  ADD COLUMN IF NOT EXISTS `billing_name`    varchar(191) DEFAULT NULL AFTER `billing_email`,
  ADD COLUMN IF NOT EXISTS `billing_address` text DEFAULT NULL         AFTER `billing_name`,
  ADD COLUMN IF NOT EXISTS `billing_city`    varchar(100) DEFAULT NULL AFTER `billing_address`,
  ADD COLUMN IF NOT EXISTS `billing_zip`     varchar(20)  DEFAULT NULL AFTER `billing_city`,
  ADD COLUMN IF NOT EXISTS `billing_country` varchar(100) DEFAULT NULL AFTER `billing_zip`,
  ADD COLUMN IF NOT EXISTS `vat_rate`        decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `vat_number`,
  ADD COLUMN IF NOT EXISTS `plan_expires_at` datetime DEFAULT NULL     AFTER `plan`,
  ADD COLUMN IF NOT EXISTS `billing_cycle`   enum('monthly','yearly') NOT NULL DEFAULT 'monthly' AFTER `plan_expires_at`;

-- ── Extend ff_invoices with VAT & detail columns ─────────────────────────
ALTER TABLE `ff_invoices`
  ADD COLUMN IF NOT EXISTS `subtotal`        decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `vat_rate`        decimal(5,2)  NOT NULL DEFAULT 0.00 AFTER `subtotal`,
  ADD COLUMN IF NOT EXISTS `vat_amount`      decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `vat_rate`,
  ADD COLUMN IF NOT EXISTS `billing_name`    varchar(191) DEFAULT NULL            AFTER `vat_amount`,
  ADD COLUMN IF NOT EXISTS `billing_address` text DEFAULT NULL                    AFTER `billing_name`,
  ADD COLUMN IF NOT EXISTS `vat_number`      varchar(50) DEFAULT NULL             AFTER `billing_address`,
  ADD COLUMN IF NOT EXISTS `line_items`      text DEFAULT NULL                    AFTER `vat_number`,
  ADD COLUMN IF NOT EXISTS `plan_slug`       varchar(80) DEFAULT NULL             AFTER `line_items`,
  ADD COLUMN IF NOT EXISTS `period_start`    date DEFAULT NULL                    AFTER `plan_slug`,
  ADD COLUMN IF NOT EXISTS `period_end`      date DEFAULT NULL                    AFTER `period_start`;

-- ── Add-on catalog ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ff_addons` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `slug`          varchar(80) NOT NULL UNIQUE,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Add-ons purchased per company ─────────────────────────────────────────
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

-- ── Admin limit overrides per company ─────────────────────────────────────
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

-- ── Plan seeds — updated pricing spec (EUR) ───────────────────────────────
-- Starter €19/mo | Growth €49/mo | Pro €99/mo | Enterprise €299/mo
DELETE FROM `ff_billing_plans`;
INSERT INTO `ff_billing_plans`
  (`name`,`slug`,`description`,`price_monthly`,`price_yearly`,`currency`,
   `max_projects`,`max_users`,`max_feedback_per_month`,`max_campaigns_per_month`,
   `max_emails`,`max_whatsapp`,`max_sms`,`max_team_members`,
   `allow_ai`,`allow_white_label`,`allow_api`,`allow_export`,
   `allow_automations`,`allow_audit_logs`,`allow_sso`,
   `highlight_color`,`features`,`is_active`,`sort_order`)
VALUES
  ('Starter','starter','Perfect for small teams getting started',
   19.00,182.00,'EUR',  2, 10,  500,  3, 1000,  100,  50, 10,
   0,0,0,0,0,0,0,'#6366f1',
   '["2 projects","10 users","500 feedback/mo","1,000 emails/mo","100 WhatsApp/mo","50 SMS/mo","Feedback widget","QR codes","Roadmap","Changelog"]',
   1,1),
  ('Growth','growth','For growing teams that need more power',
   49.00,470.00,'EUR',  5, 25, 2000, 10, 5000,  500, 200, 25,
   1,0,1,1,1,1,0,'#8b5cf6',
   '["5 projects","25 users","2,000 feedback/mo","5,000 emails/mo","500 WhatsApp/mo","200 SMS/mo","AI insights","Automations","Audit logs","API access","Bulk export","Review booster"]',
   1,2),
  ('Pro','pro','Advanced tools for professional teams',
   99.00,950.00,'EUR', 15,100,10000, 50,20000, 2000,1000,100,
   1,1,1,1,1,1,0,'#a855f7',
   '["15 projects","100 users","10,000 feedback/mo","20,000 emails/mo","2,000 WhatsApp/mo","1,000 SMS/mo","AI copilot","White-label","Custom domain","Priority support","SSO ready"]',
   1,3),
  ('Enterprise','enterprise','Unlimited scale with dedicated support',
   299.00,2870.00,'EUR',-1,-1,  -1, -1,   -1,   -1,  -1, -1,
   1,1,1,1,1,1,1,'#ec4899',
   '["Unlimited projects","Unlimited users","Unlimited feedback","Unlimited emails","Unlimited WhatsApp & SMS","AI copilot","SSO / SAML","White-label","SLA","Dedicated support","Custom contract"]',
   1,4);

-- ── Add-on seed data ──────────────────────────────────────────────────────
INSERT IGNORE INTO `ff_addons`
  (`slug`,`name`,`description`,`type`,`resource`,`unit_label`,`units_per_qty`,`price_per_qty`,`min_qty`,`max_qty`,`icon`,`sort_order`)
VALUES
  ('extra-projects', 'Extra Projects',   'Add 5 more projects per slot',              'quantity','max_projects',  '5 projects',  5, 15.00,1,20,'fa-folder-plus',  1),
  ('extra-users',    'Extra Users',      'Add 10 more team members per slot',         'quantity','max_users',     '10 users',   10, 25.00,1,20,'fa-users-plus',   2),
  ('extra-emails',   'Email Credits',    'Extra 5,000 emails per slot/mo',            'quantity','max_emails',    '5k emails', 5000,10.00,1,50,'fa-envelope-open',3),
  ('extra-whatsapp', 'WhatsApp Credits', 'Extra 500 WhatsApp messages per slot/mo',  'quantity','max_whatsapp',  '500 msgs',  500, 15.00,1,50,'fa-whatsapp',     4),
  ('extra-sms',      'SMS Credits',      'Extra 500 SMS messages per slot/mo',       'quantity','max_sms',       '500 msgs',  500, 20.00,1,50,'fa-comment-sms',  5),
  ('ai-copilot',     'AI Copilot',       'Full AI analysis, drafts & auto-replies',  'boolean','allow_ai',       'feature',     1, 25.00,1, 1,'fa-robot',        6),
  ('white-label',    'White-label',      'Remove FeedbackFlow branding entirely',    'boolean','allow_white_label','feature',   1, 35.00,1, 1,'fa-tag',          7);

-- Demo admin user (password: Admin1234!)
INSERT IGNORE INTO `ff_users` (`id`,`name`,`email`,`password`,`role`,`is_active`,`email_verified`,`is_super_admin`) VALUES
(1,'Admin User','admin@demo.com','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','owner',1,1,1);

-- Demo company
INSERT IGNORE INTO `ff_companies` (`id`,`name`,`slug`,`email`,`plan`) VALUES
(1,'Demo Company','demo','admin@demo.com','pro');
