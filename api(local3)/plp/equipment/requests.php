<?php
/**
 * Get Equipment Requests Endpoint (PLP)
 * 
 * Purpose: Get list of equipment requests (peminjaman)
 * 
 * Location: api/plp/equipment/requests.php
 * 
 * Endpoint: GET /api/plp/equipment/requests.php?status=Menunggu
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
 *       "user_id": 4,
 *       "user_name": "Admin Kampus",
 *       "jenis": "Peralatan Laboratorium",
 *       "ruangan": "Lab Kimia Dasar dan Terapan",
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
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Format response
    $formattedRequests = array_map(function($request) {
        return [
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
            'created_at' => $request['created_at']
        ];
    }, $requests);
    
    ResponseHelper::success($formattedRequests, 'Equipment requests retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get equipment requests error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data request peralatan', 500);
}
