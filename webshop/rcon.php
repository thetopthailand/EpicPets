<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// RCON Configuration
$RCON_HOST = 'localhost'; // เปลี่ยนเป็น IP ของเซิร์ฟเวอร์ Minecraft
$RCON_PORT = 25575;       // พอร์ต RCON (ค่าเริ่มต้น 25575)
$RCON_PASSWORD = 'your_rcon_password'; // รหัสผ่าน RCON

// รับข้อมูลจาก JavaScript
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['command']) || !isset($input['player'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$command = $input['command'];
$player = $input['player'];

// Validate player name
if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player)) {
    echo json_encode(['success' => false, 'error' => 'Invalid player name']);
    exit;
}

// Log the purchase attempt
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'player' => $player,
    'command' => $command,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

file_put_contents('purchases.log', json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

try {
    // Execute RCON command
    $result = executeRCONCommand($RCON_HOST, $RCON_PORT, $RCON_PASSWORD, $command);
    
    if ($result !== false) {
        echo json_encode([
            'success' => true, 
            'message' => 'Command executed successfully',
            'response' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to execute RCON command'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'RCON connection failed: ' . $e->getMessage()
    ]);
}

/**
 * Execute RCON command
 */
function executeRCONCommand($host, $port, $password, $command) {
    try {
        // Create socket connection
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new Exception('Failed to create socket');
        }

        // Set socket timeout
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0));

        // Connect to server
        if (!socket_connect($socket, $host, $port)) {
            throw new Exception('Failed to connect to RCON server');
        }

        // Authenticate
        $authPacket = createRCONPacket(1, 3, $password);
        socket_write($socket, $authPacket);
        
        $response = socket_read($socket, 4096);
        if (!$response) {
            throw new Exception('Authentication failed');
        }

        // Send command
        $commandPacket = createRCONPacket(2, 2, $command);
        socket_write($socket, $commandPacket);
        
        $commandResponse = socket_read($socket, 4096);
        
        socket_close($socket);
        
        if ($commandResponse) {
            return parseRCONResponse($commandResponse);
        }
        
        return false;
        
    } catch (Exception $e) {
        if (isset($socket)) {
            socket_close($socket);
        }
        throw $e;
    }
}

/**
 * Create RCON packet
 */
function createRCONPacket($id, $type, $body) {
    $packet = pack('VV', $id, $type) . $body . "\x00\x00";
    return pack('V', strlen($packet)) . $packet;
}

/**
 * Parse RCON response
 */
function parseRCONResponse($response) {
    if (strlen($response) < 12) {
        return false;
    }
    
    $length = unpack('V', substr($response, 0, 4))[1];
    $id = unpack('V', substr($response, 4, 4))[1];
    $type = unpack('V', substr($response, 8, 4))[1];
    $body = substr($response, 12, $length - 8);
    
    return rtrim($body, "\x00");
}

/**
 * Alternative: Simple RCON using external library or command
 * Uncomment this if you prefer to use mcrcon or similar tools
 */
/*
function executeRCONCommandAlt($host, $port, $password, $command) {
    $escapedCommand = escapeshellarg($command);
    $escapedPassword = escapeshellarg($password);
    
    // Using mcrcon (install with: apt-get install mcrcon)
    $output = shell_exec("mcrcon -H $host -P $port -p $escapedPassword $escapedCommand 2>&1");
    
    return $output;
}
*/

/**
 * Demo mode - for testing without actual RCON server
 * Remove this in production
 */
if ($RCON_HOST === 'localhost' && $RCON_PASSWORD === 'your_rcon_password') {
    // Demo mode - simulate successful command execution
    echo json_encode([
        'success' => true, 
        'message' => 'Command executed successfully (DEMO MODE)',
        'response' => "Gave $player the requested item(s)"
    ]);
    exit;
}
?>

