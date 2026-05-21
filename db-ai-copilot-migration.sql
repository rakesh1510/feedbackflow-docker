-- FeedbackFlow: AI Copilot Migration
-- Run this on existing installs. Safe to run multiple times.

-- Add intent column to feedback
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
