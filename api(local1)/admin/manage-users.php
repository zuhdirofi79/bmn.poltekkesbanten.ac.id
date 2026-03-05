<?php
/**
 * Get Manage Users Endpoint (Admin)
 * 
 * Purpose: Get list of users with role information for management
 * Similar to users.php but may include additional role management data
 * 
 * Location: api/admin/manage-users.php
 * 
 * Endpoint: GET /api/admin/manage-users.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 4,
 *       "name": "Admin Kampus",
 *       "username": "99999",
 *       "role": "admin",
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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../middleware/auth.php';

try {
    // Require admin role
    $user = AuthMiddleware::requireRole(['admin']);
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get users with role information
    $stmt = $db->prepare("
        SELECT 
            u.id, u.name, u.email, u.username, u.foto_profile,
            u.jenis_kelamin, u.no_hp, u.jurusan, u.role,
            u.email_verified_at, u.created_at, u.updated_at,
            r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role = r.name
        ORDER BY u.created_at DESC
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // Format response
    $formattedUsers = array_map(function($user) {
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'foto_profile' => $user['foto_profile'],
            'jenis_kelamin' => $user['jenis_kelamin'],
            'no_hp' => $user['no_hp'],
            'jurusan' => $user['jurusan'],
            'role' => $user['role'],
            'role_name' => $user['role_name'],
            'email_verified_at' => $user['email_verified_at'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];
    }, $users);
    
    ResponseHelper::success($formattedUsers, 'Manage users retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get manage users error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil data manage users', 500);
}
