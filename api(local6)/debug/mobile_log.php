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
 *   ...
 * }
 *
 * CONTOH RESPONSE SUKSES:
 * { "success": true, "request_id": "a1b2c3d4e5f6g7h8", "date": "2026-02-03" }
 *
 * PHP 7.4+, Hostinger compatible. Tidak pakai framework.
 */

// Prevent fatal output before we send JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// -----------------------------------------------------------------------------
// SET LOG_KEY SEBELUM DEPLOY: ganti 'CHANGE_ME_LONG_RANDOM' dengan string rahasia
// yang sama dengan yang dikirim mobile app di header X-LOG-KEY (min 32 karakter).
// Bisa juga pindahkan ke .env / config dan require di sini.
// -----------------------------------------------------------------------------
if (!defined('LOG_KEY')) {
    define('LOG_KEY', 'CHANGE_ME_LONG_RANDOM');
}

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
 * Server timestamp UTC ISO8601 dengan milliseconds (PHP 7.4 reliable).
 * gmdate('v') tidak reliable di PHP 7.4; pakai microtime(true).
 * Contoh output: 2026-02-03T10:15:30.123Z
 */
function serverTimestampUtc(): string {
    $micro = microtime(true);
    $sec = (int) floor($micro);
    $ms = (int) round(($micro - $sec) * 1000);
    if ($ms >= 1000) {
        $ms = 999;
    }
    return gmdate('Y-m-d\TH:i:s', $sec) . '.' . str_pad((string) $ms, 3, '0', STR_PAD_LEFT) . 'Z';
}

/**
 * Apakah string terlihat seperti URL (jangan di-mask).
 */
function looksLikeUrl(string $value): bool {
    $trimmed = trim($value);
    return strpos($trimmed, 'http://') === 0 || strpos($trimmed, 'https://') === 0;
}

/**
 * Mask string yang mirip token: JWT (dua titik) atau panjang > 40 alnum.
 * JANGAN mask URL.
 */
function maskTokenLike(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $value;
    }
    if (looksLikeUrl($trimmed)) {
        return $value;
    }
    // JWT: xxx.yyy.zzz => mask prefix + suffix
    $dots = substr_count($trimmed, '.');
    if ($dots >= 2 && preg_match('/^[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+/', $trimmed)) {
        $len = strlen($trimmed);
        if ($len <= 20) {
            return '***MASKED***';
        }
        return substr($trimmed, 0, 8) . '...***MASKED***...' . substr($trimmed, -4);
    }
    // Panjang > 40 dan alphanumeric/underscore/dash => mask
    if (strlen($trimmed) > 40 && preg_match('/^[a-zA-Z0-9\-_]+$/', $trimmed)) {
        return substr($trimmed, 0, 8) . '...***MASKED***';
    }
    return $value;
}

/**
 * Sanitize array: hapus HANYA key authorization, token, auth_token (case-insensitive).
 * Jangan hapus key 'bearer' generik. Mask nilai yang mirip token (JWT / >40 alnum).
 */
function sanitizePayload(array $data): array {
    $forbiddenKeys = ['authorization', 'token', 'auth_token'];
    $out = [];
    foreach ($data as $key => $value) {
        $keyLower = is_string($key) ? strtolower($key) : $key;
        if (in_array($keyLower, $forbiddenKeys, true)) {
            continue;
        }
        if (is_array($value)) {
            $out[$key] = sanitizePayload($value);
        } elseif (is_string($value)) {
            $out[$key] = maskTokenLike($value);
        } else {
            $out[$key] = $value;
        }
    }
    return $out;
}

/**
 * Daftar field yang diharapkan dari mobile (whitelist).
 */
function getExpectedFields(): array {
    return [
        'event', 'build_id', 'flavor', 'version_name', 'version_code', 'package_name',
        'dio_base_url', 'last_request_url', 'last_login_request_url', 'last_response_status',
        'last_response_length', 'last_error_type', 'last_error_message', 'last_error_reached_network',
        'network_state', 'timestamp', 'client_timestamp',
    ];
}

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

function getUserAgent(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Resolve dan normalisasi path log dir: __DIR__ . '/../../storage/mobile_logs'
 */
function getLogDirResolved(): string {
    $raw = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mobile_logs';
    $resolved = realpath($raw);
    if ($resolved !== false) {
        return $resolved;
    }
    // Dir belum ada; normalize dengan realpath pada parent
    $parent = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if ($parent === false) {
        return $raw;
    }
    return $parent . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mobile_logs';
}

/**
 * Basename path untuk error message (tanpa bocorkan full path).
 * Contoh: storage/mobile_logs/2026-02-03.log
 */
function logPathBasename(string $logDir, string $date): string {
    return 'storage' . DIRECTORY_SEPARATOR . 'mobile_logs' . DIRECTORY_SEPARATOR . $date . '.log';
}

$requestId = bin2hex(random_bytes(8));

try {
    // --- 1. HTTP: Hanya POST ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'error' => 'Method Not Allowed', 'request_id' => $requestId], 405);
    }

    // --- 2. Security: X-LOG-KEY wajib, harus sama dengan LOG_KEY ---
    $logKeyHeader = $_SERVER['HTTP_X_LOG_KEY'] ?? '';
    if ($logKeyHeader === '' || !is_string(LOG_KEY) || $logKeyHeader !== LOG_KEY) {
        sendJson(['success' => false, 'error' => 'Unauthorized', 'request_id' => $requestId], 401);
    }

    // --- 3. Content-Type harus application/json ---
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        sendJson(['success' => false, 'error' => 'Content-Type must be application/json', 'request_id' => $requestId], 400);
    }

    // --- 4. Baca raw body, batasi 20KB ---
    $rawInput = file_get_contents('php://input');
    $maxBytes = 20 * 1024;
    if (strlen($rawInput) > $maxBytes) {
        sendJson(['success' => false, 'error' => 'Payload too large (max 20KB)', 'request_id' => $requestId], 400);
    }

    $date = date('Y-m-d');
    $payload = json_decode($rawInput, true);
    $jsonError = json_last_error();
    $logEntry = ['request_id' => $requestId];

    if ($jsonError !== JSON_ERROR_NONE) {
        $logEntry['_raw_payload_truncated'] = substr($rawInput, 0, 2000);
        $logEntry['_json_error'] = true;
        $logEntry['_json_error_msg'] = json_last_error_msg();
    } else {
        $allowed = getExpectedFields();
        $extraKeys = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $logEntry[$key] = $value;
            } else {
                $extraKeys[] = $key;
            }
        }
        if (!empty($extraKeys)) {
            $logEntry['_extra_keys'] = $extraKeys;
        }
        $logEntry = sanitizePayload($logEntry);
    }

    $logEntry['server_timestamp'] = serverTimestampUtc();
    $logEntry['client_ip'] = getClientIp();
    $logEntry['user_agent'] = getUserAgent();

    // --- 5. Path deterministic: __DIR__ . '/../../storage/mobile_logs', normalisasi ---
    $logDir = getLogDirResolved();
    $logFile = $logDir . DIRECTORY_SEPARATOR . $date . '.log';
    $logPathForError = logPathBasename($logDir, $date);

    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            sendJson([
                'success' => false,
                'error' => 'Could not create log directory',
                'request_id' => $requestId,
                'path' => $logPathForError,
            ], 500);
        }
    }

    $line = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    if (file_put_contents($logFile, $line, LOCK_EX | FILE_APPEND) === false) {
        sendJson([
            'success' => false,
            'error' => 'Could not write log file',
            'request_id' => $requestId,
            'path' => $logPathForError,
        ], 500);
    }

    sendJson(['success' => true, 'request_id' => $requestId, 'date' => $date]);

} catch (Throwable $e) {
    sendJson([
        'success' => false,
        'error' => 'Server error',
        'request_id' => $requestId ?? null,
    ], 500);
}
