<?php
/**
 * Minecraft Webshop Configuration
 * กำหนดค่าต่างๆ สำหรับระบบ Webshop
 */

// Database Configuration (ถ้าต้องการใช้ฐานข้อมูล)
define('DB_HOST', 'localhost');
define('DB_NAME', 'minecraft_webshop');
define('DB_USER', 'root');
define('DB_PASS', '');

// RCON Configuration
define('RCON_HOST', 'localhost');        // IP ของเซิร์ฟเวอร์ Minecraft
define('RCON_PORT', 25575);              // พอร์ต RCON
define('RCON_PASSWORD', 'your_password'); // รหัสผ่าน RCON

// Webshop Settings
define('SITE_NAME', 'Minecraft Webshop');
define('CURRENCY_NAME', 'พ้อย');
define('DEFAULT_POINTS', 100);           // พ้อยเริ่มต้นสำหรับผู้เล่นใหม่

// Security Settings
define('MAX_USERNAME_LENGTH', 16);
define('MIN_USERNAME_LENGTH', 3);
define('ALLOWED_USERNAME_CHARS', '/^[a-zA-Z0-9_]+$/');

// Logging
define('ENABLE_LOGGING', true);
define('LOG_FILE', 'webshop.log');

// Error Reporting (ปิดในโปรดักชั่น)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Database Connection Function
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log Function
 */
function logMessage($message, $level = 'INFO') {
    if (!ENABLE_LOGGING) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Validate Username
 */
function validateUsername($username) {
    if (strlen($username) < MIN_USERNAME_LENGTH || strlen($username) > MAX_USERNAME_LENGTH) {
        return false;
    }
    
    if (!preg_match(ALLOWED_USERNAME_CHARS, $username)) {
        return false;
    }
    
    return true;
}

/**
 * Sanitize Input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>

