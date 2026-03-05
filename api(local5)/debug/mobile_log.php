<?php
/**
 * mobile_log.php — Endpoint untuk Runtime Diagnostics dari Mobile App (Flutter Play Store AAB debugging)
 *
 * TUJUAN:
 * Menerima POST JSON berisi runtime diagnostics dari aplikasi Flutter (khususnya build AAB
 * dari Google Play Internal Testing). Dipakai untuk menganalisis masalah seperti:
 * - baseUrl salah / kosong di build Play
 * - env stripped / konfigurasi berbeda antara APK lokal vs Play
 * - interceptor tidak jalan / request URL salah
 * - network error (timeout, DNS, SSL) vs server error (4xx/5xx)
 *
 * CONTOH PAYLOAD JSON (dikirim oleh Flutter ServerLogReporter):
 * {
 *   "event": "loginFail",
 *   "build_id": "BUILD_20250102_1200",
 *   "flavor": "play",
 *   "version_name": "1.0.1",
 *   "version_code": "2",
 *   "package_name": "ac.id.poltekkesbanten.laboratorium_mobile",
 *   "dio_base_url": "https://laboratorium.poltekkesbanten.ac.id/api/",
 *   "last_request_url": "https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php",
 *   "last_login_request_url": "https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php",
 *   "last_response_status": null,
 *   "last_response_length": null,
 *   "last_error_type": "DioExceptionType.connectionTimeout",
 *   "last_error_message": "Connection timed out",
 *   "last_error_reached_network": false,
 *   "network_state": "wifi",
 *   "timestamp": "2025-01-02T12:00:00.000Z",
 *   "client_timestamp": "2025-01-02T12:00:01.000Z"
 * }
 *
 * CONTOH 1 BARIS LOG HASIL PENYIMPANAN (file storage/mobile_logs/2025-02-02.log):
 * {"event":"loginFail","build_id":"BUILD_20250102_1200","flavor":"play","version_name":"1.0.1","version_code":"2","package_name":"ac.id.poltekkesbanten.laboratorium_mobile","dio_base_url":"https://laboratorium.poltekkesbanten.ac.id/api/","last_request_url":"https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php","last_login_request_url":"https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php","last_response_status":null,"last_response_length":null,"last_error_type":"DioExceptionType.connectionTimeout","last_error_message":"Connection timed out","last_error_reached_network":false,"network_state":"wifi","timestamp":"2025-01-02T12:00:00.000Z","client_timestamp":"2025-01-02T12:00:01.000Z","server_timestamp":"2025-02-02T10:15:30.123Z","client_ip":"203.0.113.42","user_agent":"laboratorium-mobile-app"}
 *
 * PHP 7.4+, Hostinger compatible. Tidak pakai framework.
 */

// Prevent fatal output before we send JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Keluarkan response JSON dan hentikan eksekusi.
 */
function sendJson(array $body, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Mask string yang mirip Bearer token (lebih dari 30 karakter hex/alphanumeric).
 * JANGAN simpan token asli di log.
 */
function maskBearerLike(string $value): string {
    $trimmed = trim($value);
    if (strlen($trimmed) <= 30) {
        return $value;
    }
    // Cek apakah seperti token (hex atau alphanumeric panjang)
    if (preg_match('/^[a-zA-Z0-9\-_\.]+$/', $trimmed)) {
        return substr($trimmed, 0, 8) . '...***MASKED***';
    }
    return $value;
}

/**
 * Recursively bersihkan array: hapus key Authorization, mask nilai yang mirip token.
 */
function sanitizePayload(array $data): array {
    $out = [];
    $forbiddenKeys = ['authorization', 'Authorization', 'token', 'Token', 'auth_token', 'bearer'];
    foreach ($data as $key => $value) {
        $keyLower = is_string($key) ? strtolower($key) : $key;
        if (in_array($keyLower, $forbiddenKeys, true)) {
            continue; // JANGAN simpan Authorization / token
        }
        if (is_array($value)) {
            $out[$key] = sanitizePayload($value);
        } elseif (is_string($value)) {
            $out[$key] = maskBearerLike($value);
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

/**
 * Daftar field yang diharapkan dari mobile (tidak wajib semua ada).
 */
function getExpectedFields(): array {
    return [
        'event', 'build_id', 'flavor', 'version_name', 'version_code', 'package_name',
        'dio_base_url', 'last_request_url', 'last_login_request_url', 'last_response_status',
        'last_response_length', 'last_error_type', 'last_error_message', 'last_error_reached_network',
        'network_state', 'timestamp', 'client_timestamp',
    ];
}

/**
 * Ambil client IP (proxy-aware).
 */
function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Ambil User-Agent.
 */
function getUserAgent(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

try {
    // --- 1. HTTP: Hanya POST ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'error' => 'Method Not Allowed'], 405);
    }

    // --- 2. Content-Type harus application/json ---
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        sendJson(['success' => false, 'error' => 'Content-Type must be application/json'], 400);
    }

    // --- 3. Baca raw body, batasi 20KB ---
    $rawInput = file_get_contents('php://input');
    $maxBytes = 20 * 1024; // 20KB
    if (strlen($rawInput) > $maxBytes) {
        sendJson(['success' => false, 'error' => 'Payload too large (max 20KB)'], 400);
    }

    $payload = json_decode($rawInput, true);
    $jsonError = json_last_error();
    $logEntry = [];

    if ($jsonError !== JSON_ERROR_NONE) {
        // JSON invalid → tetap simpan raw payload + error flag
        $logEntry['_raw_payload_truncated'] = substr($rawInput, 0, 2000);
        $logEntry['_json_error'] = true;
        $logEntry['_json_error_msg'] = json_last_error_msg();
        $payload = [];
    } else {
        // Hanya ambil field yang diharapkan (whitelist)
        $allowed = getExpectedFields();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $payload)) {
                $logEntry[$field] = $payload[$field];
            }
        }
        // Sanitize: JANGAN simpan Authorization, mask token-like
        $logEntry = sanitizePayload($logEntry);
    }

    // --- 4. Tambah field server ---
    $logEntry['server_timestamp'] = gmdate('Y-m-d\TH:i:s.v\Z');
    $logEntry['client_ip'] = getClientIp();
    $logEntry['user_agent'] = getUserAgent();

    // --- 5. Simpan ke file: public_html/storage/mobile_logs/YYYY-MM-DD.log ---
    // Path: dari api/debug naik 2 level = parent of api (public_html atau project root)
    $date = date('Y-m-d');
    $baseDir = dirname(__DIR__, 2);
    $logDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mobile_logs';
    $logFile = $logDir . DIRECTORY_SEPARATOR . $date . '.log';

    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            sendJson(['success' => false, 'error' => 'Could not create log directory'], 500);
        }
    }

    $line = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    if (file_put_contents($logFile, $line, LOCK_EX | FILE_APPEND) === false) {
        sendJson(['success' => false, 'error' => 'Could not write log file'], 500);
    }

    sendJson(['success' => true]);

} catch (Throwable $e) {
    // Semua exception ditangkap; endpoint tidak boleh fatal error
    sendJson(['success' => false, 'error' => 'Server error'], 500);
}
