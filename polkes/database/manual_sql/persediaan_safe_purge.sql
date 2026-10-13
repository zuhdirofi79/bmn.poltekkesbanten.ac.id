-- Backup database u708283844_laravelbmn di phpMyAdmin sebelum menjalankan file ini.
-- Arsip histori bukan pengganti backup.
-- Jangan menjalankan migrate:fresh, TRUNCATE, atau FOREIGN_KEY_CHECKS=0.
-- Bagian CREATE aman diulang. Bagian ALTER dijalankan sekali.
-- Lewati perintah ALTER yang melaporkan "Duplicate column name".

CREATE TABLE IF NOT EXISTS `persediaan_barang_arsip` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_barang_id` BIGINT UNSIGNED NOT NULL,
  `kode_barang` VARCHAR(255) NOT NULL,
  `nama_barang` VARCHAR(255) NOT NULL,
  `satuan` VARCHAR(255) NULL,
  `unit_kerja` VARCHAR(255) NOT NULL,
  `stok_awal` INT NOT NULL DEFAULT 0,
  `stok_minimum` INT NOT NULL DEFAULT 0,
  `harga_satuan` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `barcode_payload` VARCHAR(255) NULL,
  `rak_id` BIGINT UNSIGNED NULL,
  `soft_deleted_at` TIMESTAMP NULL,
  `archived_by` BIGINT UNSIGNED NULL,
  `archived_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_barang_arsip_original_barang_id_unique` (`original_barang_id`),
  KEY `persediaan_barang_arsip_kode_barang_index` (`kode_barang`),
  KEY `persediaan_barang_arsip_unit_kerja_index` (`unit_kerja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `persediaan_purge_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(40) NOT NULL,
  `admin_id` BIGINT UNSIGNED NULL,
  `mode` VARCHAR(20) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'running',
  `total` INT UNSIGNED NOT NULL DEFAULT 0,
  `processed` INT UNSIGNED NOT NULL DEFAULT 0,
  `succeeded` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `step_token` VARCHAR(40) NULL,
  `locked_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_purge_batches_public_id_unique` (`public_id`),
  KEY `persediaan_purge_batches_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `persediaan_purge_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `original_barang_id` BIGINT UNSIGNED NOT NULL,
  `kode_barang` VARCHAR(255) NULL,
  `nama_barang` VARCHAR(255) NULL,
  `unit_kerja` VARCHAR(255) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `history_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `message` VARCHAR(255) NULL,
  `processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persediaan_purge_logs_batch_barang_unique` (`batch_id`, `original_barang_id`),
  KEY `persediaan_purge_logs_batch_status_id_index` (`batch_id`, `status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sekali. Lewati jika kolom sudah ada.
ALTER TABLE `persediaan_stock_movements`
  ADD COLUMN `kode_barang` VARCHAR(255) NULL AFTER `created_by`,
  ADD COLUMN `nama_barang` VARCHAR(255) NULL AFTER `kode_barang`,
  ADD COLUMN `satuan` VARCHAR(255) NULL AFTER `nama_barang`,
  ADD COLUMN `harga_satuan` DECIMAL(15,2) NULL AFTER `satuan`;

-- Sekali, hanya jika tabel rak movements sudah ada. Lewati jika kolom sudah ada.
ALTER TABLE `persediaan_rak_movements`
  ADD COLUMN `kode_barang` VARCHAR(255) NULL AFTER `catatan`,
  ADD COLUMN `nama_barang` VARCHAR(255) NULL AFTER `kode_barang`,
  ADD COLUMN `satuan` VARCHAR(255) NULL AFTER `nama_barang`;
