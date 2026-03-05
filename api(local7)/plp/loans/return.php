<?php
/**
 * Mark Return Complete Endpoint (PLP)
 * 
 * Purpose: Mark loan return as complete
 * 
 * Location: api/plp/loans/return.php
 * 
 * Endpoint: POST /api/plp/loans/return.php?id=20
 * 
 * Headers:
 * Authorization: Bearer {token}
 * 
 * Request Body (JSON):
 * {
 *   "items": [
 *     {
 *       "detail_id": 26,
 *       "status": "Dikembalikan",
 *       "kondisi": "Baik"
 *     }
 *   ]
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Pengembalian berhasil dicatat"
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
    
    // Get loan ID
    $loanId = $_GET['id'] ?? null;
    
    if (!$loanId) {
        ResponseHelper::error('Loan ID is required', 400);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['items']) || !is_array($input['items'])) {
        ResponseHelper::error('Items array is required', 400);
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Check if loan exists
        $stmt = $db->prepare("SELECT id, status FROM peminjaman WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $loanId]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            throw new Exception('Loan tidak ditemukan');
        }
        
        // Update each item
        foreach ($input['items'] as $item) {
            if (empty($item['detail_id'])) {
                continue;
            }
            
            $updateFields = [];
            $params = ['id' => $item['detail_id']];
            
            if (isset($item['status'])) {
                $updateFields[] = "status = :status";
                $params['status'] = $item['status'];
            }
            
            if (isset($item['kondisi'])) {
                $updateFields[] = "kondisi = :kondisi";
                $params['kondisi'] = $item['kondisi'];
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "petugas_pengembalian = :petugas";
                $params['petugas'] = $user['name'];
                
                $sql = "UPDATE peminjaman_detail SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
        }
        
        // Update loan status and return date
        $stmt = $db->prepare("
            UPDATE peminjaman 
            SET status = 'Selesai',
                tgl_pengembalian = CURDATE()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $loanId]);
        
        // Commit transaction
        $db->commit();
        
        ResponseHelper::success(null, 'Pengembalian berhasil dicatat');
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Return loan error: " . $e->getMessage());
    ResponseHelper::error($e->getMessage(), 500);
}
