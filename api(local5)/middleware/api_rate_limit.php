<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/audit_logger.php';
require_once __DIR__ . '/../config/reputation_engine.php';

class ApiRateLimit {
    const LIMIT_UNAUTHENTICATED = 60;
    const LIMIT_AUTHENTICATED = 120;
    const WINDOW_SECONDS = 60;
    
    private static function getClientIp() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        $fallbackIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (filter_var($fallbackIp, FILTER_VALIDATE_IP) !== false) {
            return $fallbackIp;
        }
        
        return '0.0.0.0';
    }
    
    private static function getTokenHash() {
        $headers = getallheaders();
        
        if (is_array($headers)) {
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            if ($authHeader && preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
                $token = trim($matches[1]);
                if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
                    return hash('sha256', $token);
                }
            }
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
                $token = trim($matches[1]);
                if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
                    return hash('sha256', $token);
                }
            }
        }
        
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', trim($authHeader), $matches)) {
                $token = trim($matches[1]);
                if (!empty($token) && strlen($token) === 64 && ctype_xdigit($token)) {
                    return hash('sha256', $token);
                }
            }
        }
        
        return null;
    }
    
    private static function validateTokenExists($tokenHash) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT 1
                FROM api_tokens
                WHERE token_hash = :token_hash
                AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['token_hash' => $tokenHash]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("ApiRateLimit token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function sanitizeEndpoint($endpoint) {
        $endpoint = parse_url($endpoint, PHP_URL_PATH);
        $endpoint = preg_replace('/[^a-zA-Z0-9\/\-_\.]/', '', $endpoint);
        $endpoint = substr($endpoint, 0, 255);
        return $endpoint ?: '/';
    }
    
    public static function check() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return true;
        }
        
        $tokenHash = self::getTokenHash();
        $ipAddress = self::getClientIp();
        $endpoint = self::sanitizeEndpoint($_SERVER['REQUEST_URI'] ?? '/');
        $currentTime = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', floor(time() / self::WINDOW_SECONDS) * self::WINDOW_SECONDS);
        
        $isAuthenticated = false;
        $identifier = $ipAddress;
        $identifierType = 'ip';
        $baseLimit = self::LIMIT_UNAUTHENTICATED;
        
        if ($tokenHash && self::validateTokenExists($tokenHash)) {
            $isAuthenticated = true;
            $identifier = $tokenHash;
            $identifierType = 'token';
            $baseLimit = self::LIMIT_AUTHENTICATED;
        }
        
        $reputation = ReputationEngine::getReputation($ipAddress);
        $rateLimitMultiplier = $reputation['rate_limit_multiplier'];
        $limit = (int)($baseLimit / $rateLimitMultiplier);
        
        if ($reputation['score'] > 0) {
            error_log("ApiRateLimit: Adjusted limit for IP $ipAddress (reputation: {$reputation['score']}, multiplier: {$rateLimitMultiplier}x, base: $baseLimit, adjusted: $limit)");
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT request_count, window_start
                FROM api_rate_limits
                WHERE identifier = :identifier
                AND identifier_type = :identifier_type
                AND endpoint = :endpoint
                LIMIT 1
            FOR UPDATE
            ");
            
            $stmt->execute([
                'identifier' => $identifier,
                'identifier_type' => $identifierType,
                'endpoint' => $endpoint
            ]);
            
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                $recordWindowStart = $record['window_start'];
                
                if ($recordWindowStart === $windowStart) {
                    $requestCount = (int)$record['request_count'] + 1;
                    
                    if ($requestCount > $limit) {
                        $db->rollBack();
                        
                        AuditLogger::rateLimitHit($identifier, $identifierType, $endpoint);
                        
                        require_once __DIR__ . '/../config/alert_engine.php';
                        AlertEngine::check('EXCESSIVE_REQUESTS', [
                            'ip_address' => $identifierType === 'ip' ? $identifier : SecurityHelpers::getClientIp(),
                            'token_hash' => $identifierType === 'token' ? $identifier : null,
                            'endpoint' => $endpoint,
                            'status' => 'FAIL',
                            'metadata' => [
                                'identifier_type' => $identifierType,
                                'request_count' => $requestCount,
                                'limit' => $limit
                            ]
                        ]);
                        
                        http_response_code(429);
                        header('Content-Type: application/json; charset=utf-8');
                        header('Retry-After: ' . self::WINDOW_SECONDS);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Too many requests. Please slow down.'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE api_rate_limits
                        SET request_count = :request_count,
                            last_request_at = :last_request_at,
                            updated_at = NOW()
                        WHERE identifier = :identifier
                        AND identifier_type = :identifier_type
                        AND endpoint = :endpoint
                        AND window_start = :window_start
                    ");
                    
                    $stmt->execute([
                        'request_count' => $requestCount,
                        'last_request_at' => $currentTime,
                        'identifier' => $identifier,
                        'identifier_type' => $identifierType,
                        'endpoint' => $endpoint,
                        'window_start' => $windowStart
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE api_rate_limits
                        SET request_count = 1,
                            window_start = :window_start,
                            last_request_at = :last_request_at,
                            updated_at = NOW()
                        WHERE identifier = :identifier
                        AND identifier_type = :identifier_type
                        AND endpoint = :endpoint
                    ");
                    
                    $stmt->execute([
                        'window_start' => $windowStart,
                        'last_request_at' => $currentTime,
                        'identifier' => $identifier,
                        'identifier_type' => $identifierType,
                        'endpoint' => $endpoint
                    ]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO api_rate_limits (
                        identifier, identifier_type, endpoint,
                        request_count, window_start, last_request_at
                    ) VALUES (
                        :identifier, :identifier_type, :endpoint,
                        1, :window_start, :last_request_at
                    )
                ");
                
                $stmt->execute([
                    'identifier' => $identifier,
                    'identifier_type' => $identifierType,
                    'endpoint' => $endpoint,
                    'window_start' => $windowStart,
                    'last_request_at' => $currentTime
                ]);
            }
            
            $db->commit();
            
            if ($isAuthenticated && $tokenHash) {
                self::trackIpFallback($ipAddress, $endpoint, $currentTime, $windowStart);
            }
            
            return true;
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("ApiRateLimit error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            return true;
        }
    }
    
    private static function trackIpFallback($ipAddress, $endpoint, $currentTime, $windowStart) {
        try {
            $db = Database::getInstance()->getConnection();
            $ipLimit = self::LIMIT_UNAUTHENTICATED;
            
            $stmt = $db->prepare("
                SELECT request_count, window_start
                FROM api_rate_limits
                WHERE identifier = :identifier
                AND identifier_type = 'ip'
                AND endpoint = :endpoint
                LIMIT 1
            FOR UPDATE
            ");
            
            $stmt->execute([
                'identifier' => $ipAddress,
                'endpoint' => $endpoint
            ]);
            
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                if ($record['window_start'] === $windowStart) {
                    $ipRequestCount = (int)$record['request_count'] + 1;
                    
                    if ($ipRequestCount > $ipLimit) {
                        $stmt = $db->prepare("
                            UPDATE api_rate_limits
                            SET request_count = :request_count,
                                last_request_at = :last_request_at
                            WHERE identifier = :identifier
                            AND identifier_type = 'ip'
                            AND endpoint = :endpoint
                            AND window_start = :window_start
                        ");
                        $stmt->execute([
                            'request_count' => $ipRequestCount,
                            'last_request_at' => $currentTime,
                            'identifier' => $ipAddress,
                            'endpoint' => $endpoint,
                            'window_start' => $windowStart
                        ]);
                    } else {
                        $stmt = $db->prepare("
                            UPDATE api_rate_limits
                            SET request_count = :request_count,
                                last_request_at = :last_request_at
                            WHERE identifier = :identifier
                            AND identifier_type = 'ip'
                            AND endpoint = :endpoint
                            AND window_start = :window_start
                        ");
                        $stmt->execute([
                            'request_count' => $ipRequestCount,
                            'last_request_at' => $currentTime,
                            'identifier' => $ipAddress,
                            'endpoint' => $endpoint,
                            'window_start' => $windowStart
                        ]);
                    }
                } else {
                    $stmt = $db->prepare("
                        UPDATE api_rate_limits
                        SET request_count = 1,
                            window_start = :window_start,
                            last_request_at = :last_request_at
                        WHERE identifier = :identifier
                        AND identifier_type = 'ip'
                        AND endpoint = :endpoint
                    ");
                    $stmt->execute([
                        'window_start' => $windowStart,
                        'last_request_at' => $currentTime,
                        'identifier' => $ipAddress,
                        'endpoint' => $endpoint
                    ]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO api_rate_limits (
                        identifier, identifier_type, endpoint,
                        request_count, window_start, last_request_at
                    ) VALUES (
                        :identifier, 'ip', :endpoint,
                        1, :window_start, :last_request_at
                    )
                ");
                $stmt->execute([
                    'identifier' => $ipAddress,
                    'endpoint' => $endpoint,
                    'window_start' => $windowStart,
                    'last_request_at' => $currentTime
                ]);
            }
        } catch (Exception $e) {
            error_log("ApiRateLimit IP tracking error: " . $e->getMessage());
        }
    }
}
