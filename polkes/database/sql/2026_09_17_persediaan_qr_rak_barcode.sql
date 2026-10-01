-- =====================================================================
-- Persediaan: QR Pintu, QR Rak, Code 128, mapping rak-barang.
-- Additive / non-destructive. Aman dijalankan berkali-kali.
-- Tidak drop, truncate, atau mengubah data existing.
-- Jalankan via phpMyAdmin jika `php artisan migrate` bermasalah.
-- =====================================================================

SET @db := DATABASE();

-- ---------------------------------------------------------------------
-- persediaan_rak
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persediaan_rak` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_rak` VARCHAR(50) NOT NULL,
  `nama_rak` VARCHAR(150) NOT NULL,
  `unit_kerja` VARCHAR(50) NOT NULL,
  `lokasi_detail` VARCHAR(255) NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `qr_path` VARCHAR(255) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'aktif',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_rak_kode_rak_unique` (`kode_rak`),
  UNIQUE KEY `persediaan_rak_qr_token_unique` (`qr_token`),
  KEY `persediaan_rak_unit_kerja_index` (`unit_kerja`),
  KEY `persediaan_rak_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- persediaan_rak_barang
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persediaan_rak_barang` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rak_id` BIGINT UNSIGNED NOT NULL,
  `barang_id` BIGINT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP NULL,
  `assigned_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_rak_barang_barang_id_unique` (`barang_id`),
  KEY `persediaan_rak_barang_rak_id_index` (`rak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- persediaan_rak_movements
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persediaan_rak_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barang_id` BIGINT UNSIGNED NOT NULL,
  `from_rak_id` BIGINT UNSIGNED NULL,
  `to_rak_id` BIGINT UNSIGNED NOT NULL,
  `moved_by` BIGINT UNSIGNED NULL,
  `moved_at` TIMESTAMP NULL,
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `persediaan_rak_movements_barang_id_index` (`barang_id`),
  KEY `persediaan_rak_movements_to_rak_id_index` (`to_rak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- persediaan_qr_akses (QR Pintu)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persediaan_qr_akses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_kerja` VARCHAR(50) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `qr_path` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_qr_akses_token_unique` (`token`),
  KEY `persediaan_qr_akses_unit_kerja_index` (`unit_kerja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- persediaan_barang.barcode_path
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'persediaan_barang' AND COLUMN_NAME = 'barcode_path'
    ),
    'ALTER TABLE persediaan_barang ADD COLUMN barcode_path VARCHAR(255) NULL',
    'SELECT "skip barcode_path"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- persediaan_barang.barcode_payload
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'persediaan_barang' AND COLUMN_NAME = 'barcode_payload'
    ),
    'ALTER TABLE persediaan_barang ADD COLUMN barcode_payload VARCHAR(120) NULL',
    'SELECT "skip barcode_payload"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- persediaan_permintaan.source
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'persediaan_permintaan' AND COLUMN_NAME = 'source'
    ),
    'ALTER TABLE persediaan_permintaan ADD COLUMN source VARCHAR(40) NULL',
    'SELECT "skip source"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- persediaan_permintaan.rak_id
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'persediaan_permintaan' AND COLUMN_NAME = 'rak_id'
    ),
    'ALTER TABLE persediaan_permintaan ADD COLUMN rak_id BIGINT UNSIGNED NULL, ADD INDEX persediaan_permintaan_rak_id_index (rak_id)',
    'SELECT "skip rak_id"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- persediaan_permintaan.idempotency_key
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'persediaan_permintaan' AND COLUMN_NAME = 'idempotency_key'
    ),
    'ALTER TABLE persediaan_permintaan ADD COLUMN idempotency_key VARCHAR(64) NULL, ADD UNIQUE INDEX persediaan_permintaan_idempotency_key_unique (idempotency_key)',
    'SELECT "skip idempotency_key"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
