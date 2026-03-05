<?php
/**
 * Get Lab Visits Endpoint (User)
 * 
 * Purpose: Get list of user's lab visits
 * 
 * Location: api/user/lab-visits.php
 * 
 * Endpoint: GET /api/user/lab-visits.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 10,
 *       "tgl_kunjungan": "2024-02-11",
 *       "ruang_lab": "Lab Kimia Dasar dan Terapan",
 *       "waktu_mulai": "08:00:00",
 *       "waktu_selesai": "10:00:00",
 *       "tujuan": "Praktikum",
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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

try {
    // Proof log: Request received
    error_log("@@@PROOF_USER_VISITS_CALL@@@ endpoint=/user/lab-visits.php");
    
    // Validate token and get user
    $user = AuthMiddleware::validateToken();
    
    error_log("@@@PROOF_USER_VISITS_AUTH_OK@@@ user_id=" . $user['id']);
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get user's lab visits
    $stmt = $db->prepare("
        SELECT 
            id, user_id, jurusan, prodi, tgl_kunjungan,
            waktu_mulai, waktu_selesai, ruang_lab, kelas,
            tujuan, keterangan
        FROM kunjungan_lab
        WHERE user_id = :user_id
        ORDER BY tgl_kunjungan DESC, waktu_mulai DESC
    ");
    
    $stmt->execute(['user_id' => $user['id']]);
    $visits = $stmt->fetchAll();
    
    // Format response
    $formattedVisits = array_map(function($visit) {
        return [
            'id' => (int)$visit['id'],
            'jurusan' => $visit['jurusan'],
            'prodi' => $visit['prodi'],
            'tgl_kunjungan' => $visit['tgl_kunjungan'],
            'waktu_mulai' => $visit['waktu_mulai'],
            'waktu_selesai' => $visit['waktu_selesai'],
            'ruang_lab' => $visit['ruang_lab'],
            'kelas' => $visit['kelas'],
            'tujuan' => $visit['tujuan'],
            'keterangan' => $visit['keterangan']
        ];
    }, $visits);
    
    error_log("@@@PROOF_USER_VISITS_OK@@@ count=" . count($formattedVisits));
    
    ResponseHelper::success($formattedVisits, 'Lab visits retrieved successfully');
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    error_log("@@@PROOF_USER_VISITS_FAIL@@@ class=$errorClass message=$errorMessage");
    error_log("Get lab visits error: $errorMessage");
    
    $statusCode = 500;
    $errorCode = null;
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'USER_VISITS_ERROR';
    }
    
    ResponseHelper::error('Terjadi kesalahan saat mengambil data kunjungan lab', $statusCode, null, $errorCode);
}
