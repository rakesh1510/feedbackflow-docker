-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 04:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `feedbackflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `ff_activity`
--

CREATE TABLE `ff_activity` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `meta` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_addons`
--

CREATE TABLE `ff_addons` (
  `id` int(11) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `type` enum('quantity','boolean') NOT NULL DEFAULT 'quantity',
  `resource` varchar(80) DEFAULT NULL,
  `unit_label` varchar(60) NOT NULL DEFAULT 'unit',
  `units_per_qty` int(11) NOT NULL DEFAULT 1,
  `price_per_qty` decimal(8,2) NOT NULL DEFAULT 0.00,
  `min_qty` int(11) NOT NULL DEFAULT 1,
  `max_qty` int(11) NOT NULL DEFAULT 100,
  `icon` varchar(60) NOT NULL DEFAULT 'fa-puzzle-piece',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_addons`
--

INSERT INTO `ff_addons` (`id`, `slug`, `name`, `description`, `type`, `resource`, `unit_label`, `units_per_qty`, `price_per_qty`, `min_qty`, `max_qty`, `icon`, `is_active`, `sort_order`) VALUES
(1, 'extra-projects', 'Extra Projects', 'Add 5 more projects per slot', 'quantity', 'max_projects', '5 projects', 5, 15.00, 1, 20, 'fa-folder-plus', 1, 1),
(2, 'extra-users', 'Extra Users', 'Add 10 more team members per slot', 'quantity', 'max_users', '10 users', 10, 25.00, 1, 20, 'fa-users-plus', 1, 2),
(3, 'extra-emails', 'Email Credits', 'Extra 5,000 emails per slot/mo', 'quantity', 'max_emails', '5k emails', 5000, 10.00, 1, 50, 'fa-envelope-open', 1, 3),
(4, 'extra-whatsapp', 'WhatsApp Credits', 'Extra 500 WhatsApp messages per slot/mo', 'quantity', 'max_whatsapp', '500 msgs', 500, 15.00, 1, 50, 'fa-whatsapp', 1, 4),
(5, 'extra-sms', 'SMS Credits', 'Extra 500 SMS messages per slot/mo', 'quantity', 'max_sms', '500 msgs', 500, 20.00, 1, 50, 'fa-comment-sms', 1, 5),
(6, 'ai-copilot', 'AI Copilot', 'Full AI analysis, drafts & auto-replies', 'boolean', 'allow_ai', 'feature', 1, 25.00, 1, 1, 'fa-robot', 1, 6),
(7, 'white-label', 'White-label', 'Remove FeedbackFlow branding entirely', 'boolean', 'allow_white_label', 'feature', 1, 35.00, 1, 1, 'fa-tag', 1, 7);

-- --------------------------------------------------------

--
-- Table structure for table `ff_admin_overrides`
--

CREATE TABLE `ff_admin_overrides` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `resource` varchar(80) NOT NULL,
  `override_value` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `set_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_ai_clusters`
--

CREATE TABLE `ff_ai_clusters` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_ai_insights`
--

CREATE TABLE `ff_ai_insights` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `type` enum('trending','sentiment','release_impact','praise','warning') NOT NULL DEFAULT 'trending',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `metric` varchar(100) DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_ai_reports`
--

CREATE TABLE `ff_ai_reports` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `content` longtext DEFAULT NULL,
  `top_themes` text DEFAULT NULL,
  `sentiment_breakdown` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_api_keys`
--

CREATE TABLE `ff_api_keys` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(12) NOT NULL,
  `scopes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_attachments`
--

CREATE TABLE `ff_attachments` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_audit_log`
--

CREATE TABLE `ff_audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `target` varchar(80) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_audit_logs`
--

CREATE TABLE `ff_audit_logs` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_automations`
--

CREATE TABLE `ff_automations` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_billing_plans`
--

CREATE TABLE `ff_billing_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `highlight_color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `slug` varchar(80) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `max_projects` int(11) NOT NULL DEFAULT 1,
  `max_users` int(11) NOT NULL DEFAULT 3,
  `max_emails` int(11) NOT NULL DEFAULT 500,
  `max_whatsapp` int(11) NOT NULL DEFAULT 0,
  `max_sms` int(11) NOT NULL DEFAULT 0,
  `allow_ai` tinyint(1) NOT NULL DEFAULT 0,
  `allow_white_label` tinyint(1) NOT NULL DEFAULT 0,
  `allow_api` tinyint(1) NOT NULL DEFAULT 0,
  `allow_export` tinyint(1) NOT NULL DEFAULT 0,
  `allow_automations` tinyint(1) NOT NULL DEFAULT 0,
  `allow_audit_logs` tinyint(1) NOT NULL DEFAULT 0,
  `allow_sso` tinyint(1) NOT NULL DEFAULT 0,
  `max_team_members` int(11) NOT NULL DEFAULT 1,
  `max_feedback_per_month` int(11) NOT NULL DEFAULT 100,
  `max_campaigns_per_month` int(11) NOT NULL DEFAULT 0,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_billing_plans`
--

INSERT INTO `ff_billing_plans` (`id`, `name`, `description`, `highlight_color`, `slug`, `price_monthly`, `price_yearly`, `currency`, `max_projects`, `max_users`, `max_emails`, `max_whatsapp`, `max_sms`, `allow_ai`, `allow_white_label`, `allow_api`, `allow_export`, `allow_automations`, `allow_audit_logs`, `allow_sso`, `max_team_members`, `max_feedback_per_month`, `max_campaigns_per_month`, `features`, `is_active`, `sort_order`) VALUES
(8, 'Starter', 'Perfect for small teams getting started', '#6366f1', 'starter', 19.00, 182.00, 'EUR', 2, 10, 1000, 100, 50, 0, 0, 0, 0, 0, 0, 0, 10, 500, 3, '[\"2 projects\",\"10 users\",\"500 feedback/mo\",\"1,000 emails/mo\",\"100 WhatsApp/mo\",\"50 SMS/mo\",\"Public roadmap\",\"Changelog\",\"QR codes\",\"Feedback widget\"]', 1, 1),
(9, 'Growth', 'For growing teams that need more power', '#8b5cf6', 'growth', 49.00, 470.00, 'EUR', 5, 25, 5000, 500, 200, 1, 0, 1, 1, 1, 1, 0, 25, 2000, 10, '[\"5 projects\",\"25 users\",\"2,000 feedback/mo\",\"5,000 emails/mo\",\"500 WhatsApp/mo\",\"200 SMS/mo\",\"AI insights\",\"Automations\",\"Audit logs\",\"API access\",\"Bulk export\",\"Review booster\"]', 1, 2),
(10, 'Pro', 'Advanced tools for professional teams', '#a855f7', 'pro', 99.00, 950.00, 'EUR', 15, 100, 20000, 2000, 1000, 1, 1, 1, 1, 1, 1, 0, 100, 10000, 50, '[\"15 projects\",\"100 users\",\"10,000 feedback/mo\",\"20,000 emails/mo\",\"2,000 WhatsApp/mo\",\"1,000 SMS/mo\",\"AI copilot\",\"White-label\",\"Priority support\",\"Custom domain\",\"SSO ready\"]', 1, 3),
(11, 'Enterprise', 'Unlimited scale with dedicated support', '#ec4899', 'enterprise', 299.00, 2870.00, 'EUR', -1, -1, -1, -1, -1, 1, 1, 1, 1, 1, 1, 1, -1, -1, -1, '[\"Unlimited projects\",\"Unlimited users\",\"Unlimited feedback\",\"Unlimited emails\",\"Unlimited WhatsApp & SMS\",\"AI copilot\",\"SSO / SAML\",\"White-label\",\"SLA\",\"Dedicated support\",\"Custom contract\"]', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `ff_billing_usage`
--

CREATE TABLE `ff_billing_usage` (
  `id` int(11) NOT NULL,
  `year_month` char(7) NOT NULL COMMENT 'YYYY-MM',
  `feedback_count` int(11) NOT NULL DEFAULT 0,
  `campaign_count` int(11) NOT NULL DEFAULT 0,
  `email_count` int(11) NOT NULL DEFAULT 0,
  `whatsapp_count` int(11) NOT NULL DEFAULT 0,
  `sms_count` int(11) NOT NULL DEFAULT 0,
  `ai_tokens_used` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_billing_usage`
--

INSERT INTO `ff_billing_usage` (`id`, `year_month`, `feedback_count`, `campaign_count`, `email_count`, `whatsapp_count`, `sms_count`, `ai_tokens_used`, `created_at`, `updated_at`) VALUES
(1, '2026-03', 0, 0, 0, 0, 0, 0, '2026-03-24 15:46:48', '2026-03-24 15:46:48');

-- --------------------------------------------------------

--
-- Table structure for table `ff_campaigns`
--

CREATE TABLE `ff_campaigns` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_campaign_recipients`
--

CREATE TABLE `ff_campaign_recipients` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `pre_rating` tinyint(1) DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_categories`
--

CREATE TABLE `ff_categories` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `icon` varchar(50) DEFAULT 'tag',
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_changelog`
--

CREATE TABLE `ff_changelog` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('new','improvement','bugfix','breaking') NOT NULL DEFAULT 'new',
  `version` varchar(50) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `author_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_cluster_feedback`
--

CREATE TABLE `ff_cluster_feedback` (
  `cluster_id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_comments`
--

CREATE TABLE `ff_comments` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `commenter_name` varchar(100) DEFAULT NULL,
  `commenter_email` varchar(191) DEFAULT NULL,
  `content` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin_reply` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_companies`
--

CREATE TABLE `ff_companies` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
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
  `onboarding_complete` tinyint(1) NOT NULL DEFAULT 0,
  `trial_ends_at` datetime DEFAULT NULL,
  `signup_source` varchar(80) DEFAULT NULL,
  `signup_ip` varchar(45) DEFAULT NULL,
  `plan_expires_at` datetime DEFAULT NULL,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `vat_number` varchar(50) DEFAULT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `billing_email` varchar(191) DEFAULT NULL,
  `billing_name` varchar(191) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_zip` varchar(20) DEFAULT NULL,
  `billing_country` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_companies`
--

INSERT INTO `ff_companies` (`id`, `name`, `slug`, `email`, `phone`, `website`, `logo`, `address`, `city`, `country`, `timezone`, `language`, `plan`, `onboarding_complete`, `trial_ends_at`, `signup_source`, `signup_ip`, `plan_expires_at`, `billing_cycle`, `is_active`, `vat_number`, `vat_rate`, `billing_email`, `billing_name`, `billing_address`, `billing_city`, `billing_zip`, `billing_country`, `notes`, `created_at`) VALUES
(1, 'Demo Company', 'demo', 'admin@demo.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'pro', 1, NULL, NULL, NULL, NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 14:46:23'),
(2, 'testsygdhsj', 'testsygdhsj', 'sdsds@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'growth', 1, '2026-04-07 14:49:03', NULL, '::1', NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 15:49:00'),
(3, 'ram', 'ram', 'ram@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'growth', 1, '2026-04-07 15:32:39', NULL, '::1', NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 16:32:34'),
(4, 'ccc', 'ccc', 'dddd@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', '', 0, NULL, NULL, '::1', NULL, 'monthly', 0, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 10:53:48'),
(5, 'fdfdf', 'fdfdf', 'dfdfd@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'growth', 1, '2026-04-08 09:57:38', NULL, '::1', NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 10:57:31'),
(6, 'amrut bhai the king', 'amrut-bhai-the-king', 'am@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'growth', 1, '2026-04-08 10:22:48', NULL, '::1', NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 11:22:45'),
(7, 'rrrrr', 'rrrrr', 'rrr@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'UTC', 'en', 'pro', 1, '2026-04-08 12:28:54', NULL, '::1', NULL, 'monthly', 1, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 13:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `ff_company_addons`
--

CREATE TABLE `ff_company_addons` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `activated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_company_databases`
--

CREATE TABLE `ff_company_databases` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `db_host` varchar(255) NOT NULL DEFAULT 'localhost',
  `db_port` smallint(5) NOT NULL DEFAULT 3306,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `db_user` varchar(64) NOT NULL DEFAULT '',
  `db_pass_enc` text DEFAULT NULL COMMENT 'AES-256-CBC encrypted, base64-stored',
  `db_status` enum('pending','active','failed','suspended') NOT NULL DEFAULT 'pending',
  `error_msg` varchar(500) DEFAULT NULL,
  `provisioned_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tenant DB connection pool — managed by DBManager';

-- --------------------------------------------------------

--
-- Table structure for table `ff_email_campaigns`
--

CREATE TABLE `ff_email_campaigns` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_export_requests`
--

CREATE TABLE `ff_export_requests` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('feedback','campaigns','analytics','full_backup') NOT NULL DEFAULT 'feedback',
  `filters` text DEFAULT NULL,
  `status` enum('pending','processing','ready','failed') NOT NULL DEFAULT 'pending',
  `file_path` varchar(255) DEFAULT NULL,
  `row_count` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_feedback`
--

CREATE TABLE `ff_feedback` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'widget',
  `rating` tinyint(1) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
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
  `ai_intent` enum('bug','feature','ux','pricing','performance','praise','other') DEFAULT NULL,
  `ai_reply` text DEFAULT NULL,
  `ai_reply_sent` tinyint(1) NOT NULL DEFAULT 0,
  `ai_reply_sent_at` datetime DEFAULT NULL,
  `duplicate_of` int(11) DEFAULT NULL,
  `impact_score` decimal(4,2) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `browser_info` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_feedback_comments`
--

CREATE TABLE `ff_feedback_comments` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_feedback_links`
--

CREATE TABLE `ff_feedback_links` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Feedback Link',
  `source` varchar(30) NOT NULL DEFAULT 'direct',
  `token` varchar(64) NOT NULL,
  `rating_question` varchar(255) DEFAULT 'How was your experience?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `click_count` int(11) NOT NULL DEFAULT 0,
  `submit_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_feedback_tags`
--

CREATE TABLE `ff_feedback_tags` (
  `feedback_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_invoices`
--

CREATE TABLE `ff_invoices` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_name` varchar(191) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `vat_number` varchar(50) DEFAULT NULL,
  `line_items` text DEFAULT NULL,
  `plan_slug` varchar(80) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('draft','sent','paid','void','overdue') NOT NULL DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `stripe_payment_intent` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_jobs`
--

CREATE TABLE `ff_jobs` (
  `id` int(11) NOT NULL,
  `type` varchar(80) NOT NULL,
  `payload` text DEFAULT NULL,
  `status` enum('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) NOT NULL DEFAULT 3,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_notifications`
--

CREATE TABLE `ff_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_onboarding_log`
--

CREATE TABLE `ff_onboarding_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL COMMENT 'e.g. register, select_plan, create_project, invite_sent, invite_accepted',
  `flow` enum('company_signup','invited_user','other') NOT NULL DEFAULT 'other',
  `meta` text DEFAULT NULL COMMENT 'JSON extra data',
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_onboarding_log`
--

INSERT INTO `ff_onboarding_log` (`id`, `user_id`, `company_id`, `action`, `flow`, `meta`, `ip`, `user_agent`, `created_at`) VALUES
(1, 2, 2, 'register', 'company_signup', '{\"name\":\"dsds\",\"company\":\"testsygdhsj\",\"email\":\"sdsds@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 15:49:00'),
(2, 2, 2, 'select_plan', 'company_signup', '{\"plan\":\"growth\",\"cycle\":\"monthly\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 15:49:03'),
(3, 2, 2, 'create_first_project', 'company_signup', '{\"project_id\":1,\"project_name\":\"testett\",\"channels\":[\"widget\",\"email\",\"qr_code\",\"whatsapp\",\"sms\",\"in_app\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 15:49:11'),
(4, 2, 2, 'onboarding_complete', 'company_signup', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 15:49:11'),
(5, 3, 3, 'register', 'company_signup', '{\"name\":\"ram\",\"company\":\"ram\",\"email\":\"ram@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:32:34'),
(6, 3, 3, 'select_plan', 'company_signup', '{\"plan\":\"growth\",\"cycle\":\"monthly\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:32:39'),
(7, 3, 3, 'create_first_project', 'company_signup', '{\"project_id\":2,\"project_name\":\"ram\",\"channels\":[\"widget\",\"email\",\"qr_code\",\"whatsapp\",\"sms\",\"in_app\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:32:46'),
(8, 3, 3, 'onboarding_complete', 'company_signup', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:32:46'),
(9, 7, 5, 'register', 'company_signup', '{\"name\":\"dfgfdfd\",\"company\":\"fdfdf\",\"email\":\"dfdfd@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:57:31'),
(10, 7, 5, 'select_plan', 'company_signup', '{\"plan\":\"growth\",\"cycle\":\"monthly\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:57:38'),
(11, 7, 5, 'create_first_project', 'company_signup', '{\"project_id\":3,\"project_name\":\"ggg\",\"channels\":[\"widget\",\"email\",\"qr_code\",\"whatsapp\",\"sms\",\"in_app\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:57:46'),
(12, 7, 5, 'onboarding_complete', 'company_signup', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:57:46'),
(13, 8, 6, 'register', 'company_signup', '{\"name\":\"amrutbhai\",\"company\":\"amrut bhai the king\",\"email\":\"am@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-25 11:22:45'),
(14, 8, 6, 'select_plan', 'company_signup', '{\"plan\":\"growth\",\"cycle\":\"monthly\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-25 11:22:48'),
(15, 8, 6, 'create_first_project', 'company_signup', '{\"project_id\":4,\"project_name\":\"house\",\"channels\":[\"widget\",\"email\",\"qr_code\",\"whatsapp\",\"sms\",\"in_app\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-25 11:23:06'),
(16, 8, 6, 'onboarding_complete', 'company_signup', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-25 11:23:06'),
(17, 9, 7, 'register', 'company_signup', '{\"name\":\"rrr\",\"company\":\"rrrrr\",\"email\":\"rrr@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 13:28:51'),
(18, 9, 7, 'select_plan', 'company_signup', '{\"plan\":\"pro\",\"cycle\":\"monthly\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 13:28:54'),
(19, 9, 7, 'create_first_project', 'company_signup', '{\"project_id\":5,\"project_name\":\"rrr\",\"channels\":[\"widget\",\"email\",\"qr_code\",\"sms\",\"in_app\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 13:29:00'),
(20, 9, 7, 'onboarding_complete', 'company_signup', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 13:29:00');

-- --------------------------------------------------------

--
-- Table structure for table `ff_projects`
--

CREATE TABLE `ff_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_projects`
--

INSERT INTO `ff_projects` (`id`, `name`, `slug`, `description`, `website`, `logo`, `owner_id`, `is_public`, `allow_anonymous`, `widget_key`, `widget_color`, `widget_position`, `widget_theme`, `widget_title`, `widget_placeholder`, `custom_domain`, `created_at`, `updated_at`) VALUES
(1, 'testett', 'testett', 'hgscvs', NULL, NULL, 2, 1, 1, '764bdeebe07fcb6b6050e16e25fbee17', '#6366f1', 'bottom-right', 'light', 'Share your feedback', 'Tell us what you think...', NULL, '2026-03-24 15:49:11', '2026-03-24 15:49:11'),
(2, 'ram', 'ram', 'ram', NULL, NULL, 3, 1, 1, '0062dc8f74e364a5338a868aadb9c5ae', '#6366f1', 'bottom-right', 'light', 'Share your feedback', 'Tell us what you think...', NULL, '2026-03-24 16:32:46', '2026-03-24 16:32:46'),
(3, 'ggg', 'ggg', 'ggg', NULL, NULL, 7, 1, 1, 'e6efe1f5ba09e894fcdfc2824f9d9ab9', '#6366f1', 'bottom-right', 'light', 'Share your feedback', 'Tell us what you think...', NULL, '2026-03-25 10:57:46', '2026-03-25 10:57:46'),
(4, 'house', 'house', 'house maintainence', NULL, NULL, 8, 1, 1, 'bd953fac66f16d6cf8684b873406aaef', '#6366f1', 'bottom-right', 'light', 'Share your feedback', 'Tell us what you think...', NULL, '2026-03-25 11:23:06', '2026-03-25 11:23:06'),
(5, 'rrr', 'rrr', 'rrr', NULL, NULL, 9, 1, 1, '623ddc28329709b72e3cd174e148c8eb', '#6366f1', 'bottom-right', 'light', 'Share your feedback', 'Tell us what you think...', NULL, '2026-03-25 13:29:00', '2026-03-25 13:29:00');

-- --------------------------------------------------------

--
-- Table structure for table `ff_project_channels`
--

CREATE TABLE `ff_project_channels` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `channel` varchar(40) NOT NULL COMMENT 'widget|email|whatsapp|sms|qr_code|in_app',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `config` text DEFAULT NULL COMMENT 'JSON channel-specific config'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_project_channels`
--

INSERT INTO `ff_project_channels` (`id`, `project_id`, `channel`, `is_active`, `config`) VALUES
(1, 1, 'widget', 1, NULL),
(2, 1, 'email', 1, NULL),
(3, 1, 'qr_code', 1, NULL),
(4, 1, 'whatsapp', 1, NULL),
(5, 1, 'sms', 1, NULL),
(6, 1, 'in_app', 1, NULL),
(7, 2, 'widget', 1, NULL),
(8, 2, 'email', 1, NULL),
(9, 2, 'qr_code', 1, NULL),
(10, 2, 'whatsapp', 1, NULL),
(11, 2, 'sms', 1, NULL),
(12, 2, 'in_app', 1, NULL),
(13, 3, 'widget', 1, NULL),
(14, 3, 'email', 1, NULL),
(15, 3, 'qr_code', 1, NULL),
(16, 3, 'whatsapp', 1, NULL),
(17, 3, 'sms', 1, NULL),
(18, 3, 'in_app', 1, NULL),
(19, 4, 'widget', 1, NULL),
(20, 4, 'email', 1, NULL),
(21, 4, 'qr_code', 1, NULL),
(22, 4, 'whatsapp', 1, NULL),
(23, 4, 'sms', 1, NULL),
(24, 4, 'in_app', 1, NULL),
(25, 5, 'widget', 1, NULL),
(26, 5, 'email', 1, NULL),
(27, 5, 'qr_code', 1, NULL),
(28, 5, 'sms', 1, NULL),
(29, 5, 'in_app', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ff_project_members`
--

CREATE TABLE `ff_project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `invited_by` int(11) DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_provisioning_log`
--

CREATE TABLE `ff_provisioning_log` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `action` varchar(80) NOT NULL COMMENT 'e.g. create_database, apply_schema, store_connection, run_migration',
  `status` enum('success','failed','skipped','warning','pending') NOT NULL DEFAULT 'pending',
  `detail` varchar(1000) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'Super admin user ID if triggered manually',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Provisioning audit trail — written by TenantProvisioner';

-- --------------------------------------------------------

--
-- Table structure for table `ff_qr_codes`
--

CREATE TABLE `ff_qr_codes` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'QR Code',
  `token` varchar(64) NOT NULL,
  `scan_count` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_rate_limits`
--

CREATE TABLE `ff_rate_limits` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_rate_limits`
--

INSERT INTO `ff_rate_limits` (`id`, `ip`, `action`, `count`, `window_start`) VALUES
(1, '::1', 'login', 5, '2026-03-25 11:28:25');

-- --------------------------------------------------------

--
-- Table structure for table `ff_review_boosters`
--

CREATE TABLE `ff_review_boosters` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `platform` enum('google','yelp','tripadvisor','trustpilot','facebook','custom') NOT NULL DEFAULT 'google',
  `review_url` varchar(500) NOT NULL,
  `min_rating` tinyint(1) NOT NULL DEFAULT 4,
  `message_template` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `requests_sent` int(11) NOT NULL DEFAULT 0,
  `requests_clicked` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_roadmap`
--

CREATE TABLE `ff_roadmap` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('planned','in_progress','done') NOT NULL DEFAULT 'planned',
  `quarter` varchar(20) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_sessions`
--

CREATE TABLE `ff_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_settings`
--

CREATE TABLE `ff_settings` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_status_incidents`
--

CREATE TABLE `ff_status_incidents` (
  `id` int(11) NOT NULL,
  `page_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('minor','major','critical','maintenance') NOT NULL DEFAULT 'minor',
  `status` enum('investigating','identified','monitoring','resolved') NOT NULL DEFAULT 'investigating',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_status_pages`
--

CREATE TABLE `ff_status_pages` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `overall_status` enum('operational','degraded','partial_outage','major_outage','maintenance') NOT NULL DEFAULT 'operational',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_subscriptions`
--

CREATE TABLE `ff_subscriptions` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_super_admin_log`
--

CREATE TABLE `ff_super_admin_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'Super admin who performed the action',
  `action` varchar(80) NOT NULL COMMENT 'e.g. view_company, impersonate, toggle_company, export_data',
  `target_company_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `meta` text DEFAULT NULL COMMENT 'JSON extra context',
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Super admin action audit trail';

-- --------------------------------------------------------

--
-- Table structure for table `ff_suppression`
--

CREATE TABLE `ff_suppression` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `type` enum('email','phone','domain') NOT NULL DEFAULT 'email',
  `value` varchar(255) NOT NULL,
  `reason` enum('unsubscribe','bounce','complaint','manual','gdpr') NOT NULL DEFAULT 'manual',
  `added_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_tags`
--

CREATE TABLE `ff_tags` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#94a3b8'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_tasks`
--

CREATE TABLE `ff_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  `priority` enum('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_translations`
--

CREATE TABLE `ff_translations` (
  `id` int(11) NOT NULL,
  `lang` varchar(10) NOT NULL,
  `key` varchar(150) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_usage`
--

CREATE TABLE `ff_usage` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `year_month` varchar(7) NOT NULL,
  `feedback_count` int(11) NOT NULL DEFAULT 0,
  `campaign_count` int(11) NOT NULL DEFAULT 0,
  `api_calls` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_users`
--

CREATE TABLE `ff_users` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('owner','admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `timezone` varchar(100) DEFAULT 'UTC',
  `language` varchar(10) DEFAULT 'en',
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','invited','disabled') NOT NULL DEFAULT 'active',
  `invite_token` varchar(64) DEFAULT NULL,
  `invite_expires` datetime DEFAULT NULL,
  `invited_by` int(11) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verify_token` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ff_users`
--

INSERT INTO `ff_users` (`id`, `company_id`, `name`, `email`, `password`, `avatar`, `role`, `timezone`, `language`, `is_super_admin`, `is_active`, `status`, `invite_token`, `invite_expires`, `invited_by`, `email_verified`, `verify_token`, `reset_token`, `reset_expires`, `last_login`, `created_at`) VALUES
(1, NULL, 'Admin User', 'admin@demo.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'owner', 'UTC', 'en', 1, 1, 'active', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-03-24 14:46:23'),
(2, 2, 'dsds', 'sdsds@gmail.com', '$2y$12$vxbtSKizadI.eWowzkLKAOQOrhvjOZsQsPkGYygwrkOs7hfXb9j6W', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-24 15:49:00'),
(3, 3, 'ram', 'ram@gmail.com', '$2y$12$KCF6WCb2cNN54KejHrMKseudD9poZi.hi.qwcHG9CQ0LplmbQZx3i', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-24 16:32:34'),
(4, NULL, 'ter', 'ter@gmail.com', '$2y$12$zpPNDz07tb0gMY1ugotYEOol2gVtpdDMdMbisGqXYbaJlICk8JSSi', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 10:37:40'),
(5, NULL, 'wewe', 'ewew@gmail.com', '$2y$12$uXKfNXdLs2YVh8osWyYNguC.A5mFpShWAYuducoZKPEsknYkUPvXa', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 10:46:07'),
(6, NULL, 'ddd', 'dddd@gmail.com', '$2y$12$4qDzFI53uYD7lFrGCVDDQ.uuBdk7JxWv/O1faOkENc3yZeO0TwR1C', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 10:53:48'),
(7, 5, 'dfgfdfd', 'dfdfd@gmail.com', '$2y$12$7cx4/B/NsvnIaFY1Hd1DtuoWfyQAzbf5sGcN.o9NvUi2NFFeGy0di', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 10:57:31'),
(8, 6, 'amrutbhai', 'am@gmail.com', '$2y$12$efu945vj4vY7qoGp5xt7ROrs1.qMe6kpg5Yg48rzeOreW8g2Y//n6', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 11:22:45'),
(9, 7, 'rrr', 'rrr@gmail.com', '$2y$12$PrNQjMPTwTS/.CzQ.9lf8./oi/Tnyq/AN7.CFyYLMdZ1gxOZMObk2', NULL, 'owner', 'UTC', 'en', 0, 1, 'active', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2026-03-25 13:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `ff_votes`
--

CREATE TABLE `ff_votes` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `voter_ip` varchar(45) DEFAULT NULL,
  `voter_email` varchar(191) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT '?',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ff_webhooks`
--

CREATE TABLE `ff_webhooks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `secret` varchar(64) DEFAULT NULL,
  `events` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_triggered` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ff_activity`
--
ALTER TABLE `ff_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `ff_addons`
--
ALTER TABLE `ff_addons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `ff_admin_overrides`
--
ALTER TABLE `ff_admin_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_resource` (`company_id`,`resource`);

--
-- Indexes for table `ff_ai_clusters`
--
ALTER TABLE `ff_ai_clusters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_ai_insights`
--
ALTER TABLE `ff_ai_insights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_ai_reports`
--
ALTER TABLE `ff_ai_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ff_api_keys`
--
ALTER TABLE `ff_api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_hash` (`key_hash`);

--
-- Indexes for table `ff_attachments`
--
ALTER TABLE `ff_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `ff_audit_log`
--
ALTER TABLE `ff_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ff_audit_logs`
--
ALTER TABLE `ff_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `ff_automations`
--
ALTER TABLE `ff_automations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_billing_plans`
--
ALTER TABLE `ff_billing_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `ff_billing_usage`
--
ALTER TABLE `ff_billing_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_month` (`year_month`);

--
-- Indexes for table `ff_campaigns`
--
ALTER TABLE `ff_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_campaign_recipients`
--
ALTER TABLE `ff_campaign_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `ff_categories`
--
ALTER TABLE `ff_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_slug` (`project_id`,`slug`);

--
-- Indexes for table `ff_changelog`
--
ALTER TABLE `ff_changelog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_cluster_feedback`
--
ALTER TABLE `ff_cluster_feedback`
  ADD PRIMARY KEY (`cluster_id`,`feedback_id`);

--
-- Indexes for table `ff_comments`
--
ALTER TABLE `ff_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `ff_companies`
--
ALTER TABLE `ff_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `ff_company_addons`
--
ALTER TABLE `ff_company_addons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_addon` (`company_id`,`addon_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `ff_company_databases`
--
ALTER TABLE `ff_company_databases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company` (`company_id`),
  ADD KEY `db_status` (`db_status`);

--
-- Indexes for table `ff_email_campaigns`
--
ALTER TABLE `ff_email_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_export_requests`
--
ALTER TABLE `ff_export_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ff_feedback`
--
ALTER TABLE `ff_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ff_feedback_comments`
--
ALTER TABLE `ff_feedback_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `ff_feedback_links`
--
ALTER TABLE `ff_feedback_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_feedback_tags`
--
ALTER TABLE `ff_feedback_tags`
  ADD PRIMARY KEY (`feedback_id`,`tag_id`);

--
-- Indexes for table `ff_invoices`
--
ALTER TABLE `ff_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `ff_jobs`
--
ALTER TABLE `ff_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `available_at` (`available_at`);

--
-- Indexes for table `ff_notifications`
--
ALTER TABLE `ff_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `ff_onboarding_log`
--
ALTER TABLE `ff_onboarding_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `ff_projects`
--
ALTER TABLE `ff_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `ff_project_channels`
--
ALTER TABLE `ff_project_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_proj_channel` (`project_id`,`channel`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_project_members`
--
ALTER TABLE `ff_project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_user` (`project_id`,`user_id`);

--
-- Indexes for table `ff_provisioning_log`
--
ALTER TABLE `ff_provisioning_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `action` (`action`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `ff_qr_codes`
--
ALTER TABLE `ff_qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `ff_rate_limits`
--
ALTER TABLE `ff_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_action` (`ip`,`action`);

--
-- Indexes for table `ff_review_boosters`
--
ALTER TABLE `ff_review_boosters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_roadmap`
--
ALTER TABLE `ff_roadmap`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_sessions`
--
ALTER TABLE `ff_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ff_settings`
--
ALTER TABLE `ff_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_key` (`project_id`,`key`);

--
-- Indexes for table `ff_status_incidents`
--
ALTER TABLE `ff_status_incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ff_status_pages`
--
ALTER TABLE `ff_status_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `ff_subscriptions`
--
ALTER TABLE `ff_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `ff_super_admin_log`
--
ALTER TABLE `ff_super_admin_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `target_company_id` (`target_company_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ff_suppression`
--
ALTER TABLE `ff_suppression`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `value` (`value`);

--
-- Indexes for table `ff_tags`
--
ALTER TABLE `ff_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_name` (`project_id`,`name`);

--
-- Indexes for table `ff_tasks`
--
ALTER TABLE `ff_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `ff_translations`
--
ALTER TABLE `ff_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lang_key` (`lang`,`key`);

--
-- Indexes for table `ff_usage`
--
ALTER TABLE `ff_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_month` (`company_id`,`year_month`);

--
-- Indexes for table `ff_users`
--
ALTER TABLE `ff_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `ff_votes`
--
ALTER TABLE `ff_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feedback_user` (`feedback_id`,`user_id`),
  ADD KEY `feedback_ip` (`feedback_id`,`voter_ip`);

--
-- Indexes for table `ff_webhooks`
--
ALTER TABLE `ff_webhooks`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ff_activity`
--
ALTER TABLE `ff_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_addons`
--
ALTER TABLE `ff_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `ff_admin_overrides`
--
ALTER TABLE `ff_admin_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_ai_clusters`
--
ALTER TABLE `ff_ai_clusters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_ai_insights`
--
ALTER TABLE `ff_ai_insights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_ai_reports`
--
ALTER TABLE `ff_ai_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_api_keys`
--
ALTER TABLE `ff_api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_attachments`
--
ALTER TABLE `ff_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_audit_log`
--
ALTER TABLE `ff_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_audit_logs`
--
ALTER TABLE `ff_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_automations`
--
ALTER TABLE `ff_automations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_billing_plans`
--
ALTER TABLE `ff_billing_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ff_billing_usage`
--
ALTER TABLE `ff_billing_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ff_campaigns`
--
ALTER TABLE `ff_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_campaign_recipients`
--
ALTER TABLE `ff_campaign_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_categories`
--
ALTER TABLE `ff_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_changelog`
--
ALTER TABLE `ff_changelog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_comments`
--
ALTER TABLE `ff_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_companies`
--
ALTER TABLE `ff_companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ff_company_addons`
--
ALTER TABLE `ff_company_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_company_databases`
--
ALTER TABLE `ff_company_databases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_email_campaigns`
--
ALTER TABLE `ff_email_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_export_requests`
--
ALTER TABLE `ff_export_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_feedback`
--
ALTER TABLE `ff_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_feedback_comments`
--
ALTER TABLE `ff_feedback_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_feedback_links`
--
ALTER TABLE `ff_feedback_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_invoices`
--
ALTER TABLE `ff_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_jobs`
--
ALTER TABLE `ff_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_notifications`
--
ALTER TABLE `ff_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_onboarding_log`
--
ALTER TABLE `ff_onboarding_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ff_projects`
--
ALTER TABLE `ff_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ff_project_channels`
--
ALTER TABLE `ff_project_channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `ff_project_members`
--
ALTER TABLE `ff_project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_provisioning_log`
--
ALTER TABLE `ff_provisioning_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_qr_codes`
--
ALTER TABLE `ff_qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_rate_limits`
--
ALTER TABLE `ff_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ff_review_boosters`
--
ALTER TABLE `ff_review_boosters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_roadmap`
--
ALTER TABLE `ff_roadmap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_settings`
--
ALTER TABLE `ff_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_status_incidents`
--
ALTER TABLE `ff_status_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_status_pages`
--
ALTER TABLE `ff_status_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_subscriptions`
--
ALTER TABLE `ff_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_super_admin_log`
--
ALTER TABLE `ff_super_admin_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_suppression`
--
ALTER TABLE `ff_suppression`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_tags`
--
ALTER TABLE `ff_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_tasks`
--
ALTER TABLE `ff_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_translations`
--
ALTER TABLE `ff_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_usage`
--
ALTER TABLE `ff_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_users`
--
ALTER TABLE `ff_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `ff_votes`
--
ALTER TABLE `ff_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ff_webhooks`
--
ALTER TABLE `ff_webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
