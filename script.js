// Advanced Minecraft Homepage JavaScript
class MinecraftHomepage {
    constructor() {
        this.particles = [];
        this.serverStatus = null;
        this.isLoading = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.createAdvancedParticleSystem();
        this.initServerStatusMonitoring();
        this.setupAdvancedAnimations();
        this.initPerformanceOptimizations();
        this.setupResponsiveFeatures();
    }

    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            this.onDOMContentLoaded();
        });

        window.addEventListener('scroll', this.throttle(this.onScroll.bind(this), 16));
        window.addEventListener('resize', this.debounce(this.onResize.bind(this), 250));
        
        // Advanced mouse tracking for parallax effects
        document.addEventListener('mousemove', this.throttle(this.onMouseMove.bind(this), 16));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', this.onKeyDown.bind(this));
    }

    onDOMContentLoaded() {
        this.fetchServerStatus();
        this.startParticleAnimation();
        this.initIntersectionObserver();
        this.setupAdvancedButtonEffects();
        
        // Preload images for better performance
        this.preloadImages();
        
        // Initialize service worker for caching
        this.initServiceWorker();
    }

    // Advanced Particle System
    createAdvancedParticleSystem() {
        const container = document.getElementById('particles-container');
        if (!container) return;

        const particleTypes = ['green', 'gold', 'blue'];
        const particleCount = this.getOptimalParticleCount();

        for (let i = 0; i < particleCount; i++) {
            const particle = this.createParticle(particleTypes[i % particleTypes.length]);
            container.appendChild(particle);
            this.particles.push(particle);
        }
    }

    createParticle(type) {
        const particle = document.createElement('div');
        const size = Math.random() * 6 + 2;
        
        particle.className = `particle-enhanced particle-${type}`;
        particle.style.width = `${size}px`;
        particle.style.height = `${size}px`;
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 10 + 's';
        particle.style.animationDuration = (Math.random() * 5 + 8) + 's';
        
        return particle;
    }

    getOptimalParticleCount() {
        const screenWidth = window.innerWidth;
        const devicePixelRatio = window.devicePixelRatio || 1;
        const performanceLevel = this.getPerformanceLevel();
        
        let baseCount = 30;
        
        if (screenWidth > 1920) baseCount = 60;
        else if (screenWidth > 1200) baseCount = 45;
        else if (screenWidth < 768) baseCount = 15;
        
        return Math.floor(baseCount * performanceLevel / devicePixelRatio);
    }

    getPerformanceLevel() {
        // Simple performance detection
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (!gl) return 0.5;
        
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
            const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            if (renderer.includes('Intel')) return 0.7;
            if (renderer.includes('NVIDIA') || renderer.includes('AMD')) return 1.0;
        }
        
        return 0.8;
    }

    // Advanced Server Status Monitoring
    async initServerStatusMonitoring() {
        await this.fetchServerStatus();
        
        // Update every 30 seconds
        setInterval(() => {
            this.fetchServerStatus();
        }, 30000);
        
        // Retry failed requests with exponential backoff
        this.setupRetryMechanism();
    }

    async fetchServerStatus() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoadingState();
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch('https://api.mcsrvstat.us/3/hypixel.net', {
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            this.serverStatus = data;
            this.updateServerStatusUI(data);
            this.hideLoadingState();
            
        } catch (error) {
            console.error('Server status fetch error:', error);
            this.handleServerStatusError(error);
        } finally {
            this.isLoading = false;
        }
    }

    updateServerStatusUI(data) {
        const elements = {
            indicator: document.getElementById('hypixel-indicator'),
            players: document.getElementById('hypixel-players'),
            version: document.getElementById('hypixel-version'),
            motd: document.getElementById('hypixel-motd')
        };

        if (!elements.indicator) return;

        if (data.online) {
            elements.indicator.className = 'w-4 h-4 rounded-full status-online';
            elements.players.textContent = `${data.players.online.toLocaleString()} / ${data.players.max.toLocaleString()}`;
            elements.version.textContent = data.version || 'Unknown';
            elements.motd.textContent = this.cleanMOTD(data.motd?.clean?.[0]) || 'Hypixel Network';
            
            // Add success animation
            this.animateStatusUpdate(elements.indicator, 'success');
        } else {
            elements.indicator.className = 'w-4 h-4 rounded-full status-offline';
            elements.players.textContent = 'Offline';
            elements.version.textContent = 'N/A';
            elements.motd.textContent = 'Server Offline';
            
            this.animateStatusUpdate(elements.indicator, 'error');
        }
    }

    cleanMOTD(motd) {
        if (!motd) return '';
        // Remove Minecraft color codes and clean up text
        return motd.replace(/§[0-9a-fk-or]/g, '').trim();
    }

    handleServerStatusError(error) {
        const indicator = document.getElementById('hypixel-indicator');
        const players = document.getElementById('hypixel-players');
        
        if (indicator) {
            indicator.className = 'w-4 h-4 rounded-full status-loading';
            this.animateStatusUpdate(indicator, 'warning');
        }
        
        if (players) {
            if (error.name === 'AbortError') {
                players.textContent = 'Request timeout';
            } else {
                players.textContent = 'Connection error';
            }
        }
    }

    showLoadingState() {
        const players = document.getElementById('hypixel-players');
        if (players && !this.serverStatus) {
            players.innerHTML = '<div class="loading-spinner inline-block"></div>';
        }
    }

    hideLoadingState() {
        // Loading state is hidden when updateServerStatusUI is called
    }

    animateStatusUpdate(element, type) {
        element.style.transform = 'scale(1.2)';
        element.style.transition = 'transform 0.3s ease';
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 300);
    }

    // Advanced Animations and Effects
    setupAdvancedAnimations() {
        // Intersection Observer for scroll animations
        this.initIntersectionObserver();
        
        // Advanced parallax effects
        this.initParallaxEffects();
        
        // Smooth scrolling enhancements
        this.enhanceSmoothScrolling();
    }

    initIntersectionObserver() {
        const observerOptions = {
            threshold: [0.1, 0.5, 0.9],
            rootMargin: '50px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    
                    // Trigger specific animations based on element type
                    if (entry.target.classList.contains('glass-effect')) {
                        this.animateGlassEffect(entry.target);
                    }
                }
            });
        }, observerOptions);

        // Observe all animated elements
        document.querySelectorAll('.glass-effect, .minecraft-border, .btn-minecraft').forEach(el => {
            observer.observe(el);
        });
    }

    animateGlassEffect(element) {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        requestAnimationFrame(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        });
    }

    initParallaxEffects() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        parallaxElements.forEach(element => {
            const speed = parseFloat(element.dataset.parallax) || 0.5;
            element.style.transform = `translateY(${window.pageYOffset * speed}px)`;
        });
    }

    enhanceSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(anchor.getAttribute('href'));
                
                if (target) {
                    const offsetTop = target.offsetTop - 80; // Account for header
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    // Advanced Button Effects
    setupAdvancedButtonEffects() {
        document.querySelectorAll('.btn-minecraft, .btn-epic').forEach(button => {
            // Add ripple effect
            button.addEventListener('click', this.createRippleEffect.bind(this));
            
            // Add sound effect (optional)
            button.addEventListener('mouseenter', () => {
                this.playHoverSound();
            });
        });
    }

    createRippleEffect(e) {
        const button = e.currentTarget;
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    playHoverSound() {
        // Optional: Add subtle hover sound
        if (this.audioEnabled) {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
        }
    }

    // Event Handlers
    onScroll() {
        const scrollY = window.pageYOffset;
        
        // Update parallax elements
        this.updateParallaxElements(scrollY);
        
        // Update header transparency
        this.updateHeaderTransparency(scrollY);
        
        // Update particle positions
        this.updateParticlePositions(scrollY);
    }

    updateParallaxElements(scrollY) {
        document.querySelectorAll('[data-parallax]').forEach(element => {
            const speed = parseFloat(element.dataset.parallax) || 0.5;
            element.style.transform = `translateY(${scrollY * speed}px)`;
        });
    }

    updateHeaderTransparency(scrollY) {
        const header = document.querySelector('header');
        if (header) {
            const opacity = Math.min(scrollY / 100, 0.95);
            header.style.backgroundColor = `rgba(26, 26, 26, ${opacity})`;
            header.style.backdropFilter = `blur(${opacity * 10}px)`;
        }
    }

    updateParticlePositions(scrollY) {
        // Optional: Update particle positions based on scroll
        this.particles.forEach((particle, index) => {
            const speed = 0.1 + (index % 3) * 0.05;
            particle.style.transform = `translateY(${scrollY * speed}px)`;
        });
    }

    onMouseMove(e) {
        const { clientX, clientY } = e;
        const centerX = window.innerWidth / 2;
        const centerY = window.innerHeight / 2;
        
        const deltaX = (clientX - centerX) / centerX;
        const deltaY = (clientY - centerY) / centerY;
        
        // Apply subtle parallax to hero elements
        const hero = document.querySelector('.glass-effect');
        if (hero) {
            hero.style.transform = `translate(${deltaX * 10}px, ${deltaY * 10}px)`;
        }
    }

    onResize() {
        // Recalculate particle count
        this.adjustParticleCount();
        
        // Update responsive features
        this.updateResponsiveFeatures();
    }

    onKeyDown(e) {
        // Keyboard shortcuts
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'r':
                    e.preventDefault();
                    this.fetchServerStatus();
                    break;
                case 'p':
                    e.preventDefault();
                    this.toggleParticles();
                    break;
            }
        }
    }

    // Performance Optimizations
    initPerformanceOptimizations() {
        // Lazy loading for images
        this.setupLazyLoading();
        
        // Preload critical resources
        this.preloadCriticalResources();
        
        // Setup performance monitoring
        this.setupPerformanceMonitoring();
    }

    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    preloadCriticalResources() {
        const criticalImages = [
            'https://smilecraft.unaux.com/logo.png'
        ];

        criticalImages.forEach(src => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = src;
            document.head.appendChild(link);
        });
    }

    setupPerformanceMonitoring() {
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach(entry => {
                    if (entry.entryType === 'largest-contentful-paint') {
                        console.log('LCP:', entry.startTime);
                    }
                });
            });

            observer.observe({ entryTypes: ['largest-contentful-paint'] });
        }
    }

    // Utility Functions
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Additional Features
    toggleParticles() {
        const container = document.getElementById('particles-container');
        if (container) {
            container.style.display = container.style.display === 'none' ? 'block' : 'none';
        }
    }

    adjustParticleCount() {
        const optimalCount = this.getOptimalParticleCount();
        const currentCount = this.particles.length;
        
        if (optimalCount > currentCount) {
            // Add more particles
            const container = document.getElementById('particles-container');
            const particleTypes = ['green', 'gold', 'blue'];
            
            for (let i = currentCount; i < optimalCount; i++) {
                const particle = this.createParticle(particleTypes[i % particleTypes.length]);
                container.appendChild(particle);
                this.particles.push(particle);
            }
        } else if (optimalCount < currentCount) {
            // Remove excess particles
            const excessParticles = this.particles.splice(optimalCount);
            excessParticles.forEach(particle => particle.remove());
        }
    }

    setupResponsiveFeatures() {
        // Add responsive classes based on screen size
        const updateResponsiveClasses = () => {
            const width = window.innerWidth;
            const body = document.body;
            
            body.classList.remove('mobile', 'tablet', 'desktop');
            
            if (width < 768) {
                body.classList.add('mobile');
            } else if (width < 1024) {
                body.classList.add('tablet');
            } else {
                body.classList.add('desktop');
            }
        };
        
        updateResponsiveClasses();
        window.addEventListener('resize', this.debounce(updateResponsiveClasses, 250));
    }

    updateResponsiveFeatures() {
        this.setupResponsiveFeatures();
    }

    preloadImages() {
        const images = ['https://smilecraft.unaux.com/logo.png'];
        images.forEach(src => {
            const img = new Image();
            img.src = src;
        });
    }

    initServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('SW registered:', registration);
                })
                .catch(error => {
                    console.log('SW registration failed:', error);
                });
        }
    }

    setupRetryMechanism() {
        let retryCount = 0;
        const maxRetries = 3;
        
        const retryFetch = async () => {
            if (retryCount < maxRetries && !this.serverStatus) {
                retryCount++;
                setTimeout(() => {
                    this.fetchServerStatus().then(() => {
                        retryCount = 0;
                    }).catch(() => {
                        retryFetch();
                    });
                }, Math.pow(2, retryCount) * 1000); // Exponential backoff
            }
        };
        
        // Start retry mechanism if initial fetch fails
        setTimeout(retryFetch, 5000);
    }

    startParticleAnimation() {
        // Particles are already animated via CSS, this method can be used for additional JS-based animations
        setInterval(() => {
            this.particles.forEach((particle, index) => {
                if (Math.random() < 0.01) { // 1% chance per frame
                    particle.style.animationDuration = (Math.random() * 5 + 8) + 's';
                }
            });
        }, 100);
    }
}

// CSS for ripple animation
const rippleCSS = `
@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}
`;

// Add ripple CSS to document
const style = document.createElement('style');
style.textContent = rippleCSS;
document.head.appendChild(style);

// Initialize the application
const minecraftHomepage = new MinecraftHomepage();

// Export for potential external use
window.MinecraftHomepage = MinecraftHomepage;

