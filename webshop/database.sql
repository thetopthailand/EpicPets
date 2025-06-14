-- Minecraft Webshop Database Schema
-- สร้างฐานข้อมูลสำหรับระบบ Webshop (ถ้าต้องการใช้ฐานข้อมูลแทน localStorage)

CREATE DATABASE IF NOT EXISTS minecraft_webshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minecraft_webshop;

-- ตารางผู้เล่น
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(16) NOT NULL UNIQUE,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
);

-- ตารางสินค้า
CREATE TABLE IF NOT EXISTS shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    command TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
);

-- ตารางประวัติการซื้อ
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price INT NOT NULL,
    command_executed TEXT NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    INDEX idx_player (player_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- ตารางประวัติการเติมพ้อย
CREATE TABLE IF NOT EXISTS point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    amount INT NOT NULL,
    type ENUM('add', 'subtract') NOT NULL,
    reason VARCHAR(255),
    admin_username VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    INDEX idx_player (player_id),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
);

-- ตารางการตั้งค่าระบบ
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ข้อมูลสินค้าเริ่มต้น
INSERT INTO shop_items (name, description, price, command, category) VALUES
('Diamond Sword', 'ดาบเพชรคมกริบ', 100, 'give {player} diamond_sword 1', 'weapons'),
('Iron Armor Set', 'ชุดเกราะเหล็กครบเซ็ต', 200, 'give {player} iron_helmet 1; give {player} iron_chestplate 1; give {player} iron_leggings 1; give {player} iron_boots 1', 'armor'),
('Enchanted Book', 'หนังสือเวทมนตร์', 150, 'give {player} enchanted_book 1', 'books'),
('Golden Apple', 'แอปเปิ้ลทอง (5 ลูก)', 50, 'give {player} golden_apple 5', 'food'),
('Elytra', 'ปีกบิน', 500, 'give {player} elytra 1', 'special'),
('Diamond Pickaxe', 'จอบเพชร', 120, 'give {player} diamond_pickaxe 1', 'tools'),
('Netherite Ingot', 'แท่งเนเธอไรท์', 300, 'give {player} netherite_ingot 1', 'materials'),
('Shulker Box', 'กล่องชัลเกอร์', 250, 'give {player} shulker_box 1', 'storage'),
('Totem of Undying', 'โทเท็มแห่งความไม่ตาย', 400, 'give {player} totem_of_undying 1', 'special'),
('Experience Bottle', 'ขวดประสบการณ์ (10 ขวด)', 75, 'give {player} experience_bottle 10', 'experience');

-- การตั้งค่าระบบเริ่มต้น
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('default_points', '100', 'พ้อยเริ่มต้นสำหรับผู้เล่นใหม่'),
('max_daily_points', '1000', 'พ้อยสูงสุดที่สามารถเติมได้ต่อวัน'),
('shop_enabled', '1', 'เปิด/ปิดระบบร้านค้า'),
('maintenance_mode', '0', 'โหมดปิดปรับปรุง'),
('site_title', 'Minecraft Webshop', 'ชื่อเว็บไซต์'),
('currency_name', 'พ้อย', 'ชื่อสกุลเงินในเกม');

-- สร้าง View สำหรับสถิติ
CREATE VIEW purchase_stats AS
SELECT 
    p.username,
    COUNT(pu.id) as total_purchases,
    SUM(pu.price) as total_spent,
    MAX(pu.created_at) as last_purchase
FROM players p
LEFT JOIN purchases pu ON p.id = pu.player_id
WHERE pu.status = 'completed'
GROUP BY p.id, p.username;

-- สร้าง View สำหรับสินค้าขายดี
CREATE VIEW popular_items AS
SELECT 
    si.name,
    si.price,
    COUNT(pu.id) as purchase_count,
    SUM(pu.price) as total_revenue
FROM shop_items si
LEFT JOIN purchases pu ON si.id = pu.item_id
WHERE pu.status = 'completed'
GROUP BY si.id, si.name, si.price
ORDER BY purchase_count DESC;

-- Stored Procedure สำหรับเพิ่มพ้อยให้ผู้เล่น
DELIMITER //
CREATE PROCEDURE AddPlayerPoints(
    IN p_username VARCHAR(16),
    IN p_amount INT,
    IN p_reason VARCHAR(255),
    IN p_admin VARCHAR(50)
)
BEGIN
    DECLARE player_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- หาหรือสร้างผู้เล่น
    SELECT id INTO player_id FROM players WHERE username = p_username;
    
    IF player_id IS NULL THEN
        INSERT INTO players (username, points) VALUES (p_username, p_amount);
        SET player_id = LAST_INSERT_ID();
    ELSE
        UPDATE players SET points = points + p_amount WHERE id = player_id;
    END IF;
    
    -- บันทึกประวัติการเติมพ้อย
    INSERT INTO point_transactions (player_id, amount, type, reason, admin_username)
    VALUES (player_id, p_amount, 'add', p_reason, p_admin);
    
    COMMIT;
END //
DELIMITER ;

-- Stored Procedure สำหรับการซื้อสินค้า
DELIMITER //
CREATE PROCEDURE PurchaseItem(
    IN p_username VARCHAR(16),
    IN p_item_id INT,
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE player_id INT;
    DECLARE player_points INT;
    DECLARE item_price INT;
    DECLARE item_name VARCHAR(100);
    DECLARE item_command TEXT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaction failed';
    END;

    START TRANSACTION;
    
    -- ตรวจสอบผู้เล่น
    SELECT id, points INTO player_id, player_points 
    FROM players WHERE username = p_username;
    
    IF player_id IS NULL THEN
        SET p_result = 'ERROR: Player not found';
        ROLLBACK;
    ELSE
        -- ตรวจสอบสินค้า
        SELECT price, name, command INTO item_price, item_name, item_command
        FROM shop_items WHERE id = p_item_id AND is_active = TRUE;
        
        IF item_price IS NULL THEN
            SET p_result = 'ERROR: Item not found or inactive';
            ROLLBACK;
        ELSEIF player_points < item_price THEN
            SET p_result = 'ERROR: Insufficient points';
            ROLLBACK;
        ELSE
            -- หักพ้อยและบันทึกการซื้อ
            UPDATE players SET points = points - item_price WHERE id = player_id;
            
            INSERT INTO purchases (player_id, item_id, item_name, price, command_executed, status)
            VALUES (player_id, p_item_id, item_name, item_price, 
                    REPLACE(item_command, '{player}', p_username), 'completed');
            
            INSERT INTO point_transactions (player_id, amount, type, reason)
            VALUES (player_id, item_price, 'subtract', CONCAT('Purchase: ', item_name));
            
            SET p_result = 'SUCCESS: Purchase completed';
            COMMIT;
        END IF;
    END IF;
END //
DELIMITER ;

