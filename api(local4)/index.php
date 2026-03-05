<?php
header('Content-Type: application/json');

// require_once __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

http_response_code(200);

$response = [
    'api_name' => 'Laboratorium Poltekkes Kemenkes Banten API',
    'status' => 'ok',
    'server_time' => date('c'),
    'environment' => 'production',
    'available_modules' => [
        'auth',
        'admin',
        'plp',
        'user'
    ],
    'message' => 'REST API entry point. Access specific endpoints via /api/{module}/{endpoint}.php'
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
