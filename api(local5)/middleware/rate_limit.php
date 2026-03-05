<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

class RateLimiter {
    const MAX_ATTEMPTS = 5;
    const WINDOW_MINUTES = 10;
    const BLOCK_MINUTES = 10;
    
    public static function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public static function checkLoginLimit($username) {
        $db = Database::getInstance()->getConnection();
        $ipAddress = self::getClientIp();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT attempts, last_attempt, blocked_until
                FROM login_attempts
                WHERE ip_address = :ip_address
                AND username = :username
                LIMIT 1
            FOR UPDATE
            ");
            
            $stmt->execute([
                'ip_address' => $ipAddress,
                'username' => $username
            ]);
            
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                if ($record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
                    $db->rollBack();
                    
                    $remainingSeconds = strtotime($record['blocked_until']) - time();
                    $remainingMinutes = ceil($remainingSeconds / 60);
                    
                    http_response_code(429);
                    header('Retry-After: ' . $remainingSeconds);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . $remainingMinutes . ' menit.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $windowStart = strtotime('-' . self::WINDOW_MINUTES . ' minutes');
                $lastAttempt = strtotime($record['last_attempt']);
                
                if ($lastAttempt >= $windowStart) {
                    $attempts = (int)$record['attempts'];
                    
                    if ($attempts >= self::MAX_ATTEMPTS) {
                        $blockedUntil = date('Y-m-d H:i:s', strtotime('+' . self::BLOCK_MINUTES . ' minutes'));
                        
                        $stmt = $db->prepare("
                            UPDATE login_attempts
                            SET blocked_until = :blocked_until
                            WHERE ip_address = :ip_address
                            AND username = :username
                        ");
                        $stmt->execute([
                            'blocked_until' => $blockedUntil,
                            'ip_address' => $ipAddress,
                            'username' => $username
                        ]);
                        
                        $db->commit();
                        
                        $remainingSeconds = self::BLOCK_MINUTES * 60;
                        
                        http_response_code(429);
                        header('Retry-After: ' . $remainingSeconds);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . self::BLOCK_MINUTES . ' menit.'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }
            }
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("RateLimiter error: " . $e->getMessage());
            return true;
        }
    }
    
    public static function resetLoginAttempts($username) {
        $db = Database::getInstance()->getConnection();
        $ipAddress = self::getClientIp();
        
        try {
            $stmt = $db->prepare("
                DELETE FROM login_attempts
                WHERE ip_address = :ip_address
                AND username = :username
            ");
            $stmt->execute([
                'ip_address' => $ipAddress,
                'username' => $username
            ]);
        } catch (Exception $e) {
            error_log("RateLimiter reset error: " . $e->getMessage());
        }
    }
    
    public static function recordFailedAttempt($username) {
        $db = Database::getInstance()->getConnection();
        $ipAddress = self::getClientIp();
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT attempts, last_attempt
                FROM login_attempts
                WHERE ip_address = :ip_address
                AND username = :username
                LIMIT 1
            FOR UPDATE
            ");
            
            $stmt->execute([
                'ip_address' => $ipAddress,
                'username' => $username
            ]);
            
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                $windowStart = strtotime('-' . self::WINDOW_MINUTES . ' minutes');
                $lastAttempt = strtotime($record['last_attempt']);
                
                if ($lastAttempt >= $windowStart) {
                    $newAttempts = (int)$record['attempts'] + 1;
                } else {
                    $newAttempts = 1;
                }
                
                if ($newAttempts >= self::MAX_ATTEMPTS) {
                    $blockedUntil = date('Y-m-d H:i:s', strtotime('+' . self::BLOCK_MINUTES . ' minutes'));
                    $stmt = $db->prepare("
                        UPDATE login_attempts
                        SET attempts = :attempts,
                            last_attempt = NOW(),
                            blocked_until = :blocked_until
                        WHERE ip_address = :ip_address
                        AND username = :username
                    ");
                    $stmt->execute([
                        'attempts' => $newAttempts,
                        'blocked_until' => $blockedUntil,
                        'ip_address' => $ipAddress,
                        'username' => $username
                    ]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE login_attempts
                        SET attempts = :attempts,
                            last_attempt = NOW(),
                            blocked_until = NULL
                        WHERE ip_address = :ip_address
                        AND username = :username
                    ");
                    $stmt->execute([
                        'attempts' => $newAttempts,
                        'ip_address' => $ipAddress,
                        'username' => $username
                    ]);
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO login_attempts (ip_address, username, attempts, last_attempt)
                    VALUES (:ip_address, :username, 1, NOW())
                ");
                $stmt->execute([
                    'ip_address' => $ipAddress,
                    'username' => $username
                ]);
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("RateLimiter record error: " . $e->getMessage());
        }
    }
}
