// Epic Minecraft Homepage JavaScript
// Professional-grade animations and interactions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all epic features
    initNavbarEffects();
    initHeroAnimations();
    initCounterAnimations();
    initParticleSystem();
    initScrollEffects();
    initButtonEffects();
    initMobileMenu();
});

// Revolutionary Navbar Effects
function initNavbarEffects() {
    const navbar = document.querySelector('.navbar');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Dynamic navbar background on scroll
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        if (scrolled > 50) {
            navbar.style.background = 'rgba(5, 5, 5, 0.98)';
            navbar.style.borderBottom = '1px solid rgba(0, 255, 136, 0.4)';
        } else {
            navbar.style.background = 'rgba(10, 10, 10, 0.95)';
            navbar.style.borderBottom = '1px solid rgba(0, 255, 136, 0.2)';
        }
    });

    // Advanced hover effects for nav links
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
            this.style.textShadow = '0 0 10px rgba(0, 255, 136, 0.6)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.textShadow = 'none';
        });
    });
}

// Epic Hero Animations
function initHeroAnimations() {
    const heroTitle = document.querySelector('.hero-title');
    const titleLines = document.querySelectorAll('.title-line');
    const heroSubtitle = document.querySelector('.hero-subtitle');
    const ctaButtons = document.querySelectorAll('.cta-btn');
    
    // Staggered title animation
    titleLines.forEach((line, index) => {
        line.style.opacity = '0';
        line.style.transform = 'translateY(50px)';
        
        setTimeout(() => {
            line.style.transition = 'all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            line.style.opacity = '1';
            line.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Subtitle fade-in
    setTimeout(() => {
        heroSubtitle.style.opacity = '0';
        heroSubtitle.style.transform = 'translateY(30px)';
        heroSubtitle.style.transition = 'all 0.8s ease';
        
        setTimeout(() => {
            heroSubtitle.style.opacity = '1';
            heroSubtitle.style.transform = 'translateY(0)';
        }, 100);
    }, 800);
    
    // CTA buttons animation
    setTimeout(() => {
        ctaButtons.forEach((btn, index) => {
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                btn.style.transition = 'all 0.6s ease';
                btn.style.opacity = '1';
                btn.style.transform = 'translateY(0)';
            }, index * 150);
        });
    }, 1200);
}

// Advanced Counter Animations
function initCounterAnimations() {
    const counters = document.querySelectorAll('.stat-number');
    const observerOptions = {
        threshold: 0.7,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-target'));
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        
        // Format numbers with commas
        element.textContent = Math.floor(current).toLocaleString();
        
        // Add pulsing effect during animation
        if (current < target) {
            element.style.transform = 'scale(1.1)';
            setTimeout(() => {
                element.style.transform = 'scale(1)';
            }, 100);
        }
    }, 16);
}

// Epic Particle System
function initParticleSystem() {
    const particleContainer = document.querySelector('.particles');
    
    // Create floating particles
    for (let i = 0; i < 50; i++) {
        createParticle(particleContainer);
    }
    
    // Continuously spawn particles
    setInterval(() => {
        createParticle(particleContainer);
    }, 2000);
}

function createParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'floating-particle';
    
    // Random properties
    const size = Math.random() * 4 + 2;
    const left = Math.random() * 100;
    const animationDuration = Math.random() * 10 + 10;
    const opacity = Math.random() * 0.5 + 0.3;
    
    particle.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        background: radial-gradient(circle, #00ff88, #00d4ff);
        border-radius: 50%;
        left: ${left}%;
        bottom: -10px;
        opacity: ${opacity};
        animation: floatUp ${animationDuration}s linear forwards;
        pointer-events: none;
        box-shadow: 0 0 10px rgba(0, 255, 136, 0.6);
    `;
    
    container.appendChild(particle);
    
    // Remove particle after animation
    setTimeout(() => {
        if (particle.parentNode) {
            particle.parentNode.removeChild(particle);
        }
    }, animationDuration * 1000);
}

// Add floating animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes floatUp {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(-100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Advanced Scroll Effects
function initScrollEffects() {
    const scrollIndicator = document.querySelector('.scroll-indicator');
    
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const windowHeight = window.innerHeight;
        
        // Hide scroll indicator after scrolling
        if (scrolled > windowHeight * 0.1) {
            scrollIndicator.style.opacity = '0';
            scrollIndicator.style.transform = 'translateX(-50%) translateY(20px)';
        } else {
            scrollIndicator.style.opacity = '1';
            scrollIndicator.style.transform = 'translateX(-50%) translateY(0)';
        }
        
        // Parallax effect for floating blocks
        const blocks = document.querySelectorAll('.block');
        blocks.forEach((block, index) => {
            const speed = 0.5 + (index * 0.1);
            const yPos = -(scrolled * speed);
            block.style.transform = `translateY(${yPos}px) rotate(${scrolled * 0.1}deg)`;
        });
    });
}

// Epic Button Effects
function initButtonEffects() {
    const actionButtons = document.querySelectorAll('.action-btn');
    const ctaButtons = document.querySelectorAll('.cta-btn');
    
    // Store and Discord button functionality
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const isStore = this.classList.contains('store-btn');
            const isDiscord = this.classList.contains('discord-btn');
            
            // Add click animation
            this.style.transform = 'translateY(-3px) scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'translateY(-3px) scale(1)';
            }, 150);
            
            // Handle button actions
            if (isStore) {
                // Add your store URL here
                showNotification('🛒 Store coming soon! Stay tuned for epic items!');
            } else if (isDiscord) {
                // Add your Discord invite URL here
                showNotification('🎮 Discord server launching soon! Join the community!');
            }
        });
        
        // Advanced hover effects
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.05)';
            this.style.filter = 'brightness(1.1)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.filter = 'brightness(1)';
        });
    });
    
    // CTA button effects
    ctaButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const isPrimary = this.classList.contains('primary-cta');
            
            if (isPrimary) {
                showNotification('🎮 Server IP: play.epiccraft.com');
                // Add server connection logic here
            } else {
                showNotification('🎬 Trailer coming soon!');
                // Add trailer modal logic here
            }
        });
    });
}

// Mobile Menu Functionality
function initMobileMenu() {
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
            
            // Animate hamburger menu
            const spans = this.querySelectorAll('span');
            if (this.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });
    }
}

// Epic Notification System
function showNotification(message) {
    // Remove existing notification
    const existingNotification = document.querySelector('.epic-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = 'epic-notification';
    notification.textContent = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: linear-gradient(135deg, #00ff88, #00d4ff);
        color: #0a0a0a;
        padding: 1rem 2rem;
        border-radius: 12px;
        font-family: 'Orbitron', monospace;
        font-weight: 700;
        font-size: 0.9rem;
        box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        max-width: 300px;
        text-align: center;
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Animate out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Advanced Performance Optimizations
function optimizePerformance() {
    // Throttle scroll events
    let ticking = false;
    
    function updateScrollEffects() {
        // Your scroll effects here
        ticking = false;
    }
    
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateScrollEffects);
            ticking = true;
        }
    }
    
    window.addEventListener('scroll', requestTick);
}

// Initialize performance optimizations
optimizePerformance();

// Epic Loading Animation
window.addEventListener('load', function() {
    const loadingScreen = document.createElement('div');
    loadingScreen.className = 'loading-screen';
    loadingScreen.innerHTML = `
        <div class="loading-content">
            <div class="loading-logo">
                <div class="loading-cube"></div>
            </div>
            <div class="loading-text">LOADING EPIC EXPERIENCE...</div>
            <div class="loading-bar">
                <div class="loading-progress"></div>
            </div>
        </div>
    `;
    
    loadingScreen.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #0a0a0a, #050505);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        transition: opacity 0.5s ease;
    `;
    
    // Add loading styles
    const loadingStyles = document.createElement('style');
    loadingStyles.textContent = `
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .loading-cube {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #00ff88, #00d4ff);
            margin: 0 auto 2rem;
            border-radius: 8px;
            animation: loadingCube 2s ease-in-out infinite;
        }
        
        @keyframes loadingCube {
            0%, 100% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.2); }
        }
        
        .loading-text {
            font-family: 'Orbitron', monospace;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            letter-spacing: 2px;
        }
        
        .loading-bar {
            width: 300px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .loading-progress {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #00ff88, #00d4ff);
            border-radius: 2px;
            animation: loadingProgress 2s ease-in-out forwards;
        }
        
        @keyframes loadingProgress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
    `;
    
    document.head.appendChild(loadingStyles);
    document.body.appendChild(loadingScreen);
    
    // Remove loading screen after animation
    setTimeout(() => {
        loadingScreen.style.opacity = '0';
        setTimeout(() => {
            if (loadingScreen.parentNode) {
                loadingScreen.parentNode.removeChild(loadingScreen);
            }
        }, 500);
    }, 2500);
});

// Console Easter Egg
console.log(`
%c
███████╗██████╗ ██╗ ██████╗ ██████╗ ██████╗  █████╗ ███████╗████████╗
██╔════╝██╔══██╗██║██╔════╝██╔════╝██╔══██╗██╔══██╗██╔════╝╚══██╔══╝
█████╗  ██████╔╝██║██║     ██║     ██████╔╝███████║█████╗     ██║   
██╔══╝  ██╔═══╝ ██║██║     ██║     ██╔══██╗██╔══██║██╔══╝     ██║   
███████╗██║     ██║╚██████╗╚██████╗██║  ██║██║  ██║██║        ██║   
╚══════╝╚═╝     ╚═╝ ╚═════╝ ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝        ╚═╝   

🎮 Welcome to EpicCraft - The Ultimate Minecraft Experience!
🚀 Built with professional-grade web technologies
💎 Designed for epic adventures and legendary gameplay

`, 'color: #00ff88; font-weight: bold;');

console.log('%c🔥 Looking for something? Check out our epic features!', 'color: #00d4ff; font-size: 16px;');

