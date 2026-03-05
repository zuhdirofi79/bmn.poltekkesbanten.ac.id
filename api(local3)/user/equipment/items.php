<?php
declare(strict_types=1);

// === HARD DEBUG TRAP (TEMP) ===
$debugId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$logDir  = __DIR__ . '/../../_logs'; // /api/user/equipment -> /api/_logs
$logFile = $logDir . '/items_error.log';

if (!is_dir($logDir)) { 
    @mkdir($logDir, 0755, true); 
}

function log_items_err(string $msg): void {
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
        log_items_err('FATAL: ' . json_encode($err));
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success'  => false,
            'message'  => 'Terjadi kesalahan saat mengambil data items',
            'debug_id' => $GLOBALS['debugId'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

header('Content-Type: application/json; charset=UTF-8');
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

try {
    log_items_err('START items.php');
    
    // Sanity check log directory
    log_items_err('LOGDIR=' . $logDir . ' | writable=' . (is_writable($logDir) ? 'yes' : 'no'));
    log_items_err('LOGFILE=' . $logFile);
    
    // Require bootstrap/config files
    log_items_err('BEFORE require config/database.php');
    require_once __DIR__ . '/../../config/database.php';
    log_items_err('AFTER require config/database.php');
    
    log_items_err('BEFORE require config/response.php');
    require_once __DIR__ . '/../../config/response.php';
    log_items_err('AFTER require config/response.php');
    
    log_items_err('BEFORE require middleware/auth.php');
    require_once __DIR__ . '/../../middleware/auth.php';
    log_items_err('AFTER require middleware/auth.php');
    
    // Validate token and get user
    log_items_err('BEFORE AuthMiddleware::validateToken()');
    $user = AuthMiddleware::validateToken();
    log_items_err('AFTER AuthMiddleware::validateToken() | user_id=' . ($user['id'] ?? 'null'));
    
    // Konsolidasi variabel jurusan (hilangkan "not_provided" ganda)
    $jurusanParam = isset($_GET['jurusan']) ? trim((string)$_GET['jurusan']) : '';
    $resolvedJurusanFromAuth = $user['jurusan'] ?? '';
    $jurusanFinal = $jurusanParam !== '' ? $jurusanParam : $resolvedJurusanFromAuth;
    
    log_items_err('JURUSAN param=' . ($jurusanParam !== '' ? $jurusanParam : 'not_provided') . ' | final=' . $jurusanFinal);
    
    // Validate jurusan is not empty
    if ($jurusanFinal === '') {
        log_items_err('ERROR: Jurusan tidak tersedia');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Jurusan tidak tersedia',
            'debug_id' => $debugId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $jurusan = $jurusanFinal; // Variabel final untuk binding SQL
    
    // Get database connection
    log_items_err('BEFORE Database::getInstance()');
    $db = Database::getInstance()->getConnection();
    log_items_err('AFTER Database::getInstance() | DB connected ok');
    
    // Build UNION ALL query for 3 tables
    // CRITICAL: Gunakan NAMED placeholder tunggal :jurusan (WAJIB, tidak ada ? atau campuran)
    // Filter: (`delete` IS NULL OR `delete` = '-')
    // Sort: stok > 0 first, then nama ASC
    // NEW: Calculate stok_dipinjam from peminjaman_detail (status: Menunggu Konfirmasi, Dipinjamkan)
    $sql = "
        SELECT
            'alat' AS source,
            b.id,
            b.jurusan,
            b.nama,
            b.stok AS stok_total,
            b.satuan,
            b.kondisi AS extra,
            COALESCE(SUM(pd.stok_pinjam), 0) AS stok_dipinjam
        FROM barangbhp_alat b
        LEFT JOIN peminjaman_detail pd ON pd.barang_id = b.id
            AND pd.status IN ('Menunggu Konfirmasi', 'Dipinjamkan')
        WHERE b.jurusan = :jurusan
            AND (b.`delete` IS NULL OR b.`delete` = '-')
        GROUP BY b.id, b.jurusan, b.nama, b.stok, b.satuan, b.kondisi
        
        UNION ALL
        
        SELECT
            'bahan' AS source,
            b.id,
            b.jurusan,
            b.nama,
            b.stok AS stok_total,
            b.satuan,
            CONCAT(
                IFNULL(b.kimia, '-'),
                ' | exp:',
                IFNULL(DATE_FORMAT(b.tgl_expired, '%Y-%m-%d'), '-')
            ) AS extra,
            COALESCE(SUM(pd.stok_pinjam), 0) AS stok_dipinjam
        FROM barangbhp_bahan b
        LEFT JOIN peminjaman_detail pd ON pd.barang_id = b.id
            AND pd.status IN ('Menunggu Konfirmasi', 'Dipinjamkan')
        WHERE b.jurusan = :jurusan
            AND (b.`delete` IS NULL OR b.`delete` = '-')
        GROUP BY b.id, b.jurusan, b.nama, b.stok, b.satuan, b.kimia, b.tgl_expired
        
        UNION ALL
        
        SELECT
            'inventaris' AS source,
            b.id,
            b.jurusan,
            b.nama,
            b.stok AS stok_total,
            b.satuan,
            CONCAT(
                IFNULL(b.kode_barang, '-'),
                ' | ',
                IFNULL(b.ruang, '-')
            ) AS extra,
            COALESCE(SUM(pd.stok_pinjam), 0) AS stok_dipinjam
        FROM barang_inventaris b
        LEFT JOIN peminjaman_detail pd ON pd.barang_id = b.id
            AND pd.status IN ('Menunggu Konfirmasi', 'Dipinjamkan')
        WHERE b.jurusan = :jurusan
            AND (b.`delete` IS NULL OR b.`delete` = '-')
        GROUP BY b.id, b.jurusan, b.nama, b.stok, b.satuan, b.kode_barang, b.ruang
        
        ORDER BY (stok_total > 0) DESC, nama ASC
    ";
    
    log_items_err('BEFORE prepare SQL | sql_length=' . strlen($sql) . ' | jurusan=' . $jurusan);
    
    // === DIAGNOSA: Log SQL raw (sementara untuk debug) ===
    log_items_err("SQL_RAW_BEGIN\n" . $sql . "\nSQL_RAW_END");
    
    // Guard "anti HY093" sebelum execute
    $qCount = substr_count($sql, '?');
    $namedJurusanCount = preg_match_all('/:jurusan\b/', $sql, $m);
    
    log_items_err("PLACEHOLDER_COUNT q={$qCount} namedJurusan={$namedJurusanCount}");
    
    if ($qCount > 0) {
        throw new RuntimeException("Mixed/positional placeholder detected. Use only :jurusan.");
    }
    if ($namedJurusanCount < 1) {
        throw new RuntimeException("Missing :jurusan placeholder.");
    }
    
    // === PROBE: Test SQL tanpa prepare (untuk memastikan SQL valid) ===
    try {
        $probeSql = str_replace(':jurusan', $db->quote($jurusan), $sql);
        $probe = $db->query($probeSql);
        $probeRows = $probe ? $probe->fetchAll(PDO::FETCH_ASSOC) : [];
        log_items_err('PROBE_OK rows=' . count($probeRows));
    } catch (Throwable $e) {
        log_items_err('PROBE_FAIL ' . $e->getMessage());
    }
    
    // === FIX 1: Coba binding tanpa colon dulu ===
    $bind = ['jurusan' => $jurusan]; // Tanpa colon di key
    log_items_err('BIND_KEYS=' . json_encode(array_keys($bind)));
    log_items_err('BIND_COUNT=' . count($bind));
    log_items_err('BIND_VALUE=' . $jurusan);
    
    $stmt = null;
    $executeSuccess = false;
    
    try {
        $stmt = $db->prepare($sql);
        log_items_err('AFTER prepare SQL');
        
        log_items_err('BEFORE execute SQL FIX1 | binding jurusan=' . $jurusan);
        $stmt->execute($bind);
        log_items_err('EXECUTE_OK_FIX1');
        $executeSuccess = true;
    } catch (PDOException $e) {
        // Jika Fix 1 gagal, coba Fix 2: placeholder unik
        log_items_err('EXECUTE_FAIL_FIX1: ' . $e->getMessage());
        log_items_err('TRYING_FIX2: Using unique placeholders :j1 :j2 :j3');
        
        // === FIX 2: Gunakan placeholder unik untuk setiap UNION ===
        $sql2 = "
            SELECT
                'alat' AS source,
                id,
                jurusan,
                nama,
                stok,
                satuan,
                kondisi AS extra
            FROM barangbhp_alat
            WHERE jurusan = :j1
                AND (`delete` IS NULL OR `delete` = '-')
            
            UNION ALL
            
            SELECT
                'bahan' AS source,
                id,
                jurusan,
                nama,
                stok,
                satuan,
                CONCAT(
                    IFNULL(kimia, '-'),
                    ' | exp:',
                    IFNULL(DATE_FORMAT(tgl_expired, '%Y-%m-%d'), '-')
                ) AS extra
            FROM barangbhp_bahan
            WHERE jurusan = :j2
                AND (`delete` IS NULL OR `delete` = '-')
            
            UNION ALL
            
            SELECT
                'inventaris' AS source,
                id,
                jurusan,
                nama,
                stok,
                satuan,
                CONCAT(
                    IFNULL(kode_barang, '-'),
                    ' | ',
                    IFNULL(ruang, '-')
                ) AS extra
            FROM barang_inventaris
            WHERE jurusan = :j3
                AND (`delete` IS NULL OR `delete` = '-')
            
            ORDER BY (stok > 0) DESC, nama ASC
        ";
        
        // Guard: pastikan tidak ada :jurusan di SQL baru
        if (preg_match('/:jurusan\b/', $sql2)) {
            throw new RuntimeException('SQL masih mengandung :jurusan, harus :j1 :j2 :j3');
        }
        
        log_items_err('SQL_FIX2_BEGIN\n' . $sql2 . '\nSQL_FIX2_END');
        
        $bind2 = ['j1' => $jurusan, 'j2' => $jurusan, 'j3' => $jurusan];
        log_items_err('BIND2_KEYS=' . json_encode(array_keys($bind2)));
        log_items_err('BIND2_COUNT=' . count($bind2));
        
        // Coba dengan emulate prepares jika masih gagal
        $originalEmulate = $db->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        log_items_err('ORIGINAL_EMULATE_PREPARES=' . ($originalEmulate ? 'true' : 'false'));
        
        try {
            $stmt = $db->prepare($sql2);
            log_items_err('AFTER prepare SQL FIX2');
            log_items_err('BEFORE execute SQL FIX2');
            $stmt->execute($bind2);
            log_items_err('EXECUTE_OK_FIX2');
            $executeSuccess = true;
        } catch (PDOException $e2) {
            // === FIX 3: Aktifkan emulate prepares ===
            log_items_err('EXECUTE_FAIL_FIX2: ' . $e2->getMessage());
            log_items_err('TRYING_FIX3: Enable emulate prepares');
            
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            log_items_err('EMULATE_PREPARES_NOW=' . ($db->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'true' : 'false'));
            
            $stmt = $db->prepare($sql2);
            log_items_err('AFTER prepare SQL FIX3');
            log_items_err('BEFORE execute SQL FIX3');
            $stmt->execute($bind2);
            log_items_err('EXECUTE_OK_FIX3');
            
            // Restore original emulate prepares setting
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, $originalEmulate);
            log_items_err('RESTORED_EMULATE_PREPARES=' . ($originalEmulate ? 'true' : 'false'));
            
            $executeSuccess = true;
        }
    }
    
    if (!$executeSuccess || $stmt === null) {
        throw new RuntimeException('All fix attempts failed - cannot execute query');
    }
    
    log_items_err('AFTER execute SQL | success=' . ($executeSuccess ? 'true' : 'false'));
    
    log_items_err('BEFORE fetchAll');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_items_err('AFTER fetchAll | rows=' . count($rows));
    
    // Calculate stok_tersedia and is_available for each item
    log_items_err('BEFORE calculate stok_tersedia');
    foreach ($rows as &$r) {
        $stokTotal = isset($r['stok_total']) ? (int)$r['stok_total'] : (isset($r['stok']) ? (int)$r['stok'] : 0);
        $stokDipinjam = isset($r['stok_dipinjam']) ? (int)$r['stok_dipinjam'] : 0;
        $stokTersedia = max(0, $stokTotal - $stokDipinjam); // Ensure non-negative
        $isAvailable = $stokTersedia > 0;
        
        // Standardize field names for Flutter
        $r['id'] = (int)$r['id'];
        $r['stok'] = $stokTotal; // Keep 'stok' for backward compatibility
        $r['stok_total'] = $stokTotal;
        $r['stok_dipinjam'] = $stokDipinjam;
        $r['stok_tersedia'] = $stokTersedia;
        $r['is_available'] = $isAvailable;
        $r['selectable'] = $isAvailable; // For backward compatibility
        
        // Clean extra field (remove null or empty)
        if (empty($r['extra']) || $r['extra'] === '-') {
            $r['extra'] = null;
        }
        
        // Debug log first item
        if (!isset($debugLogged)) {
            log_items_err("STOCK_CALC_EXAMPLE: id={$r['id']}, nama={$r['nama']}, stok_total=$stokTotal, stok_dipinjam=$stokDipinjam, stok_tersedia=$stokTersedia, is_available=" . ($isAvailable ? 'true' : 'false'));
            $debugLogged = true;
        }
    }
    unset($r);
    log_items_err('AFTER calculate stok_tersedia | processed_rows=' . count($rows));
    
    // Return response
    log_items_err('BEFORE json_encode response');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE);
    log_items_err('AFTER json_encode response | SUCCESS');
    
} catch (PDOException $e) {
    // Log detailed PDO error
    $errorInfo = $e->errorInfo ?? [];
    $errorContext = [
        'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'driver_code' => $errorInfo[1] ?? null,
        'driver_message' => $errorInfo[2] ?? null,
        'jurusan' => $jurusan ?? 'not_set',
    ];
    log_items_err('PDOException: ' . json_encode($errorContext, JSON_UNESCAPED_UNICODE));
    log_items_err('PDOException trace: ' . $e->getTraceAsString());
    
    // Return JSON error with debug_id
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan saat mengambil data items',
        'debug_id' => $debugId,
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    // Log all exceptions (including ErrorException from error_handler)
    $errorContext = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'jurusan' => $jurusan ?? 'not_set',
    ];
    log_items_err('EXCEPTION: ' . json_encode($errorContext, JSON_UNESCAPED_UNICODE));
    log_items_err('EXCEPTION trace: ' . $e->getTraceAsString());
    
    // Return JSON error with debug_id
    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'message'  => 'Terjadi kesalahan saat mengambil data items',
        'debug_id' => $debugId,
    ], JSON_UNESCAPED_UNICODE);
}
