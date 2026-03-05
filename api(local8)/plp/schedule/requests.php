<?php
/**
 * Get Schedule Requests Endpoint (PLP)
 * 
 * Purpose: Get list of schedule requests (req_jadwal_praktikum)
 * 
 * Location: api/plp/schedule/requests.php
 * 
 * Endpoint: GET /api/plp/schedule/requests.php?status=Menunggu
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Query Parameters:
 * - status (optional): Filter by status (Menunggu, Diterima, Ditolak)
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 6,
 *       "user_id": 1497,
 *       "user_name": "User Name",
 *       "tgl": "2024-02-28",
 *       "status": "Diterima",
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
                r.id, r.user_id, r.semester_id, r.tgl, r.jurusan,
                r.penanggung_jawab, r.kelas, r.tingkat, r.ruang,
                r.hari, r.waktu_mulai, r.waktu_selesai, r.tujuan,
                r.status, r.keterangan, r.created_at, r.updated_at,
                r.petugas,
                u.name as user_name, u.username as user_username
            FROM req_jadwal_praktikum r
            INNER JOIN users u ON r.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Filter by jurusan if user has jurusan
    if (!empty($user['jurusan'])) {
        $sql .= " AND LOWER(r.jurusan) = :jurusan";
        $params['jurusan'] = strtolower($user['jurusan']);
    }
    
    // Filter by status if provided
    if ($status) {
        $sql .= " AND r.status = :status";
        $params['status'] = $status;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Format response
    $formattedRequests = array_map(function($request) {
        return [
            'id' => (int)$request['id'],
            'user_id' => (int)$request['user_id'],
            'user_name' => $request['user_name'],
            'user_username' => $request['user_username'],
            'semester_id' => (int)$request['semester_id'],
            'tgl' => $request['tgl'],
            'jurusan' => $request['jurusan'],
            'penanggung_jawab' => $request['penanggung_jawab'],
            'kelas' => $request['kelas'],
            'tingkat' => $request['tingkat'],
            'ruang' => $request['ruang'],
            'hari' => $request['hari'],
            'waktu_mulai' => $request['waktu_mulai'],
            'waktu_selesai' => $request['waktu_selesai'],
            'tujuan' => $request['tujuan'],
            'status' => $request['status'],
            'keterangan' => $request['keterangan'],
            'petugas' => $request['petugas'],
            'created_at' => $request['created_at'],
            'updated_at' => $request['updated_at']
        ];
    }, $requests);
    
    ResponseHelper::success($formattedRequests, 'Schedule requests retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get schedule requests error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data request jadwal', 500);
}
