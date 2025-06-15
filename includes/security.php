<?php
/**
 * Security Functions Library
 * ระบบความปลอดภัยหลักสำหรับ Minecraft Webshop
 * 
 * @author Minecraft Webshop Security Team
 * @version 1.0
 */

// Prevent direct access
if (!defined('WEBSHOP_INIT')) {
    die('Direct access not allowed');
}

class Security {
    
    private static $instance = null;
    private $encryption_key;
    private $session_timeout = 3600; // 1 hour
    
    private function __construct() {
        $this->encryption_key = $this->getEncryptionKey();
        $this->initializeSession();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate or retrieve encryption key
     */
    private function getEncryptionKey() {
        $key_file = __DIR__ . '/../config/encryption.key';
        
        if (!file_exists($key_file)) {
            if (!is_dir(dirname($key_file))) {
                mkdir(dirname($key_file), 0700, true);
            }
            $key = bin2hex(random_bytes(32));
            file_put_contents($key_file, $key);
            chmod($key_file, 0600);
        } else {
            $key = file_get_contents($key_file);
        }
        
        return $key;
    }
    
    /**
     * Initialize secure session
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'username':
                return preg_replace('/[^a-zA-Z0-9_]/', '', trim($input));
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'ip':
                return filter_var(trim($input), FILTER_VALIDATE_IP) ? trim($input) : '';
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     */
    public function validateInput($input, $type, $options = []) {
        switch ($type) {
            case 'username':
                return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $input);
            case 'password':
                $min_length = $options['min_length'] ?? 8;
                return strlen($input) >= $min_length && 
                       preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $input);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            case 'ip':
                return filter_var($input, FILTER_VALIDATE_IP) !== false;
            case 'port':
                $port = (int) $input;
                return $port >= 1 && $port <= 65535;
            case 'points':
                return is_numeric($input) && $input >= 0;
            default:
                return !empty($input);
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            serialize($data), 
            'AES-256-CBC', 
            hex2bin($this->encryption_key), 
            0, 
            $iv
        );
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($encrypted_data) {
        $data = base64_decode($encrypted_data);
        if ($data === false) return false;
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted, 
            'AES-256-CBC', 
            hex2bin($this->encryption_key), 
            0, 
            $iv
        );
        
        return $decrypted !== false ? unserialize($decrypted) : false;
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Rate limiting
     */
    public function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
        $rate_limit_file = __DIR__ . '/../data/rate_limits.php';
        
        if (!file_exists($rate_limit_file)) {
            file_put_contents($rate_limit_file, "<?php\n// Rate limit data\nreturn [];\n");
        }
        
        $rate_limits = include $rate_limit_file;
        $current_time = time();
        
        // Clean old entries
        foreach ($rate_limits as $key => $data) {
            if ($current_time - $data['first_attempt'] > $time_window) {
                unset($rate_limits[$key]);
            }
        }
        
        // Check current identifier
        if (!isset($rate_limits[$identifier])) {
            $rate_limits[$identifier] = [
                'attempts' => 1,
                'first_attempt' => $current_time
            ];
        } else {
            $rate_limits[$identifier]['attempts']++;
        }
        
        // Save updated data
        file_put_contents(
            $rate_limit_file, 
            "<?php\n// Rate limit data\nreturn " . var_export($rate_limits, true) . ";\n"
        );
        
        return $rate_limits[$identifier]['attempts'] <= $max_attempts;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event, $details = []) {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0700, true);
        }
        
        $log_file = $log_dir . '/security_' . date('Y-m-d') . '.log';
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event,
            'details' => $details
        ];
        
        file_put_contents(
            $log_file, 
            json_encode($log_entry) . "\n", 
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['authenticated']) && 
               $_SESSION['authenticated'] === true &&
               isset($_SESSION['last_activity']) &&
               (time() - $_SESSION['last_activity']) < $this->session_timeout;
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        // This will be implemented with the data manager
        return false;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * Generate secure random string
     */
    public function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowed_types = [], $max_size = 1048576) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return false;
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return false;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return false;
            default:
                return false;
        }
        
        if ($file['size'] > $max_size) {
            return false;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Clean old log files
     */
    public function cleanOldLogs($days = 30) {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) return;
        
        $files = glob($log_dir . '/*.log');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

// Initialize security instance
$security = Security::getInstance();
?>

