<?php
/**
 * Reject Schedule Request Endpoint (PLP)
 * 
 * Purpose: Reject schedule request
 * 
 * Location: api/plp/schedule/requests/reject.php
 * 
 * Endpoint: POST /api/plp/schedule/requests/reject.php?id=6
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON) - Optional:
 * {
 *   "keterangan": "Alasan penolakan"
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

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/response.php';
require_once __DIR__ . '/../../../middleware/auth.php';

try {
    // Require PLP role
    $user = AuthMiddleware::requireRole(['plp']);
    
    // Get request ID
    $requestId = $_GET['id'] ?? null;
    
    if (!$requestId) {
        ResponseHelper::error('Request ID is required', 400);
    }
    
    // Get JSON input (optional)
    $input = json_decode(file_get_contents('php://input'), true);
    $keterangan = $input['keterangan'] ?? null;
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if request exists
    $stmt = $db->prepare("SELECT id, status FROM req_jadwal_praktikum WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        ResponseHelper::error('Request tidak ditemukan', 404);
    }
    
    // Check if request can be rejected
    if ($request['status'] !== 'Menunggu') {
        ResponseHelper::error('Request tidak dapat ditolak. Status saat ini: ' . $request['status'], 400);
    }
    
    // Update request status
    $stmt = $db->prepare("
        UPDATE req_jadwal_praktikum 
        SET status = 'Ditolak',
            keterangan = :keterangan,
            petugas = :petugas,
            updated_at = :updated_at
        WHERE id = :id
    ");
    
    $stmt->execute([
        'id' => $requestId,
        'keterangan' => $keterangan,
        'petugas' => $user['name'],
        'updated_at' => $user['name']
    ]);
    
    ResponseHelper::success(null, 'Request jadwal berhasil ditolak');
    
} catch (Exception $e) {
    error_log("Reject schedule request error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menolak request jadwal', 500);
}
