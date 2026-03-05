<?php
/**
 * Delete Room Endpoint (Admin)
 * 
 * Purpose: Delete room
 * 
 * Location: api/admin/rooms/delete.php
 * 
 * Endpoint: DELETE /api/admin/rooms/delete.php?id=123
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if room exists
    $stmt = $db->prepare("SELECT id FROM ruangan WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $roomId]);
    if (!$stmt->fetch()) {
        ResponseHelper::error('Ruangan tidak ditemukan', 404);
    }
    
    // Delete room
    $stmt = $db->prepare("DELETE FROM ruangan WHERE id = :id");
    $stmt->execute(['id' => $roomId]);
    
    ResponseHelper::success(null, 'Ruangan berhasil dihapus');
    
} catch (Exception $e) {
    error_log("Delete room error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menghapus ruangan', 500);
}
