# 🛡️ คู่มือความปลอดภัย - Minecraft Webshop

## 🔒 ภาพรวมความปลอดภัย

ระบบ Minecraft Webshop ได้รับการออกแบบด้วยความปลอดภัยเป็นหลัก โดยมีระบบป้องกันหลายชั้น (Multi-layer Security) เพื่อปกป้องจากการโจมตีและการใช้งานที่ไม่เหมาะสม

## 🚫 ระบบ AntiHack ที่ใช้งาน

### 1. SQL Injection Protection
- **Prepared Statements**: ใช้ PDO prepared statements ทุกการ query
- **Input Validation**: ตรวจสอบและกรองข้อมูลนำเข้าทั้งหมด
- **Pattern Detection**: ตรวจจับ SQL injection patterns แบบ real-time

```php
// ตัวอย่างการป้องกัน SQL Injection
$patterns = [
    '/(\bunion\b.*\bselect\b)/i',
    '/(\bselect\b.*\bfrom\b)/i',
    '/(\binsert\b.*\binto\b)/i',
    // ... patterns อื่นๆ
];
```

### 2. Cross-Site Scripting (XSS) Protection
- **Output Encoding**: encode ข้อมูลทั้งหมดก่อนแสดงผล
- **Content Security Policy**: ใช้ CSP headers
- **Input Sanitization**: ทำความสะอาดข้อมูลนำเข้า

```php
// การป้องกัน XSS
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
```

### 3. Cross-Site Request Forgery (CSRF) Protection
- **CSRF Tokens**: สร้าง token สำหรับทุก form
- **Token Validation**: ตรวจสอบ token ทุกการส่งข้อมูล
- **SameSite Cookies**: ใช้ SameSite attribute

### 4. Path Traversal Protection
- **Path Validation**: ตรวจสอบ path ที่ไม่ปลอดภัย
- **Directory Restriction**: จำกัดการเข้าถึงไฟล์
- **Pattern Detection**: ตรวจจับ `../` และ variants

### 5. Command Injection Protection
- **Command Whitelist**: อนุญาตเฉพาะคำสั่งที่กำหนด
- **Parameter Validation**: ตรวจสอบ parameters ทั้งหมด
- **Dangerous Character Filtering**: กรอง characters อันตราย

## 🔐 ระบบ Authentication และ Authorization

### JWT (JSON Web Token) Authentication
```php
// การสร้าง JWT Token
public function createToken($userId, $username, $role = 'user') {
    $payload = [
        'user_id' => $userId,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + $this->tokenExpiry,
        'jti' => bin2hex(random_bytes(16))
    ];
    // ... token creation logic
}
```

### Session Management
- **Secure Sessions**: ใช้ secure session configuration
- **Session Regeneration**: regenerate session ID หลัง login
- **Session Timeout**: timeout อัตโนมัติ

### Role-Based Access Control (RBAC)
- **User Roles**: user, moderator, admin
- **Permission Checking**: ตรวจสอบสิทธิ์ทุกการเข้าถึง
- **Hierarchical Permissions**: ระบบสิทธิ์แบบลำดับชั้น

## ⚡ Rate Limiting และ DDoS Protection

### Rate Limiting System
```php
// การจำกัดอัตราการเข้าถึง
$limits = [
    '/api/auth/login' => ['requests' => 5, 'window' => 300],
    '/api/shop/purchase' => ['requests' => 10, 'window' => 60],
    'default' => ['requests' => 100, 'window' => 60]
];
```

### IP Management
- **IP Whitelist**: รายชื่อ IP ที่อนุญาต
- **IP Blacklist**: รายชื่อ IP ที่ถูกบล็อก
- **Automatic Blocking**: บล็อก IP อัตโนมัติเมื่อมีพฤติกรรมผิดปกติ

### Bot Detection
- **User Agent Analysis**: วิเคราะห์ User Agent
- **Behavioral Analysis**: วิเคราะห์พฤติกรรมการใช้งาน
- **CAPTCHA Integration**: รองรับ CAPTCHA (ถ้าต้องการ)

## 🔍 Security Monitoring และ Logging

### Security Event Logging
```sql
-- ตารางบันทึก Security Events
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Real-time Threat Detection
- **Pattern Matching**: ตรวจจับ patterns ที่น่าสงสัย
- **Anomaly Detection**: ตรวจจับพฤติกรรมผิดปกติ
- **Alert System**: ส่ง alert เมื่อพบภัยคุกคาม

### Audit Trail
- **User Actions**: บันทึกการกระทำของผู้ใช้ทั้งหมด
- **Admin Activities**: บันทึกการกระทำของ admin
- **System Events**: บันทึกเหตุการณ์ของระบบ

## 🛠️ RCON Security

### Command Validation
```php
// การตรวจสอบคำสั่ง RCON
private function validateAndSanitizeCommand($command) {
    // ลบ characters ที่อันตราย
    $command = preg_replace('/[^\w\s\-\.\@\{\}\/\:]/u', '', $command);
    
    // ตรวจสอบคำสั่งที่อนุญาต
    $allowedCommands = $this->getAllowedCommands();
    // ... validation logic
}
```

### Permission System
- **Command Permissions**: กำหนดสิทธิ์สำหรับแต่ละคำสั่ง
- **Role-based Commands**: คำสั่งที่แตกต่างตาม role
- **Dangerous Command Blocking**: บล็อกคำสั่งอันตราย

### Connection Security
- **Connection Pool**: จัดการ connection อย่างปลอดภัย
- **Timeout Management**: จัดการ timeout
- **Error Handling**: จัดการ error อย่างเหมาะสม

## 🌐 Network Security

### HTTPS/TLS
- **Force HTTPS**: บังคับใช้ HTTPS
- **HSTS Headers**: HTTP Strict Transport Security
- **TLS Configuration**: ใช้ TLS version ที่ปลอดภัย

### Security Headers
```php
// Security Headers ที่ใช้งาน
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'');
```

### CORS Configuration
- **Origin Validation**: ตรวจสอบ origin ที่อนุญาต
- **Method Restriction**: จำกัด HTTP methods
- **Header Control**: ควบคุม headers ที่อนุญาต

## 🔧 Configuration Security

### Environment Variables
```env
# ตัวอย่างการตั้งค่าที่ปลอดภัย
JWT_SECRET=very_long_and_random_secret_key_here
DB_PASS=strong_database_password
RCON_PASSWORD=strong_rcon_password
```

### File Permissions
```bash
# การตั้งค่าสิทธิ์ไฟล์ที่ปลอดภัย
chmod 600 .env                    # ไฟล์ config
chmod 644 *.php                   # PHP files
chmod 755 directories/            # Directories
chmod 777 logs/                   # Log directories
```

### Database Security
- **User Privileges**: จำกัดสิทธิ์ database user
- **Connection Encryption**: ใช้ SSL สำหรับ database connection
- **Regular Backups**: สำรองข้อมูลสม่ำเสมอ

## 🚨 Incident Response

### Security Incident Handling
1. **Detection**: ตรวจจับภัยคุกคาม
2. **Analysis**: วิเคราะห์ภัยคุกคาม
3. **Containment**: ควบคุมภัยคุกคาม
4. **Eradication**: กำจัดภัยคุกคาม
5. **Recovery**: กู้คืนระบบ
6. **Lessons Learned**: เรียนรู้จากเหตุการณ์

### Automated Response
```php
// การตอบสนองอัตโนมัติ
private function handleSecurityThreat($threatLevel, $details) {
    switch ($threatLevel) {
        case 'critical':
            $this->blockIPImmediate($details['ip']);
            $this->sendAlertToAdmin($details);
            break;
        case 'high':
            $this->addToTempBlacklist($details['ip'], 3600);
            $this->logSecurityEvent($details);
            break;
        // ... other cases
    }
}
```

## 📊 Security Metrics และ KPIs

### Key Security Indicators
- **Failed Login Attempts**: จำนวนการ login ที่ล้มเหลว
- **Blocked IPs**: จำนวน IP ที่ถูกบล็อก
- **Security Events**: จำนวน security events
- **Response Time**: เวลาในการตอบสนองต่อภัยคุกคาม

### Regular Security Assessments
- **Vulnerability Scanning**: สแกนหาช่องโหว่
- **Penetration Testing**: ทดสอบการเจาะระบบ
- **Code Review**: ตรวจสอบโค้ด
- **Security Audits**: ตรวจสอบความปลอดภัย

## 🔄 Security Updates และ Maintenance

### Regular Updates
- **Security Patches**: อัปเดต security patches
- **Dependency Updates**: อัปเดต dependencies
- **Configuration Reviews**: ทบทวนการตั้งค่า

### Backup และ Recovery
- **Regular Backups**: สำรองข้อมูลสม่ำเสมอ
- **Backup Testing**: ทดสอบการกู้คืนข้อมูล
- **Disaster Recovery Plan**: แผนกู้คืนจากภัยพิบัติ

## 📋 Security Checklist

### Pre-deployment Checklist
- [ ] เปลี่ยนรหัสผ่าน default ทั้งหมด
- [ ] ตั้งค่า SSL/TLS
- [ ] กำหนดค่า firewall
- [ ] ตั้งค่า backup
- [ ] ทดสอบ security features
- [ ] ตรวจสอบ file permissions
- [ ] กำหนดค่า monitoring
- [ ] ทดสอบ incident response

### Regular Maintenance
- [ ] ตรวจสอบ security logs
- [ ] อัปเดต software
- [ ] ทบทวน user permissions
- [ ] ทดสอบ backup
- [ ] ตรวจสอบ SSL certificate
- [ ] วิเคราะห์ security metrics
- [ ] ทบทวน security policies

## 🆘 Emergency Contacts

### Security Incident Response Team
- **Primary Contact**: admin@yourserver.com
- **Secondary Contact**: security@yourserver.com
- **Emergency Phone**: +66-xxx-xxx-xxxx

### Escalation Procedures
1. **Level 1**: Automated response
2. **Level 2**: Admin notification
3. **Level 3**: Emergency response team
4. **Level 4**: External security experts

---

## 📚 Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [MySQL Security Guidelines](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)
- [Minecraft Server Security](https://minecraft.fandom.com/wiki/Tutorials/Server_security)

**🔒 ความปลอดภัยเป็นความรับผิดชอบของทุกคน - ร่วมกันปกป้องระบบ!**

