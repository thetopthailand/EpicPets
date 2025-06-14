<?php
/**
 * RCON Service
 * ระบบ RCON ที่ปลอดภัยและเชื่อถือได้สำหรับ Minecraft Server
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/RconClient.php';

class RconService {
    private $db;
    private $rconClient;
    private $config;
    private $commandQueue;
    private $maxRetries = 3;
    private $retryDelay = 1; // seconds

    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
        $this->rconClient = new RconClient($this->config);
        $this->commandQueue = new CommandQueue();
    }

    /**
     * โหลดการตั้งค่า RCON
     */
    private function loadConfig() {
        $this->config = [
            'host' => $_ENV['RCON_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['RCON_PORT'] ?? 25575),
            'password' => $_ENV['RCON_PASSWORD'] ?? '',
            'timeout' => (int)($_ENV['RCON_TIMEOUT'] ?? 5),
            'max_connections' => (int)($_ENV['RCON_MAX_CONNECTIONS'] ?? 5)
        ];

        // ตรวจสอบการตั้งค่าที่จำเป็น
        if (empty($this->config['password'])) {
            throw new Exception("RCON password is required");
        }
    }

    /**
     * ส่งคำสั่งไปยัง Minecraft Server
     */
    public function executeCommand($command, $playerId = null, $priority = 'normal') {
        try {
            // Validate และ sanitize command
            $sanitizedCommand = $this->validateAndSanitizeCommand($command);
            
            // บันทึกคำสั่งในฐานข้อมูล
            $commandId = $this->logCommand($sanitizedCommand, $playerId, $priority);
            
            // เพิ่มเข้า queue หรือส่งทันที
            if ($priority === 'immediate') {
                $result = $this->sendCommandImmediate($sanitizedCommand, $commandId);
            } else {
                $result = $this->queueCommand($sanitizedCommand, $commandId, $priority);
            }

            return [
                'success' => true,
                'command_id' => $commandId,
                'result' => $result
            ];

        } catch (Exception $e) {
            $this->logError($command, $e->getMessage(), $playerId);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'command_id' => null
            ];
        }
    }

    /**
     * ตรวจสอบและทำความสะอาดคำสั่ง
     */
    private function validateAndSanitizeCommand($command) {
        // ลบ characters ที่อันตราย
        $command = preg_replace('/[^\w\s\-\.\@\{\}\/\:]/u', '', $command);
        
        // ตรวจสอบคำสั่งที่อนุญาต
        $allowedCommands = $this->getAllowedCommands();
        $commandParts = explode(' ', trim($command));
        $baseCommand = strtolower($commandParts[0]);

        if (!in_array($baseCommand, $allowedCommands)) {
            throw new Exception("Command '{$baseCommand}' is not allowed");
        }

        // ตรวจสอบความยาวคำสั่ง
        if (strlen($command) > 500) {
            throw new Exception("Command is too long");
        }

        // ตรวจสอบ patterns ที่อันตราย
        $dangerousPatterns = [
            '/\bstop\b/i',
            '/\brestart\b/i',
            '/\bshutdown\b/i',
            '/\bop\s+\w+/i', // ป้องกันการให้ op
            '/\bdeop\s+\w+/i',
            '/\bban\s+\w+/i',
            '/\bkick\s+\w+/i',
            '/\bwhitelist\s+(add|remove)/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                throw new Exception("Command contains dangerous operations");
            }
        }

        return $command;
    }

    /**
     * ได้รับรายการคำสั่งที่อนุญาต
     */
    private function getAllowedCommands() {
        return [
            'give', 'tp', 'teleport', 'xp', 'experience',
            'gamemode', 'effect', 'enchant', 'summon',
            'setblock', 'fill', 'clone', 'tellraw',
            'title', 'playsound', 'particle', 'weather',
            'time', 'difficulty', 'gamerule', 'scoreboard',
            'team', 'trigger', 'advancement', 'recipe',
            'function', 'tag', 'data', 'execute'
        ];
    }

    /**
     * ส่งคำสั่งทันที
     */
    private function sendCommandImmediate($command, $commandId) {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            try {
                $result = $this->rconClient->sendCommand($command);
                
                // อัปเดตสถานะในฐานข้อมูล
                $this->updateCommandStatus($commandId, 'completed', $result);
                
                return $result;

            } catch (Exception $e) {
                $attempts++;
                $lastError = $e->getMessage();
                
                if ($attempts < $this->maxRetries) {
                    sleep($this->retryDelay);
                    $this->retryDelay *= 2; // Exponential backoff
                }
            }
        }

        // ล้มเหลวหลังจากลองหลายครั้ง
        $this->updateCommandStatus($commandId, 'failed', null, $lastError);
        throw new Exception("Failed to execute command after {$this->maxRetries} attempts: {$lastError}");
    }

    /**
     * เพิ่มคำสั่งเข้า queue
     */
    private function queueCommand($command, $commandId, $priority) {
        $this->commandQueue->add($command, $commandId, $priority);
        $this->updateCommandStatus($commandId, 'queued');
        
        return "Command queued for execution";
    }

    /**
     * ประมวลผล command queue
     */
    public function processQueue() {
        $commands = $this->commandQueue->getNext(10); // ประมวลผล 10 คำสั่งต่อครั้ง

        foreach ($commands as $queueItem) {
            try {
                $result = $this->rconClient->sendCommand($queueItem['command']);
                
                $this->updateCommandStatus($queueItem['command_id'], 'completed', $result);
                $this->commandQueue->markCompleted($queueItem['id']);

            } catch (Exception $e) {
                $this->updateCommandStatus($queueItem['command_id'], 'failed', null, $e->getMessage());
                $this->commandQueue->markFailed($queueItem['id'], $e->getMessage());
            }
        }
    }

    /**
     * บันทึกคำสั่งในฐานข้อมูล
     */
    private function logCommand($command, $playerId, $priority) {
        $this->db->execute(
            "INSERT INTO rcon_commands (command, player_id, priority, status, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, 'pending', ?, ?, NOW())",
            [
                $command,
                $playerId,
                $priority,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * อัปเดตสถานะคำสั่ง
     */
    private function updateCommandStatus($commandId, $status, $result = null, $error = null) {
        $this->db->execute(
            "UPDATE rcon_commands SET status = ?, result = ?, error_message = ?, executed_at = NOW() WHERE id = ?",
            [$status, $result, $error, $commandId]
        );
    }

    /**
     * บันทึก error
     */
    private function logError($command, $error, $playerId = null) {
        $this->db->execute(
            "INSERT INTO rcon_errors (command, error_message, player_id, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$command, $error, $playerId, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ RCON
     */
    public function checkConnection() {
        try {
            $result = $this->rconClient->sendCommand('list');
            return [
                'connected' => true,
                'response' => $result,
                'timestamp' => time()
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    /**
     * ได้รับสถิติการใช้งาน RCON
     */
    public function getStatistics($timeRange = '24 HOUR') {
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_commands,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_commands,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_commands,
                SUM(CASE WHEN status = 'pending' OR status = 'queued' THEN 1 ELSE 0 END) as pending_commands,
                AVG(CASE WHEN executed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, executed_at) END) as avg_execution_time
             FROM rcon_commands 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$timeRange})"
        );

        $topCommands = $this->db->fetchAll(
            "SELECT 
                SUBSTRING_INDEX(command, ' ', 1) as command_type,
                COUNT(*) as usage_count
             FROM rcon_commands 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$timeRange})
             GROUP BY command_type
             ORDER BY usage_count DESC
             LIMIT 10"
        );

        return [
            'summary' => $stats,
            'top_commands' => $topCommands
        ];
    }

    /**
     * ทำความสะอาดข้อมูลเก่า
     */
    public function cleanup() {
        // ลบ commands ที่เก่ากว่า 30 วัน
        $this->db->execute(
            "DELETE FROM rcon_commands WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // ลบ errors ที่เก่ากว่า 7 วัน
        $this->db->execute(
            "DELETE FROM rcon_errors WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // ทำความสะอาด queue
        $this->commandQueue->cleanup();
    }

    /**
     * ส่งไอเทมให้ผู้เล่น (helper method)
     */
    public function giveItem($playerName, $item, $quantity = 1, $nbt = null) {
        $command = "give {$playerName} {$item}";
        
        if ($quantity > 1) {
            $command .= " {$quantity}";
        }
        
        if ($nbt) {
            $command .= " {$nbt}";
        }

        return $this->executeCommand($command);
    }

    /**
     * เทเลพอร์ตผู้เล่น
     */
    public function teleportPlayer($playerName, $x, $y, $z, $dimension = null) {
        $command = "tp {$playerName} {$x} {$y} {$z}";
        
        if ($dimension) {
            $command .= " {$dimension}";
        }

        return $this->executeCommand($command);
    }

    /**
     * ให้ experience แก่ผู้เล่น
     */
    public function giveExperience($playerName, $amount, $type = 'points') {
        $command = "xp add {$playerName} {$amount}";
        
        if ($type === 'levels') {
            $command .= " levels";
        }

        return $this->executeCommand($command);
    }

    /**
     * ส่งข้อความให้ผู้เล่น
     */
    public function sendMessage($playerName, $message) {
        $escapedMessage = json_encode($message);
        $command = "tellraw {$playerName} {$escapedMessage}";

        return $this->executeCommand($command);
    }

    /**
     * ตั้งค่า gamemode
     */
    public function setGamemode($playerName, $gamemode) {
        $validGamemodes = ['survival', 'creative', 'adventure', 'spectator'];
        
        if (!in_array(strtolower($gamemode), $validGamemodes)) {
            throw new Exception("Invalid gamemode: {$gamemode}");
        }

        $command = "gamemode {$gamemode} {$playerName}";
        return $this->executeCommand($command);
    }

    /**
     * ตรวจสอบว่าผู้เล่นออนไลน์หรือไม่
     */
    public function isPlayerOnline($playerName) {
        try {
            $result = $this->rconClient->sendCommand('list');
            return strpos($result, $playerName) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * ได้รับรายชื่อผู้เล่นออนไลน์
     */
    public function getOnlinePlayers() {
        try {
            $result = $this->rconClient->sendCommand('list');
            
            // Parse ผลลัพธ์เพื่อดึงรายชื่อผู้เล่น
            if (preg_match('/There are \d+ of a max of \d+ players online: (.+)/', $result, $matches)) {
                $playerList = trim($matches[1]);
                if (empty($playerList)) {
                    return [];
                }
                return array_map('trim', explode(',', $playerList));
            }
            
            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Batch execution สำหรับคำสั่งหลายๆ คำสั่ง
     */
    public function executeBatch($commands, $playerId = null) {
        $results = [];
        
        foreach ($commands as $command) {
            $results[] = $this->executeCommand($command, $playerId, 'normal');
        }
        
        return $results;
    }
}

/**
 * Command Queue Class
 */
class CommandQueue {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * เพิ่มคำสั่งเข้า queue
     */
    public function add($command, $commandId, $priority = 'normal') {
        $priorityValue = $this->getPriorityValue($priority);
        
        $this->db->execute(
            "INSERT INTO command_queue (command_id, command, priority, status, created_at) 
             VALUES (?, ?, ?, 'pending', NOW())",
            [$commandId, $command, $priorityValue]
        );
    }

    /**
     * ได้รับคำสั่งถัดไปจาก queue
     */
    public function getNext($limit = 10) {
        return $this->db->fetchAll(
            "SELECT id, command_id, command, priority 
             FROM command_queue 
             WHERE status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * ทำเครื่องหมายว่าเสร็จสิ้น
     */
    public function markCompleted($queueId) {
        $this->db->execute(
            "UPDATE command_queue SET status = 'completed', processed_at = NOW() WHERE id = ?",
            [$queueId]
        );
    }

    /**
     * ทำเครื่องหมายว่าล้มเหลว
     */
    public function markFailed($queueId, $error) {
        $this->db->execute(
            "UPDATE command_queue SET status = 'failed', error_message = ?, processed_at = NOW() WHERE id = ?",
            [$error, $queueId]
        );
    }

    /**
     * ทำความสะอาด queue
     */
    public function cleanup() {
        $this->db->execute(
            "DELETE FROM command_queue WHERE processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * แปลง priority เป็นตัวเลข
     */
    private function getPriorityValue($priority) {
        $priorities = [
            'low' => 1,
            'normal' => 5,
            'high' => 8,
            'immediate' => 10
        ];

        return $priorities[$priority] ?? 5;
    }
}
?>

