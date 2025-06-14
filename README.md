# 🎮 SmileCraft - Professional Minecraft Homepage

A cutting-edge, professional-grade Minecraft server homepage built with modern web technologies and advanced optimization techniques.

## ✨ Features

### 🎨 Visual Excellence
- **Glass Morphism Design** - Modern, translucent UI elements with backdrop blur effects
- **Advanced Particle System** - Dynamic, GPU-accelerated particle animations
- **Responsive Design** - Optimized for all devices from mobile to 4K displays
- **Professional Animations** - Smooth transitions, hover effects, and scroll animations
- **Minecraft-themed Styling** - Authentic color schemes and typography

### ⚡ Performance Optimizations
- **Service Worker** - Advanced caching strategies for offline functionality
- **Lazy Loading** - Images and resources load only when needed
- **Performance Monitoring** - Built-in performance tracking and optimization
- **Adaptive Particle Count** - Automatically adjusts based on device capabilities
- **Critical Resource Preloading** - Faster initial page loads

### 🔧 Technical Features
- **Real-time Server Status** - Live server monitoring with automatic updates
- **API Integration** - Fetches live data from Minecraft server status API
- **Error Handling** - Robust error handling with retry mechanisms
- **Keyboard Shortcuts** - Power user features (Ctrl+R to refresh, Ctrl+P to toggle particles)
- **Intersection Observer** - Efficient scroll-based animations
- **Advanced Event Handling** - Throttled and debounced event listeners

### 🌐 Modern Web Standards
- **Progressive Web App** - Can be installed as a native app
- **Service Worker Caching** - Offline functionality and faster loading
- **Responsive Images** - Optimized for different screen densities
- **Accessibility** - WCAG compliant design patterns
- **SEO Optimized** - Proper meta tags and semantic HTML

## 🚀 Quick Start

1. **Clone or download** the project files
2. **Open `index.html`** in a modern web browser
3. **Enjoy** the professional Minecraft homepage experience!

## 📁 Project Structure

```
smilecraft-homepage/
├── index.html          # Main HTML file with embedded Tailwind CSS
├── styles.css          # Advanced CSS animations and effects
├── script.js           # Professional JavaScript functionality
├── sw.js              # Service Worker for caching and offline support
└── README.md          # This documentation file
```

## 🛠️ Technologies Used

### Frontend Framework
- **Tailwind CSS** - Utility-first CSS framework for rapid development
- **Vanilla JavaScript** - Pure JavaScript for maximum performance
- **HTML5** - Modern semantic markup

### Advanced Features
- **Intersection Observer API** - Efficient scroll animations
- **Service Worker API** - Offline functionality and caching
- **Fetch API** - Modern HTTP requests with error handling
- **Performance Observer API** - Real-time performance monitoring
- **Web Workers** - Background processing capabilities

### External APIs
- **MC Server Status API** - `https://api.mcsrvstat.us/3/hypixel.net`
- **Tailwind CDN** - `https://cdn.tailwindcss.com`

## 🎯 Server Configuration

The homepage is configured to monitor the **Hypixel** server by default:

- **Server Name**: Hypixel Network
- **Server IP**: `hypixel.net`
- **API Endpoint**: `https://api.mcsrvstat.us/3/hypixel.net`

### Customizing Server Settings

To monitor a different server, update the following in `script.js`:

```javascript
// Change this URL to your server's API endpoint
const response = await fetch('https://api.mcsrvstat.us/3/YOUR_SERVER_IP');
```

And update the display elements in `index.html`:

```html
<h3 class="text-xl font-bold">Your Server Name</h3>
<p><span class="text-gray-400">IP:</span> <span class="text-minecraft-green font-mono">your-server.net</span></p>
```

## 🎨 Customization Guide

### Logo Replacement
Replace the logo URL in both `index.html` and `sw.js`:
```html
<img src="YOUR_LOGO_URL" alt="Your Server Logo">
```

### Color Scheme
Modify the CSS custom properties in `styles.css`:
```css
:root {
    --minecraft-green: #00AA00;  /* Primary green */
    --minecraft-gold: #FFAA00;   /* Accent gold */
    --minecraft-dark: #1a1a1a;   /* Dark background */
}
```

### Button Links
Update the navigation buttons in `index.html`:
```html
<a href="YOUR_STORE_URL" class="btn-minecraft">🛒 Store</a>
<a href="YOUR_DISCORD_URL" class="btn-minecraft">💬 Discord</a>
```

## 📱 Browser Compatibility

### Fully Supported
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

### Partially Supported
- Internet Explorer 11 (limited animations)
- Older mobile browsers (reduced particle count)

## 🔧 Performance Features

### Automatic Optimizations
- **Adaptive Particle System** - Adjusts particle count based on device performance
- **Throttled Event Listeners** - Prevents excessive function calls during scroll/resize
- **Debounced API Calls** - Prevents API spam during rapid interactions
- **Lazy Loading** - Images load only when visible
- **Critical Resource Preloading** - Important assets load first

### Manual Optimizations
- **Particle Toggle** - Press `Ctrl+P` to disable particles on low-end devices
- **Refresh Server Status** - Press `Ctrl+R` to manually refresh server data
- **Cache Management** - Service worker automatically manages cache size

## 🛡️ Security Features

- **Content Security Policy** - Prevents XSS attacks
- **HTTPS Enforcement** - Secure connections for all external resources
- **Input Sanitization** - All user inputs are properly sanitized
- **API Rate Limiting** - Prevents excessive API calls

## 📊 Analytics & Monitoring

The homepage includes built-in performance monitoring:

- **Largest Contentful Paint (LCP)** tracking
- **API Response Time** monitoring
- **Error Logging** for debugging
- **Cache Hit Rate** analysis

## 🚀 Deployment Options

### Static Hosting
- **GitHub Pages** - Free hosting for static sites
- **Netlify** - Advanced features with form handling
- **Vercel** - Optimized for performance
- **Firebase Hosting** - Google's hosting platform

### CDN Integration
- **Cloudflare** - Global CDN with DDoS protection
- **AWS CloudFront** - Amazon's content delivery network
- **Azure CDN** - Microsoft's global network

## 🤝 Contributing

This is a professional-grade template designed for Minecraft servers. Feel free to:

1. **Fork** the project
2. **Customize** for your server
3. **Share** improvements with the community
4. **Report** any issues or suggestions

## 📄 License

This project is open source and available under the [MIT License](https://opensource.org/licenses/MIT).

## 🎯 Future Enhancements

### Planned Features
- **Player Statistics Dashboard** - Live player data visualization
- **Event Calendar** - Server events and announcements
- **Gallery System** - Screenshot and build showcases
- **Multi-language Support** - Internationalization
- **Dark/Light Theme Toggle** - User preference settings

### Advanced Integrations
- **Discord Bot Integration** - Live Discord member count
- **Donation Progress Bars** - Server funding goals
- **Live Chat Widget** - Real-time communication
- **Player Leaderboards** - Top players and statistics

## 📞 Support

For technical support or customization requests:

- **Documentation**: This README file
- **Issues**: Report bugs or request features
- **Community**: Join the Minecraft server community

---

**Built with ❤️ for the Minecraft community**

*Professional web development meets gaming excellence*

