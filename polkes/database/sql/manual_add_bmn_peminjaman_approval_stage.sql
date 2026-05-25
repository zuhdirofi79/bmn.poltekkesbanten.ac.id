-- Manual idempotent SQL untuk phpMyAdmin: kolom workflow approval peminjaman aset BMN.
-- Aman dijalankan berulang (cek information_schema / SHOW INDEX sebelum ALTER).

-- ============================================================
-- 1. Kolom approval_stage
-- ============================================================
SET @db := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND COLUMN_NAME = 'approval_stage'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `peminjaman_aset_bmn`
     ADD COLUMN `approval_stage` ENUM('verifikator_1','verifikator_2','final_report','completed','rejected')
     NOT NULL DEFAULT 'verifikator_1' AFTER `status`",
    'SELECT "approval_stage already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. Kolom verifikator 1
-- ============================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND COLUMN_NAME = 'verifier_1_id'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `peminjaman_aset_bmn`
     ADD COLUMN `verifier_1_id` BIGINT UNSIGNED NULL AFTER `approval_stage`,
     ADD COLUMN `verifier_1_approved_at` TIMESTAMP NULL AFTER `verifier_1_id`,
     ADD COLUMN `verifier_1_note` TEXT NULL AFTER `verifier_1_approved_at`",
    'SELECT "verifier_1 columns already exist" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. Kolom verifikator 2
-- ============================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND COLUMN_NAME = 'verifier_2_id'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `peminjaman_aset_bmn`
     ADD COLUMN `verifier_2_id` BIGINT UNSIGNED NULL AFTER `verifier_1_note`,
     ADD COLUMN `verifier_2_approved_at` TIMESTAMP NULL AFTER `verifier_2_id`,
     ADD COLUMN `verifier_2_note` TEXT NULL AFTER `verifier_2_approved_at`",
    'SELECT "verifier_2 columns already exist" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 4. Kolom final report
-- ============================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND COLUMN_NAME = 'final_report_by'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `peminjaman_aset_bmn`
     ADD COLUMN `final_report_by` BIGINT UNSIGNED NULL AFTER `verifier_2_note`,
     ADD COLUMN `final_report_at` TIMESTAMP NULL AFTER `final_report_by`,
     ADD COLUMN `final_report_note` TEXT NULL AFTER `final_report_at`",
    'SELECT "final_report columns already exist" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 5. Kolom reject workflow
-- ============================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND COLUMN_NAME = 'rejected_stage'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `peminjaman_aset_bmn`
     ADD COLUMN `rejected_stage` VARCHAR(50) NULL AFTER `final_report_note`,
     ADD COLUMN `rejected_reason` TEXT NULL AFTER `rejected_stage`",
    'SELECT "rejected_stage columns already exist" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 6. Index (idempotent)
-- ============================================================
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND INDEX_NAME = 'peminjaman_aset_bmn_approval_stage_idx'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn` ADD INDEX `peminjaman_aset_bmn_approval_stage_idx` (`approval_stage`)',
    'SELECT "index approval_stage already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND INDEX_NAME = 'peminjaman_aset_bmn_verifier_1_idx'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn` ADD INDEX `peminjaman_aset_bmn_verifier_1_idx` (`verifier_1_id`)',
    'SELECT "index verifier_1 already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND INDEX_NAME = 'peminjaman_aset_bmn_verifier_2_idx'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn` ADD INDEX `peminjaman_aset_bmn_verifier_2_idx` (`verifier_2_id`)',
    'SELECT "index verifier_2 already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND INDEX_NAME = 'peminjaman_aset_bmn_final_report_by_idx'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn` ADD INDEX `peminjaman_aset_bmn_final_report_by_idx` (`final_report_by`)',
    'SELECT "index final_report_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 7. Foreign keys (idempotent)
-- ============================================================
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND CONSTRAINT_NAME = 'fk_peminjaman_aset_bmn_verifier_1'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn`
     ADD CONSTRAINT `fk_peminjaman_aset_bmn_verifier_1`
     FOREIGN KEY (`verifier_1_id`) REFERENCES `users`(`id`) ON DELETE SET NULL',
    'SELECT "FK verifier_1 already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND CONSTRAINT_NAME = 'fk_peminjaman_aset_bmn_verifier_2'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn`
     ADD CONSTRAINT `fk_peminjaman_aset_bmn_verifier_2`
     FOREIGN KEY (`verifier_2_id`) REFERENCES `users`(`id`) ON DELETE SET NULL',
    'SELECT "FK verifier_2 already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'peminjaman_aset_bmn' AND CONSTRAINT_NAME = 'fk_peminjaman_aset_bmn_final_report_by'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `peminjaman_aset_bmn`
     ADD CONSTRAINT `fk_peminjaman_aset_bmn_final_report_by`
     FOREIGN KEY (`final_report_by`) REFERENCES `users`(`id`) ON DELETE SET NULL',
    'SELECT "FK final_report_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 8. Backfill data lama
-- ============================================================
UPDATE `peminjaman_aset_bmn`
SET `approval_stage` = 'completed'
WHERE `status` IN ('approved', 'dipinjam', 'dikembalikan');

UPDATE `peminjaman_aset_bmn`
SET `approval_stage` = 'rejected'
WHERE `status` = 'rejected';

UPDATE `peminjaman_aset_bmn`
SET `approval_stage` = 'verifikator_1'
WHERE `status` = 'pending';
