<?php
/**
 * Edit Room Endpoint (Admin)
 * 
 * Purpose: Edit existing room
 * 
 * Location: api/admin/rooms/edit.php
 * 
 * Endpoint: PUT /api/admin/rooms/edit.php?id=123
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "jurusan": "TLM",
 *   "kampus": "Tangerang",
 *   "nama_ruang": "Lab Updated"
 * }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    
    // Get room ID
    $roomId = $_GET['id'] ?? null;
    
    if (!$roomId) {
        ResponseHelper::error('Room ID is required', 400);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ResponseHelper::error('Invalid JSON input', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if room exists
    $stmt = $db->prepare("SELECT id FROM ruangan WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $roomId]);
    if (!$stmt->fetch()) {
        ResponseHelper::error('Ruangan tidak ditemukan', 404);
    }
    
    // Update room
    $updateFields = [];
    $params = ['id' => $roomId];
    
    if (isset($input['jurusan'])) {
        $updateFields[] = "jurusan = :jurusan";
        $params['jurusan'] = $input['jurusan'];
    }
    if (isset($input['kampus'])) {
        $updateFields[] = "kampus = :kampus";
        $params['kampus'] = $input['kampus'];
    }
    if (isset($input['nama_ruang'])) {
        $updateFields[] = "nama_ruang = :nama_ruang";
        $params['nama_ruang'] = $input['nama_ruang'];
    }
    
    if (empty($updateFields)) {
        ResponseHelper::error('No fields to update', 400);
    }
    
    $sql = "UPDATE ruangan SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Get updated room
    $stmt = $db->prepare("SELECT id, jurusan, kampus, nama_ruang FROM ruangan WHERE id = :id");
    $stmt->execute(['id' => $roomId]);
    $room = $stmt->fetch();
    
    ResponseHelper::success([
        'id' => (int)$room['id'],
        'jurusan' => $room['jurusan'],
        'kampus' => $room['kampus'],
        'nama_ruang' => $room['nama_ruang']
    ], 'Ruangan berhasil diupdate');
    
} catch (Exception $e) {
    error_log("Edit room error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengupdate ruangan', 500);
}
