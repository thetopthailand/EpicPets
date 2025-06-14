<?php
/**
 * Authentication Middleware
 * ระบบ JWT Authentication และ Session Management ที่ปลอดภัย
 */

require_once __DIR__ . '/../config/database.php';

class AuthMiddleware {
    private $db;
    private $secretKey;
    private $algorithm = 'HS256';
    private $tokenExpiry = 3600; // 1 hour

    public function __construct() {
        $this->db = Database::getInstance();
        $this->secretKey = $_ENV['JWT_SECRET'] ?? $this->generateSecretKey();
    }

    /**
     * Generate secure secret key
     */
    private function generateSecretKey() {
        $key = bin2hex(random_bytes(32));
        // ในการใช้งานจริงควรเก็บ key นี้ในไฟล์ .env
        error_log("Generated JWT Secret Key: " . $key);
        return $key;
    }

    /**
     * Create JWT Token
     */
    public function createToken($userId, $username, $role = 'user') {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode([
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + $this->tokenExpiry,
            'jti' => bin2hex(random_bytes(16)) // Unique token ID
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secretKey, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $token = $base64Header . "." . $base64Payload . "." . $base64Signature;

        // บันทึก token ในฐานข้อมูลเพื่อการจัดการ
        $this->storeToken($userId, $token, time() + $this->tokenExpiry);

        return $token;
    }

    /**
     * Verify JWT Token
     */
    public function verifyToken($token) {
        if (!$token) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, $this->secretKey, true);
        $expectedBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

        if (!hash_equals($expectedBase64Signature, $signature)) {
            return false;
        }

        // Decode payload
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if (!$payloadData) {
            return false;
        }

        // Check expiration
        if ($payloadData['exp'] < time()) {
            $this->revokeToken($token);
            return false;
        }

        // Check if token is revoked
        if ($this->isTokenRevoked($token)) {
            return false;
        }

        return $payloadData;
    }

    /**
     * Store token in database
     */
    private function storeToken($userId, $token, $expiresAt) {
        $this->db->execute(
            "INSERT INTO user_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, FROM_UNIXTIME(?), NOW())",
            [$userId, hash('sha256', $token), $expiresAt]
        );
    }

    /**
     * Check if token is revoked
     */
    private function isTokenRevoked($token) {
        $result = $this->db->fetchOne(
            "SELECT id FROM user_tokens WHERE token_hash = ? AND expires_at > NOW() AND revoked_at IS NULL",
            [hash('sha256', $token)]
        );

        return $result === false;
    }

    /**
     * Revoke token
     */
    public function revokeToken($token) {
        $this->db->execute(
            "UPDATE user_tokens SET revoked_at = NOW() WHERE token_hash = ?",
            [hash('sha256', $token)]
        );
    }

    /**
     * Clean expired tokens
     */
    public function cleanExpiredTokens() {
        $this->db->execute("DELETE FROM user_tokens WHERE expires_at < NOW()");
    }

    /**
     * Authenticate user with username and password
     */
    public function authenticate($username, $password) {
        // Input validation
        if (!$this->validateUsername($username) || !$password) {
            return false;
        }

        // Rate limiting check
        if (!$this->checkRateLimit($username)) {
            throw new Exception("Too many login attempts. Please try again later.");
        }

        $user = $this->db->fetchOne(
            "SELECT id, username, password_hash, role, is_active, failed_attempts, locked_until FROM users WHERE username = ?",
            [$username]
        );

        if (!$user) {
            $this->recordFailedAttempt($username);
            return false;
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new Exception("Account is temporarily locked. Please try again later.");
        }

        // Check if account is active
        if (!$user['is_active']) {
            throw new Exception("Account is disabled.");
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($username, $user['id']);
            return false;
        }

        // Reset failed attempts on successful login
        $this->resetFailedAttempts($user['id']);

        // Update last login
        $this->updateLastLogin($user['id']);

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
    }

    /**
     * Validate username format
     */
    private function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username);
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit($username) {
        $attempts = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM login_attempts 
             WHERE (username = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$username, $_SERVER['REMOTE_ADDR']]
        );

        return $attempts['count'] < 5;
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username, $userId = null) {
        // Record in login_attempts table
        $this->db->execute(
            "INSERT INTO login_attempts (username, user_id, ip_address, user_agent, attempted_at) VALUES (?, ?, ?, ?, NOW())",
            [$username, $userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        if ($userId) {
            // Update failed attempts counter
            $this->db->execute(
                "UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?",
                [$userId]
            );

            // Lock account after 5 failed attempts
            $user = $this->db->fetchOne("SELECT failed_attempts FROM users WHERE id = ?", [$userId]);
            if ($user['failed_attempts'] >= 5) {
                $this->db->execute(
                    "UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?",
                    [$userId]
                );
            }
        }
    }

    /**
     * Reset failed attempts
     */
    private function resetFailedAttempts($userId) {
        $this->db->execute(
            "UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId) {
        $this->db->execute(
            "UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?",
            [$_SERVER['REMOTE_ADDR'], $userId]
        );
    }

    /**
     * Middleware function to protect routes
     */
    public function requireAuth($requiredRole = null) {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        $payload = $this->verifyToken($token);
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        // Check role if required
        if ($requiredRole && $payload['role'] !== $requiredRole && $payload['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }

        // Store user info in global variable for use in other parts of the application
        $GLOBALS['current_user'] = $payload;
        
        return $payload;
    }

    /**
     * Get token from request headers
     */
    private function getTokenFromRequest() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Fallback to GET/POST parameter (less secure)
        return $_GET['token'] ?? $_POST['token'] ?? null;
    }

    /**
     * Create new user account
     */
    public function createUser($username, $password, $email = null) {
        // Validation
        if (!$this->validateUsername($username)) {
            throw new Exception("Invalid username format");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Check if username already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );

        if ($existing) {
            throw new Exception("Username already exists");
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // Insert user
        $this->db->execute(
            "INSERT INTO users (username, password_hash, email, role, is_active, created_at) VALUES (?, ?, ?, 'user', 1, NOW())",
            [$username, $passwordHash, $email]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->db->fetchOne(
            "SELECT password_hash FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }

        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters long");
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_ARGON2ID);

        $this->db->execute(
            "UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?",
            [$newPasswordHash, $userId]
        );

        // Revoke all existing tokens for this user
        $this->db->execute(
            "UPDATE user_tokens SET revoked_at = NOW() WHERE user_id = ?",
            [$userId]
        );
    }
}

/**
 * Session-based Authentication (Alternative to JWT)
 */
class SessionAuth {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            
            session_start();
        }
    }

    public function login($userId, $username, $role) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }

    public function logout() {
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $this->isSessionValid();
    }

    private function isSessionValid() {
        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
}
?>

