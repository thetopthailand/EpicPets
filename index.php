<?php
/**
 * Main Frontend Page
 * หน้าหลักสำหรับผู้ใช้งาน Minecraft Webshop
 * 
 * @author Minecraft Webshop Team
 * @version 1.0
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('WEBSHOP_INIT', true);

// Check if files exist before including
if (!file_exists(__DIR__ . '/includes/security.php')) {
    die('Security file not found. Please ensure all files are uploaded correctly.');
}

if (!file_exists(__DIR__ . '/includes/data_manager.php')) {
    die('Data manager file not found. Please ensure all files are uploaded correctly.');
}

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/data_manager.php';

try {
    // Initialize components
    $security = Security::getInstance();
    $data_manager = DataManager::getInstance();

    // Check if system is installed
    $config = $data_manager->getConfig();
    if (!isset($config['installed']) || !$config['installed']) {
        header('Location: install.php');
        exit;
    }

    // Check maintenance mode
    if (isset($config['maintenance_mode']) && $config['maintenance_mode']) {
        if (file_exists(__DIR__ . '/maintenance.html')) {
            include __DIR__ . '/maintenance.html';
        } else {
            echo '<h1>ระบบปิดปรุงชั่วคราว</h1><p>กรุณาลองใหม่อีกครั้งในภายหลัง</p>';
        }
        exit;
    }

} catch (Exception $e) {
    die('System initialization error: ' . htmlspecialchars($e->getMessage()));
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['ajax']) {
            case 'server_status':
                handleServerStatus();
                break;
            case 'user_info':
                handleUserInfo();
                break;
            case 'purchase':
                handlePurchase();
                break;
            default:
                throw new Exception('Invalid AJAX action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Get products safely
try {
    $products = $data_manager->getProducts();
    $enabled_products = array_filter($products, function($product) {
        return isset($product['enabled']) && $product['enabled'];
    });
} catch (Exception $e) {
    $products = [];
    $enabled_products = [];
    error_log("Error loading products: " . $e->getMessage());
}

/**
 * Handle server status AJAX request
 */
function handleServerStatus() {
    global $config;
    
    $server_ip = isset($config['server_ip']) ? $config['server_ip'] : 'localhost';
    $server_port = isset($config['server_port']) ? $config['server_port'] : 25565;
    
    // Get status from mcsrvstat.us API
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
    
    $api_response = @file_get_contents($api_url, false, $context);
    
    if ($api_response !== false) {
        $api_data = json_decode($api_response, true);
        
        echo json_encode([
            'online' => isset($api_data['online']) ? $api_data['online'] : false,
            'players' => [
                'online' => isset($api_data['players']['online']) ? $api_data['players']['online'] : 0,
                'max' => isset($api_data['players']['max']) ? $api_data['players']['max'] : 0
            ],
            'version' => isset($api_data['version']) ? $api_data['version'] : 'Unknown',
            'motd' => isset($api_data['motd']['clean']) ? $api_data['motd']['clean'] : [],
            'icon' => isset($api_data['icon']) ? $api_data['icon'] : null
        ]);
    } else {
        echo json_encode([
            'online' => false,
            'error' => 'Unable to fetch server status'
        ]);
    }
}

/**
 * Handle user info AJAX request
 */
function handleUserInfo() {
    global $security, $data_manager;
    
    $username = $security->sanitizeInput($_GET['username'] ?? '', 'username');
    
    if (!$security->validateInput($username, 'username')) {
        throw new Exception('Invalid username format');
    }
    
    $user = $data_manager->getUser($username);
    
    if (!$user) {
        // Create new user
        $user = $data_manager->saveUser($username, []);
    }
    
    echo json_encode([
        'username' => $user['username'],
        'points' => isset($user['points']) ? $user['points'] : 0,
        'total_spent' => isset($user['total_spent']) ? $user['total_spent'] : 0,
        'total_purchases' => isset($user['total_purchases']) ? $user['total_purchases'] : 0
    ]);
}

/**
 * Handle purchase AJAX request
 */
function handlePurchase() {
    global $security, $data_manager;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    // Rate limiting
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$security->checkRateLimit('purchase_' . $client_ip, 5, 60)) {
        throw new Exception('Too many purchase attempts. Please wait.');
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = $security->sanitizeInput($input['username'] ?? '', 'username');
    $product_id = $security->sanitizeInput($input['product_id'] ?? '', 'string');
    
    if (!$security->validateInput($username, 'username')) {
        throw new Exception('Invalid username format');
    }
    
    if (empty($product_id)) {
        throw new Exception('Product ID is required');
    }
    
    // Process purchase via RCON API
    $rcon_data = [
        'username' => $username,
        'product_id' => $product_id,
        'csrf_token' => $security->generateCSRFToken()
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($rcon_data)
        ]
    ]);
    
    $response = file_get_contents('rcon.php?action=purchase', false, $context);
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['success']) || !$result['success']) {
        throw new Exception(isset($result['error']) ? $result['error'] : 'Purchase failed');
    }
    
    echo json_encode($result);
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_name'] ?? 'Minecraft Webshop') ?></title>
    <meta name="description" content="ร้านค้าออนไลน์สำหรับเซิร์ฟเวอร์ Minecraft">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⛏️</text></svg>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .server-status {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .server-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .server-details h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .server-ip {
            font-family: 'Courier New', monospace;
            background: #ecf0f1;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-online {
            background: #27ae60;
        }
        
        .status-offline {
            background: #e74c3c;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .user-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .user-input {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .user-input input {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .user-input input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .user-input button {
            padding: 12px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .user-input button:hover {
            background: #2980b9;
        }
        
        .user-info {
            display: none;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .products-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .product-description {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .product-price {
            font-size: 1.2em;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 15px;
        }
        
        .product-buy {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .product-buy:hover {
            background: #229954;
        }
        
        .product-buy:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .admin-link:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .server-info {
                flex-direction: column;
                text-align: center;
            }
            
            .user-input {
                flex-direction: column;
            }
            
            .user-input input,
            .user-input button {
                min-width: 100%;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⛏️ <?= htmlspecialchars($config['site_name'] ?? 'Minecraft Webshop') ?></h1>
            <p class="subtitle">ร้านค้าออนไลน์สำหรับเซิร์ฟเวอร์ Minecraft</p>
        </div>
        
        <!-- Server Status -->
        <div class="server-status">
            <div class="server-info">
                <div class="server-details">
                    <h3><?= htmlspecialchars($config['server_name'] ?? 'My Server') ?></h3>
                    <div class="server-ip"><?= htmlspecialchars($config['server_ip'] ?? 'localhost') ?><?= (isset($config['server_port']) && $config['server_port'] != 25565) ? ':' . $config['server_port'] : '' ?></div>
                </div>
                <div class="status-indicator">
                    <div class="status-dot status-offline" id="status-dot"></div>
                    <div id="status-text">กำลังตรวจสอบ...</div>
                </div>
            </div>
        </div>
        
        <!-- User Section -->
        <div class="user-section">
            <h2>ข้อมูลผู้เล่น</h2>
            <div class="user-input">
                <input type="text" id="username" placeholder="กรอกชื่อผู้เล่น Minecraft" maxlength="16">
                <button onclick="loadUserInfo()">ตรวจสอบ</button>
            </div>
            
            <div class="alert alert-error" id="user-error"></div>
            <div class="alert alert-success" id="user-success"></div>
            
            <div class="user-info" id="user-info">
                <div class="user-stats">
                    <div class="stat-item">
                        <div class="stat-value" id="user-points">0</div>
                        <div class="stat-label">พ้อยท์คงเหลือ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="user-spent">0</div>
                        <div class="stat-label">พ้อยท์ที่ใช้ไป</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="user-purchases">0</div>
                        <div class="stat-label">จำนวนการซื้อ</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products Section -->
        <div class="products-section">
            <h2>สินค้าในร้าน</h2>
            
            <div class="alert alert-info" id="purchase-info">
                กรุณากรอกชื่อผู้เล่นก่อนทำการซื้อสินค้า
            </div>
            <div class="alert alert-error" id="purchase-error"></div>
            <div class="alert alert-success" id="purchase-success"></div>
            
            <div class="products-grid">
                <?php if (empty($enabled_products)): ?>
                    <div style="text-align: center; color: #7f8c8d; grid-column: 1 / -1;">
                        <p>ยังไม่มีสินค้าในร้าน</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($enabled_products as $product): ?>
                        <div class="product-card">
                            <div class="product-name"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></div>
                            <div class="product-description"><?= htmlspecialchars($product['description'] ?? '') ?></div>
                            <div class="product-price"><?= number_format($product['price'] ?? 0) ?> พ้อยท์</div>
                            <button class="product-buy" onclick="purchaseProduct('<?= htmlspecialchars($product['id'] ?? '') ?>')" disabled>
                                ซื้อสินค้า
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2024 <?= htmlspecialchars($config['site_name'] ?? 'Minecraft Webshop') ?> - Powered by Minecraft Webshop</p>
        </div>
    </div>
    
    <!-- Admin Link -->
    <a href="backend.php" class="admin-link">🔧 จัดการระบบ</a>
    
    <script>
        let currentUser = null;
        
        // Load server status on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadServerStatus();
            setInterval(loadServerStatus, 30000); // Update every 30 seconds
        });
        
        // Load server status
        function loadServerStatus() {
            fetch('?ajax=server_status')
                .then(response => response.json())
                .then(data => {
                    const statusDot = document.getElementById('status-dot');
                    const statusText = document.getElementById('status-text');
                    
                    if (data.online) {
                        statusDot.className = 'status-dot status-online';
                        statusText.textContent = `ออนไลน์ - ${data.players.online}/${data.players.max} ผู้เล่น`;
                    } else {
                        statusDot.className = 'status-dot status-offline';
                        statusText.textContent = 'ออฟไลน์';
                    }
                })
                .catch(error => {
                    console.error('Error loading server status:', error);
                    document.getElementById('status-text').textContent = 'ไม่สามารถตรวจสอบได้';
                });
        }
        
        // Load user information
        function loadUserInfo() {
            const username = document.getElementById('username').value.trim();
            
            if (!username) {
                showAlert('user-error', 'กรุณากรอกชื่อผู้เล่น');
                return;
            }
            
            if (!/^[a-zA-Z0-9_]{3,16}$/.test(username)) {
                showAlert('user-error', 'ชื่อผู้เล่นไม่ถูกต้อง (3-16 ตัวอักษร, a-z, A-Z, 0-9, _)');
                return;
            }
            
            hideAlerts();
            
            fetch(`?ajax=user_info&username=${encodeURIComponent(username)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    currentUser = data;
                    
                    // Update user info display
                    document.getElementById('user-points').textContent = data.points.toLocaleString();
                    document.getElementById('user-spent').textContent = data.total_spent.toLocaleString();
                    document.getElementById('user-purchases').textContent = data.total_purchases.toLocaleString();
                    
                    // Show user info
                    document.getElementById('user-info').style.display = 'block';
                    
                    // Enable purchase buttons
                    const buyButtons = document.querySelectorAll('.product-buy');
                    buyButtons.forEach(button => {
                        button.disabled = false;
                    });
                    
                    // Hide purchase info alert
                    document.getElementById('purchase-info').style.display = 'none';
                    
                    showAlert('user-success', `โหลดข้อมูลผู้เล่น ${data.username} สำเร็จ`);
                })
                .catch(error => {
                    console.error('Error loading user info:', error);
                    showAlert('user-error', error.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }
        
        // Purchase product
        function purchaseProduct(productId) {
            if (!currentUser) {
                showAlert('purchase-error', 'กรุณาตรวจสอบข้อมูลผู้เล่นก่อน');
                return;
            }
            
            const button = event.target;
            const originalText = button.textContent;
            
            button.disabled = true;
            button.innerHTML = '<span class="loading"></span> กำลังซื้อ...';
            
            hideAlerts();
            
            fetch('?ajax=purchase', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: currentUser.username,
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Update user points
                if (data.remaining_points !== undefined) {
                    currentUser.points = data.remaining_points;
                    document.getElementById('user-points').textContent = data.remaining_points.toLocaleString();
                }
                
                // Show success message
                let message = 'ซื้อสินค้าสำเร็จ!';
                if (data.rcon_result) {
                    if (data.rcon_result.success) {
                        message += ' สินค้าได้ถูกส่งให้ในเกมแล้ว';
                    } else {
                        message += ' แต่ไม่สามารถส่งสินค้าในเกมได้ กรุณาติดต่อผู้ดูแล';
                    }
                }
                
                showAlert('purchase-success', message);
                
                // Reload user info to get updated stats
                setTimeout(() => {
                    loadUserInfo();
                }, 1000);
                
            })
            .catch(error => {
                console.error('Error purchasing product:', error);
                showAlert('purchase-error', error.message || 'เกิดข้อผิดพลาดในการซื้อสินค้า');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
        }
        
        // Show alert
        function showAlert(alertId, message) {
            const alert = document.getElementById(alertId);
            alert.textContent = message;
            alert.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
        
        // Hide all alerts
        function hideAlerts() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }
        
        // Enter key support for username input
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadUserInfo();
            }
        });
    </script>
</body>
</html>

