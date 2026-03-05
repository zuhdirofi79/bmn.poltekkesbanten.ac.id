<?php
/**
 * Add Room Endpoint (Admin)
 * 
 * Purpose: Add new room
 * 
 * Location: api/admin/rooms/add.php
 * 
 * Endpoint: POST /api/admin/rooms/add.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "jurusan": "TLM",
 *   "kampus": "Tangerang",
 *   "nama_ruang": "Lab Baru"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {...},
 *   "message": "Ruangan berhasil ditambahkan"
 * }
 */

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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../middleware/auth.php';

try {
    // Require admin role
    $user = AuthMiddleware::requireRole(['admin']);
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ResponseHelper::error('Invalid JSON input', 400);
    }
    
    // Validate required fields
    if (empty($input['jurusan']) || empty($input['kampus']) || empty($input['nama_ruang'])) {
        ResponseHelper::error('Jurusan, kampus, dan nama_ruang harus diisi', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Insert room
    $stmt = $db->prepare("INSERT INTO ruangan (jurusan, kampus, nama_ruang) VALUES (:jurusan, :kampus, :nama_ruang)");
    $stmt->execute([
        'jurusan' => $input['jurusan'],
        'kampus' => $input['kampus'],
        'nama_ruang' => $input['nama_ruang']
    ]);
    
    $roomId = $db->lastInsertId();
    
    // Get created room
    $stmt = $db->prepare("SELECT id, jurusan, kampus, nama_ruang FROM ruangan WHERE id = :id");
    $stmt->execute(['id' => $roomId]);
    $room = $stmt->fetch();
    
    ResponseHelper::success([
        'id' => (int)$room['id'],
        'jurusan' => $room['jurusan'],
        'kampus' => $room['kampus'],
        'nama_ruang' => $room['nama_ruang']
    ], 'Ruangan berhasil ditambahkan');
    
} catch (Exception $e) {
    error_log("Add room error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menambahkan ruangan', 500);
}
