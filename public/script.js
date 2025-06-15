// Minecraft Server Status Dashboard
class MinecraftServerStatus {
    constructor() {
        this.serverAddress = 'hypixel.net';
        this.autoRefreshInterval = null;
        this.isLoading = false;
        
        this.initializeElements();
        this.bindEvents();
        this.loadServerData();
        this.startAutoRefresh();
    }

    initializeElements() {
        this.elements = {
            serverCard: document.getElementById('serverCard'),
            serverIcon: document.getElementById('serverIcon'),
            serverName: document.getElementById('serverName'),
            serverAddress: document.getElementById('serverAddress'),
            statusIndicator: document.getElementById('statusIndicator'),
            statusText: document.getElementById('statusText'),
            serverDetails: document.getElementById('serverDetails'),
            serverMotd: document.getElementById('serverMotd'),
            motdContent: document.getElementById('motdContent'),
            serverStats: document.getElementById('serverStats'),
            playersOnline: document.getElementById('playersOnline'),
            playersMax: document.getElementById('playersMax'),
            serverVersion: document.getElementById('serverVersion'),
            serverProtocol: document.getElementById('serverProtocol'),
            refreshBtn: document.getElementById('refreshBtn'),
            autoRefresh: document.getElementById('autoRefresh'),
            lastUpdate: document.getElementById('lastUpdate')
        };
    }

    bindEvents() {
        this.elements.refreshBtn.addEventListener('click', () => {
            this.loadServerData(true);
        });

        this.elements.autoRefresh.addEventListener('change', (e) => {
            if (e.target.checked) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        });
    }

    async loadServerData(forceRefresh = false) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.setLoadingState(true);

        try {
            const url = `/api/server/${this.serverAddress}${forceRefresh ? '?refresh=true' : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (response.ok) {
                this.displayServerData(data);
                this.updateLastUpdateTime(data.cached ? 'จากแคช' : 'ข้อมูลใหม่');
            } else {
                this.displayError(data.message || 'ไม่สามารถโหลดข้อมูลได้');
            }
        } catch (error) {
            console.error('Error loading server data:', error);
            this.displayError('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        } finally {
            this.isLoading = false;
            this.setLoadingState(false);
        }
    }

    displayServerData(data) {
        // Update server status
        const isOnline = data.online;
        const statusDot = this.elements.statusIndicator.querySelector('.status-dot');
        
        statusDot.className = `status-dot ${isOnline ? 'online' : 'offline'}`;
        this.elements.statusText.textContent = isOnline ? 'ออนไลน์' : 'ออฟไลน์';

        // Update server icon if available
        if (data.icon) {
            this.elements.serverIcon.src = data.icon;
            this.elements.serverIcon.style.display = 'block';
            this.elements.serverIcon.parentElement.querySelector('.default-icon').style.display = 'none';
        }

        if (isOnline) {
            // Update player count
            if (data.players) {
                this.elements.playersOnline.textContent = data.players.online || '0';
                this.elements.playersMax.textContent = data.players.max || '0';
            }

            // Update version info
            if (data.version) {
                this.elements.serverVersion.textContent = data.version.name || 'ไม่ทราบ';
                this.elements.serverProtocol.textContent = data.version.protocol || 'ไม่ทราบ';
            }

            // Update MOTD
            if (data.motd) {
                this.displayMotd(data.motd);
            }

            // Show stats
            this.elements.serverStats.style.display = 'grid';
            this.elements.serverMotd.style.display = data.motd ? 'block' : 'none';
        } else {
            // Server is offline
            this.elements.playersOnline.textContent = '0';
            this.elements.playersMax.textContent = '0';
            this.elements.serverVersion.textContent = 'ออฟไลน์';
            this.elements.serverProtocol.textContent = 'ออฟไลน์';
            this.elements.serverStats.style.display = 'grid';
            this.elements.serverMotd.style.display = 'none';
        }

        // Hide loading spinner
        this.elements.serverDetails.style.display = 'none';
    }

    displayMotd(motd) {
        let motdText = '';
        
        if (motd.clean) {
            motdText = Array.isArray(motd.clean) ? motd.clean.join('<br>') : motd.clean;
        } else if (motd.raw) {
            motdText = Array.isArray(motd.raw) ? motd.raw.join('<br>') : motd.raw;
        } else if (Array.isArray(motd)) {
            motdText = motd.join('<br>');
        } else if (typeof motd === 'string') {
            motdText = motd;
        }

        // Clean up Minecraft color codes
        motdText = motdText.replace(/§[0-9a-fk-or]/g, '');
        
        this.elements.motdContent.innerHTML = motdText || 'ไม่มีข้อความจากเซิร์ฟเวอร์';
    }

    displayError(message) {
        this.elements.serverDetails.innerHTML = `
            <div class="error-message">
                <div class="error-icon">❌</div>
                <h3>เกิดข้อผิดพลาด</h3>
                <p>${message}</p>
                <button onclick="location.reload()" class="retry-btn">
                    <span>🔄</span> ลองใหม่
                </button>
            </div>
        `;
        this.elements.serverDetails.style.display = 'block';
        
        // Update status
        const statusDot = this.elements.statusIndicator.querySelector('.status-dot');
        statusDot.className = 'status-dot offline';
        this.elements.statusText.textContent = 'ไม่สามารถตรวจสอบได้';
    }

    setLoadingState(loading) {
        this.elements.refreshBtn.disabled = loading;
        
        if (loading) {
            this.elements.refreshBtn.innerHTML = `
                <span class="btn-icon">⏳</span>
                กำลังโหลด...
            `;
            this.elements.serverDetails.style.display = 'block';
        } else {
            this.elements.refreshBtn.innerHTML = `
                <span class="btn-icon">🔄</span>
                รีเฟรชข้อมูล
            `;
        }
    }

    updateLastUpdateTime(source = '') {
        const now = new Date();
        const timeString = now.toLocaleTimeString('th-TH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        this.elements.lastUpdate.textContent = `อัพเดทล่าสุด: ${timeString} ${source}`;
    }

    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval
        
        this.autoRefreshInterval = setInterval(() => {
            if (this.elements.autoRefresh.checked && !this.isLoading) {
                this.loadServerData();
            }
        }, 30000); // 30 seconds
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }
}

// Additional CSS for error messages
const errorStyles = `
    .error-message {
        text-align: center;
        padding: 40px 20px;
        color: #ff5555;
    }
    
    .error-icon {
        font-size: 48px;
        margin-bottom: 20px;
    }
    
    .error-message h3 {
        font-size: 14px;
        margin-bottom: 15px;
        color: #ff5555;
    }
    
    .error-message p {
        font-size: 10px;
        margin-bottom: 25px;
        color: #aaaaaa;
        line-height: 1.6;
    }
    
    .retry-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #ff5555;
        color: white;
        border: 2px solid #aa0000;
        font-family: inherit;
        font-size: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .retry-btn:hover {
        background: #ff7777;
        transform: translateY(-2px);
    }
    
    .retry-btn:active {
        transform: translateY(0);
    }
`;

// Inject error styles
const styleSheet = document.createElement('style');
styleSheet.textContent = errorStyles;
document.head.appendChild(styleSheet);

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MinecraftServerStatus();
});

// Handle page visibility changes to pause/resume auto-refresh
document.addEventListener('visibilitychange', () => {
    const app = window.minecraftApp;
    if (app) {
        if (document.hidden) {
            app.stopAutoRefresh();
        } else if (document.getElementById('autoRefresh').checked) {
            app.startAutoRefresh();
            app.loadServerData(); // Refresh data when page becomes visible
        }
    }
});

// Store app instance globally for debugging
window.addEventListener('load', () => {
    if (window.minecraftApp) {
        console.log('🎮 Minecraft Server Status Dashboard loaded successfully!');
        console.log('📊 App instance available at window.minecraftApp');
    }
});

