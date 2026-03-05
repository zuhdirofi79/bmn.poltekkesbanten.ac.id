<?php
/**
 * Create Room Booking Request (User) - POST
 *
 * Purpose: Insert new row into req_jadwal_praktikum (formulir peminjaman ruang LAB)
 *
 * Location: api/user/praktikum/create_request.php
 * URL: POST /api/user/praktikum/create_request.php
 *
 * Headers: Authorization: Bearer {token}
 * Body JSON: semester_id, tgl, penanggung_jawab, kelas, tingkat, ruang, hari, waktu_mulai, waktu_selesai, tujuan
 *
 * Success: { "success": true, "message": "Permintaan peminjaman berhasil diajukan", "data": { "id": <newId> } }
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

try {
    error_log("@@@PROOF_PRAKTIKUM_CREATE_REQUEST@@@ endpoint=/user/praktikum/create_request.php");

    $user = AuthMiddleware::validateToken();
    $userId = (int)($user['id'] ?? 0);
    $jurusan = isset($user['jurusan']) && is_string($user['jurusan']) ? trim($user['jurusan']) : '';
    if ($jurusan === '') {
        $jurusan = '-';
    }

    if ($userId <= 0) {
        ResponseHelper::error('User tidak valid', 401);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        ResponseHelper::error('Body harus JSON object', 400);
    }

    $semesterId = isset($input['semester_id']) ? (int)$input['semester_id'] : 0;
    $tgl = isset($input['tgl']) ? trim((string)$input['tgl']) : '';
    $penanggungJawab = isset($input['penanggung_jawab']) ? trim((string)$input['penanggung_jawab']) : '';
    $kelas = isset($input['kelas']) ? trim((string)$input['kelas']) : '';
    $tingkat = isset($input['tingkat']) ? trim((string)$input['tingkat']) : '';
    $ruang = isset($input['ruang']) ? trim((string)$input['ruang']) : '';
    $hari = isset($input['hari']) ? trim((string)$input['hari']) : '';
    $waktuMulai = isset($input['waktu_mulai']) ? trim((string)$input['waktu_mulai']) : '';
    $waktuSelesai = isset($input['waktu_selesai']) ? trim((string)$input['waktu_selesai']) : '';
    $tujuan = isset($input['tujuan']) ? trim((string)$input['tujuan']) : '';
    $keterangan = isset($input['keterangan']) ? trim((string)$input['keterangan']) : null;

    $errors = [];
    if ($semesterId <= 0) {
        $errors[] = 'semester_id wajib dan harus positif';
    }
    if ($tgl === '') {
        $errors[] = 'tgl wajib diisi';
    } else {
        $dt = date_parse($tgl);
        if (!$dt || $dt['error_count'] > 0 || !checkdate((int)($dt['month'] ?? 0), (int)($dt['day'] ?? 0), (int)($dt['year'] ?? 0))) {
            $errors[] = 'tgl tidak valid (gunakan YYYY-MM-DD)';
        }
    }
    if ($penanggungJawab === '') {
        $errors[] = 'penanggung_jawab wajib diisi';
    }
    if ($kelas === '') {
        $errors[] = 'kelas wajib diisi';
    }
    if ($tingkat === '') {
        $errors[] = 'tingkat wajib diisi';
    }
    if ($ruang === '') {
        $errors[] = 'ruang wajib diisi';
    }
    if ($hari === '') {
        $errors[] = 'hari wajib diisi';
    }
    if ($waktuMulai === '') {
        $errors[] = 'waktu_mulai wajib diisi';
    }
    if ($waktuSelesai === '') {
        $errors[] = 'waktu_selesai wajib diisi';
    }
    if ($tujuan === '') {
        $errors[] = 'tujuan wajib diisi';
    }

    if ($waktuMulai !== '' && $waktuSelesai !== '') {
        $tsStart = strtotime($waktuMulai);
        $tsEnd = strtotime($waktuSelesai);
        if ($tsStart !== false && $tsEnd !== false && $tsEnd <= $tsStart) {
            $errors[] = 'waktu_selesai harus setelah waktu_mulai';
        }
    }

    if (!empty($errors)) {
        ResponseHelper::error(implode('; ', $errors), 400, $errors);
    }

    $status = 'Menunggu Konfirmasi';
    $now = date('Y-m-d H:i:s');
    $updatedAt = $now;

    $db = Database::getInstance()->getConnection();

    $sql = "INSERT INTO req_jadwal_praktikum (
        user_id, semester_id, tgl, jurusan, penanggung_jawab, kelas, tingkat, ruang, hari,
        waktu_mulai, waktu_selesai, tujuan, status, keterangan, updated_at
    ) VALUES (
        :user_id, :semester_id, :tgl, :jurusan, :penanggung_jawab, :kelas, :tingkat, :ruang, :hari,
        :waktu_mulai, :waktu_selesai, :tujuan, :status, :keterangan, :updated_at
    )";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'semester_id' => $semesterId,
        'tgl' => $tgl,
        'jurusan' => $jurusan,
        'penanggung_jawab' => $penanggungJawab,
        'kelas' => $kelas,
        'tingkat' => $tingkat,
        'ruang' => $ruang,
        'hari' => $hari,
        'waktu_mulai' => $waktuMulai,
        'waktu_selesai' => $waktuSelesai,
        'tujuan' => $tujuan,
        'status' => $status,
        'keterangan' => $keterangan,
        'updated_at' => $updatedAt,
    ]);

    $newId = (int)$db->lastInsertId();
    error_log("@@@PROOF_PRAKTIKUM_CREATE_REQUEST@@@ user_id=$userId jurusan=$jurusan new_id=$newId");

    ResponseHelper::success(['id' => $newId], 'Permintaan peminjaman berhasil diajukan');

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    error_log("@@@PROOF_PRAKTIKUM_CREATE_REQUEST_FAIL@@@ class=$errorClass message=$errorMessage");
    error_log("create_request error: $errorMessage");

    $statusCode = 500;
    $errorCode = null;
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'PRAKTIKUM_CREATE_REQUEST_ERROR';
    }
    ResponseHelper::error('Terjadi kesalahan saat mengajukan permintaan peminjaman', $statusCode, null, $errorCode);
}
