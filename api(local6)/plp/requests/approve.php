<?php
/**
 * Approve Request Endpoint (PLP)
 * 
 * Purpose: Approve equipment request
 * 
 * Location: api/plp/requests/approve.php
 * 
 * Endpoint: POST /api/plp/requests/approve.php?id=20
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON) - Optional:
 * {
 *   "keterangan": "Request disetujui"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Request berhasil disetujui"
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
    
    // Check if request can be approved
    if ($request['status'] !== 'Menunggu Konfirmasi' && $request['status'] !== 'Menunggu') {
        ResponseHelper::error('Request tidak dapat disetujui. Status saat ini: ' . $request['status'], 400);
    }
    
    // Update request status to "Diterima"
    $stmt = $db->prepare("
        UPDATE peminjaman 
        SET status = 'Diterima',
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute(['id' => $requestId]);
    
    // Update peminjaman_detail status to "Dipinjamkan"
    $stmt = $db->prepare("
        UPDATE peminjaman_detail 
        SET status = 'Dipinjamkan',
            petugas_peminjaman = :petugas
        WHERE peminjaman_id = :id
        AND status = 'Menunggu Konfirmasi'
    ");
    $stmt->execute([
        'id' => $requestId,
        'petugas' => $user['name']
    ]);
    
    ResponseHelper::success(null, 'Request berhasil disetujui');
    
} catch (Exception $e) {
    error_log("Approve request error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat menyetujui request', 500);
}
