<?php
/**
 * Edit User Endpoint (Admin)
 * 
 * Purpose: Edit existing user
 * 
 * Location: api/admin/users/edit.php
 * 
 * Endpoint: PUT /api/admin/users/edit.php?id=123
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "name": "John Doe Updated",
 *   "email": "johnnew@example.com",
 *   "role": "plp",
 *   "jurusan": "TLM",
 *   ...
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {...},
 *   "message": "User berhasil diupdate"
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
    
    // Get user ID from query parameter
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        ResponseHelper::error('User ID is required', 400);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ResponseHelper::error('Invalid JSON input', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch()) {
        ResponseHelper::error('User tidak ditemukan', 404);
    }
    
    // Validate role if provided
    if (isset($input['role'])) {
        $allowedRoles = ['admin', 'plp', 'mahasiswa', 'dosen', 'pimpinan'];
        if (!in_array($input['role'], $allowedRoles)) {
            ResponseHelper::error('Invalid role', 400);
        }
    }
    
    // Check email uniqueness if email is being changed
    if (isset($input['email'])) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
        $stmt->execute(['email' => $input['email'], 'id' => $userId]);
        if ($stmt->fetch()) {
            ResponseHelper::error('Email sudah digunakan', 400);
        }
    }
    
    // Build update query dynamically
    $updateFields = [];
    $params = ['id' => $userId];
    
    $allowedFields = ['name', 'email', 'role', 'jurusan', 'jenis_kelamin', 'no_hp', 'foto_profile'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[$field] = $input[$field];
        }
    }
    
    // Handle password update separately (if provided)
    if (isset($input['password']) && !empty($input['password'])) {
        if (strlen($input['password']) < 6) {
            ResponseHelper::error('Password must be at least 6 characters', 400);
        }
        $updateFields[] = "password = :password";
        $params['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    
    if (empty($updateFields)) {
        ResponseHelper::error('No fields to update', 400);
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Get updated user
    $stmt = $db->prepare("
        SELECT id, name, email, username, foto_profile,
               jenis_kelamin, no_hp, jurusan, role,
               email_verified_at, created_at, updated_at
        FROM users
        WHERE id = :id
    ");
    $stmt->execute(['id' => $userId]);
    $updatedUser = $stmt->fetch();
    
    ResponseHelper::success([
        'id' => (int)$updatedUser['id'],
        'name' => $updatedUser['name'],
        'email' => $updatedUser['email'],
        'username' => $updatedUser['username'],
        'foto_profile' => $updatedUser['foto_profile'],
        'jenis_kelamin' => $updatedUser['jenis_kelamin'],
        'no_hp' => $updatedUser['no_hp'],
        'jurusan' => $updatedUser['jurusan'],
        'role' => $updatedUser['role'],
        'email_verified_at' => $updatedUser['email_verified_at'],
        'created_at' => $updatedUser['created_at'],
        'updated_at' => $updatedUser['updated_at']
    ], 'User berhasil diupdate');
    
} catch (Exception $e) {
    error_log("Edit user error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengupdate user', 500);
}
