<?php
/**
 * Get Master Data Endpoint (User)
 * 
 * Purpose: Get master data for equipment request form
 * - ruangan (filtered by user's jurusan)
 * - tingkat
 * - tujuan
 * 
 * Location: api/user/master-data.php
 * 
 * Endpoint: GET /api/user/master-data.php?type=ruangan|tingkat|tujuan
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Query Parameters:
 * - type (optional): Filter by type (ruangan, tingkat, tujuan). If not provided, returns all types
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "ruangan": [...],
 *     "tingkat": [...],
 *     "tujuan": [...]
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
    // Validate token and get user
    $user = AuthMiddleware::validateToken();
    
    // Get type filter
    $type = $_GET['type'] ?? null;
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    $result = [];
    
    // Get ruangan (filtered by user's jurusan)
    if (!$type || $type === 'ruangan') {
        $sql = "SELECT id, jurusan, kampus, nama_ruang FROM ruangan WHERE 1=1";
        $params = [];
        
        // Filter by user's jurusan (case-insensitive)
        if (!empty($user['jurusan'])) {
            $sql .= " AND LOWER(jurusan) = LOWER(:jurusan)";
            $params['jurusan'] = $user['jurusan'];
        }
        
        $sql .= " ORDER BY jurusan, nama_ruang";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ruangan = $stmt->fetchAll();
        
        $result['ruangan'] = array_map(function($room) {
            return [
                'id' => (int)$room['id'],
                'jurusan' => $room['jurusan'],
                'kampus' => $room['kampus'],
                'nama_ruang' => $room['nama_ruang']
            ];
        }, $ruangan);
    }
    
    // Get tingkat
    if (!$type || $type === 'tingkat') {
        $stmt = $db->prepare("SELECT id, tingkat FROM tingkat ORDER BY id");
        $stmt->execute();
        $tingkat = $stmt->fetchAll();
        
        $result['tingkat'] = array_map(function($t) {
            return [
                'id' => (int)$t['id'],
                'tingkat' => $t['tingkat']
            ];
        }, $tingkat);
    }
    
    // Get tujuan
    if (!$type || $type === 'tujuan') {
        $stmt = $db->prepare("SELECT id, tujuan FROM tujuan ORDER BY id");
        $stmt->execute();
        $tujuan = $stmt->fetchAll();
        
        $result['tujuan'] = array_map(function($t) {
            return [
                'id' => (int)$t['id'],
                'tujuan' => $t['tujuan']
            ];
        }, $tujuan);
    }
    
    ResponseHelper::success($result, 'Master data retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get user master data error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil master data', 500);
}
