<?php
require_once __DIR__ . '/../config/security.php';

class RequestValidator {
    public static function validateJsonInput() {
        SecurityConfig::validatePayloadSize();
        
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Request body is required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (strlen($rawInput) > SecurityConfig::MAX_PAYLOAD_SIZE) {
            http_response_code(413);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Payload too large'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON format'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!is_array($input)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request format. Expected JSON object.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        return SecurityConfig::sanitizeInput($input);
    }
    
    public static function validateRequired($input, $requiredFields) {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
