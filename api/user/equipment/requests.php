<?php
/**
 * Get User Equipment Requests Endpoint (User)
 * 
 * Purpose: Get list of user's own equipment requests
 * 
 * Location: api/user/equipment/requests.php
 * 
 * Endpoint: GET /api/user/equipment/requests.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 20,
 *       "jenis": "Aset Laboratorium",
 *       "status": "Selesai",
 *       "tgl_pinjaman": "2024-02-11",
 *       ...
 *     }
 *   ]
 * }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

// Setup logging
$logDir = __DIR__ . '/../../_logs';
$logFile = $logDir . '/requests_debug.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function log_requests_debug(string $msg): void {
    global $logFile;
    $line = '[' . date('c') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

try {
    log_requests_debug('=== START requests.php ===');
    
    // Get token hash for correlation
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? getallheaders()['Authorization'] ?? null;
    $tokenHashShort = 'unknown';
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
        $token = trim($matches[1]);
        $tokenHashShort = substr(hash('sha256', $token), 0, 8);
    }
    log_requests_debug('TOKEN_HASH_SHORT=' . $tokenHashShort);
    
    // Validate token and get user
    log_requests_debug('BEFORE AuthMiddleware::validateToken()');
    $user = AuthMiddleware::validateToken();
    $userId = $user['id'] ?? null;
    log_requests_debug('AFTER AuthMiddleware::validateToken() | user_id=' . $userId . ' | user_name=' . ($user['name'] ?? 'null'));
    
    // Validate user_id is not null
    if ($userId === null || !is_numeric($userId)) {
        log_requests_debug('ERROR: user_id is null or invalid after validateToken');
        throw new RuntimeException("Invalid user_id from token validation: " . var_export($userId, true));
    }
    
    // Get database connection
    log_requests_debug('BEFORE Database::getInstance()');
    $db = Database::getInstance()->getConnection();
    log_requests_debug('AFTER Database::getInstance() | DB connected ok');
    
    // Log database info for correlation
    try {
        $dbInfo = $db->query("SELECT DATABASE() as db_name, @@hostname as hostname, @@port as port")->fetch(PDO::FETCH_ASSOC);
        log_requests_debug('DB_INFO: database=' . ($dbInfo['db_name'] ?? 'null') . ' | hostname=' . ($dbInfo['hostname'] ?? 'null') . ' | port=' . ($dbInfo['port'] ?? 'null'));
    } catch (Exception $e) {
        log_requests_debug('DB_INFO_ERROR: ' . $e->getMessage());
    }
    
    // Sanity check: Get 5 latest rows without filter
    try {
        $sanityStmt = $db->query("SELECT id, user_id, created_at FROM peminjaman ORDER BY id DESC LIMIT 5");
        $sanityRows = $sanityStmt->fetchAll(PDO::FETCH_ASSOC);
        log_requests_debug('SANITY_CHECK: latest_5_rows=' . json_encode($sanityRows, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        log_requests_debug('SANITY_CHECK_ERROR: ' . $e->getMessage());
    }
    
    // Count total requests for this user_id
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE user_id = :user_id");
        $countStmt->execute(['user_id' => $userId]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        log_requests_debug('COUNT_QUERY: user_id=' . $userId . ' | total=' . ($countResult['total'] ?? 'null'));
    } catch (Exception $e) {
        log_requests_debug('COUNT_QUERY_ERROR: ' . $e->getMessage());
    }
    
    // Get user's requests
    $sql = "
        SELECT 
            id, jurusan, user_id, jenis, ruangan,
            penanggung_jawab, tingkat, waktu_mulai,
            waktu_selesai, tujuan, tgl_pinjaman,
            tgl_pengembalian, status, created_at
        FROM peminjaman
        WHERE user_id = :user_id
        ORDER BY created_at DESC
    ";
    
    log_requests_debug('BEFORE prepare SQL | user_id=' . $userId);
    log_requests_debug('SQL=' . $sql);
    $stmt = $db->prepare($sql);
    
    log_requests_debug('BEFORE execute SQL | params=' . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE));
    $stmt->execute(['user_id' => $userId]);
    log_requests_debug('AFTER execute SQL');
    
    $requests = $stmt->fetchAll();
    log_requests_debug('AFTER fetchAll | count=' . count($requests));
    
    // Log first few request IDs for debugging
    if (count($requests) > 0) {
        $firstFew = array_slice($requests, 0, 3);
        log_requests_debug('FIRST_FEW_REQUESTS: ' . json_encode(array_map(function($r) {
            return ['id' => $r['id'], 'user_id' => $r['user_id'], 'created_at' => $r['created_at']];
        }, $firstFew), JSON_UNESCAPED_UNICODE));
    }
    
    // Format response
    $formattedRequests = array_map(function($request) {
        return [
            'id' => (int)$request['id'],
            'jurusan' => $request['jurusan'],
            'jenis' => $request['jenis'],
            'ruangan' => $request['ruangan'],
            'penanggung_jawab' => $request['penanggung_jawab'],
            'tingkat' => $request['tingkat'],
            'waktu_mulai' => $request['waktu_mulai'],
            'waktu_selesai' => $request['waktu_selesai'],
            'tujuan' => $request['tujuan'],
            'tgl_pinjaman' => $request['tgl_pinjaman'],
            'tgl_pengembalian' => $request['tgl_pengembalian'],
            'status' => $request['status'],
            'created_at' => $request['created_at']
        ];
    }, $requests);
    
    log_requests_debug('FORMATTED_REQUESTS_COUNT=' . count($formattedRequests));
    log_requests_debug('=== END requests.php SUCCESS ===');
    
    ResponseHelper::success($formattedRequests, 'Equipment requests retrieved successfully');
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    log_requests_debug('EXCEPTION: class=' . $errorClass . ' | message=' . $errorMessage);
    log_requests_debug('EXCEPTION_TRACE: ' . $e->getTraceAsString());
    log_requests_debug('=== END requests.php ERROR ===');
    
    error_log("@@@PROOF_USER_REQUESTS_FAIL@@@ class=$errorClass message=$errorMessage");
    error_log("Get user equipment requests error: $errorMessage");
    
    // Determine proper status code
    $statusCode = 500;
    $errorCode = null;
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'USER_REQUESTS_ERROR';
    }
    
    ResponseHelper::error('Terjadi kesalahan saat mengambil data request peralatan', $statusCode, null, $errorCode);
}
