<?php
/**
 * Get Combined Barang List for Request (User)
 * 
 * Purpose: Get unified list of items from 3 tables (barangbhp_alat, barangbhp_bahan, barang_inventaris)
 * for equipment request Step 2
 * 
 * Location: api/user/list_barang_request.php
 * 
 * Endpoint: GET /api/user/list_barang_request.php?jurusan=tlm
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Query Parameters:
 * - jurusan (required): Filter by jurusan (case-insensitive)
 * 
 * Response:
 * {
 *   "status": "ok",
 *   "data": [
 *     {
 *       "source": "alat",
 *       "id": 1,
 *       "jurusan": "TLM",
 *       "nama": "Mikroskop",
 *       "stok": 10,
 *       "satuan": "pcs",
 *       "extra": "Baik",
 *       "selectable": true
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
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../middleware/auth.php';

try {
    // Validate token and get user
    $user = AuthMiddleware::validateToken();
    
    // Get jurusan parameter (required)
    $jurusan = $_GET['jurusan'] ?? null;
    if (empty($jurusan)) {
        ResponseHelper::error('Parameter jurusan wajib diisi', 400);
        exit;
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Build UNION ALL query for 3 tables
    // Filter: delete IS NULL OR delete = '-'
    // Sort: stok > 0 first, then nama ASC
    $sql = "
        SELECT 
            'alat' as source,
            id,
            jurusan,
            nama,
            stok,
            satuan,
            COALESCE(kondisi, '') as extra,
            1 as selectable
        FROM barangbhp_alat
        WHERE (delete IS NULL OR delete = '' OR delete = '-')
        AND LOWER(jurusan) = LOWER(:jurusan)
        
        UNION ALL
        
        SELECT 
            'bahan' as source,
            id,
            jurusan,
            nama,
            stok,
            satuan,
            CONCAT(COALESCE(kimia, ''), IF(tgl_expired IS NOT NULL AND tgl_expired != '', CONCAT(' | Exp: ', tgl_expired), '')) as extra,
            1 as selectable
        FROM barangbhp_bahan
        WHERE (delete IS NULL OR delete = '' OR delete = '-')
        AND LOWER(jurusan) = LOWER(:jurusan)
        
        UNION ALL
        
        SELECT 
            'inventaris' as source,
            id,
            jurusan,
            nama,
            stok,
            satuan,
            CONCAT(COALESCE(kode_barang, ''), IF(ruang IS NOT NULL AND ruang != '', CONCAT(' | Ruang: ', ruang), '')) as extra,
            1 as selectable
        FROM barang_inventaris
        WHERE (delete IS NULL OR delete = '' OR delete = '-')
        AND LOWER(jurusan) = LOWER(:jurusan)
        
        ORDER BY 
            CASE WHEN stok > 0 THEN 0 ELSE 1 END,
            nama ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['jurusan' => $jurusan]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response with selectable flag
    $formattedItems = array_map(function($item) {
        return [
            'source' => $item['source'],
            'id' => (int)$item['id'],
            'jurusan' => $item['jurusan'],
            'nama' => $item['nama'],
            'stok' => (int)$item['stok'],
            'satuan' => $item['satuan'] ?? null,
            'extra' => !empty($item['extra']) ? $item['extra'] : null,
            'selectable' => (int)$item['stok'] > 0
        ];
    }, $items);
    
    // Return response with status: ok (as per requirement)
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'data' => $formattedItems
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    error_log("Get barang request error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data barang', 500);
} catch (Exception $e) {
    error_log("Get barang request error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data barang', 500);
}
