<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security_helpers.php';

class AuditLogger {
    private static $requestId = null;
    private static $logBuffer = [];
    private static $bufferSize = 0;
    private const MAX_BUFFER_SIZE = 50;
    private const FILE_LOG_PATH = __DIR__ . '/../../logs/security.log';
    private const MAX_FILE_SIZE = 10485760;
    
    const EVENT_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    const EVENT_LOGIN_FAIL = 'LOGIN_FAIL';
    const EVENT_INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    const EVENT_TOKEN_VALID = 'TOKEN_VALID';
    const EVENT_TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const EVENT_TOKEN_REVOKED = 'TOKEN_REVOKED';
    const EVENT_TOKEN_REPLAY = 'TOKEN_REPLAY';
    const EVENT_RATE_LIMIT_HIT = 'RATE_LIMIT_HIT';
    const EVENT_UNAUTHORIZED = 'UNAUTHORIZED';
    const EVENT_FORBIDDEN = 'FORBIDDEN';
    const EVENT_DB_ERROR = 'DB_ERROR';
    const EVENT_EXCEPTION = 'EXCEPTION';
    const EVENT_SUSPICIOUS_IP = 'SUSPICIOUS_IP';
    const EVENT_SUSPICIOUS_USER = 'SUSPICIOUS_USER';
    const EVENT_LOGOUT = 'LOGOUT';
    const EVENT_PASSWORD_CHANGE = 'PASSWORD_CHANGE';
    const EVENT_TOKEN_CREATED = 'TOKEN_CREATED';
    
    const SEVERITY_INFO = 'INFO';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_CRITICAL = 'CRITICAL';
    
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAIL = 'FAIL';
    
    public static function initRequestId() {
        if (self::$requestId === null) {
            self::$requestId = self::generateUUID();
            header('X-Request-ID: ' . self::$requestId);
        }
        return self::$requestId;
    }
    
    public static function getRequestId() {
        if (self::$requestId === null) {
            self::initRequestId();
        }
        return self::$requestId;
    }
    
    private static function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    
    public static function log($eventType, $severity, $context = []) {
        $requestId = self::getRequestId();
        $timestamp = gmdate('Y-m-d H:i:s');
        
        $context = self::sanitizeContext($context);
        
        $logEntry = [
            'timestamp' => $timestamp,
            'event_type' => $eventType,
            'user_id' => $context['user_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? SecurityHelpers::getClientIp(),
            'user_agent' => $context['user_agent'] ?? SecurityHelpers::getUserAgent(),
            'endpoint' => $context['endpoint'] ?? self::getCurrentEndpoint(),
            'http_method' => $context['http_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'),
            'request_id' => $requestId,
            'status' => $context['status'] ?? self::STATUS_SUCCESS,
            'severity' => $severity,
            'metadata' => isset($context['metadata']) ? json_encode($context['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
        ];
        
        $dbSuccess = self::writeToDatabase($logEntry);
        
        if (!$dbSuccess) {
            self::writeToFile($logEntry);
        }
        
        if ($severity === self::SEVERITY_CRITICAL || $severity === self::SEVERITY_WARNING) {
            self::writeToFile($logEntry);
        }
        
        return $logEntry['request_id'];
    }
    
    private static function sanitizeContext($context) {
        $sanitized = $context;
        
        if (isset($sanitized['password'])) {
            unset($sanitized['password']);
        }
        
        if (isset($sanitized['token'])) {
            $token = $sanitized['token'];
            if (strlen($token) > 16) {
                $sanitized['token'] = substr($token, 0, 8) . '...' . substr($token, -8);
            }
            unset($sanitized['token']);
            if (!isset($sanitized['token_hash'])) {
                $sanitized['token_hash'] = substr(hash('sha256', $token), 0, 16);
            }
        }
        
        if (isset($sanitized['token_hash']) && strlen($sanitized['token_hash']) > 32) {
            $sanitized['token_hash'] = substr($sanitized['token_hash'], 0, 16) . '...';
        }
        
        if (isset($sanitized['error']) && is_object($sanitized['error'])) {
            $sanitized['error'] = [
                'message' => $sanitized['error']->getMessage(),
                'code' => $sanitized['error']->getCode(),
                'file' => basename($sanitized['error']->getFile()),
                'line' => $sanitized['error']->getLine()
            ];
        }
        
        if (isset($sanitized['metadata']) && is_array($sanitized['metadata'])) {
            $sanitized['metadata'] = self::sanitizeContext($sanitized['metadata']);
        }
        
        return $sanitized;
    }
    
    private static function getCurrentEndpoint() {
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $endpoint = parse_url($endpoint, PHP_URL_PATH);
        return substr($endpoint, 0, 255) ?: '/';
    }
    
    private static function writeToDatabase($logEntry) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO audit_logs (
                    timestamp, event_type, user_id, ip_address, user_agent,
                    endpoint, http_method, request_id, status, severity, metadata
                ) VALUES (
                    :timestamp, :event_type, :user_id, :ip_address, :user_agent,
                    :endpoint, :http_method, :request_id, :status, :severity, :metadata
                )
            ");
            
            $stmt->execute([
                'timestamp' => $logEntry['timestamp'],
                'event_type' => $logEntry['event_type'],
                'user_id' => $logEntry['user_id'],
                'ip_address' => $logEntry['ip_address'],
                'user_agent' => $logEntry['user_agent'],
                'endpoint' => $logEntry['endpoint'],
                'http_method' => $logEntry['http_method'],
                'request_id' => $logEntry['request_id'],
                'status' => $logEntry['status'],
                'severity' => $logEntry['severity'],
                'metadata' => $logEntry['metadata']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("AuditLogger DB write failed: " . $e->getMessage());
            return false;
        }
    }
    
    private static function writeToFile($logEntry) {
        $logDir = dirname(self::FILE_LOG_PATH);
        
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        if (file_exists(self::FILE_LOG_PATH) && filesize(self::FILE_LOG_PATH) > self::MAX_FILE_SIZE) {
            self::rotateLogFile();
        }
        
        $logLine = sprintf(
            "[%s] [%s] [%s] [%s] %s | IP:%s | UA:%s | Endpoint:%s | Method:%s | RequestID:%s | UserID:%s | Status:%s | Metadata:%s\n",
            $logEntry['timestamp'],
            $logEntry['severity'],
            $logEntry['event_type'],
            $logEntry['status'],
            $logEntry['event_type'],
            $logEntry['ip_address'],
            substr($logEntry['user_agent'] ?? 'N/A', 0, 100),
            $logEntry['endpoint'],
            $logEntry['http_method'],
            $logEntry['request_id'],
            $logEntry['user_id'] ?? 'NULL',
            $logEntry['status'],
            $logEntry['metadata'] ?? '{}'
        );
        
        @file_put_contents(self::FILE_LOG_PATH, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    private static function rotateLogFile() {
        $backupPath = self::FILE_LOG_PATH . '.' . date('Y-m-d_His');
        
        if (file_exists(self::FILE_LOG_PATH)) {
            @rename(self::FILE_LOG_PATH, $backupPath);
        }
        
        $oldLogs = glob(self::FILE_LOG_PATH . '.*');
        usort($oldLogs, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        while (count($oldLogs) > 10) {
            @unlink(array_shift($oldLogs));
        }
    }
    
    public static function loginSuccess($userId, $username, $ipAddress = null, $userAgent = null) {
        return self::log(self::EVENT_LOGIN_SUCCESS, self::SEVERITY_INFO, [
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => self::STATUS_SUCCESS,
            'metadata' => ['username' => $username]
        ]);
    }
    
    public static function loginFail($username, $ipAddress = null, $userAgent = null, $reason = null) {
        return self::log(self::EVENT_LOGIN_FAIL, self::SEVERITY_WARNING, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => self::STATUS_FAIL,
            'metadata' => [
                'username' => $username,
                'reason' => $reason ?? 'invalid_credentials'
            ]
        ]);
    }
    
    public static function invalidCredentials($username, $ipAddress = null, $userAgent = null) {
        return self::log(self::EVENT_INVALID_CREDENTIALS, self::SEVERITY_WARNING, [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => self::STATUS_FAIL,
            'metadata' => ['username' => $username]
        ]);
    }
    
    public static function tokenExpired($userId = null, $tokenHash = null) {
        return self::log(self::EVENT_TOKEN_EXPIRED, self::SEVERITY_WARNING, [
            'user_id' => $userId,
            'status' => self::STATUS_FAIL,
            'metadata' => ['token_hash' => $tokenHash ? substr($tokenHash, 0, 16) . '...' : null]
        ]);
    }
    
    public static function tokenRevoked($userId, $reason, $tokenHash = null) {
        return self::log(self::EVENT_TOKEN_REVOKED, self::SEVERITY_WARNING, [
            'user_id' => $userId,
            'status' => self::STATUS_FAIL,
            'metadata' => [
                'reason' => $reason,
                'token_hash' => $tokenHash ? substr($tokenHash, 0, 16) . '...' : null
            ]
        ]);
    }
    
    public static function tokenReplay($userId, $ipAddress, $userAgent, $reason) {
        return self::log(self::EVENT_TOKEN_REPLAY, self::SEVERITY_CRITICAL, [
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => self::STATUS_FAIL,
            'metadata' => ['reason' => $reason]
        ]);
    }
    
    public static function rateLimitHit($identifier, $identifierType, $endpoint) {
        return self::log(self::EVENT_RATE_LIMIT_HIT, self::SEVERITY_WARNING, [
            'ip_address' => $identifierType === 'ip' ? $identifier : SecurityHelpers::getClientIp(),
            'status' => self::STATUS_FAIL,
            'metadata' => [
                'identifier_type' => $identifierType,
                'endpoint' => $endpoint,
                'identifier_truncated' => substr($identifier, 0, 16) . '...'
            ]
        ]);
    }
    
    public static function unauthorized($userId = null, $reason = null) {
        return self::log(self::EVENT_UNAUTHORIZED, self::SEVERITY_WARNING, [
            'user_id' => $userId,
            'status' => self::STATUS_FAIL,
            'metadata' => ['reason' => $reason ?? 'unauthorized_access']
        ]);
    }
    
    public static function forbidden($userId, $requiredRole = null) {
        return self::log(self::EVENT_FORBIDDEN, self::SEVERITY_WARNING, [
            'user_id' => $userId,
            'status' => self::STATUS_FAIL,
            'metadata' => ['required_role' => $requiredRole]
        ]);
    }
    
    public static function dbError($error, $query = null) {
        return self::log(self::EVENT_DB_ERROR, self::SEVERITY_CRITICAL, [
            'status' => self::STATUS_FAIL,
            'metadata' => [
                'error' => $error,
                'query_truncated' => $query ? substr($query, 0, 100) : null
            ]
        ]);
    }
    
    public static function exception($exception, $context = []) {
        return self::log(self::EVENT_EXCEPTION, self::SEVERITY_CRITICAL, [
            'status' => self::STATUS_FAIL,
            'metadata' => array_merge([
                'error' => $exception
            ], $context)
        ]);
    }
    
    public static function suspiciousIp($ipAddress, $eventCount, $timeWindow) {
        return self::log(self::EVENT_SUSPICIOUS_IP, self::SEVERITY_CRITICAL, [
            'ip_address' => $ipAddress,
            'status' => self::STATUS_FAIL,
            'metadata' => [
                'event_count' => $eventCount,
                'time_window_seconds' => $timeWindow
            ]
        ]);
    }
    
    public static function suspiciousUser($userId, $reason) {
        return self::log(self::EVENT_SUSPICIOUS_USER, self::SEVERITY_CRITICAL, [
            'user_id' => $userId,
            'status' => self::STATUS_FAIL,
            'metadata' => ['reason' => $reason]
        ]);
    }
    
    public static function logout($userId, $tokenHash = null) {
        return self::log(self::EVENT_LOGOUT, self::SEVERITY_INFO, [
            'user_id' => $userId,
            'status' => self::STATUS_SUCCESS,
            'metadata' => ['token_hash' => $tokenHash ? substr($tokenHash, 0, 16) . '...' : null]
        ]);
    }
    
    public static function passwordChange($userId) {
        return self::log(self::EVENT_PASSWORD_CHANGE, self::SEVERITY_WARNING, [
            'user_id' => $userId,
            'status' => self::STATUS_SUCCESS,
            'metadata' => []
        ]);
    }
    
    public static function tokenCreated($userId, $tokenHash) {
        return self::log(self::EVENT_TOKEN_CREATED, self::SEVERITY_INFO, [
            'user_id' => $userId,
            'status' => self::STATUS_SUCCESS,
            'metadata' => ['token_hash' => substr($tokenHash, 0, 16) . '...']
        ]);
    }
}
