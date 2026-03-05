<?php
/**
 * mobile_log_config.php — Konfigurasi optional untuk endpoint mobile_log.php
 *
 * TUJUAN:
 * Mendefinisikan MOBILE_LOG_KEY untuk validasi header X-MOBILE-LOG-KEY.
 * Jika file ini tidak ada atau MOBILE_LOG_KEY tidak didefinisikan, mobile_log.php
 * berjalan dalam mode dev (accept tanpa key, response berisi warning).
 *
 * PENEMPATAN: public_html/api/debug/mobile_log_config.php (satu folder dengan mobile_log.php)
 *
 * OPSI 1 — Hardcode (ganti 'your_long_random_secret' sebelum production):
 *   define('MOBILE_LOG_KEY', 'your_long_random_secret');
 *
 * OPSI 2 — Dari environment (Hostinger: set di panel / .env jika dipakai):
 *   define('MOBILE_LOG_KEY', getenv('MOBILE_LOG_KEY') ?: '');
 *
 * PHP 7.4+, tidak pakai framework.
 */

if (!defined('MOBILE_LOG_KEY')) {
    // Baca dari env jika ada; kalau tidak, kosong = mode dev (key tidak wajib)
    $key = getenv('MOBILE_LOG_KEY');
    define('MOBILE_LOG_KEY', $key !== false ? (string) $key : '');
}
