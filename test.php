<?php
/**
 * Simple Test File
 * ไฟล์ทดสอบง่ายๆ เพื่อตรวจสอบว่าระบบทำงานได้
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🎮 Minecraft Webshop - System Test</h1>";

// Test 1: PHP Version
echo "<h2>1. PHP Version Test</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Required: 7.4+<br>";
echo "Status: " . (version_compare(PHP_VERSION, '7.4.0', '>=') ? "✅ OK" : "❌ FAIL") . "<br><br>";

// Test 2: Required Extensions
echo "<h2>2. Required Extensions Test</h2>";
$extensions = ['openssl', 'json', 'curl'];
foreach ($extensions as $ext) {
    echo "{$ext}: " . (extension_loaded($ext) ? "✅ OK" : "❌ FAIL") . "<br>";
}
echo "<br>";

// Test 3: File Permissions
echo "<h2>3. File Permissions Test</h2>";
$dirs = ['data', 'config', 'logs', 'backups'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    echo "{$dir}/: " . (is_writable($dir) ? "✅ Writable" : "❌ Not Writable") . "<br>";
}
echo "<br>";

// Test 4: Include Files
echo "<h2>4. Include Files Test</h2>";
$files = [
    'includes/security.php',
    'includes/data_manager.php', 
    'includes/rcon_handler.php'
];

foreach ($files as $file) {
    echo "{$file}: " . (file_exists($file) ? "✅ Exists" : "❌ Missing") . "<br>";
}
echo "<br>";

// Test 5: Basic Class Loading
echo "<h2>5. Class Loading Test</h2>";
try {
    define('WEBSHOP_INIT', true);
    
    if (file_exists('includes/security.php')) {
        require_once 'includes/security.php';
        $security = Security::getInstance();
        echo "Security Class: ✅ OK<br>";
    } else {
        echo "Security Class: ❌ File Missing<br>";
    }
    
    if (file_exists('includes/data_manager.php')) {
        require_once 'includes/data_manager.php';
        $data_manager = DataManager::getInstance();
        echo "DataManager Class: ✅ OK<br>";
    } else {
        echo "DataManager Class: ❌ File Missing<br>";
    }
    
} catch (Exception $e) {
    echo "Class Loading: ❌ Error - " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 6: Configuration Test
echo "<h2>6. Configuration Test</h2>";
try {
    if (isset($data_manager)) {
        $config = $data_manager->getConfig();
        echo "Config Loading: ✅ OK<br>";
        echo "Installed: " . (isset($config['installed']) && $config['installed'] ? "✅ Yes" : "❌ No") . "<br>";
    } else {
        echo "Config Loading: ❌ DataManager not available<br>";
    }
} catch (Exception $e) {
    echo "Config Loading: ❌ Error - " . $e->getMessage() . "<br>";
}

echo "<br>";

echo "<h2>🚀 Next Steps</h2>";
echo "<p>If all tests pass, you can proceed with:</p>";
echo "<ul>";
echo "<li><a href='install.php'>🔧 Run Installation Wizard</a></li>";
echo "<li><a href='index.php'>🏠 Go to Main Page</a></li>";
echo "<li><a href='backend.php'>⚙️ Access Admin Panel</a></li>";
echo "</ul>";

echo "<br><hr>";
echo "<p><small>Generated at: " . date('Y-m-d H:i:s') . "</small></p>";
?>

