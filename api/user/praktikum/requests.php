<?php
/**
 * Get User Room Booking Requests (User) - List for dashboard
 *
 * Purpose: List req_jadwal_praktikum for current user (formulir peminjaman ruang LAB)
 *
 * Location: api/user/praktikum/requests.php
 * URL: GET /api/user/praktikum/requests.php
 *
 * Headers: Authorization: Bearer {token}
 *
 * Response:
 * { "success": true, "data": [ { id, tgl, jurusan, penanggung_jawab, kelas, tingkat, ruang, hari, waktu_mulai, waktu_selesai, tujuan, status, created_at }, ... ] }
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

try {
    error_log("@@@PROOF_PRAKTIKUM_REQUESTS@@@ endpoint=/user/praktikum/requests.php");

    $user = AuthMiddleware::validateToken();
    $userId = (int)($user['id'] ?? 0);

    if ($userId <= 0) {
        ResponseHelper::error('User tidak valid', 401);
    }

    $db = Database::getInstance()->getConnection();

    $sql = "SELECT id, user_id, semester_id, tgl, jurusan, penanggung_jawab, kelas, tingkat, ruang, hari,
            waktu_mulai, waktu_selesai, tujuan, status, keterangan, created_at, updated_at, petugas
            FROM req_jadwal_praktikum
            WHERE user_id = :user_id
            ORDER BY id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'semester_id' => (int)$r['semester_id'],
            'tgl' => $r['tgl'],
            'jurusan' => $r['jurusan'],
            'penanggung_jawab' => $r['penanggung_jawab'],
            'kelas' => $r['kelas'],
            'tingkat' => $r['tingkat'],
            'ruang' => $r['ruang'],
            'hari' => $r['hari'],
            'waktu_mulai' => $r['waktu_mulai'],
            'waktu_selesai' => $r['waktu_selesai'],
            'tujuan' => $r['tujuan'],
            'status' => $r['status'],
            'keterangan' => $r['keterangan'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
            'petugas' => $r['petugas'],
        ];
    }, $rows);

    error_log("@@@PROOF_PRAKTIKUM_REQUESTS@@@ user_id=$userId count=" . count($data));

    ResponseHelper::success($data, 'Requests retrieved successfully');

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    error_log("@@@PROOF_PRAKTIKUM_REQUESTS_FAIL@@@ class=$errorClass message=$errorMessage");
    error_log("requests error: $errorMessage");

    $statusCode = 500;
    $errorCode = null;
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'PRAKTIKUM_REQUESTS_ERROR';
    }
    ResponseHelper::error('Terjadi kesalahan saat mengambil daftar permintaan', $statusCode, null, $errorCode);
}
