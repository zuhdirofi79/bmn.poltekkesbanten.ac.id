-- =====================================================================
-- BMN: Tambah kolom Nota Dinas dan Bukti Foto Pengembalian.
-- Aman dijalankan berkali-kali (idempotent).
-- Jalankan via phpMyAdmin / mysql client jika tidak menjalankan
-- `php artisan migrate` di server production.
--
-- TIDAK mengubah kolom existing. TIDAK menambahkan kode_transaksi.
-- TIDAK menambahkan received_by. TIDAK menambahkan peminjaman_id pada
-- tabel pengembalian.
-- =====================================================================

SET @db := DATABASE();

-- ---------------------------------------------------------------------
-- peminjaman_aset_bmn.nota_dinas_path
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'peminjaman_aset_bmn'
      AND COLUMN_NAME = 'nota_dinas_path'
    ),
    'ALTER TABLE peminjaman_aset_bmn ADD COLUMN nota_dinas_path VARCHAR(255) NULL AFTER catatan',
    'SELECT "skip nota_dinas_path"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- peminjaman_aset_bmn.nota_dinas_original_name
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'peminjaman_aset_bmn'
      AND COLUMN_NAME = 'nota_dinas_original_name'
    ),
    'ALTER TABLE peminjaman_aset_bmn ADD COLUMN nota_dinas_original_name VARCHAR(255) NULL AFTER nota_dinas_path',
    'SELECT "skip nota_dinas_original_name"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- pengembalian_aset_bmn.bukti_foto_path
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'pengembalian_aset_bmn'
      AND COLUMN_NAME = 'bukti_foto_path'
    ),
    'ALTER TABLE pengembalian_aset_bmn ADD COLUMN bukti_foto_path VARCHAR(255) NULL AFTER catatan_pengembalian',
    'SELECT "skip bukti_foto_path"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- pengembalian_aset_bmn.bukti_foto_original_name
-- ---------------------------------------------------------------------
SET @sql := (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'pengembalian_aset_bmn'
      AND COLUMN_NAME = 'bukti_foto_original_name'
    ),
    'ALTER TABLE pengembalian_aset_bmn ADD COLUMN bukti_foto_original_name VARCHAR(255) NULL AFTER bukti_foto_path',
    'SELECT "skip bukti_foto_original_name"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
