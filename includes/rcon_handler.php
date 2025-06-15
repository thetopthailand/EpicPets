<?php
/**
 * RCON Handler Class
 * จัดการการเชื่อมต่อ RCON สำหรับ Minecraft Server
 * 
 * @author Minecraft Webshop Team
 * @version 1.0
 */

// Prevent direct access
if (!defined('WEBSHOP_INIT')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/security.php';

class RCONHandler {
    
    private $socket;
    private $host;
    private $port;
    private $password;
    private $timeout;
    private $security;
    private $request_id;
    
    // RCON packet types
    const SERVERDATA_AUTH = 3;
    const SERVERDATA_AUTH_RESPONSE = 2;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;
    
    public function __construct($host, $port, $password, $timeout = 3) {
        $this->host = $host;
        $this->port = (int) $port;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->security = Security::getInstance();
        $this->request_id = 1;
    }
    
    /**
     * Connect to RCON server
     */
    public function connect() {
        // Validate connection parameters
        if (!$this->security->validateInput($this->host, 'ip') && $this->host !== 'localhost') {
            throw new Exception('Invalid RCON host');
        }
        
        if (!$this->security->validateInput($this->port, 'port')) {
            throw new Exception('Invalid RCON port');
        }
        
        if (empty($this->password)) {
            throw new Exception('RCON password is required');
        }
        
        // Create socket connection
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$this->socket) {
            $this->security->logSecurityEvent('rcon_connection_failed', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $errstr,
                'errno' => $errno
            ]);
            throw new Exception("RCON connection failed: $errstr ($errno)");
        }
        
        // Set socket timeout
        stream_set_timeout($this->socket, $this->timeout);
        
        // Authenticate
        if (!$this->authenticate()) {
            $this->disconnect();
            throw new Exception('RCON authentication failed');
        }
        
        $this->security->logSecurityEvent('rcon_connected', [
            'host' => $this->host,
            'port' => $this->port
        ]);
        
        return true;
    }
    
    /**
     * Authenticate with RCON server
     */
    private function authenticate() {
        $packet = $this->buildPacket(self::SERVERDATA_AUTH, $this->password);
        
        if (!$this->sendPacket($packet)) {
            return false;
        }
        
        // Read authentication response
        $response = $this->readPacket();
        
        if (!$response || $response['type'] !== self::SERVERDATA_AUTH_RESPONSE) {
            $this->security->logSecurityEvent('rcon_auth_failed', [
                'host' => $this->host,
                'port' => $this->port,
                'response_type' => $response['type'] ?? 'null'
            ]);
            return false;
        }
        
        // Check if authentication was successful
        if ($response['id'] === -1) {
            $this->security->logSecurityEvent('rcon_auth_rejected', [
                'host' => $this->host,
                'port' => $this->port
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Execute command on server
     */
    public function executeCommand($command) {
        if (!$this->socket) {
            throw new Exception('RCON not connected');
        }
        
        // Sanitize and validate command
        $command = $this->sanitizeCommand($command);
        
        if (!$this->validateCommand($command)) {
            throw new Exception('Invalid or dangerous command');
        }
        
        // Build and send command packet
        $packet = $this->buildPacket(self::SERVERDATA_EXECCOMMAND, $command);
        
        if (!$this->sendPacket($packet)) {
            throw new Exception('Failed to send RCON command');
        }
        
        // Read response
        $response = $this->readPacket();
        
        if (!$response) {
            throw new Exception('Failed to read RCON response');
        }
        
        // Log command execution
        $this->security->logSecurityEvent('rcon_command_executed', [
            'command' => $command,
            'response_length' => strlen($response['body']),
            'success' => true
        ]);
        
        return $response['body'];
    }
    
    /**
     * Sanitize command input
     */
    private function sanitizeCommand($command) {
        // Remove dangerous characters
        $command = preg_replace('/[^\w\s\-\.\@\[\]\/\:\=]/', '', $command);
        
        // Trim whitespace
        $command = trim($command);
        
        // Limit command length
        if (strlen($command) > 500) {
            $command = substr($command, 0, 500);
        }
        
        return $command;
    }
    
    /**
     * Validate command for security
     */
    private function validateCommand($command) {
        // List of allowed command prefixes
        $allowed_commands = [
            'give',
            'tp',
            'teleport',
            'msg',
            'tell',
            'say',
            'title',
            'effect',
            'enchant',
            'xp',
            'experience',
            'gamemode',
            'weather',
            'time',
            'summon'
        ];
        
        // List of dangerous commands to block
        $blocked_commands = [
            'stop',
            'restart',
            'reload',
            'op',
            'deop',
            'ban',
            'kick',
            'whitelist',
            'pardon',
            'save-all',
            'save-off',
            'save-on',
            'difficulty',
            'defaultgamemode',
            'gamerule',
            'execute',
            'function',
            'scoreboard'
        ];
        
        $command_parts = explode(' ', strtolower($command));
        $base_command = $command_parts[0];
        
        // Check if command is blocked
        if (in_array($base_command, $blocked_commands)) {
            $this->security->logSecurityEvent('rcon_blocked_command', [
                'command' => $command,
                'reason' => 'blocked_command'
            ]);
            return false;
        }
        
        // Check if command is allowed (if whitelist is enabled)
        if (!empty($allowed_commands) && !in_array($base_command, $allowed_commands)) {
            $this->security->logSecurityEvent('rcon_blocked_command', [
                'command' => $command,
                'reason' => 'not_whitelisted'
            ]);
            return false;
        }
        
        // Additional validation for specific commands
        switch ($base_command) {
            case 'give':
                // Validate give command format: give <player> <item> [amount]
                if (count($command_parts) < 3) {
                    return false;
                }
                
                $player = $command_parts[1];
                $item = $command_parts[2];
                $amount = isset($command_parts[3]) ? (int) $command_parts[3] : 1;
                
                // Validate player name
                if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player)) {
                    return false;
                }
                
                // Validate item name
                if (!preg_match('/^[a-z0-9_:]+$/', $item)) {
                    return false;
                }
                
                // Validate amount (reasonable limits)
                if ($amount < 1 || $amount > 64) {
                    return false;
                }
                break;
                
            case 'tp':
            case 'teleport':
                // Basic validation for teleport commands
                if (count($command_parts) < 2) {
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Build RCON packet
     */
    private function buildPacket($type, $body) {
        $id = $this->request_id++;
        
        // Build packet data
        $packet_data = pack('VV', $id, $type) . $body . "\x00\x00";
        
        // Add packet length
        $packet = pack('V', strlen($packet_data)) . $packet_data;
        
        return $packet;
    }
    
    /**
     * Send packet to server
     */
    private function sendPacket($packet) {
        $bytes_sent = 0;
        $packet_length = strlen($packet);
        
        while ($bytes_sent < $packet_length) {
            $result = fwrite($this->socket, substr($packet, $bytes_sent));
            
            if ($result === false) {
                return false;
            }
            
            $bytes_sent += $result;
        }
        
        return true;
    }
    
    /**
     * Read packet from server
     */
    private function readPacket() {
        // Read packet length
        $length_data = fread($this->socket, 4);
        
        if (strlen($length_data) < 4) {
            return false;
        }
        
        $length = unpack('V', $length_data)[1];
        
        // Validate packet length
        if ($length < 10 || $length > 4096) {
            return false;
        }
        
        // Read packet data
        $packet_data = '';
        $bytes_read = 0;
        
        while ($bytes_read < $length) {
            $data = fread($this->socket, $length - $bytes_read);
            
            if ($data === false || strlen($data) === 0) {
                return false;
            }
            
            $packet_data .= $data;
            $bytes_read += strlen($data);
        }
        
        // Unpack packet
        if (strlen($packet_data) < 8) {
            return false;
        }
        
        $header = unpack('Vid/Vtype', substr($packet_data, 0, 8));
        $body = substr($packet_data, 8, -2); // Remove null terminators
        
        return [
            'id' => $header['id'],
            'type' => $header['type'],
            'body' => $body
        ];
    }
    
    /**
     * Test RCON connection
     */
    public function testConnection() {
        try {
            $this->connect();
            
            // Try a simple command
            $response = $this->executeCommand('list');
            
            $this->disconnect();
            
            return [
                'success' => true,
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process webshop command with username replacement
     */
    public function processWebshopCommand($command_template, $username, $additional_vars = []) {
        // Sanitize username
        $username = $this->security->sanitizeInput($username, 'username');
        
        if (!$this->security->validateInput($username, 'username')) {
            throw new Exception('Invalid username');
        }
        
        // Replace placeholders
        $replacements = array_merge([
            '{username}' => $username,
            '{player}' => $username
        ], $additional_vars);
        
        $command = str_replace(array_keys($replacements), array_values($replacements), $command_template);
        
        // Execute command
        return $this->executeCommand($command);
    }
    
    /**
     * Get server status
     */
    public function getServerStatus() {
        try {
            $this->connect();
            
            $list_response = $this->executeCommand('list');
            $tps_response = $this->executeCommand('forge tps'); // For modded servers
            
            $this->disconnect();
            
            // Parse player count from list command
            $player_count = 0;
            if (preg_match('/There are (\d+) of a max of (\d+) players online/', $list_response, $matches)) {
                $player_count = (int) $matches[1];
                $max_players = (int) $matches[2];
            }
            
            return [
                'online' => true,
                'players' => $player_count ?? 0,
                'max_players' => $max_players ?? 0,
                'list_response' => $list_response
            ];
            
        } catch (Exception $e) {
            return [
                'online' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Disconnect from RCON server
     */
    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            
            $this->security->logSecurityEvent('rcon_disconnected', [
                'host' => $this->host,
                'port' => $this->port
            ]);
        }
    }
    
    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct() {
        $this->disconnect();
    }
}

/**
 * RCON Factory Class
 */
class RCONFactory {
    
    public static function create($config = null) {
        if (!$config) {
            require_once __DIR__ . '/data_manager.php';
            $data_manager = DataManager::getInstance();
            $config = $data_manager->getConfig();
        }
        
        return new RCONHandler(
            $config['rcon_ip'] ?? 'localhost',
            $config['rcon_port'] ?? 25575,
            $config['rcon_password'] ?? '',
            $config['rcon_timeout'] ?? 3
        );
    }
}
?>

