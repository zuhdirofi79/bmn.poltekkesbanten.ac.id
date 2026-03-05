<?php
/**
 * Get Dosen List (User) - For backward compatibility / form dropdown
 *
 * Purpose: Return list of dosen (users with role='dosen'). Same as form_master.php "dosen" part.
 * Flutter may call this until switched to single form_master.php call.
 *
 * Location: api/user/praktikum/dosen.php
 * URL: GET /api/user/praktikum/dosen.php
 *
 * Headers: Authorization: Bearer {token}
 *
 * Response: { "success": true, "data": [ {"id": 1084, "name": "..."}, ... ] }
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
    error_log("@@@PROOF_PRAKTIKUM_DOSEN@@@ endpoint=/user/praktikum/dosen.php");

    $user = AuthMiddleware::validateToken();
    $userJurusan = isset($user['jurusan']) && is_string($user['jurusan']) ? trim($user['jurusan']) : null;
    $jurusanFilter = ($userJurusan !== null && $userJurusan !== '') ? $userJurusan : null;

    $db = Database::getInstance()->getConnection();

    $sql = "SELECT id, name FROM users WHERE role = 'dosen'";
    $params = [];
    if ($jurusanFilter !== null) {
        $sql .= " AND (jurusan IS NULL OR TRIM(jurusan) = '' OR jurusan = :jurusan)";
        $params['jurusan'] = $jurusanFilter;
    }
    $sql .= " ORDER BY name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dosen = array_map(function ($r) {
        return ['id' => (int)$r['id'], 'name' => (string)$r['name']];
    }, $rows);

    error_log("@@@PROOF_PRAKTIKUM_DOSEN@@@ count=" . count($dosen));

    ResponseHelper::success($dosen, 'Dosen list retrieved successfully');

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    error_log("@@@PROOF_PRAKTIKUM_DOSEN_FAIL@@@ class=$errorClass message=$errorMessage");

    $statusCode = 500;
    $errorCode = null;
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'PRAKTIKUM_DOSEN_ERROR';
    }
    ResponseHelper::error('Terjadi kesalahan saat mengambil daftar dosen', $statusCode, null, $errorCode);
}
