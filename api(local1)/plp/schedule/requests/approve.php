<?php
/**
 * Approve Schedule Request Endpoint (PLP)
 * 
 * Purpose: Approve schedule request and create jadwal_praktikum
 * 
 * Location: api/plp/schedule/requests/approve.php
 * 
 * Endpoint: POST /api/plp/schedule/requests/approve.php?id=6
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON) - Optional:
 * {
 *   "keterangan": "Request disetujui"
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
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Get request detail
        $stmt = $db->prepare("SELECT * FROM req_jadwal_praktikum WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            throw new Exception('Request tidak ditemukan');
        }
        
        // Check if request can be approved
        if ($request['status'] !== 'Menunggu') {
            throw new Exception('Request tidak dapat disetujui. Status saat ini: ' . $request['status']);
        }
        
        // Create jadwal_praktikum from request
        $stmt = $db->prepare("
            INSERT INTO jadwal_praktikum (
                semester_id, tgl, jurusan, penanggung_jawab,
                kelas, tingkat, ruang, hari, waktu_mulai,
                waktu_selesai, tujuan, created_at, updated_at
            ) VALUES (
                :semester_id, :tgl, :jurusan, :penanggung_jawab,
                :kelas, :tingkat, :ruang, :hari, :waktu_mulai,
                :waktu_selesai, :tujuan, NOW(), :updated_at
            )
        ");
        
        $stmt->execute([
            'semester_id' => $request['semester_id'],
            'tgl' => $request['tgl'],
            'jurusan' => $request['jurusan'],
            'penanggung_jawab' => $request['penanggung_jawab'],
            'kelas' => $request['kelas'],
            'tingkat' => $request['tingkat'],
            'ruang' => $request['ruang'],
            'hari' => $request['hari'],
            'waktu_mulai' => $request['waktu_mulai'],
            'waktu_selesai' => $request['waktu_selesai'],
            'tujuan' => $request['tujuan'],
            'updated_at' => $user['name']
        ]);
        
        // Update request status
        $stmt = $db->prepare("
            UPDATE req_jadwal_praktikum 
            SET status = 'Diterima',
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
        
        // Commit transaction
        $db->commit();
        
        ResponseHelper::success(null, 'Request jadwal berhasil disetujui');
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Approve schedule request error: " . $e->getMessage());
    ResponseHelper::error($e->getMessage(), 500);
}
