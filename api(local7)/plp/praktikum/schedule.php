<?php
/**
 * Get Praktikum Schedule Endpoint (PLP)
 * 
 * Purpose: Get list of praktikum schedules
 * 
 * Location: api/plp/praktikum/schedule.php
 * 
 * Endpoint: GET /api/plp/praktikum/schedule.php?jurusan=keperawatan
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 16,
 *       "semester_id": 2,
 *       "tgl": "2024-01-08",
 *       "jurusan": "tlm",
 *       "penanggung_jawab": "Nurmeily R, S.Pd,M.Si",
 *       "kelas": "Biokimia",
 *       "tingkat": "1D",
 *       "ruang": "Lab Kimia",
 *       "hari": "Senin",
 *       "waktu_mulai": "08:50:00",
 *       "waktu_selesai": "11:40:00",
 *       "tujuan": "Praktikum"
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
    // Require PLP role
    $user = AuthMiddleware::requireRole(['plp']);

    // Get requested jurusan and determine required permission
    $requestedJurusan = $_GET['jurusan'] ?? null;

    if (empty($requestedJurusan)) {
        ResponseHelper::error('Jurusan parameter is required', 400);
    }

    // Validate jurusan and require specific permission
    $jurusanLower = strtolower($requestedJurusan);
    $requiredPermission = 'PLP_JADWAL_' . strtoupper($jurusanLower);

    // Require specific jurusan permission
    $user = AuthMiddleware::requirePermission($requiredPermission);

    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get schedules filtered by requested jurusan
    $sql = "SELECT
                id, semester_id, tgl, jurusan, penanggung_jawab,
                kelas, tingkat, ruang, hari, waktu_mulai,
                waktu_selesai, tujuan, created_at, updated_at
            FROM jadwal_praktikum
            WHERE LOWER(jurusan) = :jurusan";

    $params = ['jurusan' => $jurusanLower];
    
    $sql .= " ORDER BY tgl DESC, waktu_mulai ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
    // Format response
    $formattedSchedules = array_map(function($schedule) {
        return [
            'id' => (int)$schedule['id'],
            'semester_id' => (int)$schedule['semester_id'],
            'tgl' => $schedule['tgl'],
            'jurusan' => $schedule['jurusan'],
            'penanggung_jawab' => $schedule['penanggung_jawab'],
            'kelas' => $schedule['kelas'],
            'tingkat' => $schedule['tingkat'],
            'ruang' => $schedule['ruang'],
            'hari' => $schedule['hari'],
            'waktu_mulai' => $schedule['waktu_mulai'],
            'waktu_selesai' => $schedule['waktu_selesai'],
            'tujuan' => $schedule['tujuan'],
            'created_at' => $schedule['created_at'],
            'updated_at' => $schedule['updated_at']
        ];
    }, $schedules);
    
    ResponseHelper::success($formattedSchedules, 'Schedule retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get schedule error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mengambil jadwal praktikum', 500);
}
