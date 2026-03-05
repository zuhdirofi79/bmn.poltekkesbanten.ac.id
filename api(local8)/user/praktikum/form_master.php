<?php
/**
 * Form Master Data Endpoint (User) - Single call for form dropdowns
 *
 * Purpose: Return dosen, matkul, tingkat, ruangan, hari, tujuan, semester in one response
 *
 * Location: api/user/praktikum/form_master.php
 * URL: GET /api/user/praktikum/form_master.php
 *
 * Headers: Authorization: Bearer {token}
 *
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "dosen": [ {"id": 1084, "name": "..."}, ... ],
 *     "matkul": [ {"id": 1, "matkul": "..."}, ... ],
 *     "tingkat": [ {"id": 1, "tingkat": "1A"}, ... ],
 *     "ruangan": [ {"id": 1, "nama_ruang": "...", "kampus": "...", "jurusan": "..."}, ... ],
 *     "hari": [ {"id": 1, "hari": "Senin"}, ... ],
 *     "tujuan": [ {"id": 1, "tujuan": "Praktikum"}, ... ],
 *     "semester": {"id": 6, "nama": "Genap 2025-2026"}
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

try {
    error_log("@@@PROOF_PRAKTIKUM_FORM_MASTER@@@ endpoint=/user/praktikum/form_master.php");

    $user = AuthMiddleware::validateToken();
    $userId = (int)($user['id'] ?? 0);
    $userJurusan = $user['jurusan'] ?? null;
    $jurusanFilter = is_string($userJurusan) && trim($userJurusan) !== '' ? trim($userJurusan) : null;

    $db = Database::getInstance()->getConnection();

    $dosen = [];
    $matkul = [];
    $tingkat = [];
    $ruangan = [];
    $hari = [];
    $tujuan = [];
    $semester = ['id' => 1, 'nama' => 'Semester 1'];

    // Dosen: users WHERE role='dosen' (id, name); optional filter by jurusan
    try {
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
    } catch (Exception $e) {
        error_log("form_master dosen: " . $e->getMessage());
    }

    // Matkul: kelas DISTINCT matkul (id, matkul); optional filter jurusan
    try {
        $sql = "SELECT id, jurusan, matkul FROM kelas";
        $params = [];
        if ($jurusanFilter !== null) {
            $sql .= " WHERE jurusan = :jurusan";
            $params['jurusan'] = $jurusanFilter;
        }
        $sql .= " ORDER BY matkul ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $seen = [];
        foreach ($rows as $r) {
            $matkulName = (string)($r['matkul'] ?? '');
            if ($matkulName !== '' && !isset($seen[$matkulName])) {
                $seen[$matkulName] = true;
                $matkul[] = ['id' => (int)$r['id'], 'matkul' => $matkulName];
            }
        }
    } catch (Exception $e) {
        error_log("form_master matkul: " . $e->getMessage());
    }

    // Tingkat: tingkat (id, tingkat)
    try {
        $stmt = $db->query("SELECT id, tingkat FROM tingkat ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tingkat = array_map(function ($r) {
            return ['id' => (int)$r['id'], 'tingkat' => (string)$r['tingkat']];
        }, $rows);
    } catch (Exception $e) {
        error_log("form_master tingkat: " . $e->getMessage());
    }

    // Ruangan: ruangan (id, nama_ruang, kampus, jurusan)
    try {
        $sql = "SELECT id, jurusan, kampus, nama_ruang FROM ruangan";
        $params = [];
        if ($jurusanFilter !== null) {
            $sql .= " WHERE jurusan = :jurusan";
            $params['jurusan'] = $jurusanFilter;
        }
        $sql .= " ORDER BY nama_ruang ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ruangan = array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'nama_ruang' => (string)$r['nama_ruang'],
                'kampus' => (string)($r['kampus'] ?? ''),
                'jurusan' => (string)($r['jurusan'] ?? ''),
            ];
        }, $rows);
    } catch (Exception $e) {
        error_log("form_master ruangan: " . $e->getMessage());
    }

    // Hari: hari (id, hari)
    try {
        $stmt = $db->query("SELECT id, hari FROM hari ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hari = array_map(function ($r) {
            return ['id' => (int)$r['id'], 'hari' => (string)$r['hari']];
        }, $rows);
    } catch (Exception $e) {
        error_log("form_master hari: " . $e->getMessage());
    }

    // Tujuan: tujuan (id, tujuan)
    try {
        $stmt = $db->query("SELECT id, tujuan FROM tujuan ORDER BY id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tujuan = array_map(function ($r) {
            return ['id' => (int)$r['id'], 'tujuan' => (string)$r['tujuan']];
        }, $rows);
    } catch (Exception $e) {
        error_log("form_master tujuan: " . $e->getMessage());
    }

    // Semester aktif: semester WHERE status='aktif'
    try {
        $stmt = $db->query("SELECT id, semester, ta FROM semester WHERE status = 'aktif' LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $semester = [
                'id' => (int)$row['id'],
                'nama' => (string)($row['semester'] ?? '') . ' ' . (string)($row['ta'] ?? ''),
            ];
        }
    } catch (Exception $e) {
        error_log("form_master semester: " . $e->getMessage());
    }

    $counts = 'dosen=' . count($dosen) . ' matkul=' . count($matkul) . ' tingkat=' . count($tingkat) . ' ruangan=' . count($ruangan) . ' hari=' . count($hari) . ' tujuan=' . count($tujuan);
    error_log("@@@PROOF_PRAKTIKUM_FORM_MASTER@@@ counts=" . $counts);

    $data = [
        'dosen' => $dosen,
        'matkul' => $matkul,
        'tingkat' => $tingkat,
        'ruangan' => $ruangan,
        'hari' => $hari,
        'tujuan' => $tujuan,
        'semester' => $semester,
    ];

    ResponseHelper::success($data, 'Form master data retrieved successfully');

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorClass = get_class($e);
    error_log("@@@PROOF_PRAKTIKUM_FORM_MASTER_FAIL@@@ class=$errorClass message=$errorMessage");
    error_log("form_master error: $errorMessage");

    $statusCode = 500;
    $errorCode = null;
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorCode = 'PRAKTIKUM_FORM_MASTER_ERROR';
    }
    ResponseHelper::error('Terjadi kesalahan saat mengambil data master form', $statusCode, null, $errorCode);
}
