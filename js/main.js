/**
 * LokAlert - Main JavaScript
 * Scroll animations and interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    initScrollAnimations();
    initBounceEffects();
    initSmoothScroll();
    initMobileMenu();
});

/**
 * Initialize mobile hamburger menu
 */
function initMobileMenu() {
    const hamburgerBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileLinks = document.querySelectorAll('.mobile-menu-link');
    
    if (!hamburgerBtn || !mobileMenu) return;
    
    // Toggle menu on hamburger click
    hamburgerBtn.addEventListener('click', () => {
        hamburgerBtn.classList.toggle('active');
        mobileMenu.classList.toggle('active');
        document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
    });
    
    // Close menu when a link is clicked
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            hamburgerBtn.classList.remove('active');
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
        });
    });
    
    // Close menu on scroll
    let lastScroll = window.scrollY;
    window.addEventListener('scroll', () => {
        if (Math.abs(window.scrollY - lastScroll) > 50) {
            hamburgerBtn.classList.remove('active');
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
            lastScroll = window.scrollY;
        }
    }, { passive: true });
    
    // Close menu on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            hamburgerBtn.classList.remove('active');
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

/**
 * Initialize scroll-triggered animations
 */
function initScrollAnimations() {
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -10% 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-visible');
            }
        });
    }, observerOptions);

    // Observe elements that should animate
    const animatedElements = document.querySelectorAll(`
        .spotlight-content,
        .spotlight-visual,
        .feature-item,
        .step,
        .platform-device,
        .customize-item,
        .download-content,
        .section-header
    `);

    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        // Use a small fixed delay based on position in grid, max 0.3s
        const delay = Math.min(index * 0.05, 0.3);
        el.style.transition = `opacity 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${delay}s, 
                              transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${delay}s`;
        observer.observe(el);
    });
}

/**
 * Initialize bounce hover effects
 */
function initBounceEffects() {
    const bounceElements = document.querySelectorAll('.bounce-hover, .feature-item, .customize-item');
    
    bounceElements.forEach(el => {
        el.addEventListener('mouseenter', () => {
            el.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease';
        });
    });
}

/**
 * Initialize smooth scrolling
 */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const navHeight = document.querySelector('#globalheader').offsetHeight;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Add visible state styles
const styles = document.createElement('style');
styles.textContent = `
    .animate-visible {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;
document.head.appendChild(styles);

// Reduced motion support
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--ease-smooth', 'ease');
    document.documentElement.style.setProperty('--ease-bounce', 'ease');
    document.documentElement.style.setProperty('--ease-spring', 'ease');
}

/**
 * Initialize Customization Section - Apple TV Style Scroll
 * Features change as user scrolls through the list
 */
function initCustomizeScroll() {
    const section = document.querySelector('.section-customize');
    const featurePanels = document.querySelectorAll('.feature-panel');
    const screenStates = document.querySelectorAll('.screen-state');
    const stickyContent = document.querySelector('.customize-sticky-content');
    
    if (!section || !featurePanels.length) return;
    
    // Set initial active state
    screenStates[0]?.classList.add('active');
    
    function updateActiveFeature() {
        // Find which panel is most in view
        let activeFeature = 'sounds';
        let maxVisibility = 0;
        
        featurePanels.forEach(panel => {
            const rect = panel.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            
            // Calculate how much of the panel is visible in the viewport center
            const panelCenter = rect.top + rect.height / 2;
            const viewportCenter = viewportHeight / 2;
            const distance = Math.abs(panelCenter - viewportCenter);
            const visibility = Math.max(0, 1 - distance / viewportHeight);
            
            if (visibility > maxVisibility) {
                maxVisibility = visibility;
                activeFeature = panel.dataset.feature;
            }
        });
        
        // Update screen states
        screenStates.forEach(state => {
            if (state.dataset.feature === activeFeature) {
                state.classList.add('active');
            } else {
                state.classList.remove('active');
            }
        });
        
        // Update sticky background to match current panel
        if (stickyContent) {
            const backgrounds = {
                'sounds': 'linear-gradient(135deg, #1a0a2e 0%, #16082a 50%, #0d0015 100%)',
                'themes': 'linear-gradient(135deg, #0a1a1a 0%, #062a2a 50%, #001515 100%)',
                'vibration': 'linear-gradient(135deg, #1a1005 0%, #2a1a08 50%, #150d00 100%)',
                'schedule': 'linear-gradient(135deg, #0a0f1a 0%, #0d1a2e 50%, #050a15 100%)'
            };
            stickyContent.style.background = backgrounds[activeFeature] || backgrounds['sounds'];
        }
    }
    
    // Listen to scroll events with throttling
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                updateActiveFeature();
                ticking = false;
            });
            ticking = true;
        }
    });
    
    // Initial update
    updateActiveFeature();
}

// Initialize customize scroll after DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    initCustomizeScroll();
});

console.log('🔔 LokAlert - iOS-style design loaded');
