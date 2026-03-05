-- IP Reputation Cache Table
CREATE TABLE IF NOT EXISTS `ip_reputation` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reputation_score` int NOT NULL DEFAULT 0 COMMENT 'Negative=good, Positive=bad, Range: -100 to +1000',
  `first_seen` datetime NOT NULL,
  `last_seen` datetime NOT NULL,
  `last_incident_at` datetime NULL COMMENT 'Last time reputation increased',
  `total_alerts` int UNSIGNED NOT NULL DEFAULT 0,
  `critical_alerts` int UNSIGNED NOT NULL DEFAULT 0,
  `auto_block_count` int UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('NORMAL','SUSPICIOUS','MALICIOUS') NOT NULL DEFAULT 'NORMAL',
  `last_decay_at` datetime NULL COMMENT 'Last time score decayed',
  `metadata` json NULL COMMENT 'Additional context (last_attacks, patterns, etc.)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_reputation_score` (`reputation_score`),
  KEY `idx_status` (`status`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_last_incident` (`last_incident_at`),
  KEY `idx_decay_candidate` (`status`, `last_incident_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Score thresholds (defined in code, but documented):
-- NORMAL: -100 to +10
-- SUSPICIOUS: +11 to +50
-- MALICIOUS: +51 to +1000
-- Auto-block threshold: +30 (configurable)
