<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/reputation_engine.php';
require_once __DIR__ . '/alert_engine.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/api_rate_limit.php';

AuditLogger::initRequestId();

CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $ipAddress = SecurityHelpers::getClientIp();
    
    if (AlertEngine::isIpBlocked($ipAddress)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    SecurityConfig::validatePayloadSize();
    ApiRateLimit::check();
}
