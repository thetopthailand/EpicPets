<?php
/**
 * RCON Interface
 * API endpoint สำหรับการจัดการ RCON commands
 * 
 * @author Minecraft Webshop Team
 * @version 1.0
 */

define('WEBSHOP_INIT', true);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/data_manager.php';
require_once __DIR__ . '/includes/rcon_handler.php';

// Initialize components
$security = Security::getInstance();
$data_manager = DataManager::getInstance();

// Check if system is installed
$config = $data_manager->getConfig();
if (!$config['installed']) {
    http_response_code(503);
    die(json_encode(['error' => 'System not installed']));
}

// Check maintenance mode
if ($config['maintenance_mode']) {
    http_response_code(503);
    die(json_encode(['error' => 'System under maintenance']));
}

// Set JSON response header
header('Content-Type: application/json');

// Handle CORS if needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!$security->checkRateLimit('rcon_' . $client_ip, 10, 60)) { // 10 requests per minute
    http_response_code(429);
    die(json_encode(['error' => 'Rate limit exceeded']));
}

try {
    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Validate CSRF token for POST requests
    if ($method === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$security->verifyCSRFToken($csrf_token)) {
            throw new Exception('Invalid CSRF token');
        }
    }
    
    switch ($action) {
        case 'test':
            handleTestConnection();
            break;
            
        case 'execute':
            handleExecuteCommand();
            break;
            
        case 'purchase':
            handlePurchaseCommand();
            break;
            
        case 'status':
            handleServerStatus();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $security->logSecurityEvent('rcon_error', [
        'action' => $action ?? 'unknown',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Test RCON connection
 */
function handleTestConnection() {
    global $security, $data_manager;
    
    // Admin authentication required
    if (!$security->isAuthenticated()) {
        throw new Exception('Authentication required');
    }
    
    $config = $data_manager->getConfig();
    
    if (empty($config['rcon_password'])) {
        throw new Exception('RCON not configured');
    }
    
    $rcon = RCONFactory::create($config);
    $result = $rcon->testConnection();
    
    echo json_encode($result);
}

/**
 * Execute RCON command (Admin only)
 */
function handleExecuteCommand() {
    global $security, $data_manager;
    
    // Admin authentication required
    if (!$security->isAuthenticated()) {
        throw new Exception('Authentication required');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $command = $security->sanitizeInput($_POST['command'] ?? '', 'string');
    
    if (empty($command)) {
        throw new Exception('Command is required');
    }
    
    $config = $data_manager->getConfig();
    
    if (empty($config['rcon_password'])) {
        throw new Exception('RCON not configured');
    }
    
    $rcon = RCONFactory::create($config);
    
    try {
        $rcon->connect();
        $response = $rcon->executeCommand($command);
        $rcon->disconnect();
        
        echo json_encode([
            'success' => true,
            'response' => $response
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle purchase command execution
 */
function handlePurchaseCommand() {
    global $security, $data_manager;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    // Get and validate input
    $username = $security->sanitizeInput($_POST['username'] ?? '', 'username');
    $product_id = $security->sanitizeInput($_POST['product_id'] ?? '', 'string');
    
    if (!$security->validateInput($username, 'username')) {
        throw new Exception('Invalid username');
    }
    
    if (empty($product_id)) {
        throw new Exception('Product ID is required');
    }
    
    // Check if user exists, create if not
    $user = $data_manager->getUser($username);
    if (!$user) {
        $user = $data_manager->saveUser($username, []);
    }
    
    // Process purchase
    $purchase_result = $data_manager->processPurchase($username, $product_id);
    
    // Execute RCON command if configured
    $config = $data_manager->getConfig();
    $rcon_result = null;
    
    if (!empty($config['rcon_password']) && !empty($purchase_result['command'])) {
        try {
            $rcon = RCONFactory::create($config);
            $rcon->connect();
            
            $rcon_response = $rcon->processWebshopCommand(
                $purchase_result['command'],
                $username
            );
            
            $rcon->disconnect();
            
            $rcon_result = [
                'success' => true,
                'response' => $rcon_response
            ];
            
            // Update transaction with RCON result
            $data_manager->logTransaction([
                'type' => 'rcon_executed',
                'transaction_id' => $purchase_result['transaction_id'],
                'username' => $username,
                'command' => $purchase_result['command'],
                'rcon_response' => $rcon_response
            ]);
            
        } catch (Exception $e) {
            $rcon_result = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
            // Log RCON failure
            $security->logSecurityEvent('rcon_purchase_failed', [
                'username' => $username,
                'product_id' => $product_id,
                'transaction_id' => $purchase_result['transaction_id'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'transaction_id' => $purchase_result['transaction_id'],
        'remaining_points' => $purchase_result['remaining_points'],
        'rcon_result' => $rcon_result
    ]);
}

/**
 * Get server status via API and RCON
 */
function handleServerStatus() {
    global $data_manager;
    
    $config = $data_manager->getConfig();
    $server_ip = $config['server_ip'] ?? 'localhost';
    $server_port = $config['server_port'] ?? 25565;
    
    $status = [
        'api_status' => null,
        'rcon_status' => null,
        'timestamp' => time()
    ];
    
    // Get status from mcsrvstat.us API
    try {
        $api_url = "https://api.mcsrvstat.us/3/{$server_ip}";
        if ($server_port !== 25565) {
            $api_url .= ":{$server_port}";
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'Minecraft-Webshop/1.0'
            ]
        ]);
        
        $api_response = file_get_contents($api_url, false, $context);
        
        if ($api_response !== false) {
            $api_data = json_decode($api_response, true);
            
            $status['api_status'] = [
                'online' => $api_data['online'] ?? false,
                'players' => [
                    'online' => $api_data['players']['online'] ?? 0,
                    'max' => $api_data['players']['max'] ?? 0
                ],
                'version' => $api_data['version'] ?? 'Unknown',
                'motd' => $api_data['motd']['clean'] ?? [],
                'icon' => $api_data['icon'] ?? null
            ];
        }
        
    } catch (Exception $e) {
        $status['api_status'] = [
            'error' => $e->getMessage()
        ];
    }
    
    // Get status via RCON if configured
    if (!empty($config['rcon_password'])) {
        try {
            $rcon = RCONFactory::create($config);
            $rcon_status = $rcon->getServerStatus();
            $status['rcon_status'] = $rcon_status;
            
        } catch (Exception $e) {
            $status['rcon_status'] = [
                'online' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode($status);
}

/**
 * Get CSRF token for frontend
 */
function handleGetCSRFToken() {
    global $security;
    
    echo json_encode([
        'csrf_token' => $security->generateCSRFToken()
    ]);
}
?>

