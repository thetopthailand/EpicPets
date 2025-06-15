const express = require('express');
const axios = require('axios');
const cors = require('cors');
const fs = require('fs').promises;
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('public'));

// Cache configuration
const CACHE_DURATION = 30 * 1000; // 30 seconds
const DATA_DIR = path.join(__dirname, 'data');
const CACHE_FILE = path.join(DATA_DIR, 'servers.json');

// Ensure data directory exists
async function ensureDataDir() {
    try {
        await fs.access(DATA_DIR);
    } catch {
        await fs.mkdir(DATA_DIR, { recursive: true });
    }
}

// Cache management
class ServerCache {
    constructor() {
        this.cache = new Map();
        this.loadCache();
    }

    async loadCache() {
        try {
            const data = await fs.readFile(CACHE_FILE, 'utf8');
            const cacheData = JSON.parse(data);
            this.cache = new Map(Object.entries(cacheData));
        } catch (error) {
            console.log('No existing cache file, starting fresh');
        }
    }

    async saveCache() {
        try {
            const cacheObj = Object.fromEntries(this.cache);
            await fs.writeFile(CACHE_FILE, JSON.stringify(cacheObj, null, 2));
        } catch (error) {
            console.error('Error saving cache:', error);
        }
    }

    get(key) {
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < CACHE_DURATION) {
            return cached.data;
        }
        return null;
    }

    set(key, data) {
        this.cache.set(key, {
            data,
            timestamp: Date.now()
        });
        this.saveCache();
    }
}

const cache = new ServerCache();

// API Routes
app.get('/api/server/:address', async (req, res) => {
    const { address } = req.params;
    
    try {
        // Check cache first
        const cachedData = cache.get(address);
        if (cachedData) {
            return res.json({
                ...cachedData,
                cached: true,
                cacheTime: new Date().toISOString()
            });
        }

        // Fetch from mcsrvstat.us API
        const response = await axios.get(`https://api.mcsrvstat.us/3/${address}`, {
            timeout: 10000
        });

        const serverData = {
            ...response.data,
            fetchTime: new Date().toISOString(),
            cached: false
        };

        // Cache the result
        cache.set(address, serverData);

        res.json(serverData);
    } catch (error) {
        console.error('Error fetching server data:', error.message);
        res.status(500).json({
            error: 'Failed to fetch server data',
            message: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

// Get multiple servers
app.get('/api/servers', async (req, res) => {
    const servers = ['hypixel.net']; // Can be extended
    const results = {};

    for (const server of servers) {
        try {
            const cachedData = cache.get(server);
            if (cachedData) {
                results[server] = { ...cachedData, cached: true };
            } else {
                const response = await axios.get(`https://api.mcsrvstat.us/3/${server}`, {
                    timeout: 10000
                });
                const serverData = {
                    ...response.data,
                    fetchTime: new Date().toISOString(),
                    cached: false
                };
                cache.set(server, serverData);
                results[server] = serverData;
            }
        } catch (error) {
            results[server] = {
                error: 'Failed to fetch data',
                message: error.message,
                timestamp: new Date().toISOString()
            };
        }
    }

    res.json(results);
});

// Health check
app.get('/api/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        uptime: process.uptime()
    });
});

// Start server
async function startServer() {
    await ensureDataDir();
    app.listen(PORT, () => {
        console.log(`🎮 Minecraft Server Status API running on port ${PORT}`);
        console.log(`📊 Dashboard: http://localhost:${PORT}`);
        console.log(`🔗 API: http://localhost:${PORT}/api/server/hypixel.net`);
    });
}

startServer().catch(console.error);

