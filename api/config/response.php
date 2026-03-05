<?php
/**
 * Response Helper
 * 
 * Purpose: Standardized JSON response format for all API endpoints
 * 
 * Location: api/config/response.php
 */

class ResponseHelper {
    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send error response
     * 
     * @param string $message User-friendly error message
     * @param int $statusCode HTTP status code (400, 401, 403, 500, etc.)
     * @param array|null $errors Validation errors (optional)
     * @param string|null $errorCode Debug-safe error code (only if APP_DEBUG=true)
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = null, $errorCode = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        // Add debug-safe error code if APP_DEBUG is enabled
        if ($errorCode !== null && (defined('APP_DEBUG') && APP_DEBUG)) {
            $response['error_code'] = $errorCode;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
}
