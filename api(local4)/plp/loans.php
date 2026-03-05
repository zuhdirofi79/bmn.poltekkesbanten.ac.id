<?php
/**
 * Get Loans & Returns Endpoint (PLP)
 * 
 * Purpose: Get list of loans and returns for tracking
 * 
 * Location: api/plp/loans.php
 * 
 * Endpoint: GET /api/plp/loans.php?status=Selesai
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Query Parameters:
 * - status (optional): Filter by status
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 20,
 *       "user_name": "Admin Kampus",
 *       "jenis": "Peralatan Laboratorium",
 *       "status": "Selesai",
 *       "tgl_pinjaman": "2024-02-11",
 *       "tgl_pengembalian": "2024-02-11",
 *       "items": [...]
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
    
    // Get status filter
    $status = $_GET['status'] ?? null;
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Build query
    $sql = "SELECT 
                p.id, p.jurusan, p.user_id, p.jenis, p.ruangan,
                p.penanggung_jawab, p.tingkat, p.waktu_mulai,
                p.waktu_selesai, p.tujuan, p.tgl_pinjaman,
                p.tgl_pengembalian, p.status, p.created_at,
                u.name as user_name, u.username as user_username
            FROM peminjaman p
            INNER JOIN users u ON p.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Filter by jurusan if user has jurusan
    if (!empty($user['jurusan'])) {
        $sql .= " AND LOWER(p.jurusan) = :jurusan";
        $params['jurusan'] = strtolower($user['jurusan']);
    }
    
    // Filter by status if provided
    if ($status) {
        $sql .= " AND p.status = :status";
        $params['status'] = $status;
    }
    
    $sql .= " ORDER BY p.tgl_pinjaman DESC, p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
    
    // Get items for each loan
    $formattedLoans = [];
    foreach ($loans as $loan) {
        // Get loan items
        $stmt = $db->prepare("
            SELECT 
                pd.id, pd.barang_id, pd.stok_pinjam, pd.status,
                pd.kondisi, pd.petugas_peminjaman, pd.petugas_pengembalian
            FROM peminjaman_detail pd
            WHERE pd.peminjaman_id = :id
        ");
        $stmt->execute(['id' => $loan['id']]);
        $items = $stmt->fetchAll();
        
        // Get barang names
        $formattedItems = [];
        foreach ($items as $item) {
            $barangId = $item['barang_id'];
            $barangNama = 'Unknown';
            
            // Try to find barang name
            $stmt = $db->prepare("SELECT nama FROM barang_inventaris WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $barangId]);
            $barang = $stmt->fetch();
            if ($barang) {
                $barangNama = $barang['nama'];
            } else {
                $stmt = $db->prepare("SELECT nama FROM barangbhp_alat WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $barangId]);
                $barang = $stmt->fetch();
                if ($barang) {
                    $barangNama = $barang['nama'];
                } else {
                    $stmt = $db->prepare("SELECT nama FROM barangbhp_bahan WHERE id = :id LIMIT 1");
                    $stmt->execute(['id' => $barangId]);
                    $barang = $stmt->fetch();
                    if ($barang) {
                        $barangNama = $barang['nama'];
                    }
                }
            }
            
            $formattedItems[] = [
                'id' => (int)$item['id'],
                'barang_id' => (int)$item['barang_id'],
                'barang_nama' => $barangNama,
                'stok_pinjam' => (int)$item['stok_pinjam'],
                'status' => $item['status'],
                'kondisi' => $item['kondisi'],
                'petugas_peminjaman' => $item['petugas_peminjaman'],
                'petugas_pengembalian' => $item['petugas_pengembalian']
            ];
        }
        
        $formattedLoans[] = [
            'id' => (int)$loan['id'],
            'jurusan' => $loan['jurusan'],
            'user_id' => (int)$loan['user_id'],
            'user_name' => $loan['user_name'],
            'user_username' => $loan['user_username'],
            'jenis' => $loan['jenis'],
            'ruangan' => $loan['ruangan'],
            'penanggung_jawab' => $loan['penanggung_jawab'],
            'tingkat' => $loan['tingkat'],
            'waktu_mulai' => $loan['waktu_mulai'],
            'waktu_selesai' => $loan['waktu_selesai'],
            'tujuan' => $loan['tujuan'],
            'tgl_pinjaman' => $loan['tgl_pinjaman'],
            'tgl_pengembalian' => $loan['tgl_pengembalian'],
            'status' => $loan['status'],
            'created_at' => $loan['created_at'],
            'items' => $formattedItems
        ];
    }
    
    ResponseHelper::success($formattedLoans, 'Loans retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get loans error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data pinjaman', 500);
}
