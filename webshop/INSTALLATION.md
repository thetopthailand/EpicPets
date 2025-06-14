# 🚀 คู่มือการติดตั้ง Minecraft Webshop

## 📋 ความต้องการของระบบ

### เซิร์ฟเวอร์
- **PHP**: 7.4 หรือสูงกว่า (แนะนำ 8.0+)
- **MySQL**: 5.7 หรือสูงกว่า (แนะนำ 8.0+)
- **Web Server**: Apache หรือ Nginx
- **Extensions**: PDO, PDO_MySQL, JSON, OpenSSL, mbstring

### Minecraft Server
- **RCON**: เปิดใช้งานแล้ว
- **Port**: 25575 (หรือตามที่กำหนด)
- **Password**: ตั้งรหัสผ่าน RCON ที่แข็งแกร่ง

### Optional (สำหรับประสิทธิภาพดีขึ้น)
- **Redis**: สำหรับ caching และ rate limiting
- **SSL Certificate**: สำหรับ HTTPS

## 🔧 ขั้นตอนการติดตั้ง

### 1. เตรียมฐานข้อมูล

```sql
-- สร้างฐานข้อมูล
CREATE DATABASE minecraft_webshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- สร้าง user สำหรับฐานข้อมูล
CREATE USER 'webshop_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON minecraft_webshop.* TO 'webshop_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. อัปโหลดไฟล์

อัปโหลดไฟล์ทั้งหมดไปยัง web server ของคุณ

```bash
# ตัวอย่างการใช้ rsync
rsync -avz webshop/ user@yourserver.com:/var/www/html/webshop/
```

### 3. ตั้งค่า Environment

```bash
# คัดลอกไฟล์ environment
cp .env.example .env

# แก้ไขการตั้งค่า
nano .env
```

แก้ไขค่าต่างๆ ในไฟล์ `.env`:

```env
# Database
DB_HOST=localhost
DB_NAME=minecraft_webshop
DB_USER=webshop_user
DB_PASS=your_database_password

# RCON
RCON_HOST=your_minecraft_server_ip
RCON_PORT=25575
RCON_PASSWORD=your_rcon_password

# JWT Secret (สร้างใหม่)
JWT_SECRET=your_very_long_and_secure_jwt_secret_key_here
```

### 4. ตั้งค่าสิทธิ์ไฟล์

```bash
# ตั้งค่าสิทธิ์ไฟล์
chmod -R 755 webshop/
chmod -R 777 webshop/logs/
chmod 600 webshop/.env

# เปลี่ยน owner (ถ้าจำเป็น)
chown -R www-data:www-data webshop/
```

### 5. รัน Database Migrations

เข้าไปที่ `webshop/backend/config/database.php` และรัน migrations:

```php
<?php
require_once 'database.php';

$migration = new DatabaseMigration();
$migration->runMigrations();
?>
```

หรือรันผ่าน command line:

```bash
php -r "
require_once 'webshop/backend/config/database.php';
\$migration = new DatabaseMigration();
\$migration->runMigrations();
echo 'Migrations completed successfully!';
"
```

### 6. ตั้งค่า Web Server

#### Apache (.htaccess)

สร้างไฟล์ `.htaccess` ในโฟลเดอร์ `webshop/`:

```apache
RewriteEngine On

# Redirect to HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API Routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^backend/api/(.*)$ backend/api/index.php [QSA,L]

# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Hide sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx

เพิ่มใน server block:

```nginx
server {
    listen 443 ssl http2;
    server_name yourserver.com;
    root /var/www/html/webshop;
    index index.html index.php;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # Security Headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # API Routes
    location ~ ^/backend/api/(.*)$ {
        try_files $uri $uri/ /backend/api/index.php?$query_string;
    }

    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Hide sensitive files
    location ~ /\.(env|git) {
        deny all;
    }

    location ~ \.sql$ {
        deny all;
    }
}
```

### 7. ตั้งค่า Minecraft Server

แก้ไขไฟล์ `server.properties`:

```properties
# เปิดใช้งาน RCON
enable-rcon=true
rcon.port=25575
rcon.password=your_strong_rcon_password

# ตั้งค่าความปลอดภัย
broadcast-rcon-to-ops=false
```

รีสตาร์ท Minecraft Server หลังจากแก้ไขการตั้งค่า

### 8. ทดสอบระบบ

#### ทดสอบ API

```bash
# Health Check
curl https://yourserver.com/webshop/backend/api/health

# ทดสอบ RCON
curl -X POST https://yourserver.com/webshop/backend/api/rcon/status \
  -H "Content-Type: application/json"
```

#### ทดสอบการเข้าสู่ระบบ

1. เข้าไปที่ `https://yourserver.com/webshop/`
2. ลองเข้าสู่ระบบด้วย username: `admin`, password: `admin123`
3. **เปลี่ยนรหัสผ่าน admin ทันที!**

## 🔒 การตั้งค่าความปลอดภัย

### 1. เปลี่ยนรหัสผ่าน Admin

```sql
-- เปลี่ยนรหัสผ่าน admin (ใช้ password ที่แข็งแกร่ง)
UPDATE users SET password_hash = '$argon2id$v=19$m=65536,t=4,p=3$...' WHERE username = 'admin';
```

### 2. ตั้งค่า Firewall

```bash
# อนุญาตเฉพาะ port ที่จำเป็น
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 25565/tcp  # Minecraft
ufw allow 22/tcp     # SSH

# ปิด RCON port จากภายนอก (ใช้ internal เท่านั้น)
ufw deny 25575/tcp
```

### 3. ตั้งค่า SSL/TLS

ใช้ Let's Encrypt สำหรับ SSL certificate ฟรี:

```bash
# ติดตั้ง Certbot
apt install certbot python3-certbot-apache

# สร้าง certificate
certbot --apache -d yourserver.com
```

### 4. ตั้งค่า Backup

สร้าง script สำหรับ backup อัตโนมัติ:

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/webshop"

# สร้างโฟลเดอร์ backup
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u webshop_user -p minecraft_webshop > $BACKUP_DIR/database_$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/webshop

# ลบ backup เก่า (เก็บไว้ 30 วัน)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

เพิ่มใน crontab:

```bash
# รัน backup ทุกวันเวลา 2:00 AM
0 2 * * * /path/to/backup.sh
```

## 🔧 การปรับแต่งเพิ่มเติม

### 1. ตั้งค่า Redis (Optional)

```bash
# ติดตั้ง Redis
apt install redis-server

# ตั้งค่า Redis
echo "requirepass your_redis_password" >> /etc/redis/redis.conf
systemctl restart redis
```

### 2. ตั้งค่า Monitoring

สร้าง script สำหรับ monitoring:

```bash
#!/bin/bash
# monitor.sh

# ตรวจสอบ MySQL
if ! mysqladmin ping -h localhost --silent; then
    echo "MySQL is down!" | mail -s "Alert: MySQL Down" admin@yourserver.com
fi

# ตรวจสอบ RCON
if ! nc -z localhost 25575; then
    echo "RCON is not responding!" | mail -s "Alert: RCON Down" admin@yourserver.com
fi
```

### 3. ตั้งค่า Log Rotation

```bash
# สร้างไฟล์ /etc/logrotate.d/webshop
/var/www/html/webshop/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

## 🚨 การแก้ไขปัญหา

### ปัญหาที่พบบ่อย

#### 1. Database Connection Error
```bash
# ตรวจสอบการเชื่อมต่อ
mysql -u webshop_user -p minecraft_webshop

# ตรวจสอบ PHP extensions
php -m | grep -i pdo
```

#### 2. RCON Connection Failed
```bash
# ทดสอบ RCON connection
telnet your_minecraft_server_ip 25575

# ตรวจสอบ firewall
ufw status
```

#### 3. Permission Denied
```bash
# ตั้งค่าสิทธิ์ใหม่
chown -R www-data:www-data /var/www/html/webshop
chmod -R 755 /var/www/html/webshop
```

#### 4. 500 Internal Server Error
```bash
# ตรวจสอบ error log
tail -f /var/log/apache2/error.log
# หรือ
tail -f /var/log/nginx/error.log
```

## 📞 การสนับสนุน

หากพบปัญหาในการติดตั้ง:

1. ตรวจสอบ log files ใน `/var/log/`
2. ตรวจสอบ PHP error log
3. ตรวจสอบการตั้งค่า `.env`
4. ตรวจสอบสิทธิ์ไฟล์และโฟลเดอร์

---

**🎉 ยินดีด้วย! ระบบ Minecraft Webshop ของคุณพร้อมใช้งานแล้ว!**

