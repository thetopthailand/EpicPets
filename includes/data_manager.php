<?php
/**
 * Data Manager Class
 * จัดการข้อมูลแบบไฟล์สำหรับ Minecraft Webshop
 * 
 * @author Minecraft Webshop Team
 * @version 1.0
 */

// Prevent direct access
if (!defined('WEBSHOP_INIT')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/security.php';

class DataManager {
    
    private static $instance = null;
    private $security;
    private $data_dir;
    private $lock_timeout = 10; // seconds
    
    private function __construct() {
        $this->security = Security::getInstance();
        $this->data_dir = __DIR__ . '/../data';
        
        // Create data directory if not exists
        if (!is_dir($this->data_dir)) {
            mkdir($this->data_dir, 0700, true);
        }
        
        $this->initializeDataFiles();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize data files with default structure
     */
    private function initializeDataFiles() {
        $files = [
            'users.php' => [
                'structure' => 'users',
                'default' => []
            ],
            'products.php' => [
                'structure' => 'products', 
                'default' => [
                    'example_item' => [
                        'id' => 'example_item',
                        'name' => 'Example Item',
                        'description' => 'This is an example item',
                        'price' => 100,
                        'command' => 'give {username} diamond 1',
                        'enabled' => false,
                        'created_at' => time()
                    ]
                ]
            ],
            'transactions.php' => [
                'structure' => 'transactions',
                'default' => []
            ],
            'config.php' => [
                'structure' => 'config',
                'default' => [
                    'site_name' => 'Minecraft Webshop',
                    'server_name' => 'My Server',
                    'server_ip' => 'localhost',
                    'server_port' => 25565,
                    'rcon_ip' => 'localhost',
                    'rcon_port' => 25575,
                    'rcon_password' => '',
                    'admin_username' => 'admin',
                    'admin_password' => '',
                    'installed' => false,
                    'maintenance_mode' => false,
                    'max_points_per_user' => 10000,
                    'allow_registration' => true
                ]
            ]
        ];
        
        foreach ($files as $filename => $data) {
            $filepath = $this->data_dir . '/' . $filename;
            if (!file_exists($filepath)) {
                $this->writeDataFile($filename, $data['default']);
            }
        }
    }
    
    /**
     * Read data from encrypted file
     */
    private function readDataFile($filename) {
        $filepath = $this->data_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $content = file_get_contents($filepath);
        
        // Check if file starts with <?php (unencrypted legacy format)
        if (strpos($content, '<?php') === 0) {
            // Legacy format - include the file
            return include $filepath;
        } else {
            // Encrypted format
            $decrypted = $this->security->decrypt($content);
            return $decrypted !== false ? $decrypted : [];
        }
    }
    
    /**
     * Write data to encrypted file
     */
    private function writeDataFile($filename, $data) {
        $filepath = $this->data_dir . '/' . $filename;
        $lock_file = $filepath . '.lock';
        
        // Acquire file lock
        $lock_handle = fopen($lock_file, 'w');
        if (!$lock_handle) {
            throw new Exception('Could not create lock file');
        }
        
        $lock_acquired = false;
        $start_time = time();
        
        while (!$lock_acquired && (time() - $start_time) < $this->lock_timeout) {
            $lock_acquired = flock($lock_handle, LOCK_EX | LOCK_NB);
            if (!$lock_acquired) {
                usleep(100000); // 0.1 second
            }
        }
        
        if (!$lock_acquired) {
            fclose($lock_handle);
            throw new Exception('Could not acquire file lock');
        }
        
        try {
            // Encrypt and write data
            $encrypted_data = $this->security->encrypt($data);
            $result = file_put_contents($filepath, $encrypted_data, LOCK_EX);
            
            if ($result === false) {
                throw new Exception('Could not write data file');
            }
            
            // Set secure permissions
            chmod($filepath, 0600);
            
        } finally {
            // Release lock
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
            unlink($lock_file);
        }
        
        return true;
    }
    
    /**
     * Get user data
     */
    public function getUser($username) {
        $users = $this->readDataFile('users.php');
        return isset($users[$username]) ? $users[$username] : null;
    }
    
    /**
     * Create or update user
     */
    public function saveUser($username, $user_data) {
        $username = $this->security->sanitizeInput($username, 'username');
        
        if (!$this->security->validateInput($username, 'username')) {
            throw new Exception('Invalid username format');
        }
        
        $users = $this->readDataFile('users.php');
        
        // Set default user data
        $default_user = [
            'username' => $username,
            'points' => 0,
            'total_spent' => 0,
            'total_purchases' => 0,
            'created_at' => time(),
            'last_activity' => time(),
            'status' => 'active'
        ];
        
        if (isset($users[$username])) {
            // Update existing user
            $users[$username] = array_merge($users[$username], $user_data);
            $users[$username]['last_activity'] = time();
        } else {
            // Create new user
            $users[$username] = array_merge($default_user, $user_data);
        }
        
        $this->writeDataFile('users.php', $users);
        
        // Log user activity
        $this->security->logSecurityEvent('user_saved', [
            'username' => $username,
            'action' => isset($users[$username]) ? 'update' : 'create'
        ]);
        
        return $users[$username];
    }
    
    /**
     * Add points to user
     */
    public function addPoints($username, $points) {
        $points = (int) $points;
        if ($points <= 0) {
            throw new Exception('Points must be positive');
        }
        
        $user = $this->getUser($username);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $config = $this->getConfig();
        $max_points = $config['max_points_per_user'] ?? 10000;
        
        if (($user['points'] + $points) > $max_points) {
            throw new Exception('Maximum points limit exceeded');
        }
        
        $user['points'] += $points;
        $this->saveUser($username, $user);
        
        // Log transaction
        $this->logTransaction([
            'type' => 'points_added',
            'username' => $username,
            'amount' => $points,
            'balance_after' => $user['points'],
            'admin_action' => true
        ]);
        
        return $user['points'];
    }
    
    /**
     * Deduct points from user
     */
    public function deductPoints($username, $points) {
        $points = (int) $points;
        if ($points <= 0) {
            throw new Exception('Points must be positive');
        }
        
        $user = $this->getUser($username);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        if ($user['points'] < $points) {
            throw new Exception('Insufficient points');
        }
        
        $user['points'] -= $points;
        $user['total_spent'] += $points;
        $this->saveUser($username, $user);
        
        return $user['points'];
    }
    
    /**
     * Get all products
     */
    public function getProducts() {
        return $this->readDataFile('products.php');
    }
    
    /**
     * Get single product
     */
    public function getProduct($product_id) {
        $products = $this->getProducts();
        return isset($products[$product_id]) ? $products[$product_id] : null;
    }
    
    /**
     * Save product
     */
    public function saveProduct($product_id, $product_data) {
        $product_id = $this->security->sanitizeInput($product_id, 'string');
        
        $products = $this->getProducts();
        
        $default_product = [
            'id' => $product_id,
            'name' => '',
            'description' => '',
            'price' => 0,
            'command' => '',
            'enabled' => true,
            'created_at' => time(),
            'updated_at' => time()
        ];
        
        if (isset($products[$product_id])) {
            $products[$product_id] = array_merge($products[$product_id], $product_data);
            $products[$product_id]['updated_at'] = time();
        } else {
            $products[$product_id] = array_merge($default_product, $product_data);
        }
        
        $this->writeDataFile('products.php', $products);
        
        return $products[$product_id];
    }
    
    /**
     * Delete product
     */
    public function deleteProduct($product_id) {
        $products = $this->getProducts();
        
        if (!isset($products[$product_id])) {
            throw new Exception('Product not found');
        }
        
        unset($products[$product_id]);
        $this->writeDataFile('products.php', $products);
        
        return true;
    }
    
    /**
     * Process purchase
     */
    public function processPurchase($username, $product_id) {
        $user = $this->getUser($username);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $product = $this->getProduct($product_id);
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if (!$product['enabled']) {
            throw new Exception('Product is not available');
        }
        
        if ($user['points'] < $product['price']) {
            throw new Exception('Insufficient points');
        }
        
        // Deduct points
        $this->deductPoints($username, $product['price']);
        
        // Update user stats
        $user = $this->getUser($username); // Refresh user data
        $user['total_purchases']++;
        $this->saveUser($username, $user);
        
        // Log transaction
        $transaction_id = $this->logTransaction([
            'type' => 'purchase',
            'username' => $username,
            'product_id' => $product_id,
            'product_name' => $product['name'],
            'amount' => $product['price'],
            'balance_after' => $user['points'],
            'command' => $product['command']
        ]);
        
        return [
            'transaction_id' => $transaction_id,
            'command' => $product['command'],
            'remaining_points' => $user['points']
        ];
    }
    
    /**
     * Log transaction
     */
    public function logTransaction($transaction_data) {
        $transactions = $this->readDataFile('transactions.php');
        
        $transaction_id = uniqid('txn_', true);
        $transaction = array_merge([
            'id' => $transaction_id,
            'timestamp' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $transaction_data);
        
        $transactions[$transaction_id] = $transaction;
        $this->writeDataFile('transactions.php', $transactions);
        
        return $transaction_id;
    }
    
    /**
     * Get transactions
     */
    public function getTransactions($username = null, $limit = 100) {
        $transactions = $this->readDataFile('transactions.php');
        
        // Sort by timestamp (newest first)
        uasort($transactions, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Filter by username if specified
        if ($username) {
            $transactions = array_filter($transactions, function($transaction) use ($username) {
                return isset($transaction['username']) && $transaction['username'] === $username;
            });
        }
        
        // Limit results
        return array_slice($transactions, 0, $limit, true);
    }
    
    /**
     * Get configuration
     */
    public function getConfig() {
        return $this->readDataFile('config.php');
    }
    
    /**
     * Save configuration
     */
    public function saveConfig($config_data) {
        $config = $this->getConfig();
        $config = array_merge($config, $config_data);
        
        // Sanitize sensitive data
        if (isset($config_data['rcon_password'])) {
            $config['rcon_password'] = $this->security->sanitizeInput($config_data['rcon_password'], 'string');
        }
        
        if (isset($config_data['admin_password']) && !empty($config_data['admin_password'])) {
            $config['admin_password'] = $this->security->hashPassword($config_data['admin_password']);
        }
        
        $this->writeDataFile('config.php', $config);
        
        return $config;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        $users = $this->readDataFile('users.php');
        $products = $this->readDataFile('products.php');
        $transactions = $this->readDataFile('transactions.php');
        
        $stats = [
            'total_users' => count($users),
            'total_products' => count($products),
            'total_transactions' => count($transactions),
            'total_points_distributed' => 0,
            'total_points_spent' => 0,
            'active_products' => 0
        ];
        
        // Calculate user stats
        foreach ($users as $user) {
            $stats['total_points_distributed'] += ($user['total_spent'] + $user['points']);
            $stats['total_points_spent'] += $user['total_spent'];
        }
        
        // Calculate product stats
        foreach ($products as $product) {
            if ($product['enabled']) {
                $stats['active_products']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Backup data
     */
    public function createBackup() {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0700, true);
        }
        
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.json';
        
        $backup_data = [
            'timestamp' => time(),
            'version' => '1.0',
            'users' => $this->readDataFile('users.php'),
            'products' => $this->readDataFile('products.php'),
            'transactions' => $this->readDataFile('transactions.php'),
            'config' => $this->readDataFile('config.php')
        ];
        
        $encrypted_backup = $this->security->encrypt($backup_data);
        file_put_contents($backup_file, $encrypted_backup);
        chmod($backup_file, 0600);
        
        return $backup_file;
    }
    
    /**
     * Clean old data
     */
    public function cleanOldData($days = 90) {
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        // Clean old transactions
        $transactions = $this->readDataFile('transactions.php');
        $cleaned_transactions = array_filter($transactions, function($transaction) use ($cutoff) {
            return $transaction['timestamp'] > $cutoff;
        });
        
        if (count($cleaned_transactions) !== count($transactions)) {
            $this->writeDataFile('transactions.php', $cleaned_transactions);
        }
        
        return count($transactions) - count($cleaned_transactions);
    }
}
?>

