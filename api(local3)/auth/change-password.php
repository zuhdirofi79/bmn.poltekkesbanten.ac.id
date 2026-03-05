<?php
/**
 * Change Password Endpoint
 * 
 * Purpose: Change user password
 * 
 * Location: api/auth/change-password.php
 * 
 * Endpoint: POST /api/auth/change-password.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "old_password": "oldpass123",
 *   "new_password": "newpass123"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Password berhasil diubah"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/request_validator.php';
require_once __DIR__ . '/../config/audit_logger.php';

try {
    $user = AuthMiddleware::validateToken();
    
    $input = RequestValidator::validateJsonInput();
    RequestValidator::validateRequired($input, ['old_password', 'new_password']);
    
    $oldPassword = $input['old_password'];
    $newPassword = $input['new_password'];
    
    // Validate new password length (minimum 6 characters)
    if (strlen($newPassword) < 6) {
        ResponseHelper::error('New password must be at least 6 characters', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get current user password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $user['id']]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        ResponseHelper::error('User not found', 404);
    }
    
    // Verify old password
    if (!password_verify($oldPassword, $userData['password'])) {
        ResponseHelper::error('Password lama salah', 400);
    }
    
    // Hash new password (bcrypt)
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :user_id");
    $stmt->execute([
        'password' => $newPasswordHash,
        'user_id' => $user['id']
    ]);
    
    AuditLogger::passwordChange($user['id']);
    
    ResponseHelper::success(null, 'Password berhasil diubah');
    
} catch (Exception $e) {
    AuditLogger::exception($e, ['action' => 'change_password']);
    ResponseHelper::error('Terjadi kesalahan saat mengubah password', 500);
}
