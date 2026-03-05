<?php
/**
 * Get Items List Endpoint (PLP)
 * 
 * Purpose: Get list of items (barang_inventaris, barangbhp_alat, barangbhp_bahan)
 * Filtered by user's jurusan
 * 
 * Location: api/plp/items.php
 * 
 * Endpoint: GET /api/plp/items.php?type=inventaris|alat|bahan
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Query Parameters:
 * - type (optional): Filter by type (inventaris, alat, bahan). If not provided, returns all types
 * - jurusan (optional): Filter by jurusan/department. If not provided, uses user's jurusan if available
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "type": "inventaris",
 *       "nama": "Mikroskop",
 *       "stok": 10,
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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../middleware/auth.php';

try {
    // Require PLP role
    $user = AuthMiddleware::requireRole(['plp']);

    // Get filters
    $type = $_GET['type'] ?? null;
    $requestedJurusan = $_GET['jurusan'] ?? null;

    // Determine which jurusan to filter by
    // Priority: requested jurusan > user's jurusan (if user has one)
    $filterJurusan = null;
    if (!empty($requestedJurusan)) {
        $filterJurusan = strtolower($requestedJurusan);
    } elseif (!empty($user['jurusan'])) {
        $filterJurusan = strtolower($user['jurusan']);
    }

    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    $items = [];
    
    // Get barang_inventaris
    if (!$type || $type === 'inventaris') {
        $sql = "SELECT
                    id, jurusan, kode_barang, nama, tgl_masuk,
                    merk, type, no_seri, ruang, stok, satuan,
                    kondisi, updated_at
                FROM barang_inventaris
                WHERE (delete IS NULL OR delete = '')";

        $params = [];
        if ($filterJurusan !== null) {
            $sql .= " AND LOWER(jurusan) = :jurusan";
            $params['jurusan'] = $filterJurusan;
        }

        $sql .= " ORDER BY nama";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $inventaris = $stmt->fetchAll();
        
        foreach ($inventaris as $item) {
            $items[] = [
                'id' => (int)$item['id'],
                'type' => 'inventaris',
                'jurusan' => $item['jurusan'],
                'kode_barang' => $item['kode_barang'],
                'nama' => $item['nama'],
                'tgl_masuk' => $item['tgl_masuk'],
                'merk' => $item['merk'],
                'type_item' => $item['type'],
                'no_seri' => $item['no_seri'],
                'ruang' => $item['ruang'],
                'stok' => (int)$item['stok'],
                'satuan' => $item['satuan'],
                'kondisi' => $item['kondisi'],
                'updated_at' => $item['updated_at']
            ];
        }
    }
    
    // Get barangbhp_alat
    if (!$type || $type === 'alat') {
        $sql = "SELECT
                    id, jurusan, nama, stok, satuan, kondisi, updated_at
                FROM barangbhp_alat
                WHERE (delete IS NULL OR delete = '')";

        $params = [];
        if ($filterJurusan !== null) {
            $sql .= " AND LOWER(jurusan) = :jurusan";
            $params['jurusan'] = $filterJurusan;
        }

        $sql .= " ORDER BY nama";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $alat = $stmt->fetchAll();
        
        foreach ($alat as $item) {
            $items[] = [
                'id' => (int)$item['id'],
                'type' => 'alat',
                'jurusan' => $item['jurusan'],
                'nama' => $item['nama'],
                'stok' => (int)$item['stok'],
                'satuan' => $item['satuan'],
                'kondisi' => $item['kondisi'],
                'updated_at' => $item['updated_at']
            ];
        }
    }
    
    // Get barangbhp_bahan
    if (!$type || $type === 'bahan') {
        $sql = "SELECT
                    id, jurusan, nama, kimia, stok, satuan,
                    tgl_expired, updated_at
                FROM barangbhp_bahan
                WHERE (delete IS NULL OR delete = '')";

        $params = [];
        if ($filterJurusan !== null) {
            $sql .= " AND LOWER(jurusan) = :jurusan";
            $params['jurusan'] = $filterJurusan;
        }

        $sql .= " ORDER BY nama";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $bahan = $stmt->fetchAll();
        
        foreach ($bahan as $item) {
            $items[] = [
                'id' => (int)$item['id'],
                'type' => 'bahan',
                'jurusan' => $item['jurusan'],
                'nama' => $item['nama'],
                'kimia' => $item['kimia'],
                'stok' => (int)$item['stok'],
                'satuan' => $item['satuan'],
                'tgl_expired' => $item['tgl_expired'],
                'updated_at' => $item['updated_at']
            ];
        }
    }
    
    ResponseHelper::success($items, 'Items retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get items error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data items', 500);
}
