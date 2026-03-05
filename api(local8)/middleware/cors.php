<?php
require_once __DIR__ . '/../config/security.php';

class CorsMiddleware {
    public static function handle() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $allowedOrigin = null;
        
        if ($origin) {
            foreach (SecurityConfig::ALLOWED_ORIGINS as $allowed) {
                if ($origin === $allowed) {
                    $allowedOrigin = $origin;
                    break;
                }
            }
        }
        
        if ($allowedOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
