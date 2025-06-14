<?php
/**
 * Rate Limiter Class
 * ระบบจำกัดอัตราการเข้าถึงเพื่อป้องกัน DDoS และ Brute Force
 */

class RateLimiter {
    private $db;
    private $redis;
    private $useRedis;

    public function __construct() {
        $this->db = Database::getInstance();
        
        // ตรวจสอบว่ามี Redis หรือไม่
        $this->useRedis = class_exists('Redis') && extension_loaded('redis');
        
        if ($this->useRedis) {
            try {
                $this->redis = new Redis();
                $this->redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', $_ENV['REDIS_PORT'] ?? 6379);
                
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $this->redis->auth($_ENV['REDIS_PASSWORD']);
                }
            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->useRedis = false;
            }
        }
    }

    /**
     * ตรวจสอบ Rate Limit
     * @param string $identifier - IP address หรือ user ID
     * @param string $action - ประเภทของการกระทำ
     * @param int $maxRequests - จำนวนคำขอสูงสุด
     * @param int $timeWindow - ช่วงเวลาในวินาที
     * @return bool
     */
    public function checkLimit($identifier, $action, $maxRequests, $timeWindow) {
        if ($this->useRedis) {
            return $this->checkLimitRedis($identifier, $action, $maxRequests, $timeWindow);
        } else {
            return $this->checkLimitDatabase($identifier, $action, $maxRequests, $timeWindow);
        }
    }

    /**
     * ตรวจสอบ Rate Limit ด้วย Redis (เร็วกว่า)
     */
    private function checkLimitRedis($identifier, $action, $maxRequests, $timeWindow) {
        $key = "rate_limit:{$action}:{$identifier}";
        $current = $this->redis->get($key);

        if ($current === false) {
            // ไม่มีข้อมูล - สร้างใหม่
            $this->redis->setex($key, $timeWindow, 1);
            return true;
        }

        if ($current >= $maxRequests) {
            // เกินขีดจำกัด
            $this->logRateLimitExceeded($identifier, $action, $current, $maxRequests);
            return false;
        }

        // เพิ่มจำนวนคำขอ
        $this->redis->incr($key);
        return true;
    }

    /**
     * ตรวจสอบ Rate Limit ด้วย Database
     */
    private function checkLimitDatabase($identifier, $action, $maxRequests, $timeWindow) {
        $windowStart = date('Y-m-d H:i:s', time() - $timeWindow);

        // นับจำนวนคำขอในช่วงเวลาที่กำหนด
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM rate_limits 
             WHERE identifier = ? AND action = ? AND created_at >= ?",
            [$identifier, $action, $windowStart]
        );

        if ($count['count'] >= $maxRequests) {
            $this->logRateLimitExceeded($identifier, $action, $count['count'], $maxRequests);
            return false;
        }

        // บันทึกคำขอใหม่
        $this->db->execute(
            "INSERT INTO rate_limits (identifier, action, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                $identifier,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        return true;
    }

    /**
     * บันทึกการเกินขีดจำกัด
     */
    private function logRateLimitExceeded($identifier, $action, $currentCount, $maxRequests) {
        $this->db->execute(
            "INSERT INTO rate_limit_violations (identifier, action, current_count, max_requests, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $identifier,
                $action,
                $currentCount,
                $maxRequests,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }

    /**
     * รีเซ็ต Rate Limit สำหรับ identifier และ action ที่กำหนด
     */
    public function resetLimit($identifier, $action) {
        if ($this->useRedis) {
            $key = "rate_limit:{$action}:{$identifier}";
            $this->redis->del($key);
        } else {
            $this->db->execute(
                "DELETE FROM rate_limits WHERE identifier = ? AND action = ?",
                [$identifier, $action]
            );
        }
    }

    /**
     * ได้รับข้อมูลการใช้งานปัจจุบัน
     */
    public function getCurrentUsage($identifier, $action, $timeWindow) {
        if ($this->useRedis) {
            $key = "rate_limit:{$action}:{$identifier}";
            $current = $this->redis->get($key);
            $ttl = $this->redis->ttl($key);
            
            return [
                'current_requests' => $current ?: 0,
                'time_remaining' => $ttl > 0 ? $ttl : 0
            ];
        } else {
            $windowStart = date('Y-m-d H:i:s', time() - $timeWindow);
            
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) as count, 
                        GREATEST(0, ? - UNIX_TIMESTAMP(NOW()) + UNIX_TIMESTAMP(MAX(created_at))) as time_remaining
                 FROM rate_limits 
                 WHERE identifier = ? AND action = ? AND created_at >= ?",
                [$timeWindow, $identifier, $action, $windowStart]
            );

            return [
                'current_requests' => $result['count'] ?? 0,
                'time_remaining' => $result['time_remaining'] ?? 0
            ];
        }
    }

    /**
     * ทำความสะอาดข้อมูลเก่า (สำหรับ Database mode)
     */
    public function cleanup() {
        if (!$this->useRedis) {
            // ลบข้อมูลที่เก่ากว่า 24 ชั่วโมง
            $this->db->execute(
                "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            
            $this->db->execute(
                "DELETE FROM rate_limit_violations WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
        }
    }

    /**
     * ได้รับสถิติการใช้งาน
     */
    public function getStatistics($timeRange = '1 HOUR') {
        $stats = $this->db->fetchAll(
            "SELECT action, COUNT(*) as total_requests, COUNT(DISTINCT identifier) as unique_identifiers
             FROM rate_limits 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$timeRange})
             GROUP BY action
             ORDER BY total_requests DESC"
        );

        $violations = $this->db->fetchAll(
            "SELECT action, COUNT(*) as violations, COUNT(DISTINCT identifier) as unique_violators
             FROM rate_limit_violations 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$timeRange})
             GROUP BY action
             ORDER BY violations DESC"
        );

        return [
            'requests' => $stats,
            'violations' => $violations
        ];
    }

    /**
     * ตั้งค่า Rate Limit แบบ Dynamic
     */
    public function setDynamicLimit($identifier, $action, $multiplier = 0.5, $duration = 3600) {
        $key = "dynamic_limit:{$action}:{$identifier}";
        
        if ($this->useRedis) {
            $this->redis->setex($key, $duration, $multiplier);
        } else {
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
            $this->db->execute(
                "INSERT INTO dynamic_limits (identifier, action, multiplier, expires_at, created_at) 
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE multiplier = ?, expires_at = ?, updated_at = NOW()",
                [$identifier, $action, $multiplier, $expiresAt, $multiplier, $expiresAt]
            );
        }
    }

    /**
     * ได้รับ Dynamic Limit Multiplier
     */
    public function getDynamicMultiplier($identifier, $action) {
        $key = "dynamic_limit:{$action}:{$identifier}";
        
        if ($this->useRedis) {
            $multiplier = $this->redis->get($key);
            return $multiplier !== false ? (float)$multiplier : 1.0;
        } else {
            $result = $this->db->fetchOne(
                "SELECT multiplier FROM dynamic_limits 
                 WHERE identifier = ? AND action = ? AND expires_at > NOW()",
                [$identifier, $action]
            );
            
            return $result ? (float)$result['multiplier'] : 1.0;
        }
    }

    /**
     * ตรวจสอบ Rate Limit พร้อม Dynamic Adjustment
     */
    public function checkLimitWithDynamic($identifier, $action, $maxRequests, $timeWindow) {
        $multiplier = $this->getDynamicMultiplier($identifier, $action);
        $adjustedMaxRequests = (int)($maxRequests * $multiplier);
        
        return $this->checkLimit($identifier, $action, $adjustedMaxRequests, $timeWindow);
    }

    /**
     * Sliding Window Rate Limiter (แม่นยำกว่า)
     */
    public function checkSlidingWindowLimit($identifier, $action, $maxRequests, $timeWindow) {
        if (!$this->useRedis) {
            // Fallback to regular database method
            return $this->checkLimitDatabase($identifier, $action, $maxRequests, $timeWindow);
        }

        $key = "sliding:{$action}:{$identifier}";
        $now = time();
        $windowStart = $now - $timeWindow;

        // ลบ entries ที่เก่าเกินไป
        $this->redis->zRemRangeByScore($key, 0, $windowStart);

        // นับจำนวนคำขอปัจจุบัน
        $currentCount = $this->redis->zCard($key);

        if ($currentCount >= $maxRequests) {
            $this->logRateLimitExceeded($identifier, $action, $currentCount, $maxRequests);
            return false;
        }

        // เพิ่มคำขอใหม่
        $this->redis->zAdd($key, $now, uniqid());
        $this->redis->expire($key, $timeWindow);

        return true;
    }

    /**
     * Token Bucket Rate Limiter
     */
    public function checkTokenBucket($identifier, $action, $capacity, $refillRate, $refillPeriod = 1) {
        if (!$this->useRedis) {
            return $this->checkLimitDatabase($identifier, $action, $capacity, $refillPeriod);
        }

        $key = "bucket:{$action}:{$identifier}";
        $now = time();

        // ได้รับข้อมูล bucket ปัจจุบัน
        $bucketData = $this->redis->hMGet($key, ['tokens', 'last_refill']);
        
        $tokens = isset($bucketData['tokens']) ? (int)$bucketData['tokens'] : $capacity;
        $lastRefill = isset($bucketData['last_refill']) ? (int)$bucketData['last_refill'] : $now;

        // คำนวณ tokens ที่ต้องเติม
        $timePassed = $now - $lastRefill;
        $tokensToAdd = floor($timePassed / $refillPeriod) * $refillRate;
        $tokens = min($capacity, $tokens + $tokensToAdd);

        if ($tokens < 1) {
            $this->logRateLimitExceeded($identifier, $action, 0, 1);
            return false;
        }

        // ใช้ token และอัปเดต bucket
        $tokens--;
        $this->redis->hMSet($key, [
            'tokens' => $tokens,
            'last_refill' => $now
        ]);
        $this->redis->expire($key, $capacity * $refillPeriod);

        return true;
    }
}

/**
 * Distributed Rate Limiter (สำหรับระบบหลายเซิร์ฟเวอร์)
 */
class DistributedRateLimiter extends RateLimiter {
    private $nodeId;
    private $totalNodes;

    public function __construct($nodeId = 1, $totalNodes = 1) {
        parent::__construct();
        $this->nodeId = $nodeId;
        $this->totalNodes = $totalNodes;
    }

    /**
     * ตรวจสอบ Rate Limit แบบกระจาย
     */
    public function checkDistributedLimit($identifier, $action, $maxRequests, $timeWindow) {
        // แบ่ง limit ตามจำนวน nodes
        $nodeLimit = ceil($maxRequests / $this->totalNodes);
        
        return $this->checkLimit($identifier, $action, $nodeLimit, $timeWindow);
    }

    /**
     * Sync ข้อมูลระหว่าง nodes
     */
    public function syncNodes() {
        if (!$this->useRedis) {
            return false;
        }

        // ใช้ Redis pub/sub หรือ shared storage เพื่อ sync ข้อมูล
        $syncKey = "rate_limit_sync:" . time();
        $nodeData = [
            'node_id' => $this->nodeId,
            'timestamp' => time(),
            'stats' => $this->getStatistics('5 MINUTE')
        ];

        $this->redis->setex($syncKey, 300, json_encode($nodeData));
        return true;
    }
}
?>

