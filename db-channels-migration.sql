-- FeedbackFlow — Channels Feature Migration
-- Run this SQL on your database AFTER the main install.sql
-- This adds multi-channel feedback collection support

-- Add source tracking to existing feedback table
ALTER TABLE `ff_feedback`
  ADD COLUMN IF NOT EXISTS `source` varchar(30) NOT NULL DEFAULT 'widget' AFTER `project_id`,
  ADD COLUMN IF NOT EXISTS `rating` tinyint(1) DEFAULT NULL AFTER `source`,
  ADD COLUMN IF NOT EXISTS `campaign_id` int(11) DEFAULT NULL AFTER `rating`;

-- Email Campaigns
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

-- Campaign Recipients
CREATE TABLE IF NOT EXISTS `ff_campaign_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `token` varchar(64) NOT NULL UNIQUE,
  `pre_rating` tinyint(1) DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shareable Feedback Links (QR / WhatsApp / SMS / Direct)
CREATE TABLE IF NOT EXISTS `ff_feedback_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Feedback Link',
  `source` varchar(30) NOT NULL DEFAULT 'direct',
  `token` varchar(64) NOT NULL UNIQUE,
  `rating_question` varchar(255) DEFAULT 'How was your experience?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `click_count` int(11) NOT NULL DEFAULT 0,
  `submit_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
