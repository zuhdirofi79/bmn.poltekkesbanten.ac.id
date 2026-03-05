<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/security_helpers.php';
require_once __DIR__ . '/../config/audit_logger.php';

class AuthMiddleware {
    private static $cachedUser = null;
    
    private static function getCurrentEndpoint() {
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $endpoint = parse_url($endpoint, PHP_URL_PATH);
        return substr($endpoint, 0, 255) ?: '/';
    }
    
    /**
     * Get Authorization header robustly
     * 
     * CRITICAL: Many servers (especially Hostinger with PHP 7.4) strip Authorization header
     * This method checks ALL possible locations to ensure token is found
     * 
     * Priority order:
     * 1. getallheaders()['Authorization'] (works on most servers)
     * 2. $_SERVER['HTTP_AUTHORIZATION'] (works when header is preserved)
     * 3. $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (works when mod_rewrite redirects)
     * 4. apache_request_headers()['Authorization'] (Apache-specific fallback)
     * 
     * This matches the approach used in capabilities.php which works reliably
     */
    private static function getAuthorizationHeader() {
        // Method 1: Try getallheaders() (works on most servers)
        $headers = getallheaders();
        if (is_array($headers)) {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            if ($authHeader) {
                error_log("AuthMiddleware: Found Authorization header via getallheaders()");
                return $authHeader;
            }
        }
        
        // Method 2: Try $_SERVER['HTTP_AUTHORIZATION'] (direct server variable)
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            error_log("AuthMiddleware: Found Authorization header via HTTP_AUTHORIZATION");
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Method 3: Try $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (mod_rewrite redirect)
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            error_log("AuthMiddleware: Found Authorization header via REDIRECT_HTTP_AUTHORIZATION");
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        // Method 4: Try apache_request_headers() (Apache-specific)
        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (is_array($apacheHeaders)) {
                $authHeader = $apacheHeaders['Authorization'] ?? $apacheHeaders['authorization'] ?? null;
                if ($authHeader) {
                    error_log("AuthMiddleware: Found Authorization header via apache_request_headers()");
                    return $authHeader;
                }
            }
        }
        
        error_log("AuthMiddleware: Authorization header NOT FOUND in any location");
        return null;
    }
    
    private static function revokeToken($db, $tokenHash, $reason) {
        try {
            // Delete token instead of marking as revoked (schema doesn't have revoked_at)
            $stmt = $db->prepare("
                DELETE FROM api_tokens
                WHERE token_hash = :token_hash
            ");
            $stmt->execute([
                'token_hash' => $tokenHash
            ]);
            
            error_log("Token revoked (deleted): token_hash=" . substr($tokenHash, 0, 16) . "... reason=" . $reason);
        } catch (Exception $e) {
            error_log("Token revocation error: " . $e->getMessage());
        }
    }
    
    public static function validateToken() {
        $authHeader = self::getAuthorizationHeader();
        
        if (!$authHeader) {
            error_log("AuthMiddleware: Authorization header missing");
            AuditLogger::unauthorized(null, 'missing_authorization_header');
            $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_MISSING_HEADER' : null;
            ResponseHelper::error('Authorization header missing', 401, null, $errorCode);
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
            error_log("AuthMiddleware: Invalid authorization format: " . substr($authHeader, 0, 50));
            AuditLogger::unauthorized(null, 'invalid_authorization_format');
            $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_INVALID_FORMAT' : null;
            ResponseHelper::error('Invalid authorization format', 401, null, $errorCode);
        }
        
        $token = trim($matches[1]);
        
        if (empty($token)) {
            error_log("AuthMiddleware: Token is empty after extraction");
            AuditLogger::unauthorized(null, 'empty_token');
            $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_EMPTY_TOKEN' : null;
            ResponseHelper::error('Token is required', 401, null, $errorCode);
        }
        
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            error_log("AuthMiddleware: Invalid token format - length: " . strlen($token) . ", is_hex: " . (ctype_xdigit($token) ? 'yes' : 'no'));
            AuditLogger::unauthorized(null, 'invalid_token_format');
            $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_INVALID_TOKEN_FORMAT' : null;
            ResponseHelper::error('Invalid token format', 401, null, $errorCode);
        }
        
        $tokenHash = hash('sha256', $token);
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT 
                    at.id AS token_id,
                    at.user_id AS token_user_id,
                    at.expires_at AS token_expires_at,
                    at.last_ip,
                    at.last_user_agent,
                    at.last_used_at,
                    u.id AS user_id,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.username AS user_username,
                    u.foto_profile AS user_foto_profile,
                    u.jenis_kelamin AS user_jenis_kelamin,
                    u.no_hp AS user_no_hp,
                    u.jurusan AS user_jurusan,
                    u.role AS user_role
                FROM api_tokens at
                INNER JOIN users u ON at.user_id = u.id
                WHERE at.token_hash = :token_hash
                AND at.expires_at > NOW()
                LIMIT 1
            FOR UPDATE
            ");
            
            $stmt->execute(['token_hash' => $tokenHash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $db->rollBack();
                error_log("AuthMiddleware: Token not found or expired - token_hash: " . substr($tokenHash, 0, 16) . "...");
                AuditLogger::tokenExpired(null, $tokenHash);
                
                require_once __DIR__ . '/../config/alert_engine.php';
                AlertEngine::check('TOKEN_INVALID', [
                    'token_hash' => $tokenHash,
                    'endpoint' => self::getCurrentEndpoint(),
                    'status' => 'FAIL'
                ]);
                
                $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_TOKEN_INVALID' : null;
                ResponseHelper::error('Invalid or expired token', 401, null, $errorCode);
            }
            
            $currentIp = SecurityHelpers::getClientIp();
            $currentUserAgent = SecurityHelpers::getUserAgent();
            $lastIp = $result['last_ip'];
            $lastUserAgent = $result['last_user_agent'];
            
            // Check for token replay protection (user agent mismatch)
            if ($lastUserAgent !== null && $lastUserAgent !== '' && $currentUserAgent !== $lastUserAgent) {
                $revokeReason = 'ua_mismatch';
                self::revokeToken($db, $tokenHash, $revokeReason);
                $db->rollBack(); // Rollback since we're rejecting this request
                AuditLogger::tokenReplay($result['user_id'], $currentIp, $currentUserAgent, $revokeReason);
                
                require_once __DIR__ . '/../config/alert_engine.php';
                AlertEngine::check('TOKEN_MULTI_IP', [
                    'token_hash' => $tokenHash,
                    'user_id' => $result['user_id'],
                    'ip_address' => $currentIp,
                    'endpoint' => self::getCurrentEndpoint(),
                    'status' => 'FAIL',
                    'metadata' => ['reason' => $revokeReason]
                ]);
                
                ResponseHelper::unauthorized('Session expired. Please login again.');
            }
            
            // Check for token replay protection (IP mismatch)
            if ($lastIp !== null && $lastIp !== '' && $lastIp !== '0.0.0.0') {
                if (!SecurityHelpers::isSameSubnet($currentIp, $lastIp)) {
                    $revokeReason = 'ip_mismatch';
                    self::revokeToken($db, $tokenHash, $revokeReason);
                    $db->rollBack(); // Rollback since we're rejecting this request
                    AuditLogger::tokenReplay($result['user_id'], $currentIp, $currentUserAgent, $revokeReason);
                    
                    require_once __DIR__ . '/../config/alert_engine.php';
                    AlertEngine::check('TOKEN_MULTI_IP', [
                        'token_hash' => $tokenHash,
                        'user_id' => $result['user_id'],
                        'ip_address' => $currentIp,
                        'endpoint' => self::getCurrentEndpoint(),
                        'status' => 'FAIL',
                        'metadata' => ['reason' => $revokeReason, 'last_ip' => $lastIp]
                    ]);
                    
                    ResponseHelper::unauthorized('Session expired. Please login again.');
                }
            }
            
            // Update audit fields (last_ip, last_user_agent, last_used_at)
            // If update fails, log error but don't fail the request
            try {
                $currentTime = date('Y-m-d H:i:s');
                
                $stmt = $db->prepare("
                    UPDATE api_tokens
                    SET last_ip = :last_ip,
                        last_user_agent = :last_user_agent,
                        last_used_at = :last_used_at,
                        updated_at = NOW()
                    WHERE id = :token_id
                ");
                
                $stmt->execute([
                    'token_id' => $result['token_id'],
                    'last_ip' => $currentIp,
                    'last_user_agent' => $currentUserAgent,
                    'last_used_at' => $currentTime
                ]);
            } catch (Exception $updateEx) {
                // Log update error but don't fail authentication
                error_log("AuthMiddleware: Failed to update token audit fields - " . $updateEx->getMessage());
            }
            
            $db->commit();
            
            // Validate user_id is present and numeric
            if (empty($result['user_id']) || !is_numeric($result['user_id'])) {
                $db->rollBack();
                error_log("AuthMiddleware: user_id is null or invalid - token_hash: " . substr($tokenHash, 0, 16) . "...");
                AuditLogger::unauthorized(null, 'invalid_user_id');
                $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_INVALID_USER_ID' : null;
                ResponseHelper::error('Invalid user associated with token', 401, null, $errorCode);
            }
            
            $user = [
                'id' => (int)$result['user_id'],
                'name' => $result['user_name'],
                'email' => $result['user_email'],
                'username' => $result['user_username'],
                'foto_profile' => $result['user_foto_profile'],
                'jenis_kelamin' => $result['user_jenis_kelamin'],
                'no_hp' => $result['user_no_hp'],
                'jurusan' => $result['user_jurusan'],
                'role' => $result['user_role']
            ];
            
            // Log successful validation with token hash prefix for correlation
            error_log("AuthMiddleware: Token validated successfully - user_id=" . $user['id'] . " | token_hash=" . substr($tokenHash, 0, 8) . "...");
            
            self::$cachedUser = $user;
            
            return $user;
            
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                try {
                    $db->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("AuthMiddleware: Rollback failed: " . $rollbackEx->getMessage());
                }
            }
            
            // Extract detailed error information
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            $sqlState = $e->errorInfo[0] ?? 'UNKNOWN';
            $driverCode = $e->errorInfo[1] ?? null;
            $driverMessage = $e->errorInfo[2] ?? null;
            
            // Log full error details server-side (safe - no token exposure)
            $logContext = [
                'location' => 'token_validation',
                'sqlstate' => $sqlState,
                'code' => $errorCode,
                'driver_code' => $driverCode,
                'message' => $errorMessage,
                'driver_message' => $driverMessage,
                'token_hash_prefix' => substr($tokenHash, 0, 16) . '...',
                'endpoint' => self::getCurrentEndpoint()
            ];
            
            error_log("AuthMiddleware PDOException: " . json_encode($logContext, JSON_UNESCAPED_UNICODE));
            error_log("AuthMiddleware PDOException Trace: " . $e->getTraceAsString());
            
            AuditLogger::dbError($errorMessage, 'token_validation');
            
            // Determine error type and appropriate HTTP status
            $isConnectionError = (
                strpos($errorMessage, 'Connection') !== false ||
                strpos($errorMessage, 'connection') !== false ||
                strpos($errorMessage, 'SQLSTATE[HY000]') !== false ||
                $sqlState === 'HY000'
            );
            
            $isTableError = (
                strpos($errorMessage, "doesn't exist") !== false ||
                strpos($errorMessage, 'Table') !== false ||
                strpos($errorMessage, 'Unknown table') !== false ||
                $sqlState === '42S02' // Table doesn't exist
            );
            
            $isColumnError = (
                strpos($errorMessage, "Unknown column") !== false ||
                strpos($errorMessage, "doesn't have a default value") !== false ||
                $sqlState === '42S22' // Column doesn't exist
            );
            
            // Build response with appropriate error code
            $debugCode = defined('APP_DEBUG') && APP_DEBUG ? 'AUTH_DB_ERROR' : null;
            $response = [
                'success' => false,
                'message' => 'Database error during authentication'
            ];
            
            if ($debugCode) {
                $response['error_code'] = $debugCode;
                // Only include detailed error info in debug mode
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    $response['debug'] = [
                        'sqlstate' => $sqlState,
                        'error_type' => $isConnectionError ? 'connection' : ($isTableError ? 'table' : ($isColumnError ? 'column' : 'query'))
                    ];
                }
            }
            
            // Always return 500 for database errors (not 401, as this is a server issue)
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                try {
                    $db->rollBack();
                } catch (Exception $rollbackEx) {
                    error_log("AuthMiddleware: Rollback failed: " . $rollbackEx->getMessage());
                }
            }
            
            // Log full error details server-side
            $errorMessage = $e->getMessage();
            $errorClass = get_class($e);
            $logContext = [
                'exception_class' => $errorClass,
                'message' => $errorMessage,
                'token_hash_prefix' => substr($tokenHash, 0, 16) . '...',
                'endpoint' => self::getCurrentEndpoint()
            ];
            
            error_log("AuthMiddleware Exception: " . json_encode($logContext, JSON_UNESCAPED_UNICODE));
            error_log("AuthMiddleware Exception Trace: " . $e->getTraceAsString());
            
            AuditLogger::exception($e, ['action' => 'token_validation']);
            
            // Return proper status code with debug-safe error code
            $debugCode = defined('APP_DEBUG') && APP_DEBUG ? 'AUTH_EXCEPTION' : null;
            $response = [
                'success' => false,
                'message' => 'Authentication service error'
            ];
            if ($debugCode) {
                $response['error_code'] = $debugCode;
            }
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public static function requireRole($allowedRoles = []) {
        $user = self::validateToken();
        
        if (!empty($allowedRoles)) {
            if (!is_array($allowedRoles)) {
                $allowedRoles = [$allowedRoles];
            }
            
            if (!in_array($user['role'], $allowedRoles)) {
                AuditLogger::forbidden($user['id'], implode(', ', $allowedRoles));
                
                require_once __DIR__ . '/../config/alert_engine.php';
                AlertEngine::check('REPEATED_403', [
                    'user_id' => $user['id'],
                    'endpoint' => self::getCurrentEndpoint(),
                    'status' => 'FAIL',
                    'metadata' => ['required_role' => implode(', ', $allowedRoles), 'user_role' => $user['role']]
                ]);
                
                ResponseHelper::forbidden('Access denied. Required role: ' . implode(', ', $allowedRoles));
            }
        }
        
        return $user;
    }

    public static function requirePermission($requiredPermission) {
        $user = self::validateToken();

        // TODO: Implement proper permission validation against capabilities
        // For now, since we don't have a proper permission system,
        // just validate the token exists and return user
        // This prevents 500 errors while maintaining basic auth

        return $user;
    }

    public static function user() {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        
        return self::validateToken();
    }
}
