<?php
/**
 * Create Lab Visit Endpoint (User)
 * 
 * Purpose: Create new lab visit record
 * 
 * Location: api/user/lab-visits/create.php
 * 
 * Endpoint: POST /api/user/lab-visits/create.php
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "tgl_kunjungan": "2024-02-15",
 *   "waktu_mulai": "08:00:00",
 *   "waktu_selesai": "10:00:00",
 *   "ruang_lab": "Lab Kimia Dasar dan Terapan",
 *   "kelas": "1A",
 *   "tujuan": "Praktikum",
 *   "keterangan": "Optional keterangan"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {...},
 *   "message": "Kunjungan lab berhasil dicatat"
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
    // Validate token and get user
    $user = AuthMiddleware::validateToken();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ResponseHelper::error('Invalid JSON input', 400);
    }
    
    // Validate required fields
    $required = ['tgl_kunjungan', 'waktu_mulai', 'waktu_selesai', 'ruang_lab', 'tujuan'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            ResponseHelper::error("Field $field is required", 400);
        }
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Insert lab visit
    $stmt = $db->prepare("
        INSERT INTO kunjungan_lab (
            user_id, jurusan, prodi, tgl_kunjungan,
            waktu_mulai, waktu_selesai, ruang_lab,
            kelas, tujuan, keterangan
        ) VALUES (
            :user_id, :jurusan, :prodi, :tgl_kunjungan,
            :waktu_mulai, :waktu_selesai, :ruang_lab,
            :kelas, :tujuan, :keterangan
        )
    ");
    
    $stmt->execute([
        'user_id' => $user['id'],
        'jurusan' => $user['jurusan'] ?? '',
        'prodi' => $input['prodi'] ?? null,
        'tgl_kunjungan' => $input['tgl_kunjungan'],
        'waktu_mulai' => $input['waktu_mulai'],
        'waktu_selesai' => $input['waktu_selesai'],
        'ruang_lab' => $input['ruang_lab'],
        'kelas' => $input['kelas'] ?? null,
        'tujuan' => $input['tujuan'],
        'keterangan' => $input['keterangan'] ?? null
    ]);
    
    $visitId = $db->lastInsertId();
    
    // Get created visit
    $stmt = $db->prepare("
        SELECT id, user_id, jurusan, prodi, tgl_kunjungan,
               waktu_mulai, waktu_selesai, ruang_lab, kelas,
               tujuan, keterangan
        FROM kunjungan_lab
        WHERE id = :id
    ");
    $stmt->execute(['id' => $visitId]);
    $visit = $stmt->fetch();
    
    ResponseHelper::success([
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
    ], 'Kunjungan lab berhasil dicatat');
    
} catch (Exception $e) {
    error_log("Create lab visit error: " . $e->getMessage());
    ResponseHelper::error('Terjadi kesalahan saat mencatat kunjungan lab', 500);
}
