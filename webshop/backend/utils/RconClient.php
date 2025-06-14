<?php
/**
 * RCON Client
 * Low-level RCON client สำหรับการเชื่อมต่อกับ Minecraft Server
 */

class RconClient {
    private $host;
    private $port;
    private $password;
    private $timeout;
    private $socket;
    private $requestId = 0;
    private $isConnected = false;
    private $connectionPool = [];
    private $maxConnections;

    // RCON Packet Types
    const SERVERDATA_AUTH = 3;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;
    const SERVERDATA_AUTH_RESPONSE = 2;

    public function __construct($config) {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->password = $config['password'];
        $this->timeout = $config['timeout'] ?? 5;
        $this->maxConnections = $config['max_connections'] ?? 5;
    }

    /**
     * เชื่อมต่อกับ RCON server
     */
    public function connect() {
        if ($this->isConnected) {
            return true;
        }

        try {
            // สร้าง socket connection
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if (!$this->socket) {
                throw new Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
            }

            // ตั้งค่า timeout
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $this->timeout,
                'usec' => 0
            ]);
            
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, [
                'sec' => $this->timeout,
                'usec' => 0
            ]);

            // เชื่อมต่อ
            if (!socket_connect($this->socket, $this->host, $this->port)) {
                $error = socket_strerror(socket_last_error($this->socket));
                socket_close($this->socket);
                throw new Exception("Failed to connect to RCON server: {$error}");
            }

            // Authentication
            if (!$this->authenticate()) {
                socket_close($this->socket);
                throw new Exception("RCON authentication failed");
            }

            $this->isConnected = true;
            return true;

        } catch (Exception $e) {
            $this->isConnected = false;
            if ($this->socket) {
                socket_close($this->socket);
                $this->socket = null;
            }
            throw $e;
        }
    }

    /**
     * Authentication กับ RCON server
     */
    private function authenticate() {
        $packet = $this->createPacket(self::SERVERDATA_AUTH, $this->password);
        
        if (!$this->sendPacket($packet)) {
            return false;
        }

        $response = $this->readPacket();
        
        if (!$response || $response['type'] !== self::SERVERDATA_AUTH_RESPONSE) {
            return false;
        }

        // ตรวจสอบ authentication response
        return $response['id'] === $this->requestId - 1;
    }

    /**
     * ส่งคำสั่งไปยัง server
     */
    public function sendCommand($command) {
        if (!$this->isConnected) {
            $this->connect();
        }

        try {
            $packet = $this->createPacket(self::SERVERDATA_EXECCOMMAND, $command);
            
            if (!$this->sendPacket($packet)) {
                throw new Exception("Failed to send command packet");
            }

            $response = $this->readPacket();
            
            if (!$response) {
                throw new Exception("No response received from server");
            }

            if ($response['type'] !== self::SERVERDATA_RESPONSE_VALUE) {
                throw new Exception("Invalid response type received");
            }

            return $response['body'];

        } catch (Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * สร้าง RCON packet
     */
    private function createPacket($type, $body) {
        $id = $this->requestId++;
        
        // สร้าง packet body
        $packet = pack('VV', $id, $type) . $body . "\x00\x00";
        
        // เพิ่ม packet length
        $packet = pack('V', strlen($packet)) . $packet;
        
        return $packet;
    }

    /**
     * ส่ง packet ไปยัง server
     */
    private function sendPacket($packet) {
        $totalSent = 0;
        $packetLength = strlen($packet);

        while ($totalSent < $packetLength) {
            $sent = socket_write($this->socket, substr($packet, $totalSent));
            
            if ($sent === false) {
                return false;
            }
            
            $totalSent += $sent;
        }

        return true;
    }

    /**
     * อ่าน packet จาก server
     */
    private function readPacket() {
        // อ่าน packet length (4 bytes)
        $lengthData = $this->readBytes(4);
        if (!$lengthData) {
            return false;
        }

        $length = unpack('V', $lengthData)[1];
        
        if ($length < 10 || $length > 4096) {
            throw new Exception("Invalid packet length: {$length}");
        }

        // อ่าน packet data
        $packetData = $this->readBytes($length);
        if (!$packetData) {
            return false;
        }

        // Parse packet
        $id = unpack('V', substr($packetData, 0, 4))[1];
        $type = unpack('V', substr($packetData, 4, 4))[1];
        $body = substr($packetData, 8, $length - 10); // -10 for id, type, and null terminators

        return [
            'id' => $id,
            'type' => $type,
            'body' => $body
        ];
    }

    /**
     * อ่านข้อมูลจำนวนที่กำหนดจาก socket
     */
    private function readBytes($length) {
        $data = '';
        $totalRead = 0;

        while ($totalRead < $length) {
            $chunk = socket_read($this->socket, $length - $totalRead, PHP_BINARY_READ);
            
            if ($chunk === false || $chunk === '') {
                return false;
            }
            
            $data .= $chunk;
            $totalRead += strlen($chunk);
        }

        return $data;
    }

    /**
     * ตัดการเชื่อมต่อ
     */
    public function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        
        $this->isConnected = false;
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ
     */
    public function isConnected() {
        if (!$this->isConnected || !$this->socket) {
            return false;
        }

        // ทดสอบการเชื่อมต่อด้วยการส่งคำสั่งง่ายๆ
        try {
            $this->sendCommand('list');
            return true;
        } catch (Exception $e) {
            $this->isConnected = false;
            return false;
        }
    }

    /**
     * Ping server เพื่อทดสอบ latency
     */
    public function ping() {
        $startTime = microtime(true);
        
        try {
            $this->sendCommand('list');
            $endTime = microtime(true);
            
            return ($endTime - $startTime) * 1000; // milliseconds
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * ได้รับข้อมูล server info
     */
    public function getServerInfo() {
        try {
            $listResult = $this->sendCommand('list');
            $versionResult = $this->sendCommand('version');
            
            return [
                'players' => $this->parsePlayerList($listResult),
                'version' => $this->parseVersion($versionResult),
                'ping' => $this->ping()
            ];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Parse รายชื่อผู้เล่นจากคำสั่ง list
     */
    private function parsePlayerList($listResult) {
        if (preg_match('/There are (\d+) of a max of (\d+) players online/', $listResult, $matches)) {
            $online = (int)$matches[1];
            $max = (int)$matches[2];
            
            $players = [];
            if (preg_match('/players online: (.+)/', $listResult, $playerMatches)) {
                $playerList = trim($playerMatches[1]);
                if (!empty($playerList)) {
                    $players = array_map('trim', explode(',', $playerList));
                }
            }
            
            return [
                'online' => $online,
                'max' => $max,
                'players' => $players
            ];
        }
        
        return ['online' => 0, 'max' => 0, 'players' => []];
    }

    /**
     * Parse version information
     */
    private function parseVersion($versionResult) {
        if (preg_match('/This server is running (.+) version (.+)/', $versionResult, $matches)) {
            return [
                'software' => trim($matches[1]),
                'version' => trim($matches[2])
            ];
        }
        
        return ['software' => 'Unknown', 'version' => 'Unknown'];
    }

    /**
     * Destructor - ปิดการเชื่อมต่อ
     */
    public function __destruct() {
        $this->disconnect();
    }
}

/**
 * RCON Connection Pool
 * จัดการ connection pool เพื่อประสิทธิภาพที่ดีขึ้น
 */
class RconConnectionPool {
    private $config;
    private $pool = [];
    private $maxConnections;
    private $activeConnections = 0;

    public function __construct($config) {
        $this->config = $config;
        $this->maxConnections = $config['max_connections'] ?? 5;
    }

    /**
     * ได้รับ connection จาก pool
     */
    public function getConnection() {
        // หา connection ที่ว่างอยู่
        foreach ($this->pool as $key => $connection) {
            if ($connection['in_use'] === false && $connection['client']->isConnected()) {
                $this->pool[$key]['in_use'] = true;
                $this->pool[$key]['last_used'] = time();
                return $connection['client'];
            }
        }

        // สร้าง connection ใหม่หากยังไม่เต็ม
        if ($this->activeConnections < $this->maxConnections) {
            $client = new RconClient($this->config);
            $client->connect();
            
            $connectionId = uniqid();
            $this->pool[$connectionId] = [
                'client' => $client,
                'in_use' => true,
                'created_at' => time(),
                'last_used' => time()
            ];
            
            $this->activeConnections++;
            return $client;
        }

        throw new Exception("Connection pool is full");
    }

    /**
     * คืน connection กลับไปยัง pool
     */
    public function releaseConnection($client) {
        foreach ($this->pool as $key => $connection) {
            if ($connection['client'] === $client) {
                $this->pool[$key]['in_use'] = false;
                $this->pool[$key]['last_used'] = time();
                return;
            }
        }
    }

    /**
     * ทำความสะอาด connections ที่ไม่ได้ใช้งาน
     */
    public function cleanup() {
        $now = time();
        $maxIdleTime = 300; // 5 minutes

        foreach ($this->pool as $key => $connection) {
            if (!$connection['in_use'] && ($now - $connection['last_used']) > $maxIdleTime) {
                $connection['client']->disconnect();
                unset($this->pool[$key]);
                $this->activeConnections--;
            }
        }
    }

    /**
     * ปิด connections ทั้งหมด
     */
    public function closeAll() {
        foreach ($this->pool as $connection) {
            $connection['client']->disconnect();
        }
        
        $this->pool = [];
        $this->activeConnections = 0;
    }

    /**
     * ได้รับสถิติ connection pool
     */
    public function getStats() {
        $inUse = 0;
        $idle = 0;

        foreach ($this->pool as $connection) {
            if ($connection['in_use']) {
                $inUse++;
            } else {
                $idle++;
            }
        }

        return [
            'total_connections' => $this->activeConnections,
            'in_use' => $inUse,
            'idle' => $idle,
            'max_connections' => $this->maxConnections
        ];
    }
}

/**
 * Async RCON Client (สำหรับการประมวลผลแบบ asynchronous)
 */
class AsyncRconClient {
    private $clients = [];
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * ส่งคำสั่งหลายๆ คำสั่งพร้อมกัน
     */
    public function sendMultipleCommands($commands) {
        $results = [];
        $processes = [];

        foreach ($commands as $index => $command) {
            $client = new RconClient($this->config);
            $this->clients[$index] = $client;
            
            // สร้าง process สำหรับแต่ละคำสั่ง
            $processes[$index] = $this->createAsyncProcess($client, $command);
        }

        // รอผลลัพธ์จากทุก processes
        foreach ($processes as $index => $process) {
            $results[$index] = $this->waitForResult($process);
        }

        return $results;
    }

    /**
     * สร้าง async process
     */
    private function createAsyncProcess($client, $command) {
        // ใช้ pcntl_fork() หรือ pthreads สำหรับ true async
        // หรือใช้ curl_multi สำหรับ HTTP-based approach
        
        return [
            'client' => $client,
            'command' => $command,
            'start_time' => microtime(true)
        ];
    }

    /**
     * รอผลลัพธ์จาก process
     */
    private function waitForResult($process) {
        try {
            $result = $process['client']->sendCommand($process['command']);
            $executionTime = microtime(true) - $process['start_time'];
            
            return [
                'success' => true,
                'result' => $result,
                'execution_time' => $executionTime
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $process['start_time']
            ];
        }
    }
}
?>

