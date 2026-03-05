<?php
/**
 * Debug Endpoint: Database and Token Query Test
 * 
 * Purpose: Test database connection and token query functionality
 * Location: api/auth/ping.php
 * 
 * Endpoint: GET /api/auth/ping.php
 * 
 * Headers (optional):
 * Authorization: Bearer {token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "db_connection": true/false,
 *     "token_query": true/false,
 *     "db_error": "error message if db_connection is false",
 *     "token_error": "error message if token_query is false"
 *   }
 * }
 * 
 * SECURITY: This endpoint should be disabled in production
 * Set ENABLE_DEBUG_PING=false in your environment or config
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if debug endpoint is enabled
$enableDebug = defined('ENABLE_DEBUG_PING') ? ENABLE_DEBUG_PING : true;

// In production, you can disable this by setting ENABLE_DEBUG_PING=false
// For now, we'll allow it but log all access
if (!$enableDebug) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Debug endpoint is disabled'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

$result = [
    'db_connection' => false,
    'token_query' => false,
    'db_error' => null,
    'token_error' => null,
    'db_details' => null,
    'token_details' => null
];

try {
    // Test 1: Database Connection
    $db = Database::getInstance()->getConnection();
    
    // Test with a simple query
    $stmt = $db->query("SELECT 1 as test");
    $testResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testResult && $testResult['test'] == 1) {
        $result['db_connection'] = true;
        $result['db_details'] = [
            'status' => 'connected',
            'charset' => $db->query("SELECT @@character_set_database as charset")->fetch(PDO::FETCH_ASSOC)['charset'] ?? 'unknown'
        ];
    } else {
        $result['db_error'] = 'Connection test query failed';
    }
    
} catch (PDOException $e) {
    $errorInfo = $e->errorInfo ?? [];
    $result['db_error'] = [
        'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'driver_code' => $errorInfo[1] ?? null,
        'driver_message' => $errorInfo[2] ?? null
    ];
    error_log("Debug ping - DB connection error: " . json_encode($result['db_error'], JSON_UNESCAPED_UNICODE));
} catch (Exception $e) {
    $result['db_error'] = [
        'exception' => get_class($e),
        'message' => $e->getMessage()
    ];
    error_log("Debug ping - DB exception: " . json_encode($result['db_error'], JSON_UNESCAPED_UNICODE));
}

// Test 2: Token Query (if token is provided)
$authHeader = null;
$headers = getallheaders();
if (is_array($headers)) {
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

if ($authHeader && preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
    $token = trim($matches[1]);
    
    if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
        try {
            $tokenHash = hash('sha256', $token);
            
            // Test token query (without transaction to avoid locking)
            $stmt = $db->prepare("
                SELECT 
                    at.id AS token_id,
                    at.user_id AS token_user_id,
                    at.expires_at AS token_expires_at,
                    u.id AS user_id,
                    u.name AS user_name,
                    u.role AS user_role
                FROM api_tokens at
                INNER JOIN users u ON at.user_id = u.id
                WHERE at.token_hash = :token_hash
                AND at.expires_at > NOW()
                LIMIT 1
            ");
            
            $stmt->execute(['token_hash' => $tokenHash]);
            $tokenResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tokenResult) {
                $result['token_query'] = true;
                $result['token_details'] = [
                    'status' => 'valid',
                    'user_id' => (int)$tokenResult['user_id'],
                    'user_name' => $tokenResult['user_name'],
                    'user_role' => $tokenResult['user_role'],
                    'expires_at' => $tokenResult['token_expires_at']
                ];
            } else {
                $result['token_error'] = 'Token not found or expired';
            }
            
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo ?? [];
            $errorDetails = [
                'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'driver_code' => $errorInfo[1] ?? null,
                'driver_message' => $errorInfo[2] ?? null
            ];
            
            // Include detailed error in response if APP_DEBUG is enabled
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $result['token_error'] = $errorDetails;
            } else {
                $result['token_error'] = 'Token query failed';
            }
            
            error_log("Debug ping - Token query error: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $errorDetails = [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ];
            
            // Include detailed error in response if APP_DEBUG is enabled
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $result['token_error'] = $errorDetails;
            } else {
                $result['token_error'] = 'Token query exception';
            }
            
            error_log("Debug ping - Token query exception: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
        }
    } else {
        $result['token_error'] = 'Invalid token format (must be 64 hex characters)';
    }
} else {
    $result['token_error'] = 'No token provided (optional for this endpoint)';
}

// Log access to this debug endpoint
error_log("Debug ping accessed from: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " at " . date('Y-m-d H:i:s'));

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => $result
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
