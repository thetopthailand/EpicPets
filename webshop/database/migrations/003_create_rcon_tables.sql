-- Migration: Create RCON-related tables
-- สร้างตารางสำหรับจัดการ RCON commands และ monitoring

-- สร้างตารางสำหรับ RCON commands
CREATE TABLE IF NOT EXISTS rcon_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command TEXT NOT NULL,
    player_id INT NULL,
    priority ENUM('low', 'normal', 'high', 'immediate') DEFAULT 'normal',
    status ENUM('pending', 'queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    result TEXT NULL,
    error_message TEXT NULL,
    execution_time DECIMAL(10,4) NULL, -- in seconds
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_player_id (player_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_executed_at (executed_at),
    INDEX idx_retry_count (retry_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ command queue
CREATE TABLE IF NOT EXISTS command_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command_id INT NOT NULL,
    command TEXT NOT NULL,
    priority INT DEFAULT 5, -- 1-10, higher = more priority
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (command_id) REFERENCES rcon_commands(id) ON DELETE CASCADE,
    INDEX idx_command_id (command_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ RCON errors
CREATE TABLE IF NOT EXISTS rcon_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command TEXT NOT NULL,
    error_message TEXT NOT NULL,
    error_code VARCHAR(50) NULL,
    player_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    stack_trace TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_player_id (player_id),
    INDEX idx_error_code (error_code),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ RCON server monitoring
CREATE TABLE IF NOT EXISTS rcon_server_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(100) DEFAULT 'main',
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    is_online BOOLEAN DEFAULT FALSE,
    response_time DECIMAL(10,4) NULL, -- in seconds
    last_error TEXT NULL,
    player_count INT NULL,
    max_players INT NULL,
    server_version VARCHAR(100) NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_server_name (server_name),
    INDEX idx_is_online (is_online),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ RCON connection pool
CREATE TABLE IF NOT EXISTS rcon_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connection_id VARCHAR(64) NOT NULL UNIQUE,
    server_name VARCHAR(100) DEFAULT 'main',
    is_active BOOLEAN DEFAULT TRUE,
    in_use BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    connection_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    
    INDEX idx_connection_id (connection_id),
    INDEX idx_server_name (server_name),
    INDEX idx_is_active (is_active),
    INDEX idx_in_use (in_use),
    INDEX idx_last_used (last_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ command templates
CREATE TABLE IF NOT EXISTS command_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    command_template TEXT NOT NULL,
    parameters JSON NULL, -- JSON object defining parameters
    category VARCHAR(50) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_usage_count (usage_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ scheduled commands
CREATE TABLE IF NOT EXISTS scheduled_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    command TEXT NOT NULL,
    schedule_type ENUM('once', 'daily', 'weekly', 'monthly', 'cron') NOT NULL,
    schedule_value VARCHAR(100) NULL, -- cron expression or specific time
    next_run TIMESTAMP NULL,
    last_run TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    run_count INT DEFAULT 0,
    max_runs INT DEFAULT -1, -- -1 = unlimited
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_schedule_type (schedule_type),
    INDEX idx_next_run (next_run),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ command permissions
CREATE TABLE IF NOT EXISTS command_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    command_pattern VARCHAR(255) NOT NULL,
    required_role ENUM('user', 'moderator', 'admin') DEFAULT 'user',
    required_permission VARCHAR(100) NULL,
    is_allowed BOOLEAN DEFAULT TRUE,
    description TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_command_pattern (command_pattern),
    INDEX idx_required_role (required_role),
    INDEX idx_is_allowed (is_allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ player online status
CREATE TABLE IF NOT EXISTS player_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(16) NOT NULL,
    user_id INT NULL,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen TIMESTAMP NULL,
    server_name VARCHAR(100) DEFAULT 'main',
    ip_address VARCHAR(45) NULL,
    game_mode VARCHAR(20) NULL,
    location_x DECIMAL(10,2) NULL,
    location_y DECIMAL(10,2) NULL,
    location_z DECIMAL(10,2) NULL,
    dimension VARCHAR(50) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_username_server (username, server_name),
    INDEX idx_username (username),
    INDEX idx_user_id (user_id),
    INDEX idx_is_online (is_online),
    INDEX idx_last_seen (last_seen),
    INDEX idx_server_name (server_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่ม command templates เริ่มต้น
INSERT INTO command_templates (name, description, command_template, parameters, category) VALUES
('give_item', 'ให้ไอเทมแก่ผู้เล่น', 'give {player} {item} {quantity}', 
 '{"player": {"type": "string", "required": true}, "item": {"type": "string", "required": true}, "quantity": {"type": "integer", "default": 1}}', 
 'items'),
('teleport_player', 'เทเลพอร์ตผู้เล่น', 'tp {player} {x} {y} {z}', 
 '{"player": {"type": "string", "required": true}, "x": {"type": "number", "required": true}, "y": {"type": "number", "required": true}, "z": {"type": "number", "required": true}}', 
 'teleport'),
('give_experience', 'ให้ประสบการณ์แก่ผู้เล่น', 'xp add {player} {amount} {type}', 
 '{"player": {"type": "string", "required": true}, "amount": {"type": "integer", "required": true}, "type": {"type": "string", "default": "points", "options": ["points", "levels"]}}', 
 'experience'),
('set_gamemode', 'ตั้งค่าโหมดเกม', 'gamemode {mode} {player}', 
 '{"player": {"type": "string", "required": true}, "mode": {"type": "string", "required": true, "options": ["survival", "creative", "adventure", "spectator"]}}', 
 'gamemode'),
('send_message', 'ส่งข้อความให้ผู้เล่น', 'tellraw {player} {message}', 
 '{"player": {"type": "string", "required": true}, "message": {"type": "string", "required": true}}', 
 'communication'),
('weather_control', 'ควบคุมสภาพอากาศ', 'weather {type} {duration}', 
 '{"type": {"type": "string", "required": true, "options": ["clear", "rain", "thunder"]}, "duration": {"type": "integer", "default": 1000}}', 
 'world'),
('time_control', 'ควบคุมเวลา', 'time set {time}', 
 '{"time": {"type": "string", "required": true, "options": ["day", "night", "noon", "midnight"]}}', 
 'world')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- เพิ่ม command permissions เริ่มต้น
INSERT INTO command_permissions (command_pattern, required_role, is_allowed, description) VALUES
('give %', 'admin', TRUE, 'อนุญาตให้ admin ใช้คำสั่ง give'),
('tp %', 'moderator', TRUE, 'อนุญาตให้ moderator ใช้คำสั่ง teleport'),
('gamemode %', 'moderator', TRUE, 'อนุญาตให้ moderator เปลี่ยน gamemode'),
('op %', 'admin', FALSE, 'ห้ามใช้คำสั่ง op'),
('deop %', 'admin', FALSE, 'ห้ามใช้คำสั่ง deop'),
('stop', 'admin', FALSE, 'ห้ามใช้คำสั่ง stop server'),
('restart', 'admin', FALSE, 'ห้ามใช้คำสั่ง restart server'),
('ban %', 'admin', FALSE, 'ห้ามใช้คำสั่ง ban ผ่าน webshop'),
('kick %', 'moderator', FALSE, 'ห้ามใช้คำสั่ง kick ผ่าน webshop'),
('whitelist %', 'admin', FALSE, 'ห้ามจัดการ whitelist ผ่าน webshop')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- สร้าง Views สำหรับ RCON statistics
CREATE OR REPLACE VIEW rcon_command_stats AS
SELECT 
    DATE(created_at) as command_date,
    status,
    priority,
    COUNT(*) as command_count,
    AVG(execution_time) as avg_execution_time,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_commands,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_commands
FROM rcon_commands
GROUP BY DATE(created_at), status, priority
ORDER BY command_date DESC, priority DESC;

CREATE OR REPLACE VIEW rcon_error_summary AS
SELECT 
    DATE(created_at) as error_date,
    error_code,
    COUNT(*) as error_count,
    COUNT(DISTINCT player_id) as affected_players
FROM rcon_errors
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), error_code
ORDER BY error_date DESC, error_count DESC;

CREATE OR REPLACE VIEW server_performance AS
SELECT 
    server_name,
    AVG(response_time) as avg_response_time,
    MIN(response_time) as min_response_time,
    MAX(response_time) as max_response_time,
    SUM(CASE WHEN is_online = TRUE THEN 1 ELSE 0 END) / COUNT(*) * 100 as uptime_percentage,
    COUNT(*) as total_checks,
    MAX(checked_at) as last_check
FROM rcon_server_status
WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY server_name;

-- สร้าง Triggers สำหรับ automatic cleanup
DELIMITER //

CREATE TRIGGER update_command_queue_on_completion
AFTER UPDATE ON rcon_commands
FOR EACH ROW
BEGIN
    IF NEW.status IN ('completed', 'failed', 'cancelled') AND OLD.status != NEW.status THEN
        UPDATE command_queue 
        SET status = NEW.status, processed_at = NOW() 
        WHERE command_id = NEW.id;
    END IF;
END//

CREATE TRIGGER increment_template_usage
AFTER INSERT ON rcon_commands
FOR EACH ROW
BEGIN
    DECLARE template_name VARCHAR(100);
    
    -- ตรวจหา template ที่ตรงกับ command
    SELECT name INTO template_name
    FROM command_templates
    WHERE NEW.command LIKE CONCAT(REPLACE(command_template, '{%}', '%'), '%')
    LIMIT 1;
    
    IF template_name IS NOT NULL THEN
        UPDATE command_templates 
        SET usage_count = usage_count + 1 
        WHERE name = template_name;
    END IF;
END//

DELIMITER ;

