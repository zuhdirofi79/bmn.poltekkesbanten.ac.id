<?php
declare(strict_types=1);

/**
 * Create Equipment Request Endpoint (User)
 * 
 * Purpose: Create new equipment request
 * 
 * Location: api/user/equipment/request/create.php
 * 
 * Endpoint: POST /api/user/equipment/request/create.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "jenis": "Aset Laboratorium",
 *   "ruangan": "Lab Kimia Dasar dan Terapan",
 *   "penanggung_jawab": "Dosen Name",
 *   "tingkat": "1A",
 *   "waktu_mulai": "08:00:00",
 *   "waktu_selesai": "10:00:00",
 *   "tujuan": "Praktikum",
 *   "tgl_pinjaman": "2024-02-15",
 *   "items": [
 *     {
 *       "barang_id": 23,
 *       "barang_type": "inventaris",
 *       "stok_pinjam": 1
 *     }
 *   ]
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "id": 25,
 *     "status": "Menunggu Konfirmasi",
 *     ...
 *   },
 *   "message": "Request berhasil dibuat",
 *   "request_id": 25
 * }
 */

// === HARD DEBUG TRAP (TEMP) ===
$debugId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$logDir  = __DIR__ . '/../../../_logs'; // /api/user/equipment/request -> /api/_logs
$logFile = $logDir . '/create_request_error.log';

if (!is_dir($logDir)) { 
    @mkdir($logDir, 0755, true); 
}

function log_create_err(string $msg): void {
    global $logFile, $debugId;
    $line = '[' . date('c') . '][' . $debugId . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // jangan bocorin error ke publik
ini_set('log_errors', '1');

// WARNING/NOTICE => Exception supaya masuk catch
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// FATAL error trap via shutdown handler
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_create_err('FATAL: ' . json_encode($err));
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success'  => false,
            'message'  => 'Terjadi kesalahan saat membuat request peralatan',
            'debug_id' => $GLOBALS['debugId'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// CRITICAL: Set headers BEFORE any output (including whitespace)
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    log_create_err('START create.php');
    
    // Sanity check log directory
    log_create_err('LOGDIR=' . $logDir . ' | writable=' . (is_writable($logDir) ? 'yes' : 'no'));
    log_create_err('LOGFILE=' . $logFile);
    
    // === FIX: Calculate API base path correctly ===
    // create.php location: /api/user/equipment/request/create.php
    // To reach /api/ we need to go up 3 levels: ../../../ 
    // Using realpath() to resolve symlinks and normalize path
    $API_BASE = realpath(__DIR__ . '/../../../');
    
    if ($API_BASE === false) {
        throw new RuntimeException("Cannot resolve API base path from: " . __DIR__);
    }
    
    log_create_err('API_BASE=' . $API_BASE);
    log_create_err('__DIR__=' . __DIR__);
    
    // Require bootstrap/config files with path validation
    $dbPath = $API_BASE . '/config/database.php';
    $responsePath = $API_BASE . '/config/response.php';
    $authPath = $API_BASE . '/middleware/auth.php';
    
    log_create_err('BEFORE require config/database.php | path=' . $dbPath);
    log_create_err('FILE_EXISTS=' . (file_exists($dbPath) ? 'yes' : 'no'));
    if (!file_exists($dbPath)) {
        log_create_err('ERROR: Config file not found: ' . $dbPath);
        throw new RuntimeException("Config file not found: $dbPath");
    }
    require_once $dbPath;
    log_create_err('AFTER require config/database.php | OK');
    
    log_create_err('BEFORE require config/response.php | path=' . $responsePath);
    log_create_err('FILE_EXISTS=' . (file_exists($responsePath) ? 'yes' : 'no'));
    if (!file_exists($responsePath)) {
        log_create_err('ERROR: Config file not found: ' . $responsePath);
        throw new RuntimeException("Config file not found: $responsePath");
    }
    require_once $responsePath;
    log_create_err('AFTER require config/response.php | OK');
    
    log_create_err('BEFORE require middleware/auth.php | path=' . $authPath);
    log_create_err('FILE_EXISTS=' . (file_exists($authPath) ? 'yes' : 'no'));
    if (!file_exists($authPath)) {
        log_create_err('ERROR: Middleware file not found: ' . $authPath);
        throw new RuntimeException("Middleware file not found: $authPath");
    }
    require_once $authPath;
    log_create_err('AFTER require middleware/auth.php | OK');
    
    // Validate token and get user
    log_create_err('BEFORE AuthMiddleware::validateToken()');
    
    // Get token hash for correlation (before validateToken which hashes it)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? getallheaders()['Authorization'] ?? null;
    $tokenHashShort = 'unknown';
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
        $token = trim($matches[1]);
        $tokenHashShort = substr(hash('sha256', $token), 0, 8);
    }
    log_create_err('TOKEN_HASH_SHORT=' . $tokenHashShort);
    
    $user = AuthMiddleware::validateToken();
    $userId = $user['id'] ?? null;
    log_create_err('AFTER AuthMiddleware::validateToken() | user_id=' . $userId . ' | user_name=' . ($user['name'] ?? 'null'));
    
    // Validate user_id is not null
    if ($userId === null || !is_numeric($userId)) {
        log_create_err('ERROR: user_id is null or invalid after validateToken');
        throw new RuntimeException("Invalid user_id from token validation: " . var_export($userId, true));
    }
    
    // Get JSON input
    log_create_err('BEFORE json_decode input');
    $input = json_decode(file_get_contents('php://input'), true);
    log_create_err('AFTER json_decode input | input_keys=' . (is_array($input) ? implode(',', array_keys($input)) : 'null'));
    
    if (!$input) {
        log_create_err('ERROR: Invalid JSON input');
        ResponseHelper::error('Invalid JSON input', 400);
    }
    
    // Validate required fields
    $required = ['jenis', 'ruangan', 'tingkat', 'waktu_mulai', 'waktu_selesai', 'tujuan', 'tgl_pinjaman', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            log_create_err("ERROR: Field $field is required but empty");
            ResponseHelper::error("Field $field is required", 400);
        }
    }
    
    if (!is_array($input['items']) || empty($input['items'])) {
        log_create_err('ERROR: Items array is required and must not be empty');
        ResponseHelper::error('Items array is required and must not be empty', 400);
    }
    
    log_create_err('VALIDATION OK | items_count=' . count($input['items']));
    
    // Get database connection
    log_create_err('BEFORE Database::getInstance()');
    $db = Database::getInstance()->getConnection();
    log_create_err('AFTER Database::getInstance() | DB connected ok');
    
    // Log database info for correlation
    try {
        $dbInfo = $db->query("SELECT DATABASE() as db_name, @@hostname as hostname, @@port as port")->fetch(PDO::FETCH_ASSOC);
        log_create_err('DB_INFO: database=' . ($dbInfo['db_name'] ?? 'null') . ' | hostname=' . ($dbInfo['hostname'] ?? 'null') . ' | port=' . ($dbInfo['port'] ?? 'null'));
    } catch (Exception $e) {
        log_create_err('DB_INFO_ERROR: ' . $e->getMessage());
    }
    
    // Begin transaction
    log_create_err('BEFORE beginTransaction');
    $db->beginTransaction();
    log_create_err('AFTER beginTransaction');
    
    try {
        // Insert peminjaman
        log_create_err('BEFORE INSERT peminjaman');
        $stmt = $db->prepare("
            INSERT INTO peminjaman (
                jurusan, user_id, jenis, ruangan, penanggung_jawab,
                tingkat, waktu_mulai, waktu_selesai, tujuan,
                tgl_pinjaman, status, created_at
            ) VALUES (
                :jurusan, :user_id, :jenis, :ruangan, :penanggung_jawab,
                :tingkat, :waktu_mulai, :waktu_selesai, :tujuan,
                :tgl_pinjaman, 'Menunggu Konfirmasi', NOW()
            )
        ");
        
        $stmt->execute([
            'jurusan' => $user['jurusan'] ?? '',
            'user_id' => $user['id'],
            'jenis' => $input['jenis'],
            'ruangan' => $input['ruangan'],
            'penanggung_jawab' => $input['penanggung_jawab'] ?? null,
            'tingkat' => $input['tingkat'],
            'waktu_mulai' => $input['waktu_mulai'],
            'waktu_selesai' => $input['waktu_selesai'],
            'tujuan' => $input['tujuan'],
            'tgl_pinjaman' => $input['tgl_pinjaman']
        ]);
        
        $peminjamanId = $db->lastInsertId();
        log_create_err('AFTER INSERT peminjaman | id=' . $peminjamanId . ' | user_id=' . $userId . ' | token_hash=' . $tokenHashShort);
        
        // Sanity check: verify the inserted row exists
        $verifyStmt = $db->prepare("SELECT id, user_id, created_at FROM peminjaman WHERE id = :id");
        $verifyStmt->execute(['id' => $peminjamanId]);
        $verifyRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        log_create_err('VERIFY_INSERT: found=' . ($verifyRow ? 'yes' : 'no') . ' | verify_user_id=' . ($verifyRow['user_id'] ?? 'null') . ' | verify_created_at=' . ($verifyRow['created_at'] ?? 'null'));
        
        // Insert peminjaman_detail for each item
        $itemsCount = 0;
        foreach ($input['items'] as $item) {
            if (empty($item['barang_id']) || empty($item['stok_pinjam'])) {
                continue;
            }
            
            log_create_err('BEFORE INSERT peminjaman_detail | barang_id=' . ($item['barang_id'] ?? 'null') . ' | stok_pinjam=' . ($item['stok_pinjam'] ?? 'null'));
            
            $stmt = $db->prepare("
                INSERT INTO peminjaman_detail (
                    peminjaman_id, barang_id, stok_pinjam, status
                ) VALUES (
                    :peminjaman_id, :barang_id, :stok_pinjam, 'Menunggu Konfirmasi'
                )
            ");
            
            $stmt->execute([
                'peminjaman_id' => $peminjamanId,
                'barang_id' => $item['barang_id'],
                'stok_pinjam' => $item['stok_pinjam']
            ]);
            
            $itemsCount++;
            log_create_err('AFTER INSERT peminjaman_detail | items_count=' . $itemsCount);
        }
        
        log_create_err('AFTER INSERT all peminjaman_detail | total_items=' . $itemsCount);
        
        // Commit transaction
        log_create_err('BEFORE commit transaction');
        $db->commit();
        log_create_err('AFTER commit transaction | OK');
        
        // Get created request
        log_create_err('BEFORE SELECT created request | id=' . $peminjamanId);
        $stmt = $db->prepare("
            SELECT id, jurusan, user_id, jenis, ruangan,
                   penanggung_jawab, tingkat, waktu_mulai,
                   waktu_selesai, tujuan, tgl_pinjaman,
                   tgl_pengembalian, status, created_at
            FROM peminjaman
            WHERE id = :id
        ");
        $stmt->execute(['id' => $peminjamanId]);
        $request = $stmt->fetch();
        log_create_err('AFTER SELECT created request | found=' . ($request ? 'yes' : 'no'));
        
        if (!$request) {
            throw new RuntimeException("Created request not found: id=$peminjamanId");
        }
        
        // Return success response with request_id
        log_create_err('BEFORE ResponseHelper::success');
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'message' => 'Request berhasil dibuat',
            'request_id' => (int)$peminjamanId,
            'data' => [
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
            ]
        ], JSON_UNESCAPED_UNICODE);
        log_create_err('AFTER ResponseHelper::success | DONE | request_id=' . $peminjamanId);
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        log_create_err('EXCEPTION in transaction: ' . $e->getMessage());
        log_create_err('EXCEPTION trace: ' . $e->getTraceAsString());
        $db->rollBack();
        log_create_err('AFTER rollback transaction');
        throw $e;
    }
    
} catch (PDOException $e) {
    // Log detailed PDO error
    $errorInfo = $e->errorInfo ?? [];
    $errorContext = [
        'type' => 'PDOException',
        'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'driver_code' => $errorInfo[1] ?? null,
        'driver_message' => $errorInfo[2] ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
    log_create_err('PDOException: ' . json_encode($errorContext, JSON_UNESCAPED_UNICODE));
    log_create_err('PDOException trace: ' . $e->getTraceAsString());
    
    // Return JSON error with debug_id
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan saat membuat request peralatan',
        'debug_id' => $debugId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Throwable $e) {
    // Log all exceptions (including ErrorException from error_handler)
    $errorContext = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
    log_create_err('EXCEPTION: ' . json_encode($errorContext, JSON_UNESCAPED_UNICODE));
    log_create_err('EXCEPTION trace: ' . $e->getTraceAsString());
    
    // Return JSON error with debug_id
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan saat membuat request peralatan',
        'debug_id' => $debugId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
