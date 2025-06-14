<?php
/**
 * Main API Router
 * จัดการ routing และ middleware สำหรับ API endpoints
 */

// Error reporting และ headers
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดในโปรดักชั่น

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../services/RconService.php';

// Initialize components
$security = new SecurityMiddleware();
$auth = new AuthMiddleware();
$db = Database::getInstance();

// Apply security middleware
try {
    $security->checkSecurity();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$pathInfo = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if exists
$basePath = '/webshop/backend/api';
if (strpos($pathInfo, $basePath) === 0) {
    $pathInfo = substr($pathInfo, strlen($basePath));
}

// Split path into segments
$pathSegments = array_filter(explode('/', $pathInfo));
$endpoint = $pathSegments[0] ?? '';
$action = $pathSegments[1] ?? '';
$id = $pathSegments[2] ?? '';

// Get request body
$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true) ?? [];

// Merge with POST data
$requestData = array_merge($_POST, $requestData);

/**
 * API Response Helper
 */
function sendResponse($data, $statusCode = 200, $message = null) {
    http_response_code($statusCode);
    
    $response = [];
    
    if ($statusCode >= 200 && $statusCode < 300) {
        $response['success'] = true;
        $response['data'] = $data;
        if ($message) $response['message'] = $message;
    } else {
        $response['success'] = false;
        $response['error'] = $data;
        if ($message) $response['message'] = $message;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Error Handler
 */
function handleError($message, $statusCode = 400) {
    sendResponse($message, $statusCode);
}

// Set error handler
set_exception_handler(function($e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    handleError("Internal server error", 500);
});

// Route handling
try {
    switch ($endpoint) {
        case 'auth':
            require_once __DIR__ . '/endpoints/auth.php';
            break;
            
        case 'shop':
            require_once __DIR__ . '/endpoints/shop.php';
            break;
            
        case 'user':
            require_once __DIR__ . '/endpoints/user.php';
            break;
            
        case 'admin':
            require_once __DIR__ . '/endpoints/admin.php';
            break;
            
        case 'rcon':
            require_once __DIR__ . '/endpoints/rcon.php';
            break;
            
        case 'health':
            // Health check endpoint
            $health = [
                'status' => 'ok',
                'timestamp' => time(),
                'version' => '1.0.0',
                'database' => 'connected'
            ];
            
            // Test database connection
            try {
                $db->fetchOne("SELECT 1");
            } catch (Exception $e) {
                $health['database'] = 'error';
                $health['status'] = 'error';
            }
            
            // Test RCON connection
            try {
                $rcon = new RconService();
                $rconStatus = $rcon->checkConnection();
                $health['rcon'] = $rconStatus['connected'] ? 'connected' : 'error';
                if (!$rconStatus['connected']) {
                    $health['status'] = 'warning';
                }
            } catch (Exception $e) {
                $health['rcon'] = 'error';
                $health['status'] = 'warning';
            }
            
            sendResponse($health);
            break;
            
        case 'docs':
            // API Documentation
            $docs = [
                'title' => 'Minecraft Webshop API',
                'version' => '1.0.0',
                'description' => 'RESTful API for Minecraft Webshop with RCON integration',
                'endpoints' => [
                    'auth' => [
                        'POST /auth/login' => 'User login',
                        'POST /auth/register' => 'User registration',
                        'POST /auth/logout' => 'User logout',
                        'POST /auth/refresh' => 'Refresh token',
                        'POST /auth/change-password' => 'Change password'
                    ],
                    'shop' => [
                        'GET /shop/items' => 'Get shop items',
                        'GET /shop/items/{id}' => 'Get specific item',
                        'GET /shop/categories' => 'Get categories',
                        'POST /shop/purchase' => 'Purchase item',
                        'GET /shop/cart' => 'Get shopping cart',
                        'POST /shop/cart/add' => 'Add to cart',
                        'DELETE /shop/cart/{id}' => 'Remove from cart'
                    ],
                    'user' => [
                        'GET /user/profile' => 'Get user profile',
                        'PUT /user/profile' => 'Update profile',
                        'GET /user/transactions' => 'Get transaction history',
                        'GET /user/points' => 'Get points balance',
                        'POST /user/points/add' => 'Add points (admin only)'
                    ],
                    'admin' => [
                        'GET /admin/users' => 'List users',
                        'GET /admin/transactions' => 'List transactions',
                        'GET /admin/stats' => 'Get statistics',
                        'POST /admin/items' => 'Create item',
                        'PUT /admin/items/{id}' => 'Update item',
                        'DELETE /admin/items/{id}' => 'Delete item'
                    ],
                    'rcon' => [
                        'POST /rcon/command' => 'Execute RCON command',
                        'GET /rcon/status' => 'Get RCON status',
                        'GET /rcon/queue' => 'Get command queue',
                        'GET /rcon/players' => 'Get online players'
                    ]
                ],
                'authentication' => [
                    'type' => 'Bearer Token (JWT)',
                    'header' => 'Authorization: Bearer {token}'
                ],
                'rate_limits' => [
                    'login' => '5 requests per 5 minutes',
                    'purchase' => '10 requests per minute',
                    'default' => '100 requests per minute'
                ]
            ];
            
            sendResponse($docs);
            break;
            
        default:
            handleError("Endpoint not found", 404);
    }
    
} catch (Exception $e) {
    error_log("API Exception: " . $e->getMessage());
    handleError("Internal server error", 500);
}

/**
 * Utility Functions
 */

/**
 * Validate required fields
 */
function validateRequired($data, $required) {
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        handleError("Missing required fields: " . implode(', ', $missing), 400);
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate pagination parameters
 */
function validatePagination($page = 1, $limit = 20) {
    $page = max(1, (int)$page);
    $limit = max(1, min(100, (int)$limit)); // Max 100 items per page
    
    return [$page, $limit];
}

/**
 * Calculate pagination offset
 */
function getPaginationOffset($page, $limit) {
    return ($page - 1) * $limit;
}

/**
 * Create pagination response
 */
function createPaginationResponse($data, $total, $page, $limit) {
    $totalPages = ceil($total / $limit);
    
    return [
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
}

/**
 * Log API request
 */
function logApiRequest($endpoint, $method, $userId = null, $responseCode = 200) {
    global $db;
    
    try {
        $db->execute(
            "INSERT INTO api_logs (endpoint, method, user_id, ip_address, user_agent, response_code, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $endpoint,
                $method,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $responseCode
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log API request: " . $e->getMessage());
    }
}

/**
 * Check if user has permission
 */
function checkPermission($requiredRole, $currentUser) {
    $roleHierarchy = ['user' => 1, 'moderator' => 2, 'admin' => 3];
    
    $currentLevel = $roleHierarchy[$currentUser['role']] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;
    
    return $currentLevel >= $requiredLevel;
}

/**
 * Format currency (points)
 */
function formatPoints($points) {
    return number_format($points) . ' พ้อย';
}

/**
 * Generate transaction hash
 */
function generateTransactionHash() {
    return hash('sha256', uniqid() . microtime() . random_bytes(16));
}

/**
 * Validate Minecraft username
 */
function validateMinecraftUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username);
}

/**
 * Clean expired data
 */
function cleanupExpiredData() {
    global $db;
    
    try {
        // Clean expired tokens
        $db->execute("DELETE FROM user_tokens WHERE expires_at < NOW()");
        
        // Clean old rate limit data
        $db->execute("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Clean old API logs
        $db->execute("DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
    } catch (Exception $e) {
        error_log("Cleanup failed: " . $e->getMessage());
    }
}

// Run cleanup occasionally (1% chance)
if (rand(1, 100) === 1) {
    cleanupExpiredData();
}
?>

