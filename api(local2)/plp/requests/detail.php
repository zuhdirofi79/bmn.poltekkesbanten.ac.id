<?php
/**
 * Get Request Detail Endpoint (PLP)
 * 
 * Purpose: Get detail of equipment request with items (peminjaman + peminjaman_detail)
 * 
 * Location: api/plp/requests/detail.php
 * 
 * Endpoint: GET /api/plp/requests/detail.php?id=20
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "id": 20,
 *     "user_id": 4,
 *     "user_name": "Admin Kampus",
 *     "jenis": "Peralatan Laboratorium",
 *     "status": "Selesai",
 *     "items": [
 *       {
 *         "id": 26,
 *         "barang_id": 23,
 *         "barang_nama": "Pipet Vol 1 ml",
 *         "stok_pinjam": 1,
 *         "status": "Habis Pakai"
 *       }
 *     ]
 *   }
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

try {
    // Require PLP role
    $user = AuthMiddleware::requireRole(['plp']);
    
    // Get request ID
    $requestId = $_GET['id'] ?? null;
    
    if (!$requestId) {
        ResponseHelper::error('Request ID is required', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get request detail
    $stmt = $db->prepare("
        SELECT 
            p.id, p.jurusan, p.user_id, p.jenis, p.ruangan,
            p.penanggung_jawab, p.tingkat, p.waktu_mulai,
            p.waktu_selesai, p.tujuan, p.tgl_pinjaman,
            p.tgl_pengembalian, p.status, p.created_at,
            u.name as user_name, u.username as user_username
        FROM peminjaman p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.id = :id
    ");
    
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        ResponseHelper::error('Request tidak ditemukan', 404);
    }
    
    // Get request items (peminjaman_detail)
    // Note: barang_id can reference different tables, so we need to check all
    $stmt = $db->prepare("
        SELECT 
            pd.id, pd.peminjaman_id, pd.barang_id, pd.stok_pinjam,
            pd.status, pd.kondisi, pd.petugas_peminjaman,
            pd.petugas_pengembalian
        FROM peminjaman_detail pd
        WHERE pd.peminjaman_id = :id
    ");
    
    $stmt->execute(['id' => $requestId]);
    $details = $stmt->fetchAll();
    
    // Get barang names (try to find in all tables)
    $items = [];
    foreach ($details as $detail) {
        $barangId = $detail['barang_id'];
        $barangNama = 'Unknown';
        $barangType = null;
        
        // Try barang_inventaris
        $stmt = $db->prepare("SELECT nama FROM barang_inventaris WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $barangId]);
        $barang = $stmt->fetch();
        if ($barang) {
            $barangNama = $barang['nama'];
            $barangType = 'inventaris';
        } else {
            // Try barangbhp_alat
            $stmt = $db->prepare("SELECT nama FROM barangbhp_alat WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $barangId]);
            $barang = $stmt->fetch();
            if ($barang) {
                $barangNama = $barang['nama'];
                $barangType = 'alat';
            } else {
                // Try barangbhp_bahan
                $stmt = $db->prepare("SELECT nama FROM barangbhp_bahan WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $barangId]);
                $barang = $stmt->fetch();
                if ($barang) {
                    $barangNama = $barang['nama'];
                    $barangType = 'bahan';
                }
            }
        }
        
        $items[] = [
            'id' => (int)$detail['id'],
            'barang_id' => (int)$detail['barang_id'],
            'barang_nama' => $barangNama,
            'barang_type' => $barangType,
            'stok_pinjam' => (int)$detail['stok_pinjam'],
            'status' => $detail['status'],
            'kondisi' => $detail['kondisi'],
            'petugas_peminjaman' => $detail['petugas_peminjaman'],
            'petugas_pengembalian' => $detail['petugas_pengembalian']
        ];
    }
    
    $response = [
        'id' => (int)$request['id'],
        'jurusan' => $request['jurusan'],
        'user_id' => (int)$request['user_id'],
        'user_name' => $request['user_name'],
        'user_username' => $request['user_username'],
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
        'created_at' => $request['created_at'],
        'items' => $items
    ];
    
    ResponseHelper::success($response, 'Request detail retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get request detail error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil detail request', 500);
}
