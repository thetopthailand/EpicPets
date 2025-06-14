<?php
/**
 * Security Middleware
 * ระบบป้องกันการโจมตีและ AntiHack ที่ครอบคลุม
 */

class SecurityMiddleware {
    private $db;
    private $rateLimiter;
    private $ipWhitelist = [];
    private $ipBlacklist = [];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
        $this->loadSecurityConfig();
    }

    /**
     * โหลดการตั้งค่าความปลอดภัย
     */
    private function loadSecurityConfig() {
        // โหลด IP whitelist และ blacklist จากฐานข้อมูล
        $whitelist = $this->db->fetchAll("SELECT ip_address FROM ip_whitelist WHERE is_active = 1");
        $this->ipWhitelist = array_column($whitelist, 'ip_address');

        $blacklist = $this->db->fetchAll("SELECT ip_address FROM ip_blacklist WHERE is_active = 1");
        $this->ipBlacklist = array_column($blacklist, 'ip_address');
    }

    /**
     * ตรวจสอบความปลอดภัยหลัก
     */
    public function checkSecurity() {
        $this->setSecurityHeaders();
        $this->checkIPRestrictions();
        $this->checkRateLimit();
        $this->detectSuspiciousActivity();
        $this->validateRequest();
    }

    /**
     * ตั้งค่า Security Headers
     */
    private function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // HSTS (HTTP Strict Transport Security)
        if (isset($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }

    /**
     * ตรวจสอบข้อจำกัด IP
     */
    private function checkIPRestrictions() {
        $clientIP = $this->getClientIP();

        // ตรวจสอบ IP blacklist
        if (in_array($clientIP, $this->ipBlacklist)) {
            $this->logSecurityEvent('IP_BLOCKED', "Blocked IP attempted access: {$clientIP}");
            $this->blockAccess('Your IP address has been blocked');
        }

        // ตรวจสอบ IP whitelist (ถ้ามีการตั้งค่า)
        if (!empty($this->ipWhitelist) && !in_array($clientIP, $this->ipWhitelist)) {
            $this->logSecurityEvent('IP_NOT_WHITELISTED', "Non-whitelisted IP attempted access: {$clientIP}");
            $this->blockAccess('Access denied');
        }
    }

    /**
     * ตรวจสอบ Rate Limiting
     */
    private function checkRateLimit() {
        $clientIP = $this->getClientIP();
        $endpoint = $_SERVER['REQUEST_URI'];

        // กำหนด rate limit ตาม endpoint
        $limits = [
            '/api/auth/login' => ['requests' => 5, 'window' => 300], // 5 requests per 5 minutes
            '/api/shop/purchase' => ['requests' => 10, 'window' => 60], // 10 requests per minute
            'default' => ['requests' => 100, 'window' => 60] // 100 requests per minute
        ];

        $limit = $limits[$endpoint] ?? $limits['default'];

        if (!$this->rateLimiter->checkLimit($clientIP, $endpoint, $limit['requests'], $limit['window'])) {
            $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', "Rate limit exceeded for IP: {$clientIP}, Endpoint: {$endpoint}");
            
            // เพิ่ม IP ใน temporary blacklist
            $this->addToTempBlacklist($clientIP, 300); // 5 minutes
            
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
            exit;
        }
    }

    /**
     * ตรวจจับกิจกรรมที่น่าสงสัย
     */
    private function detectSuspiciousActivity() {
        $this->detectSQLInjection();
        $this->detectXSSAttempts();
        $this->detectPathTraversal();
        $this->detectCommandInjection();
        $this->detectBotActivity();
    }

    /**
     * ตรวจจับ SQL Injection
     */
    private function detectSQLInjection() {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\'|\")(\s*)(or|and)(\s*)(\'|\")/i',
            '/(\-\-|\#|\/\*)/i'
        ];

        $input = json_encode($_REQUEST);
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('SQL_INJECTION_ATTEMPT', "SQL injection detected: " . substr($input, 0, 200));
                $this->blockAccess('Malicious request detected');
            }
        }
    }

    /**
     * ตรวจจับ XSS Attempts
     */
    private function detectXSSAttempts() {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>/i'
        ];

        $input = json_encode($_REQUEST);
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('XSS_ATTEMPT', "XSS attempt detected: " . substr($input, 0, 200));
                $this->blockAccess('Malicious request detected');
            }
        }
    }

    /**
     * ตรวจจับ Path Traversal
     */
    private function detectPathTraversal() {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/',
            '/%2e%2e\\\\/',
            '/\.\.\%2f/',
            '/\.\.\%5c/'
        ];

        $input = $_SERVER['REQUEST_URI'] . json_encode($_REQUEST);
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('PATH_TRAVERSAL_ATTEMPT', "Path traversal detected: " . substr($input, 0, 200));
                $this->blockAccess('Malicious request detected');
            }
        }
    }

    /**
     * ตรวจจับ Command Injection
     */
    private function detectCommandInjection() {
        $patterns = [
            '/[;&|`$(){}[\]]/i',
            '/\b(cat|ls|pwd|id|whoami|uname|wget|curl|nc|netcat)\b/i'
        ];

        $input = json_encode($_REQUEST);
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('COMMAND_INJECTION_ATTEMPT', "Command injection detected: " . substr($input, 0, 200));
                $this->blockAccess('Malicious request detected');
            }
        }
    }

    /**
     * ตรวจจับ Bot Activity
     */
    private function detectBotActivity() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // ตรวจสอบ User Agent ที่น่าสงสัย
        $suspiciousAgents = [
            'sqlmap', 'nikto', 'nmap', 'masscan', 'zap', 'burp',
            'python-requests', 'curl', 'wget', 'httperf'
        ];

        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $this->logSecurityEvent('SUSPICIOUS_USER_AGENT', "Suspicious user agent: {$userAgent}");
                $this->addToTempBlacklist($this->getClientIP(), 3600); // 1 hour
                $this->blockAccess('Access denied');
            }
        }

        // ตรวจสอบการขาด User Agent
        if (empty($userAgent)) {
            $this->logSecurityEvent('MISSING_USER_AGENT', "Request without user agent from IP: " . $this->getClientIP());
        }
    }

    /**
     * ตรวจสอบความถูกต้องของ Request
     */
    private function validateRequest() {
        // ตรวจสอบ CSRF Token สำหรับ POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRFToken();
        }

        // ตรวจสอบ Content-Type
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $allowedTypes = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'];
            
            $isValidType = false;
            foreach ($allowedTypes as $type) {
                if (strpos($contentType, $type) === 0) {
                    $isValidType = true;
                    break;
                }
            }

            if (!$isValidType) {
                $this->logSecurityEvent('INVALID_CONTENT_TYPE', "Invalid content type: {$contentType}");
                $this->blockAccess('Invalid request format');
            }
        }
    }

    /**
     * ตรวจสอบ CSRF Token
     */
    private function validateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            $this->logSecurityEvent('CSRF_TOKEN_MISMATCH', "CSRF token validation failed");
            $this->blockAccess('Invalid request token');
        }
    }

    /**
     * สร้าง CSRF Token
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * ได้รับ IP ของ Client
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * เพิ่ม IP ใน Temporary Blacklist
     */
    private function addToTempBlacklist($ip, $duration) {
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        
        $this->db->execute(
            "INSERT INTO ip_blacklist (ip_address, reason, expires_at, is_active, created_at) 
             VALUES (?, 'Temporary block', ?, 1, NOW()) 
             ON DUPLICATE KEY UPDATE expires_at = ?, updated_at = NOW()",
            [$ip, $expiresAt, $expiresAt]
        );
    }

    /**
     * บันทึก Security Event
     */
    private function logSecurityEvent($eventType, $description) {
        $this->db->execute(
            "INSERT INTO security_logs (event_type, description, ip_address, user_agent, request_uri, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $eventType,
                $description,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REQUEST_URI'] ?? ''
            ]
        );

        // ส่ง alert หากเป็น event ที่ร้ายแรง
        $criticalEvents = ['SQL_INJECTION_ATTEMPT', 'COMMAND_INJECTION_ATTEMPT', 'PATH_TRAVERSAL_ATTEMPT'];
        if (in_array($eventType, $criticalEvents)) {
            $this->sendSecurityAlert($eventType, $description);
        }
    }

    /**
     * ส่ง Security Alert
     */
    private function sendSecurityAlert($eventType, $description) {
        // ส่งอีเมลหรือ notification ไปยังผู้ดูแลระบบ
        $alertData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'description' => $description,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ];

        // บันทึกใน log file สำหรับการตรวจสอบ
        error_log("SECURITY ALERT: " . json_encode($alertData));

        // TODO: ส่งอีเมลหรือ webhook notification
    }

    /**
     * บล็อกการเข้าถึง
     */
    private function blockAccess($message = 'Access denied') {
        http_response_code(403);
        
        // ส่ง response ในรูปแบบ JSON หรือ HTML ตามความเหมาะสม
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            echo "<html><body><h1>403 Forbidden</h1><p>{$message}</p></body></html>";
        }
        
        exit;
    }

    /**
     * ทำความสะอาด Input
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        // ลบ null bytes
        $input = str_replace(chr(0), '', $input);
        
        // ลบ control characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // HTML encode
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return trim($input);
    }

    /**
     * ตรวจสอบและทำความสะอาด expired blacklist entries
     */
    public function cleanupExpiredBlacklist() {
        $this->db->execute(
            "UPDATE ip_blacklist SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );
    }
}

/**
 * Input Validator Class
 */
class InputValidator {
    /**
     * ตรวจสอบ Minecraft username
     */
    public static function validateMinecraftUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username);
    }

    /**
     * ตรวจสอบอีเมล
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * ตรวจสอบรหัสผ่าน
     */
    public static function validatePassword($password) {
        // อย่างน้อย 8 ตัวอักษร, มีตัวพิมพ์เล็ก, ตัวพิมพ์ใหญ่, ตัวเลข
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }

    /**
     * ตรวจสอบจำนวนเต็มบวก
     */
    public static function validatePositiveInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    /**
     * ตรวจสอบ UUID
     */
    public static function validateUUID($uuid) {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }
}
?>

