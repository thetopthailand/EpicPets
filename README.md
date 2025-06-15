# Minecraft Webshop - ระบบร้านค้าออนไลน์สำหรับเซิร์ฟเวอร์ Minecraft

ระบบร้านค้าออนไลน์ที่ปลอดภัยและใช้งานง่ายสำหรับเซิร์ฟเวอร์ Minecraft โดยใช้ระบบพ้อยท์และ RCON

## 🔒 ความปลอดภัย 100%

- **เข้ารหัสข้อมูล**: ใช้ AES-256-CBC เข้ารหัสข้อมูลทั้งหมด
- **ป้องกันการโจมตี**: XSS, CSRF, SQL Injection, Brute Force
- **Session ปลอดภัย**: การจัดการ session ที่เข้มงวด
- **File Permissions**: การตั้งค่าสิทธิ์ไฟล์ที่ปลอดภัย
- **Input Validation**: ตรวจสอบข้อมูลนำเข้าอย่างเข้มงวด
- **Security Logging**: บันทึกเหตุการณ์ด้านความปลอดภัย
- **Rate Limiting**: จำกัดการเข้าถึงเพื่อป้องกันการโจมตี

## 🚀 คุณสมบัติหลัก

- **ระบบพ้อยท์**: ใช้ระบบพ้อยท์แทน SQL Database
- **RCON Integration**: เชื่อมต่อกับเซิร์ฟเวอร์ผ่าน RCON
- **Server Status**: แสดงสถานะเซิร์ฟเวอร์แบบ Real-time
- **Admin Panel**: ระบบจัดการที่ครบครัน
- **Responsive Design**: รองรับทุกอุปกรณ์
- **Installation Wizard**: ติดตั้งง่ายแบบ Step-by-step

## 📋 ความต้องการระบบ

- PHP 7.4 หรือสูงกว่า
- OpenSSL Extension
- JSON Extension
- cURL Extension
- Web Server (Apache/Nginx)
- RCON เปิดใช้งานในเซิร์ฟเวอร์ Minecraft

## 🛠️ การติดตั้ง

1. **อัปโหลดไฟล์**: อัปโหลดไฟล์ทั้งหมดไปยัง Web Server
2. **ตั้งค่าสิทธิ์**: ตั้งค่าสิทธิ์ไฟล์และโฟลเดอร์
   ```bash
   chmod 755 .
   chmod 644 *.php
   chmod 700 data/ config/ logs/ backups/
   ```
3. **เข้าสู่ระบบติดตั้ง**: เปิดเว็บไซต์และทำตาม Installation Wizard
4. **กำหนดค่า RCON**: ตั้งค่า RCON ในเซิร์ฟเวอร์ Minecraft
   ```properties
   # server.properties
   enable-rcon=true
   rcon.port=25575
   rcon.password=your_secure_password
   ```

## 📁 โครงสร้างไฟล์

```
minecraft-webshop/
├── index.php              # หน้าหลักสำหรับผู้ใช้
├── backend.php            # ระบบจัดการแอดมิน
├── rcon.php              # API endpoint สำหรับ RCON
├── install.php           # ระบบติดตั้ง
├── maintenance.html      # หน้าปิดปรุงระบบ
├── .htaccess            # การตั้งค่าความปลอดภัย
├── includes/
│   ├── security.php      # ระบบความปลอดภัย
│   ├── data_manager.php  # จัดการข้อมูล
│   └── rcon_handler.php  # จัดการ RCON
├── data/                # ข้อมูลเข้ารหัส (ห้ามเข้าถึงจาก Web)
├── config/              # ไฟล์การตั้งค่า (ห้ามเข้าถึงจาก Web)
├── logs/                # Log ไฟล์ (ห้ามเข้าถึงจาก Web)
└── backups/             # ไฟล์สำรองข้อมูล
```

## 🎮 การใช้งาน

### สำหรับผู้เล่น
1. เข้าสู่เว็บไซต์
2. กรอกชื่อผู้เล่น Minecraft
3. ตรวจสอบพ้อยท์ที่มี
4. เลือกซื้อสินค้า
5. สินค้าจะถูกส่งให้ในเกมอัตโนมัติ

### สำหรับแอดมิน
1. เข้าสู่ระบบจัดการ (`backend.php`)
2. จัดการผู้ใช้และเพิ่มพ้อยท์
3. เพิ่ม/แก้ไข/ลบสินค้า
4. ดูประวัติธุรกรรม
5. ตั้งค่าระบบ

## 🔧 การตั้งค่า RCON Commands

ตัวอย่างคำสั่งที่สามารถใช้ได้:
- `give {username} diamond 1` - ให้ไดมอนด์ 1 ชิ้น
- `give {username} golden_apple 5` - ให้แอปเปิ้ลทอง 5 ชิ้น
- `tp {username} 0 100 0` - เทเลพอร์ตไปยังตำแหน่ง
- `effect give {username} minecraft:speed 60 1` - ให้เอฟเฟกต์ความเร็ว

## 🛡️ ความปลอดภัย

### การป้องกันที่มี
- **File-based Storage**: ไม่ใช้ SQL Database เพื่อป้องกัน SQL Injection
- **Data Encryption**: เข้ารหัสข้อมูลทั้งหมดด้วย AES-256-CBC
- **CSRF Protection**: ป้องกันการโจมตีแบบ Cross-Site Request Forgery
- **XSS Protection**: ป้องกันการโจมตีแบบ Cross-Site Scripting
- **Rate Limiting**: จำกัดจำนวนคำขอเพื่อป้องกัน DDoS
- **Secure Headers**: ตั้งค่า HTTP Headers เพื่อความปลอดภัย
- **Input Validation**: ตรวจสอบข้อมูลนำเข้าอย่างเข้มงวด

### การตั้งค่าเพิ่มเติม
- ใช้ HTTPS สำหรับการเข้ารหัสการสื่อสار
- ตั้งค่า Firewall เพื่อจำกัดการเข้าถึง RCON
- สำรองข้อมูลเป็นประจำ
- อัปเดตระบบและ PHP เป็นประจำ

## 📊 API Endpoints

### RCON API (`rcon.php`)
- `?action=status` - ดูสถานะเซิร์ฟเวอร์
- `?action=purchase` - ซื้อสินค้า (POST)
- `?action=test` - ทดสอบการเชื่อมต่อ RCON (Admin)
- `?action=execute` - รันคำสั่ง RCON (Admin)

### Frontend API (`index.php`)
- `?ajax=server_status` - ดูสถานะเซิร์ฟเวอร์
- `?ajax=user_info` - ดูข้อมูลผู้ใช้
- `?ajax=purchase` - ซื้อสินค้า (POST)

## 🔄 การสำรองข้อมูล

ระบบจะสร้างไฟล์สำรองอัตโนมัติใน folder `backups/`
- สำรองข้อมูลเมื่อติดตั้งเสร็จ
- สำรองข้อมูลผ่าน Admin Panel
- ไฟล์สำรองจะถูกเข้ารหัสเพื่อความปลอดภัย

## 🐛 การแก้ไขปัญหา

### ปัญหาที่พบบ่อย
1. **ไม่สามารถเชื่อมต่อ RCON**: ตรวจสอบ IP, Port และรหัสผ่าน
2. **ข้อผิดพลาด Permission**: ตั้งค่าสิทธิ์ไฟล์และโฟลเดอร์
3. **ไม่สามารถส่งสินค้า**: ตรวจสอบคำสั่ง RCON และการเชื่อมต่อ
4. **หน้าเว็บไม่แสดง**: ตรวจสอบ PHP Extensions และ Error Log

### Log Files
- Security Log: `logs/security_YYYY-MM-DD.log`
- Error Log: ตรวจสอบ PHP Error Log ของเซิร์ฟเวอร์

## 📞 การสนับสนุน

หากพบปัญหาหรือต้องการความช่วยเหลือ:
1. ตรวจสอบ Log Files
2. ตรวจสอบการตั้งค่า PHP และ Extensions
3. ตรวจสอบการตั้งค่า RCON ในเซิร์ฟเวอร์

## 📄 License

โปรเจกต์นี้เป็น Open Source และสามารถใช้งานได้ฟรี
สร้างขึ้นเพื่อชุมชน Minecraft ในประเทศไทย

---

**⚠️ คำเตือน**: กรุณาเปลี่ยนรหัสผ่าน Admin และ RCON ให้แข็งแกร่งเพื่อความปลอดภัย
