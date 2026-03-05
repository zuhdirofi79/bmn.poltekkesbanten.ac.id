<?php
/**
 * Get User Profile Endpoint
 * 
 * Purpose: Example protected endpoint that requires authentication
 * Demonstrates token validation and role-based access
 * 
 * Location: api/user/profile.php
 * 
 * Endpoint: GET /api/user/profile.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "id": 4,
 *     "name": "Admin Kampus",
 *     "email": "fajar.nur@raharja.info",
 *     "username": "99999",
 *     "role": "admin",
 *     ...
 *   }
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
    // Validate token - this will return user data or throw error
    $user = AuthMiddleware::validateToken();
    
    // Return user profile
    ResponseHelper::success($user, 'Profile retrieved successfully');
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil profile', 500);
}
