-- Migration: Create shop-related tables
-- สร้างตารางที่เกี่ยวข้องกับร้านค้าและการซื้อขาย

-- สร้างตารางหมวดหมู่สินค้า
CREATE TABLE IF NOT EXISTS item_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสินค้า
CREATE TABLE IF NOT EXISTS shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price INT NOT NULL,
    original_price INT NULL,
    category_id INT NULL,
    command TEXT NOT NULL,
    icon VARCHAR(255) NULL,
    image_url VARCHAR(500) NULL,
    stock_quantity INT DEFAULT -1, -- -1 = unlimited
    max_per_user INT DEFAULT -1, -- -1 = unlimited
    min_level INT DEFAULT 0,
    required_permissions TEXT NULL,
    sale_start TIMESTAMP NULL,
    sale_end TIMESTAMP NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_price (price),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_sort_order (sort_order),
    INDEX idx_sale_period (sale_start, sale_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางการซื้อขาย
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    item_price INT NOT NULL,
    quantity INT DEFAULT 1,
    total_amount INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method ENUM('points', 'credits', 'external') DEFAULT 'points',
    transaction_hash VARCHAR(64) NULL,
    rcon_command TEXT NULL,
    rcon_response TEXT NULL,
    error_message TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_hash (transaction_hash),
    INDEX idx_payment_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางประวัติการเปลี่ยนแปลงพ้อย
CREATE TABLE IF NOT EXISTS point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    type ENUM('add', 'subtract', 'purchase', 'refund', 'bonus', 'admin_adjustment') NOT NULL,
    reason VARCHAR(255) NULL,
    reference_type ENUM('transaction', 'manual', 'bonus', 'system') DEFAULT 'manual',
    reference_id INT NULL,
    admin_user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ user purchase history
CREATE TABLE IF NOT EXISTS user_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_spent INT NOT NULL,
    first_purchase TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_purchase TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    purchase_count INT DEFAULT 1,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    INDEX idx_last_purchase (last_purchase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ shopping cart
CREATE TABLE IF NOT EXISTS shopping_cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_added_at (added_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ discount codes/coupons
CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    type ENUM('percentage', 'fixed_amount') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    min_purchase_amount INT DEFAULT 0,
    max_discount_amount INT NULL,
    usage_limit INT DEFAULT -1, -- -1 = unlimited
    usage_count INT DEFAULT 0,
    user_usage_limit INT DEFAULT 1,
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP NULL,
    applicable_categories TEXT NULL, -- JSON array of category IDs
    applicable_items TEXT NULL, -- JSON array of item IDs
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_valid_period (valid_from, valid_until),
    INDEX idx_usage (usage_count, usage_limit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ discount code usage
CREATE TABLE IF NOT EXISTS discount_code_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discount_code_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_id INT NULL,
    discount_amount INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    INDEX idx_discount_code (discount_code_id),
    INDEX idx_user_id (user_id),
    INDEX idx_used_at (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างตารางสำหรับ wishlist
CREATE TABLE IF NOT EXISTS user_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_added_at (added_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มข้อมูลหมวดหมู่เริ่มต้น
INSERT INTO item_categories (name, description, icon, sort_order) VALUES
('weapons', 'อาวุธและเครื่องมือต่อสู้', '🗡️', 1),
('armor', 'เกราะและอุปกรณ์ป้องกัน', '🛡️', 2),
('tools', 'เครื่องมือและอุปกรณ์', '⛏️', 3),
('food', 'อาหารและยาชูกำลัง', '🍖', 4),
('blocks', 'บล็อกและวัสดุก่อสร้าง', '🧱', 5),
('special', 'ไอเทมพิเศษและหายาก', '✨', 6),
('experience', 'ประสบการณ์และระดับ', '⭐', 7),
('currency', 'เงินและสกุลเงินในเกม', '💰', 8)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- เพิ่มสินค้าตัวอย่าง
INSERT INTO shop_items (name, description, price, category_id, command, icon, is_featured, sort_order) VALUES
('Diamond Sword', 'ดาบเพชรคมกริบ สำหรับการต่อสู้', 100, 1, 'give {player} diamond_sword 1', '💎', TRUE, 1),
('Iron Armor Set', 'ชุดเกราะเหล็กครบเซ็ต', 200, 2, 'give {player} iron_helmet 1; give {player} iron_chestplate 1; give {player} iron_leggings 1; give {player} iron_boots 1', '⚔️', TRUE, 2),
('Enchanted Book', 'หนังสือเวทมนตร์สุ่ม', 150, 6, 'give {player} enchanted_book 1', '📚', FALSE, 3),
('Golden Apple', 'แอปเปิ้ลทอง 5 ลูก', 50, 4, 'give {player} golden_apple 5', '🍎', FALSE, 4),
('Elytra', 'ปีกบินสำหรับการเดินทาง', 500, 6, 'give {player} elytra 1', '🪶', TRUE, 5),
('Diamond Pickaxe', 'จอบเพชรสำหรับขุด', 120, 3, 'give {player} diamond_pickaxe 1', '⛏️', FALSE, 6),
('Experience Bottle', 'ขวดประสบการณ์ 10 ขวด', 75, 7, 'give {player} experience_bottle 10', '🧪', FALSE, 7),
('Netherite Ingot', 'แท่งเนเธอไรท์หายาก', 300, 6, 'give {player} netherite_ingot 1', '🔥', TRUE, 8),
('Shulker Box', 'กล่องชัลเกอร์สำหรับเก็บของ', 250, 5, 'give {player} shulker_box 1', '📦', FALSE, 9),
('Totem of Undying', 'โทเท็มแห่งความไม่ตาย', 400, 6, 'give {player} totem_of_undying 1', '🏺', TRUE, 10)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- สร้าง Views สำหรับการ query ที่ซับซ้อน
CREATE OR REPLACE VIEW popular_items AS
SELECT 
    si.id,
    si.name,
    si.price,
    si.category_id,
    ic.name as category_name,
    COUNT(t.id) as purchase_count,
    SUM(t.total_amount) as total_revenue,
    AVG(t.total_amount) as avg_purchase_amount
FROM shop_items si
LEFT JOIN transactions t ON si.id = t.item_id AND t.status = 'completed'
LEFT JOIN item_categories ic ON si.category_id = ic.id
WHERE si.is_active = TRUE
GROUP BY si.id, si.name, si.price, si.category_id, ic.name
ORDER BY purchase_count DESC, total_revenue DESC;

CREATE OR REPLACE VIEW user_statistics AS
SELECT 
    u.id,
    u.username,
    u.points,
    COUNT(t.id) as total_purchases,
    SUM(t.total_amount) as total_spent,
    MAX(t.created_at) as last_purchase,
    COUNT(DISTINCT t.item_id) as unique_items_purchased
FROM users u
LEFT JOIN transactions t ON u.id = t.user_id AND t.status = 'completed'
WHERE u.is_active = TRUE
GROUP BY u.id, u.username, u.points;

CREATE OR REPLACE VIEW daily_sales AS
SELECT 
    DATE(created_at) as sale_date,
    COUNT(*) as transaction_count,
    SUM(total_amount) as total_revenue,
    COUNT(DISTINCT user_id) as unique_customers,
    AVG(total_amount) as avg_transaction_value
FROM transactions
WHERE status = 'completed'
GROUP BY DATE(created_at)
ORDER BY sale_date DESC;

