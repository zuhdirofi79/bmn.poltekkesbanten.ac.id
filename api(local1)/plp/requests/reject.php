<?php
/**
 * Reject Request Endpoint (PLP)
 * 
 * Purpose: Reject equipment request
 * 
 * Location: api/plp/requests/reject.php
 * 
 * Endpoint: POST /api/plp/requests/reject.php?id=20
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON) - Optional:
 * {
 *   "keterangan": "Alasan penolakan"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Request berhasil ditolak"
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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

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
    $stmt = $db->prepare("SELECT id, status FROM peminjaman WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        ResponseHelper::error('Request tidak ditemukan', 404);
    }
    
    // Check if request can be rejected
    if ($request['status'] !== 'Menunggu Konfirmasi' && $request['status'] !== 'Menunggu') {
        ResponseHelper::error('Request tidak dapat ditolak. Status saat ini: ' . $request['status'], 400);
    }
    
    // Update request status to "Ditolak"
    $stmt = $db->prepare("
        UPDATE peminjaman 
        SET status = 'Ditolak',
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute(['id' => $requestId]);
    
    // Update peminjaman_detail status to "Ditolak"
    $stmt = $db->prepare("
        UPDATE peminjaman_detail 
        SET status = 'Ditolak'
        WHERE peminjaman_id = :id
        AND status = 'Menunggu Konfirmasi'
    ");
    $stmt->execute(['id' => $requestId]);
    
    ResponseHelper::success(null, 'Request berhasil ditolak');
    
} catch (Exception $e) {
    error_log("Reject request error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menolak request', 500);
}
