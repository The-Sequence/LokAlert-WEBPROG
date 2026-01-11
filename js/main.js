/**
 * LokAlert - Main JavaScript
 * Smooth scroll animations, bounce effects, and interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    initScrollAnimations();
    initBounceEffects();
    initSmoothScroll();
    initParallaxEffects();
    initCardInteractions();
    initStepAnimations();
});

/**
 * Initialize scroll-triggered animations using Intersection Observer
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
                
                // If it's a bounce-in element, trigger the animation
                if (entry.target.classList.contains('bounce-in')) {
                    entry.target.style.animationPlayState = 'running';
                }
                
                // Stagger children animations
                const children = entry.target.querySelectorAll('.animate-child');
                children.forEach((child, index) => {
                    child.style.animationDelay = `${index * 0.1}s`;
                    child.classList.add('animate-visible');
                });
            }
        });
    }, observerOptions);

    // Observe all animated elements
    const animatedElements = document.querySelectorAll(`
        .showcase-container,
        .card-interactive,
        .step-card,
        .custom-item,
        .feature-compact,
        .section-header-center,
        .cta-content,
        .bounce-in
    `);

    animatedElements.forEach(el => {
        el.classList.add('pre-animate');
        observer.observe(el);
    });
}

/**
 * Initialize bounce hover effects
 */
function initBounceEffects() {
    const bounceElements = document.querySelectorAll('.bounce-hover, .card-interactive, .custom-item');
    
    bounceElements.forEach(el => {
        el.addEventListener('mouseenter', () => {
            el.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease';
            el.style.transform = 'translateY(-8px) scale(1.02)';
            el.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.3)';
        });
        
        el.addEventListener('mouseleave', () => {
            el.style.transition = 'transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55), box-shadow 0.3s ease';
            el.style.transform = 'translateY(0) scale(1)';
            el.style.boxShadow = '';
        });
    });

    // Extra bouncy effect for step icons
    const stepIcons = document.querySelectorAll('.step-icon-container');
    stepIcons.forEach(icon => {
        icon.addEventListener('mouseenter', () => {
            icon.style.animation = 'none';
            icon.style.transform = 'scale(1.15) rotate(5deg)';
        });
        
        icon.addEventListener('mouseleave', () => {
            icon.style.transform = 'scale(1) rotate(0deg)';
        });
    });
}

/**
 * Initialize smooth scrolling for anchor links
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

/**
 * Initialize subtle parallax effects
 */
function initParallaxEffects() {
    const parallaxElements = document.querySelectorAll('.hero-device, .gradient-orb');
    
    let ticking = false;
    
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrolled = window.pageYOffset;
                
                parallaxElements.forEach(el => {
                    const speed = el.dataset.parallax || 0.3;
                    const yPos = -(scrolled * speed);
                    el.style.transform = `translateY(${yPos}px)`;
                });
                
                ticking = false;
            });
            
            ticking = true;
        }
    });
}

/**
 * Initialize interactive card effects
 */
function initCardInteractions() {
    const cards = document.querySelectorAll('.card-interactive');
    
    cards.forEach(card => {
        // Add subtle tilt effect
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            card.style.transform = `
                perspective(1000px) 
                rotateX(${rotateX}deg) 
                rotateY(${rotateY}deg) 
                translateY(-8px) 
                scale(1.02)
            `;
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0) scale(1)';
        });
    });
}

/**
 * Initialize step-by-step animations
 */
function initStepAnimations() {
    const steps = document.querySelectorAll('.step-card');
    
    const stepObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                // Get the index of this step among all steps
                const allSteps = Array.from(steps);
                const stepIndex = allSteps.indexOf(entry.target);
                
                // Delay animation based on position
                setTimeout(() => {
                    entry.target.classList.add('step-animate-in');
                }, stepIndex * 150);
                
                stepObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    });

    steps.forEach(step => {
        step.classList.add('step-pre-animate');
        stepObserver.observe(step);
    });
}

/**
 * Add CSS for pre-animation and visible states
 */
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    .pre-animate {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94), 
                    transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    .animate-visible {
        opacity: 1;
        transform: translateY(0);
    }
    
    .step-pre-animate {
        opacity: 0;
        transform: translateY(40px) scale(0.9);
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .step-animate-in {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    
    .card-interactive {
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                    box-shadow 0.3s ease,
                    background 0.3s ease;
    }
    
    .feature-pill {
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .feature-pill:active {
        transform: scale(0.95);
    }
`;
document.head.appendChild(animationStyles);

/**
 * Performance optimization: Reduce animations for users who prefer reduced motion
 */
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition-smooth', 'ease');
    document.documentElement.style.setProperty('--transition-bounce', 'ease');
    document.documentElement.style.setProperty('--transition-spring', 'ease');
}

console.log('🔔 LokAlert - Loaded with smooth transitions & bouncy animations!');
