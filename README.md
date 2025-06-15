# 🎮 Minecraft Server Status Dashboard

เว็บแอปพลิเคชันสำหรับตรวจสอบสถานะเซิร์ฟเวอร์ Minecraft โดยเฉพาะเซิร์ฟเวอร์ Hypixel พร้อมธีม Minecraft ที่สวยงาม

## ✨ ฟีเจอร์

- 🔍 **ตรวจสอบสถานะเซิร์ฟเวอร์แบบ Real-time**
- 👥 **แสดงจำนวนผู้เล่นออนไลน์**
- 📢 **แสดง MOTD (Message of the Day)**
- 🔄 **รีเฟรชข้อมูลอัตโนมัติทุก 30 วินาที**
- 💾 **ระบบ Caching เพื่อลดการเรียก API**
- 📱 **Responsive Design สำหรับทุกอุปกรณ์**
- 🎨 **ธีม Minecraft ที่สวยงาม**

## 🛠️ เทคโนโลยีที่ใช้

### Backend
- **Node.js** - JavaScript Runtime
- **Express.js** - Web Framework
- **Axios** - HTTP Client
- **CORS** - Cross-Origin Resource Sharing

### Frontend
- **HTML5** - Structure
- **CSS3** - Styling (Minecraft Theme)
- **Vanilla JavaScript** - Functionality

### Data Storage
- **JSON Files** - File-based storage (ไม่ใช้ SQL)
- **In-memory Caching** - เพื่อประสิทธิภาพ

### API
- **mcsrvstat.us** - Minecraft Server Status API

## 🚀 การติดตั้งและใช้งาน

### ข้อกำหนดระบบ
- Node.js 16+ 
- npm หรือ yarn

### ขั้นตอนการติดตั้ง

1. **Clone โปรเจค**
   ```bash
   git clone <repository-url>
   cd minecraft-server-status
   ```

2. **ติดตั้ง Dependencies**
   ```bash
   npm install
   ```

3. **รันเซิร์ฟเวอร์**
   ```bash
   # Production
   npm start
   
   # Development (with auto-reload)
   npm run dev
   ```

4. **เปิดเว็บไซต์**
   - เปิดเบราว์เซอร์และไปที่: `http://localhost:3000`

## 📁 โครงสร้างโปรเจค

```
minecraft-server-status/
├── public/                 # Frontend files
│   ├── index.html         # หน้าเว็บหลัก
│   ├── style.css          # CSS ธีม Minecraft
│   ├── script.js          # JavaScript functionality
│   └── assets/            # รูปภาพและไฟล์อื่นๆ
├── data/                  # Data storage
│   ├── servers.json       # Cache ข้อมูลเซิร์ฟเวอร์
│   └── cache-config.json  # การตั้งค่า Cache
├── routes/                # API routes (ถ้าต้องการขยาย)
├── utils/                 # Utility functions
├── server.js              # Main server file
├── package.json           # Dependencies และ scripts
└── README.md              # เอกสารนี้
```

## 🔧 API Endpoints

### GET `/api/server/:address`
ดึงข้อมูลเซิร์ฟเวอร์ตาม address

**ตัวอย่าง:**
```bash
curl http://localhost:3000/api/server/hypixel.net
```

**Response:**
```json
{
  "online": true,
  "ip": "hypixel.net",
  "port": 25565,
  "players": {
    "online": 45000,
    "max": 200000
  },
  "version": {
    "name": "Requires MC 1.8 / 1.19",
    "protocol": 47
  },
  "motd": {
    "raw": ["§aHypixel Network §c[1.8-1.19]"],
    "clean": ["Hypixel Network [1.8-1.19]"]
  },
  "fetchTime": "2024-01-01T12:00:00.000Z",
  "cached": false
}
```

### GET `/api/servers`
ดึงข้อมูลเซิร์ฟเวอร์หลายตัวพร้อมกัน

### GET `/api/health`
ตรวจสอบสถานะของ API

## ⚙️ การตั้งค่า

### Environment Variables
สร้างไฟล์ `.env` (ถ้าต้องการ):
```env
PORT=3000
CACHE_DURATION=30000
API_TIMEOUT=10000
```

### การปรับแต่ง Cache
แก้ไขใน `server.js`:
```javascript
const CACHE_DURATION = 30 * 1000; // 30 วินาที
```

### การเพิ่มเซิร์ฟเวอร์อื่น
แก้ไขใน `server.js` ที่ endpoint `/api/servers`:
```javascript
const servers = ['hypixel.net', 'mineplex.com', 'cubecraft.net'];
```

## 🎨 การปรับแต่งธีม

### สี Minecraft
ปรับแต่งใน `public/style.css`:
```css
:root {
    --minecraft-green: #00ff00;
    --minecraft-red: #ff5555;
    --minecraft-blue: #5555ff;
    --minecraft-yellow: #ffff55;
    /* เพิ่มสีอื่นๆ ตามต้องการ */
}
```

### ฟอนต์
ใช้ฟอนต์ "Press Start 2P" เพื่อให้ดูเหมือน Minecraft

## 🔍 การแก้ไขปัญหา

### เซิร์ฟเวอร์ไม่เริ่มต้น
```bash
# ตรวจสอบ port ที่ใช้
netstat -tulpn | grep :3000

# ลองเปลี่ยน port
PORT=3001 npm start
```

### API ไม่ตอบสนอง
- ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต
- ตรวจสอบว่า mcsrvstat.us ทำงานปกติ
- ดูใน console สำหรับ error messages

### ข้อมูลไม่อัพเดท
- ลบไฟล์ `data/servers.json` เพื่อล้าง cache
- รีสตาร์ทเซิร์ฟเวอร์

## 🤝 การพัฒนาต่อ

### การเพิ่มฟีเจอร์ใหม่
1. เพิ่ม API endpoint ใน `server.js`
2. อัพเดท frontend ใน `public/script.js`
3. ปรับแต่ง UI ใน `public/style.css`

### การปรับปรุงประสิทธิภาพ
- ใช้ Redis สำหรับ caching
- เพิ่ม rate limiting
- ใช้ WebSocket สำหรับ real-time updates

## 📄 License

MIT License - ใช้งานได้อย่างอิสระ

## 🙏 Credits

- **mcsrvstat.us** - สำหรับ API ข้อมูลเซิร์ฟเวอร์
- **Google Fonts** - สำหรับฟอนต์ "Press Start 2P"
- **Minecraft** - สำหรับแรงบันดาลใจในการออกแบบ

---

🎮 **สนุกกับการใช้งาน Minecraft Server Status Dashboard!** 🎮

