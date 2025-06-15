<?php
/**
 * Backend Administration Panel
 * ระบบจัดการแบ็กเอนด์สำหรับ Minecraft Webshop
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
    header('Location: install.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $security->sanitizeInput($_POST['username'] ?? '', 'username');
    $password = $_POST['password'] ?? '';
    
    if ($username === $config['admin_username'] && 
        $security->verifyPassword($password, $config['admin_password'])) {
        
        $_SESSION['authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        
        $security->logSecurityEvent('admin_login_success', ['username' => $username]);
        header('Location: backend.php');
        exit;
    } else {
        $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        $security->logSecurityEvent('admin_login_failed', ['username' => $username]);
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $security->logout();
    header('Location: backend.php');
    exit;
}

// Check authentication
if (!$security->isAuthenticated()) {
    include 'login_form.php';
    exit;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
        
        switch ($_GET['ajax']) {
            case 'add_points':
                handleAddPoints();
                break;
            case 'save_product':
                handleSaveProduct();
                break;
            case 'delete_product':
                handleDeleteProduct();
                break;
            case 'test_rcon':
                handleTestRcon();
                break;
            case 'save_config':
                handleSaveConfig();
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

// Get data for display
$users = $data_manager->readDataFile('users.php');
$products = $data_manager->getProducts();
$transactions = $data_manager->getTransactions(null, 50);
$stats = $data_manager->getStatistics();

/**
 * Handle add points AJAX request
 */
function handleAddPoints() {
    global $security, $data_manager;
    
    $username = $security->sanitizeInput($_POST['username'] ?? '', 'username');
    $points = $security->sanitizeInput($_POST['points'] ?? 0, 'int');
    
    if (!$security->validateInput($username, 'username')) {
        throw new Exception('Invalid username format');
    }
    
    if (!$security->validateInput($points, 'points') || $points <= 0) {
        throw new Exception('Points must be positive');
    }
    
    $new_balance = $data_manager->addPoints($username, $points);
    
    echo json_encode([
        'success' => true,
        'new_balance' => $new_balance,
        'message' => "เพิ่ม {$points} พ้อยท์ให้ {$username} สำเร็จ"
    ]);
}

/**
 * Handle save product AJAX request
 */
function handleSaveProduct() {
    global $security, $data_manager;
    
    $product_id = $security->sanitizeInput($_POST['product_id'] ?? '', 'string');
    $name = $security->sanitizeInput($_POST['name'] ?? '', 'string');
    $description = $security->sanitizeInput($_POST['description'] ?? '', 'string');
    $price = $security->sanitizeInput($_POST['price'] ?? 0, 'int');
    $command = $security->sanitizeInput($_POST['command'] ?? '', 'string');
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    
    if (empty($product_id) || empty($name) || $price < 0) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
    }
    
    $product_data = [
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'command' => $command,
        'enabled' => $enabled
    ];
    
    $product = $data_manager->saveProduct($product_id, $product_data);
    
    echo json_encode([
        'success' => true,
        'product' => $product,
        'message' => 'บันทึกสินค้าสำเร็จ'
    ]);
}

/**
 * Handle delete product AJAX request
 */
function handleDeleteProduct() {
    global $data_manager;
    
    $product_id = $_POST['product_id'] ?? '';
    
    if (empty($product_id)) {
        throw new Exception('Product ID is required');
    }
    
    $data_manager->deleteProduct($product_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบสินค้าสำเร็จ'
    ]);
}

/**
 * Handle test RCON AJAX request
 */
function handleTestRcon() {
    global $config;
    
    $rcon = RCONFactory::create($config);
    $result = $rcon->testConnection();
    
    echo json_encode($result);
}

/**
 * Handle save config AJAX request
 */
function handleSaveConfig() {
    global $security, $data_manager;
    
    $config_data = [];
    
    $fields = ['site_name', 'server_name', 'server_ip', 'server_port', 'rcon_ip', 'rcon_port', 'rcon_password', 'max_points_per_user'];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $config_data[$field] = $security->sanitizeInput($_POST[$field], 'string');
        }
    }
    
    if (isset($_POST['maintenance_mode'])) {
        $config_data['maintenance_mode'] = $_POST['maintenance_mode'] === '1';
    }
    
    $data_manager->saveConfig($config_data);
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการตั้งค่าสำเร็จ'
    ]);
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการ - <?= htmlspecialchars($config['site_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab { padding: 15px 20px; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab.active { border-bottom-color: #3498db; background: #f8f9fa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 2em; font-weight: bold; }
        .stat-label { margin-top: 5px; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🔧 ระบบจัดการ</h1>
            <p>ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['admin_username']) ?> | <a href="?action=logout" style="color: #ecf0f1;">ออกจากระบบ</a></p>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">ผู้ใช้ทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
                <div class="stat-label">สินค้าทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_transactions']) ?></div>
                <div class="stat-label">ธุรกรรมทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_points_distributed']) ?></div>
                <div class="stat-label">พ้อยท์ที่แจกทั้งหมด</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('users')">จัดการผู้ใช้</div>
            <div class="tab" onclick="showTab('products')">จัดการสินค้า</div>
            <div class="tab" onclick="showTab('transactions')">ประวัติธุรกรรม</div>
            <div class="tab" onclick="showTab('settings')">ตั้งค่าระบบ</div>
        </div>

        <!-- Users Tab -->
        <div id="users-tab" class="tab-content active">
            <div class="card">
                <h3>เพิ่มพ้อยท์ให้ผู้ใช้</h3>
                <form id="add-points-form">
                    <div style="display: flex; gap: 15px; align-items: end;">
                        <div class="form-group" style="flex: 1;">
                            <label>ชื่อผู้เล่น</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>จำนวนพ้อยท์</label>
                            <input type="number" name="points" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-success">เพิ่มพ้อยท์</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>รายชื่อผู้ใช้</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ชื่อผู้เล่น</th>
                            <th>พ้อยท์คงเหลือ</th>
                            <th>พ้อยท์ที่ใช้ไป</th>
                            <th>จำนวนการซื้อ</th>
                            <th>วันที่สร้าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= number_format($user['points']) ?></td>
                            <td><?= number_format($user['total_spent']) ?></td>
                            <td><?= number_format($user['total_purchases']) ?></td>
                            <td><?= date('d/m/Y H:i', $user['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Products Tab -->
        <div id="products-tab" class="tab-content">
            <div class="card">
                <h3>เพิ่ม/แก้ไขสินค้า</h3>
                <form id="product-form">
                    <div class="form-group">
                        <label>ID สินค้า</label>
                        <input type="text" name="product_id" required>
                    </div>
                    <div class="form-group">
                        <label>ชื่อสินค้า</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>คำอธิบาย</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>ราคา (พ้อยท์)</label>
                        <input type="number" name="price" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>คำสั่ง RCON</label>
                        <input type="text" name="command" placeholder="give {username} diamond 1">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="enabled" value="1" checked> เปิดใช้งาน
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success">บันทึกสินค้า</button>
                </form>
            </div>

            <div class="card">
                <h3>รายการสินค้า</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อสินค้า</th>
                            <th>ราคา</th>
                            <th>สถานะ</th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['id']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= number_format($product['price']) ?></td>
                            <td><?= $product['enabled'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="editProduct('<?= htmlspecialchars($product['id']) ?>')">แก้ไข</button>
                                <button class="btn btn-danger" onclick="deleteProduct('<?= htmlspecialchars($product['id']) ?>')">ลบ</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transactions Tab -->
        <div id="transactions-tab" class="tab-content">
            <div class="card">
                <h3>ประวัติธุรกรรม</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>ประเภท</th>
                            <th>ผู้เล่น</th>
                            <th>รายละเอียด</th>
                            <th>จำนวน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i:s', $transaction['timestamp']) ?></td>
                            <td><?= htmlspecialchars($transaction['type']) ?></td>
                            <td><?= htmlspecialchars($transaction['username'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($transaction['product_name'] ?? $transaction['details'] ?? '-') ?></td>
                            <td><?= number_format($transaction['amount'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="card">
                <h3>การตั้งค่าทั่วไป</h3>
                <form id="settings-form">
                    <div class="form-group">
                        <label>ชื่อเว็บไซต์</label>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($config['site_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>ชื่อเซิร์ฟเวอร์</label>
                        <input type="text" name="server_name" value="<?= htmlspecialchars($config['server_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>IP เซิร์ฟเวอร์</label>
                        <input type="text" name="server_ip" value="<?= htmlspecialchars($config['server_ip']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Port เซิร์ฟเวอร์</label>
                        <input type="number" name="server_port" value="<?= htmlspecialchars($config['server_port']) ?>">
                    </div>
                    <div class="form-group">
                        <label>RCON IP</label>
                        <input type="text" name="rcon_ip" value="<?= htmlspecialchars($config['rcon_ip']) ?>">
                    </div>
                    <div class="form-group">
                        <label>RCON Port</label>
                        <input type="number" name="rcon_port" value="<?= htmlspecialchars($config['rcon_port']) ?>">
                    </div>
                    <div class="form-group">
                        <label>RCON Password</label>
                        <input type="password" name="rcon_password" placeholder="กรอกรหัสผ่านใหม่หากต้องการเปลี่ยน">
                    </div>
                    <div class="form-group">
                        <label>พ้อยท์สูงสุดต่อผู้ใช้</label>
                        <input type="number" name="max_points_per_user" value="<?= htmlspecialchars($config['max_points_per_user']) ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="maintenance_mode" value="1" <?= $config['maintenance_mode'] ? 'checked' : '' ?>> โหมดปิดปรุง
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success">บันทึกการตั้งค่า</button>
                    <button type="button" class="btn btn-primary" onclick="testRcon()">ทดสอบ RCON</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?= $security->generateCSRFToken() ?>';
        
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Add points form
        document.getElementById('add-points-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('csrf_token', csrfToken);
            
            fetch('?ajax=add_points', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('เกิดข้อผิดพลาด: ' + data.error);
                } else {
                    alert(data.message);
                    this.reset();
                    location.reload();
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            });
        });
        
        // Product form
        document.getElementById('product-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('csrf_token', csrfToken);
            
            fetch('?ajax=save_product', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('เกิดข้อผิดพลาด: ' + data.error);
                } else {
                    alert(data.message);
                    this.reset();
                    location.reload();
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            });
        });
        
        // Settings form
        document.getElementById('settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('csrf_token', csrfToken);
            
            fetch('?ajax=save_config', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('เกิดข้อผิดพลาด: ' + data.error);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            });
        });
        
        function editProduct(productId) {
            // Implementation for editing product
            const product = <?= json_encode($products) ?>[productId];
            if (product) {
                document.querySelector('[name="product_id"]').value = product.id;
                document.querySelector('[name="name"]').value = product.name;
                document.querySelector('[name="description"]').value = product.description;
                document.querySelector('[name="price"]').value = product.price;
                document.querySelector('[name="command"]').value = product.command;
                document.querySelector('[name="enabled"]').checked = product.enabled;
            }
        }
        
        function deleteProduct(productId) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบสินค้านี้?')) {
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('csrf_token', csrfToken);
                
                fetch('?ajax=delete_product', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('เกิดข้อผิดพลาด: ' + data.error);
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                })
                .catch(error => {
                    alert('เกิดข้อผิดพลาด: ' + error.message);
                });
            }
        }
        
        function testRcon() {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            
            fetch('?ajax=test_rcon', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('การเชื่อมต่อ RCON สำเร็จ!\nResponse: ' + data.response);
                } else {
                    alert('การเชื่อมต่อ RCON ล้มเหลว: ' + data.error);
                }
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
            });
        }
    </script>
</body>
</html>

<?php
// Login form (shown when not authenticated)
function showLoginForm() {
    global $login_error;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบจัดการ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; margin: 20px; }
        .login-header { background: #2c3e50; color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .login-body { padding: 40px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { width: 100%; background: #3498db; color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔧 ระบบจัดการ</h1>
            <p>เข้าสู่ระบบเพื่อจัดการเว็บไซต์</p>
        </div>
        <div class="login-body">
            <?php if (isset($login_error)): ?>
                <div class="alert-error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>ชื่อผู้ใช้</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>รหัสผ่าน</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// Show login form if not authenticated
if (!$security->isAuthenticated()) {
    showLoginForm();
}
?>
