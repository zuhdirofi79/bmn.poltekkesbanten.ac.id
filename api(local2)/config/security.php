<?php
class SecurityConfig {
    const MAX_PAYLOAD_SIZE = 1048576;
    const ALLOWED_ORIGINS = [
        'https://laboratorium.poltekkesbanten.ac.id',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:5050'
    ];
    
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
    
    public static function validateOrigin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        
        if (!$origin && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return true;
        }
        
        if (!$origin) {
            return true;
        }
        
        foreach (self::ALLOWED_ORIGINS as $allowed) {
            if ($origin === $allowed) {
                return true;
            }
        }
        
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Origin not allowed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function validatePayloadSize() {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        
        if ($contentLength > self::MAX_PAYLOAD_SIZE) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Payload too large'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public static function sanitizeString($input) {
        if (!is_string($input)) {
            return $input;
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if (is_string($input)) {
            return self::sanitizeString($input);
        }
        
        return $input;
    }
}
