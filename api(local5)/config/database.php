<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!file_exists(__DIR__ . '/../../logs')) {
    @mkdir(__DIR__ . '/../../logs', 0755, true);
}

ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

date_default_timezone_set('Asia/Jakarta');

class Database {
    private static $instance = null;
    private $connection;
    
    // Database credentials - UPDATE THESE with your actual values
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'u708283844_sisenes';
    private const DB_USER = 'u708283844_sisenes';  // UPDATE THIS
    private const DB_PASS = 'h*S2TmYSrQ';  // UPDATE THIS
    private const DB_CHARSET = 'utf8mb4';
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=" . self::DB_CHARSET;
            // $dsn = "mysql:host=localhost;port=3306;dbname=" . self::DB_NAME . ";charset=" . self::DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5, // Connection timeout in seconds
                PDO::ATTR_PERSISTENT         => false, // Disable persistent connections to avoid connection pool issues
            ];
            
            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            
            // Test connection with a simple query
            $this->connection->exec("SET NAMES " . self::DB_CHARSET);
            $this->connection->exec("SET CHARACTER SET " . self::DB_CHARSET);
            
        } catch (PDOException $e) {
            // Log detailed connection error
            $errorInfo = $e->errorInfo ?? [];
            $logContext = [
                'host' => self::DB_HOST,
                'database' => self::DB_NAME,
                'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'driver_code' => $errorInfo[1] ?? null,
                'driver_message' => $errorInfo[2] ?? null
            ];
            
            error_log("Database connection failed: " . json_encode($logContext, JSON_UNESCAPED_UNICODE));
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
