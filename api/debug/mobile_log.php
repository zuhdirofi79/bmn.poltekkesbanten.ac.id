<?php
/**
 * mobile_log.php — Endpoint Runtime Diagnostics dari Flutter Play AAB (Hostinger, anti-spam)
 *
 * TUJUAN:
 * Menerima POST JSON runtime diagnostics dari aplikasi Flutter (Play Store AAB).
 * Tidak butuh login token (log dipakai saat login gagal). Aman: tidak simpan token/auth, mask token-like.
 * Akses dikunci dengan header X-MOBILE-LOG-KEY (wajib cocok dengan secret server).
 *
 * PENEMPATAN: public_html/api/debug/mobile_log.php
 * LOG FILE:    DOCUMENT_ROOT/storage/mobile_logs/YYYY-MM-DD.log
 *
 * OPTIONS → 200 (CORS preflight, tanpa auth check)
 * GET     → 200 { "success": true, "status": "ok" } (healthcheck only)
 * POST    → application/json, max 20KB; header X-MOBILE-LOG-KEY wajib; missing/wrong → 401
 *
 * PHP 7.4+, Hostinger compatible. Tidak pakai framework/database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$requestId = bin2hex(random_bytes(8));

if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'mobile_log_config.php')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'mobile_log_config.php';
}
if (!defined('MOBILE_LOG_KEY') || MOBILE_LOG_KEY === '') {
    define('MOBILE_LOG_KEY', '0000000000000000000000000000000000000000000000000000000000000000');
}

function sendJson(array $body, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- OPTIONS: CORS preflight, balas 200 tanpa auth, exit ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-MOBILE-LOG-KEY');
    exit;
}

// --- GET: healthcheck only, tidak bocorkan info ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    sendJson(['success' => true, 'status' => 'ok']);
}

function serverTimestampUtc(): string {
    $micro = microtime(true);
    $sec = (int) floor($micro);
    $ms = (int) round(($micro - $sec) * 1000);
    if ($ms >= 1000) {
        $ms = 999;
    }
    return gmdate('Y-m-d\TH:i:s', $sec) . '.' . str_pad((string) $ms, 3, '0', STR_PAD_LEFT) . 'Z';
}

function looksLikeUrl(string $value): bool {
    $v = trim($value);
    return $v !== '' && (strpos($v, 'http://') === 0 || strpos($v, 'https://') === 0);
}

function maskTokenLike(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '' || looksLikeUrl($trimmed)) {
        return $value;
    }
    if (substr_count($trimmed, '.') >= 2 && preg_match('/^[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+\.[a-zA-Z0-9\-_]+/', $trimmed)) {
        $len = strlen($trimmed);
        return $len <= 20 ? '***MASKED***' : substr($trimmed, 0, 8) . '...***MASKED***...' . substr($trimmed, -4);
    }
    if (strlen($trimmed) > 40 && preg_match('/^[a-zA-Z0-9\-_]+$/', $trimmed)) {
        return substr($trimmed, 0, 8) . '...***MASKED***';
    }
    return $value;
}

function sanitizePayload(array $data): array {
    $forbidden = ['authorization', 'token', 'auth_token'];
    $out = [];
    foreach ($data as $key => $value) {
        $k = is_string($key) ? strtolower($key) : $key;
        if (in_array($k, $forbidden, true)) {
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
        $p = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($p[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getUserAgent(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function getLogDir(): string {
    $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($root === '') {
        $root = dirname(__DIR__, 2);
    }
    $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
    return $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mobile_logs';
}

function relativeLogPath(string $date): string {
    return 'storage/mobile_logs/' . $date . '.log';
}

try {
    // --- POST only (GET/OPTIONS already handled above) ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'error' => 'Method Not Allowed', 'request_id' => $requestId], 405);
    }

    // --- X-MOBILE-LOG-KEY wajib: tidak ada atau salah → 401 ---
    $keyHeader = $_SERVER['HTTP_X_MOBILE_LOG_KEY'] ?? '';
    if ($keyHeader === '' || !hash_equals(MOBILE_LOG_KEY, $keyHeader)) {
        sendJson(['success' => false, 'error' => 'Unauthorized', 'request_id' => $requestId], 401);
    }

    // --- Tolak jika ada Authorization header (jangan log request yang bawa auth) ---
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader !== '') {
        sendJson(['success' => false, 'error' => 'Authorization header not allowed', 'request_id' => $requestId], 400);
    }

    // --- Content-Type application/json ---
    $ct = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') === false) {
        sendJson(['success' => false, 'error' => 'Content-Type must be application/json', 'request_id' => $requestId], 400);
    }

    // --- Body max 20KB ---
    $rawInput = file_get_contents('php://input');
    $maxBytes = 20 * 1024;
    if (strlen($rawInput) > $maxBytes) {
        sendJson(['success' => false, 'error' => 'Payload too large (max 20KB)', 'request_id' => $requestId], 400);
    }

    $date = date('Y-m-d');
    $payload = json_decode($rawInput, true);
    $jsonErr = json_last_error();
    $logEntry = ['request_id' => $requestId];

    if ($jsonErr !== JSON_ERROR_NONE) {
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

    $logDir = getLogDir();
    $logFile = $logDir . DIRECTORY_SEPARATOR . $date . '.log';
    $writtenTo = relativeLogPath($date);

    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            sendJson([
                'success' => false,
                'error' => 'Could not create log directory',
                'request_id' => $requestId,
                'written_to' => $writtenTo,
            ], 500);
        }
    }

    $line = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    $bytesWritten = strlen($line);
    if (file_put_contents($logFile, $line, LOCK_EX | FILE_APPEND) === false) {
        sendJson([
            'success' => false,
            'error' => 'Could not write log file',
            'request_id' => $requestId,
            'written_to' => $writtenTo,
        ], 500);
    }

    sendJson([
        'success' => true,
        'written_to' => $writtenTo,
        'bytes_written' => $bytesWritten,
        'request_id' => $requestId,
    ]);

} catch (Throwable $e) {
    sendJson([
        'success' => false,
        'error' => 'Server error',
        'request_id' => $requestId,
    ], 500);
}
