<?php
/**
 * Add User Endpoint (Admin)
 * 
 * Purpose: Add new user
 * 
 * Location: api/admin/users/add.php
 * 
 * Endpoint: POST /api/admin/users/add.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "name": "John Doe",
 *   "email": "john@example.com",
 *   "username": "johndoe",
 *   "password": "password123",
 *   "role": "mahasiswa",
 *   "jurusan": "TLM",
 *   "jenis_kelamin": "Laki-laki",
 *   "no_hp": "081234567890"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "id": 1234,
 *     "name": "John Doe",
 *     ...
 *   },
 *   "message": "User berhasil ditambahkan"
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
    $required = ['name', 'email', 'username', 'password', 'role'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            ResponseHelper::error("Field $field is required", 400);
        }
    }
    
    // Validate password length
    if (strlen($input['password']) < 6) {
        ResponseHelper::error('Password must be at least 6 characters', 400);
    }
    
    // Validate role
    $allowedRoles = ['admin', 'plp', 'mahasiswa', 'dosen', 'pimpinan'];
    if (!in_array($input['role'], $allowedRoles)) {
        ResponseHelper::error('Invalid role. Allowed roles: ' . implode(', ', $allowedRoles), 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $input['username']]);
    if ($stmt->fetch()) {
        ResponseHelper::error('Username sudah digunakan', 400);
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $input['email']]);
    if ($stmt->fetch()) {
        ResponseHelper::error('Email sudah digunakan', 400);
    }
    
    // Hash password
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (
            name, email, username, password, role,
            jurusan, jenis_kelamin, no_hp, foto_profile,
            created_at, updated_at
        ) VALUES (
            :name, :email, :username, :password, :role,
            :jurusan, :jenis_kelamin, :no_hp, :foto_profile,
            NOW(), NOW()
        )
    ");
    
    $stmt->execute([
        'name' => $input['name'],
        'email' => $input['email'],
        'username' => $input['username'],
        'password' => $passwordHash,
        'role' => $input['role'],
        'jurusan' => $input['jurusan'] ?? null,
        'jenis_kelamin' => $input['jenis_kelamin'] ?? null,
        'no_hp' => $input['no_hp'] ?? null,
        'foto_profile' => $input['foto_profile'] ?? 'default.png'
    ]);
    
    $userId = $db->lastInsertId();
    
    // Get created user
    $stmt = $db->prepare("
        SELECT id, name, email, username, foto_profile,
               jenis_kelamin, no_hp, jurusan, role,
               email_verified_at, created_at, updated_at
        FROM users
        WHERE id = :id
    ");
    $stmt->execute(['id' => $userId]);
    $newUser = $stmt->fetch();
    
    ResponseHelper::success([
        'id' => (int)$newUser['id'],
        'name' => $newUser['name'],
        'email' => $newUser['email'],
        'username' => $newUser['username'],
        'foto_profile' => $newUser['foto_profile'],
        'jenis_kelamin' => $newUser['jenis_kelamin'],
        'no_hp' => $newUser['no_hp'],
        'jurusan' => $newUser['jurusan'],
        'role' => $newUser['role'],
        'email_verified_at' => $newUser['email_verified_at'],
        'created_at' => $newUser['created_at'],
        'updated_at' => $newUser['updated_at']
    ], 'User berhasil ditambahkan');
    
} catch (Exception $e) {
    error_log("Add user error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menambahkan user', 500);
}
