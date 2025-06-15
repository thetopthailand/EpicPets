<?php
/**
 * Installation Wizard
 * ระบบติดตั้งเริ่มต้นสำหรับ Minecraft Webshop
 * 
 * @author Minecraft Webshop Team
 * @version 1.0
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('WEBSHOP_INIT', true);

// Check if required files exist
if (!file_exists(__DIR__ . '/includes/security.php')) {
    die('Error: includes/security.php not found. Please upload all files.');
}

if (!file_exists(__DIR__ . '/includes/data_manager.php')) {
    die('Error: includes/data_manager.php not found. Please upload all files.');
}

if (!file_exists(__DIR__ . '/includes/rcon_handler.php')) {
    die('Error: includes/rcon_handler.php not found. Please upload all files.');
}

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/data_manager.php';
require_once __DIR__ . '/includes/rcon_handler.php';

try {
    // Initialize components
    $security = Security::getInstance();
    $data_manager = DataManager::getInstance();

    // Check if already installed
    $config = $data_manager->getConfig();
    if (isset($config['installed']) && $config['installed']) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    die('Initialization error: ' . htmlspecialchars($e->getMessage()));
}

// Handle installation steps
$step = (int) ($_GET['step'] ?? 1);
$max_steps = 5;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        switch ($step) {
            case 1:
                handleStep1();
                break;
            case 2:
                handleStep2();
                break;
            case 3:
                handleStep3();
                break;
            case 4:
                handleStep4();
                break;
            case 5:
                handleStep5();
                break;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Handle Step 1: Environment Check
 */
function handleStep1() {
    global $step;
    
    // Environment checks passed, proceed to step 2
    $step = 2;
}

/**
 * Handle Step 2: Basic Configuration
 */
function handleStep2() {
    global $security, $data_manager, $step;
    
    $site_name = $security->sanitizeInput($_POST['site_name'] ?? '', 'string');
    $server_name = $security->sanitizeInput($_POST['server_name'] ?? '', 'string');
    $server_ip = $security->sanitizeInput($_POST['server_ip'] ?? '', 'string');
    $server_port = $security->sanitizeInput($_POST['server_port'] ?? 25565, 'int');
    
    if (empty($site_name) || empty($server_name) || empty($server_ip)) {
        throw new Exception('All fields are required');
    }
    
    if (!$security->validateInput($server_port, 'port')) {
        throw new Exception('Invalid server port');
    }
    
    // Save basic configuration
    $data_manager->saveConfig([
        'site_name' => $site_name,
        'server_name' => $server_name,
        'server_ip' => $server_ip,
        'server_port' => $server_port
    ]);
    
    $step = 3;
}

/**
 * Handle Step 3: RCON Configuration
 */
function handleStep3() {
    global $security, $data_manager, $step;
    
    $rcon_ip = $security->sanitizeInput($_POST['rcon_ip'] ?? '', 'string');
    $rcon_port = $security->sanitizeInput($_POST['rcon_port'] ?? 25575, 'int');
    $rcon_password = $_POST['rcon_password'] ?? '';
    
    if (empty($rcon_ip) || empty($rcon_password)) {
        throw new Exception('RCON IP and password are required');
    }
    
    if (!$security->validateInput($rcon_port, 'port')) {
        throw new Exception('Invalid RCON port');
    }
    
    // Test RCON connection
    try {
        $rcon = new RCONHandler($rcon_ip, $rcon_port, $rcon_password, 5);
        $test_result = $rcon->testConnection();
        
        if (!$test_result['success']) {
            throw new Exception('RCON connection failed: ' . $test_result['error']);
        }
        
    } catch (Exception $e) {
        throw new Exception('RCON test failed: ' . $e->getMessage());
    }
    
    // Save RCON configuration
    $data_manager->saveConfig([
        'rcon_ip' => $rcon_ip,
        'rcon_port' => $rcon_port,
        'rcon_password' => $rcon_password
    ]);
    
    $step = 4;
}

/**
 * Handle Step 4: Admin Account
 */
function handleStep4() {
    global $security, $data_manager, $step;
    
    $admin_username = $security->sanitizeInput($_POST['admin_username'] ?? '', 'username');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
    
    if (!$security->validateInput($admin_username, 'username')) {
        throw new Exception('Invalid admin username format');
    }
    
    if (!$security->validateInput($admin_password, 'password')) {
        throw new Exception('Password must be at least 8 characters with uppercase, lowercase, number and special character');
    }
    
    if ($admin_password !== $admin_password_confirm) {
        throw new Exception('Passwords do not match');
    }
    
    // Save admin configuration
    $data_manager->saveConfig([
        'admin_username' => $admin_username,
        'admin_password' => $admin_password // Will be hashed in saveConfig
    ]);
    
    $step = 5;
}

/**
 * Handle Step 5: Finalize Installation
 */
function handleStep5() {
    global $data_manager, $step;
    
    // Create sample products
    $sample_products = [
        'diamond_sword' => [
            'id' => 'diamond_sword',
            'name' => 'Diamond Sword',
            'description' => 'A sharp diamond sword',
            'price' => 500,
            'command' => 'give {username} diamond_sword 1',
            'enabled' => true
        ],
        'golden_apple' => [
            'id' => 'golden_apple',
            'name' => 'Golden Apple',
            'description' => 'A magical golden apple',
            'price' => 100,
            'command' => 'give {username} golden_apple 1',
            'enabled' => true
        ]
    ];
    
    foreach ($sample_products as $product_id => $product_data) {
        $data_manager->saveProduct($product_id, $product_data);
    }
    
    // Mark as installed
    $data_manager->saveConfig([
        'installed' => true,
        'installation_date' => time()
    ]);
    
    // Create initial backup
    try {
        $data_manager->createBackup();
    } catch (Exception $e) {
        // Backup failed but continue
        error_log("Backup creation failed: " . $e->getMessage());
    }
    
    // Redirect to main page
    header('Location: index.php?installed=1');
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้ง Minecraft Webshop</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .install-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .install-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            background: rgba(255,255,255,0.2);
            height: 6px;
            border-radius: 3px;
            margin-top: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: #3498db;
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .install-body {
            padding: 40px;
        }
        
        .step-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .requirements {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .requirement:last-child {
            margin-bottom: 0;
        }
        
        .requirement-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .requirement-ok {
            background: #27ae60;
            color: white;
        }
        
        .requirement-error {
            background: #e74c3c;
            color: white;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .minecraft-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="minecraft-icon">⛏️</div>
            <h1>Minecraft Webshop</h1>
            <p>ระบบติดตั้งเริ่มต้น</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= ($step / $max_steps) * 100 ?>%"></div>
            </div>
            <p style="margin-top: 10px; font-size: 14px;">ขั้นตอนที่ <?= $step ?> จาก <?= $max_steps ?></p>
        </div>
        
        <div class="install-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <strong>เกิดข้อผิดพลาด:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <h2 class="step-title">ตรวจสอบระบบ</h2>
                
                <div class="requirements">
                    <?php
                    $requirements = [
                        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                        'OpenSSL Extension' => extension_loaded('openssl'),
                        'JSON Extension' => extension_loaded('json'),
                        'cURL Extension' => extension_loaded('curl'),
                        'Data Directory Writable' => is_writable(__DIR__ . '/data') || is_writable(__DIR__),
                        'Config Directory Writable' => is_writable(__DIR__ . '/config') || is_writable(__DIR__)
                    ];
                    
                    $all_ok = true;
                    foreach ($requirements as $name => $status):
                        if (!$status) $all_ok = false;
                    ?>
                        <div class="requirement">
                            <div class="requirement-status <?= $status ? 'requirement-ok' : 'requirement-error' ?>">
                                <?= $status ? '✓' : '✗' ?>
                            </div>
                            <span><?= $name ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($all_ok): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $security->generateCSRFToken() ?>">
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">เริ่มติดตั้ง</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-error">
                        กรุณาแก้ไขปัญหาที่พบก่อนดำเนินการต่อ
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step === 2): ?>
                <h2 class="step-title">การตั้งค่าพื้นฐาน</h2>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $security->generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="site_name">ชื่อเว็บไซต์</label>
                        <input type="text" id="site_name" name="site_name" required 
                               value="<?= htmlspecialchars($_POST['site_name'] ?? 'Minecraft Webshop') ?>">
                        <small>ชื่อที่จะแสดงบนเว็บไซต์</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="server_name">ชื่อเซิร์ฟเวอร์</label>
                        <input type="text" id="server_name" name="server_name" required 
                               value="<?= htmlspecialchars($_POST['server_name'] ?? 'My Minecraft Server') ?>">
                        <small>ชื่อเซิร์ฟเวอร์ Minecraft ของคุณ</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="server_ip">IP เซิร์ฟเวอร์</label>
                        <input type="text" id="server_ip" name="server_ip" required 
                               value="<?= htmlspecialchars($_POST['server_ip'] ?? 'localhost') ?>">
                        <small>IP Address หรือ Domain ของเซิร์ฟเวอร์</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="server_port">Port เซิร์ฟเวอร์</label>
                        <input type="number" id="server_port" name="server_port" required 
                               value="<?= htmlspecialchars($_POST['server_port'] ?? '25565') ?>" min="1" max="65535">
                        <small>Port ของเซิร์ฟเวอร์ Minecraft (ปกติคือ 25565)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">ถัดไป</button>
                    </div>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h2 class="step-title">การตั้งค่า RCON</h2>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $security->generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="rcon_ip">RCON IP</label>
                        <input type="text" id="rcon_ip" name="rcon_ip" required 
                               value="<?= htmlspecialchars($_POST['rcon_ip'] ?? 'localhost') ?>">
                        <small>IP Address สำหรับเชื่อมต่อ RCON</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="rcon_port">RCON Port</label>
                        <input type="number" id="rcon_port" name="rcon_port" required 
                               value="<?= htmlspecialchars($_POST['rcon_port'] ?? '25575') ?>" min="1" max="65535">
                        <small>Port สำหรับ RCON (ปกติคือ 25575)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="rcon_password">RCON Password</label>
                        <input type="password" id="rcon_password" name="rcon_password" required>
                        <small>รหัสผ่าน RCON ที่ตั้งไว้ในเซิร์ฟเวอร์</small>
                    </div>
                    
                    <div class="alert alert-success">
                        <strong>หมายเหตุ:</strong> ระบบจะทดสอบการเชื่อมต่อ RCON ก่อนดำเนินการต่อ
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">ทดสอบและถัดไป</button>
                    </div>
                </form>
                
            <?php elseif ($step === 4): ?>
                <h2 class="step-title">สร้างบัญชีผู้ดูแล</h2>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $security->generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="admin_username">ชื่อผู้ใช้ผู้ดูแล</label>
                        <input type="text" id="admin_username" name="admin_username" required 
                               value="<?= htmlspecialchars($_POST['admin_username'] ?? 'admin') ?>">
                        <small>ชื่อผู้ใช้สำหรับเข้าสู่ระบบจัดการ (3-16 ตัวอักษร, a-z, A-Z, 0-9, _)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">รหัสผ่าน</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                        <small>รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร ประกอบด้วย A-Z, a-z, 0-9, และอักขระพิเศษ</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password_confirm">ยืนยันรหัสผ่าน</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                        <small>กรอกรหัสผ่านอีกครั้งเพื่อยืนยัน</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn">สร้างบัญชี</button>
                    </div>
                </form>
                
            <?php elseif ($step === 5): ?>
                <h2 class="step-title">เสร็จสิ้นการติดตั้ง</h2>
                
                <div class="alert alert-success">
                    <strong>ติดตั้งสำเร็จ!</strong> ระบบพร้อมใช้งานแล้ว
                </div>
                
                <p style="text-align: center; margin-bottom: 20px;">
                    ระบบได้สร้างสินค้าตัวอย่างและการตั้งค่าเริ่มต้นให้แล้ว<br>
                    คุณสามารถเข้าสู่ระบบจัดการเพื่อปรับแต่งเพิ่มเติม
                </p>
                
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $security->generateCSRFToken() ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">เข้าสู่เว็บไซต์</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

