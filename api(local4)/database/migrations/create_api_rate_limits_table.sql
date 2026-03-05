CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` varchar(128) NOT NULL,
  `identifier_type` enum('token','ip') NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `request_count` int UNSIGNED NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL,
  `last_request_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier_type_endpoint_window` (`identifier`, `identifier_type`, `endpoint`, `window_start`),
  KEY `identifier_type` (`identifier`, `identifier_type`),
  KEY `endpoint` (`endpoint`),
  KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
