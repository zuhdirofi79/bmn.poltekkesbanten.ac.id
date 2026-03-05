<?php
/**
 * Logout Endpoint
 * 
 * Purpose: Invalidate API token
 * 
 * Location: api/auth/logout.php
 * 
 * Endpoint: POST /api/auth/logout.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Logout berhasil"
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
require_once __DIR__ . '/../config/audit_logger.php';

try {
    $user = AuthMiddleware::validateToken();
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches);
    $token = $matches[1] ?? '';
    $tokenHash = hash('sha256', $token);
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM api_tokens WHERE token_hash = :token_hash");
    $stmt->execute(['token_hash' => $tokenHash]);
    
    AuditLogger::logout($user['id'], $tokenHash);
    
    ResponseHelper::success(null, 'Logout berhasil');
    
} catch (Exception $e) {
    AuditLogger::exception($e, ['action' => 'logout']);
    ResponseHelper::error('Terjadi kesalahan saat logout', 500);
}
