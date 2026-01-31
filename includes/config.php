<?php
/**
 * LokAlert - Database Configuration
 * 
 * Configure your database connection settings here.
 * For InfinityFree hosting, update credentials.php with your real values.
 */

// Load credentials from separate file (not committed to git)
$credentialsFile = __DIR__ . '/credentials.php';
if (file_exists($credentialsFile)) {
    require_once $credentialsFile;
}

// Detect environment (production vs development)
$isProduction = !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '']);

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? 0 : 1);
ini_set('log_errors', 1);

// Database Configuration
// For PRODUCTION: Credentials loaded from credentials.php
if ($isProduction) {
    // Production Database (loaded from credentials.php)
    define('DB_HOST', defined('PROD_DB_HOST') ? PROD_DB_HOST : 'localhost');
    define('DB_NAME', defined('PROD_DB_NAME') ? PROD_DB_NAME : 'lokalert_db');
    define('DB_USER', defined('PROD_DB_USER') ? PROD_DB_USER : 'root');
    define('DB_PASS', defined('PROD_DB_PASS') ? PROD_DB_PASS : '');
} else {
    // Local Development Database
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'lokalert_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Site Configuration
define('SITE_URL', $isProduction 
    ? 'https://lokalert.infinityfree.me'     // Your PHP hosting URL
    : 'http://localhost/LokAlert');
define('SITE_NAME', 'LokAlert');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('RELEASES_DIR', __DIR__ . '/../releases/');

// Email Configuration
// For PRODUCTION: Credentials loaded from credentials.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', $isProduction && defined('PROD_SMTP_USER') ? PROD_SMTP_USER : '');
define('SMTP_PASS', $isProduction && defined('PROD_SMTP_PASS') ? PROD_SMTP_PASS : '');
define('SMTP_FROM_EMAIL', 'noreply@lokalert.com');
define('SMTP_FROM_NAME', 'LokAlert');

// EMAIL_ENABLED: true in production to send real emails
define('EMAIL_ENABLED', $isProduction);

// Security Configuration
define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_EXPIRY_MINUTES', 15);
define('RESET_TOKEN_EXPIRY_HOURS', 24);
define('DOWNLOAD_COOLDOWN_MINUTES', 5);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Class
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Auto-migrate database schema - creates tables and adds missing columns
     */
    public function autoMigrate() {
        $db = $this->conn;
        
        // Create apk_versions table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `apk_versions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `version` VARCHAR(20) NOT NULL,
                `filename` VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Add missing columns to apk_versions
        $this->addColumnIfNotExists('apk_versions', 'file_size', 'BIGINT DEFAULT 0');
        $this->addColumnIfNotExists('apk_versions', 'download_url', 'VARCHAR(500) NULL');
        $this->addColumnIfNotExists('apk_versions', 'release_notes', 'TEXT NULL');
        $this->addColumnIfNotExists('apk_versions', 'is_latest', 'TINYINT(1) DEFAULT 0');
        $this->addColumnIfNotExists('apk_versions', 'download_count', 'INT DEFAULT 0');
        $this->addColumnIfNotExists('apk_versions', 'upload_date', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        
        // Create users table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NULL,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Add missing columns to users
        $this->addColumnIfNotExists('users', 'is_admin', 'TINYINT(1) DEFAULT 0');
        $this->addColumnIfNotExists('users', 'is_verified', 'TINYINT(1) DEFAULT 0');
        $this->addColumnIfNotExists('users', 'verification_code', 'VARCHAR(10) NULL');
        $this->addColumnIfNotExists('users', 'verification_expires', 'DATETIME NULL');
        $this->addColumnIfNotExists('users', 'reset_token', 'VARCHAR(100) NULL');
        $this->addColumnIfNotExists('users', 'reset_expires', 'DATETIME NULL');
        $this->addColumnIfNotExists('users', 'download_count', 'INT DEFAULT 0');
        $this->addColumnIfNotExists('users', 'last_download_at', 'DATETIME NULL');
        
        // Create download_logs table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `download_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `version_id` INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Add missing columns to download_logs
        $this->addColumnIfNotExists('download_logs', 'ip_address', 'VARCHAR(45) NULL');
        $this->addColumnIfNotExists('download_logs', 'user_agent', 'TEXT NULL');
        $this->addColumnIfNotExists('download_logs', 'download_token', 'VARCHAR(64) NULL');
        $this->addColumnIfNotExists('download_logs', 'status', "ENUM('started','completed','failed','cancelled') DEFAULT 'started'");
        $this->addColumnIfNotExists('download_logs', 'file_size', 'BIGINT DEFAULT 0');
        $this->addColumnIfNotExists('download_logs', 'bytes_downloaded', 'BIGINT DEFAULT 0');
        $this->addColumnIfNotExists('download_logs', 'started_at', 'DATETIME NULL');
        $this->addColumnIfNotExists('download_logs', 'completed_at', 'DATETIME NULL');
        
        // Create contact_messages table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `contact_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `subject` VARCHAR(200) NOT NULL,
                `message` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->addColumnIfNotExists('contact_messages', 'is_read', 'TINYINT(1) DEFAULT 0');
        
        // Create email_logs table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `email_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `recipient_email` VARCHAR(100) NOT NULL,
                `email_type` VARCHAR(50) NOT NULL,
                `subject` VARCHAR(200) NULL,
                `status` VARCHAR(20) DEFAULT 'pending',
                `error_message` TEXT NULL,
                `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create default admin user if not exists
        $this->createDefaultAdmin();
    }
    
    /**
     * Create default admin user
     */
    private function createDefaultAdmin() {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                // Create admin user - password is 'lokalert2024'
                $hashedPassword = password_hash('lokalert2024', PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("
                    INSERT INTO users (username, email, password, is_admin, is_verified, created_at) 
                    VALUES ('admin', 'admin', ?, 1, 1, NOW())
                ");
                $stmt->execute([$hashedPassword]);
            }
        } catch (PDOException $e) {
            // Ignore - admin might already exist
        }
    }
    
    /**
     * Add column to table if it doesn't exist
     */
    private function addColumnIfNotExists($table, $column, $definition) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([DB_NAME, $table, $column]);
            $exists = $stmt->fetchColumn() > 0;
            
            if (!$exists) {
                $this->conn->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (PDOException $e) {
            // Ignore errors
        }
    }
}

// Run auto-migration on first database access
$_dbMigrated = false;
function ensureDatabaseMigrated() {
    global $_dbMigrated;
    if (!$_dbMigrated) {
        try {
            Database::getInstance()->autoMigrate();
            $_dbMigrated = true;
        } catch (Exception $e) {
            // Ignore migration errors
        }
    }
}

// Helper Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    // CORS headers - allow requests from GitHub Pages and localhost
    $allowedOrigins = [
        'https://the-sequence.github.io',           // Your GitHub Pages URL
        'https://lokalert.infinityfree.me',         // Your InfinityFree URL
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:5500',                     // VS Code Live Server
        'http://127.0.0.1:5500'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: *');  // Fallback for testing
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Download-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Expose-Headers: X-Download-Token');
    echo json_encode($data);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function isVerified() {
    return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
}

function requireVerified() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
    if (!isVerified()) {
        jsonResponse(['error' => 'Email verification required'], 403);
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }
}

function generateVerificationCode() {
    return str_pad(random_int(0, 999999), VERIFICATION_CODE_LENGTH, '0', STR_PAD_LEFT);
}

function generateResetToken() {
    return bin2hex(random_bytes(32));
}

function generateDownloadToken() {
    return bin2hex(random_bytes(32));
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function canUserDownload($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if user has downloaded in the last DOWNLOAD_COOLDOWN_MINUTES
        $stmt = $db->prepare("
            SELECT last_download_at 
            FROM users 
            WHERE id = ? 
            AND last_download_at IS NOT NULL 
            AND last_download_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$userId, DOWNLOAD_COOLDOWN_MINUTES]);
        
        if ($stmt->fetch()) {
            return false;
        }
        return true;
    } catch (PDOException $e) {
        return true; // Allow on error
    }
}

function getTimeUntilNextDownload($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(last_download_at, INTERVAL ? MINUTE)) as seconds_remaining
            FROM users 
            WHERE id = ? 
            AND last_download_at IS NOT NULL
        ");
        $stmt->execute([DOWNLOAD_COOLDOWN_MINUTES, $userId]);
        $result = $stmt->fetch();
        
        if ($result && $result['seconds_remaining'] > 0) {
            return $result['seconds_remaining'];
        }
        return 0;
    } catch (PDOException $e) {
        return 0;
    }
}
?>
