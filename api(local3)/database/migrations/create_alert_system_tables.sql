-- Alert Rules Configuration Table
CREATE TABLE IF NOT EXISTS `alert_rules` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('IP_BASED','TOKEN_BASED','USER_BASED','ENDPOINT_BASED','COMPOSITE') NOT NULL,
  `threshold_warning` int UNSIGNED NOT NULL,
  `threshold_critical` int UNSIGNED NOT NULL,
  `time_window_seconds` int UNSIGNED NOT NULL,
  `scope` varchar(255) NULL COMMENT 'IP, token_hash, user_id, endpoint pattern',
  `severity` enum('WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
  `cooldown_seconds` int UNSIGNED NOT NULL DEFAULT 300 COMMENT 'Minimum seconds between same alert',
  `auto_action` json NULL COMMENT 'Automated response actions',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_name` (`rule_name`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Events (Triggered Alerts)
CREATE TABLE IF NOT EXISTS `alert_events` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` int UNSIGNED NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `severity` enum('WARNING','CRITICAL') NOT NULL,
  `source_type` enum('IP','TOKEN','USER','ENDPOINT') NOT NULL,
  `source_value` varchar(255) NOT NULL COMMENT 'IP address, token hash, user_id, endpoint',
  `trigger_count` int UNSIGNED NOT NULL COMMENT 'Count that triggered alert',
  `time_window_seconds` int UNSIGNED NOT NULL,
  `metadata` json NULL COMMENT 'Additional context',
  `fired_at` datetime NOT NULL,
  `acknowledged_at` datetime NULL,
  `resolved_at` datetime NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rule_id` (`rule_id`),
  KEY `idx_fired_at` (`fired_at`),
  KEY `idx_severity` (`severity`),
  KEY `idx_source` (`source_type`, `source_value`),
  KEY `idx_unresolved` (`resolved_at`),
  KEY `idx_rule_fired` (`rule_id`, `fired_at`),
  CONSTRAINT `alert_events_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert State (Prevent Duplicate Firing)
CREATE TABLE IF NOT EXISTS `alert_state` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` int UNSIGNED NOT NULL,
  `source_hash` varchar(64) NOT NULL COMMENT 'SHA256 hash of (rule_id + source_type + source_value)',
  `last_fired_at` datetime NOT NULL,
  `fire_count` int UNSIGNED NOT NULL DEFAULT 1,
  `escalated` tinyint(1) NOT NULL DEFAULT 0,
  `cooldown_until` datetime NOT NULL,
  `metadata` json NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_source` (`rule_id`, `source_hash`),
  KEY `idx_cooldown` (`cooldown_until`),
  KEY `idx_rule_cooldown` (`rule_id`, `cooldown_until`),
  CONSTRAINT `alert_state_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Metrics (Efficient Count Storage)
CREATE TABLE IF NOT EXISTS `alert_metrics` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` int UNSIGNED NOT NULL,
  `source_hash` varchar(64) NOT NULL COMMENT 'SHA256 hash of source',
  `window_start` datetime NOT NULL COMMENT 'Start of time window',
  `count` int UNSIGNED NOT NULL DEFAULT 1,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_source_window` (`rule_id`, `source_hash`, `window_start`),
  KEY `idx_rule_window` (`rule_id`, `window_start`),
  KEY `idx_cleanup` (`window_start`),
  CONSTRAINT `alert_metrics_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked IPs (Automated Response)
CREATE TABLE IF NOT EXISTS `blocked_ips` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `blocked_at` datetime NOT NULL,
  `blocked_until` datetime NOT NULL,
  `reason` varchar(255) NOT NULL,
  `rule_id` int UNSIGNED NULL,
  `alert_id` bigint UNSIGNED NULL,
  `auto_unblock` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_blocked_until` (`blocked_until`),
  KEY `idx_active` (`blocked_until`, `ip_address`),
  CONSTRAINT `blocked_ips_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Alert Rules
INSERT INTO `alert_rules` (`rule_name`, `rule_type`, `threshold_warning`, `threshold_critical`, `time_window_seconds`, `scope`, `severity`, `cooldown_seconds`, `auto_action`, `enabled`) VALUES
('EXCESSIVE_REQUESTS_PER_IP', 'IP_BASED', 100, 200, 60, NULL, 'CRITICAL', 300, '{"block_ip": true, "duration_seconds": 3600}', 1),
('AUTH_FAILURE_BURST', 'IP_BASED', 5, 10, 60, '/auth/*', 'CRITICAL', 600, '{"block_ip": true, "duration_seconds": 7200}', 1),
('TOKEN_INVALID_BURST', 'TOKEN_BASED', 3, 5, 60, NULL, 'WARNING', 300, '{"revoke_token": true}', 1),
('HIGH_401_RATIO', 'IP_BASED', 10, 20, 300, NULL, 'CRITICAL', 600, '{"block_ip": true, "duration_seconds": 3600}', 1),
('TOKEN_MULTI_IP', 'TOKEN_BASED', 2, 3, 60, NULL, 'CRITICAL', 300, '{"revoke_token": true}', 1),
('SENSITIVE_ENDPOINT_ABUSE', 'ENDPOINT_BASED', 5, 10, 300, '/admin/*', 'CRITICAL', 600, '{"flag_user": true}', 1),
('ABNORMAL_BURST', 'IP_BASED', 50, 100, 10, NULL, 'WARNING', 300, NULL, 1),
('REPEATED_403', 'USER_BASED', 3, 5, 300, NULL, 'WARNING', 300, '{"flag_user": true}', 1);
