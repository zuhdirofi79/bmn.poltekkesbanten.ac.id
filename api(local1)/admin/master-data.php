<?php
/**
 * Get Master Data (Rooms) Endpoint (Admin)
 * 
 * Purpose: Get list of rooms (ruangan)
 * 
 * Location: api/admin/master-data.php
 * 
 * Endpoint: GET /api/admin/master-data.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "jurusan": "TLM",
 *       "kampus": "Tangerang",
 *       "nama_ruang": "Lab Parasitologi dan Mikologi"
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
    // Require admin role
    $user = AuthMiddleware::requireRole(['admin']);
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get all rooms
    $stmt = $db->prepare("SELECT id, jurusan, kampus, nama_ruang FROM ruangan ORDER BY jurusan, nama_ruang");
    $stmt->execute();
    $rooms = $stmt->fetchAll();
    
    // Format response
    $formattedRooms = array_map(function($room) {
        return [
            'id' => (int)$room['id'],
            'jurusan' => $room['jurusan'],
            'kampus' => $room['kampus'],
            'nama_ruang' => $room['nama_ruang']
        ];
    }, $rooms);
    
    ResponseHelper::success($formattedRooms, 'Master data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get master data error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil master data', 500);
}
