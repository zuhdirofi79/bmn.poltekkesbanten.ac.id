ALTER TABLE `api_tokens` 
ADD COLUMN `last_ip` varchar(45) NULL AFTER `token_hash`,
ADD COLUMN `last_user_agent` varchar(255) NULL AFTER `last_ip`,
ADD COLUMN `last_used_at` datetime NULL AFTER `last_user_agent`,
ADD COLUMN `revoked_at` datetime NULL AFTER `last_used_at`,
ADD COLUMN `revoked_reason` varchar(255) NULL AFTER `revoked_at`;

ALTER TABLE `api_tokens`
ADD INDEX `idx_revoked_at` (`revoked_at`),
ADD INDEX `idx_last_used_at` (`last_used_at`);
