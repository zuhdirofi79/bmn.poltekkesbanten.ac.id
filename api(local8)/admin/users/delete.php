<?php
/**
 * Delete User Endpoint (Admin)
 * 
 * Purpose: Delete user
 * 
 * Location: api/admin/users/delete.php
 * 
 * Endpoint: DELETE /api/admin/users/delete.php?id=123
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "User berhasil dihapus"
 * }
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
    
    // Get user ID from query parameter
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        ResponseHelper::error('User ID is required', 400);
    }
    
    // Prevent self-deletion
    if ($userId == $user['id']) {
        ResponseHelper::error('Tidak dapat menghapus akun sendiri', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch()) {
        ResponseHelper::error('User tidak ditemukan', 404);
    }
    
    // Delete user (CASCADE will handle related records in api_tokens)
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    
    ResponseHelper::success(null, 'User berhasil dihapus');
    
} catch (Exception $e) {
    error_log("Delete user error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menghapus user', 500);
}
