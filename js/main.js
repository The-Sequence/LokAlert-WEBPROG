/**
 * LokAlert Mockup - JavaScript
 * Interactions and animations
 */

document.addEventListener('DOMContentLoaded', () => {
    // Smooth scroll for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const headerOffset = 100;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Tabnav active state on scroll
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.tabnav-link');

    function updateActiveNav() {
        const scrollPosition = window.scrollY + 150;
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionId = section.getAttribute('id');
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                navLinks.forEach(link => {
                    link.classList.remove('current');
                    if (link.getAttribute('href') === `#${sectionId}`) {
                        link.classList.add('current');
                    }
                });
            }
        });
    }

    window.addEventListener('scroll', updateActiveNav);

    // Gallery horizontal scroll with dot navigation
    const gallery = document.querySelector('.media-gallery');
    const dotLinks = document.querySelectorAll('.dotnav-link');
    const cards = document.querySelectorAll('.card-container.gallery-item');
    let currentCard = 0;
    let autoScrollInterval;
    let isPlaying = true;

    function scrollToCard(index) {
        if (cards[index]) {
            const card = cards[index];
            const scrollLeft = card.offsetLeft - (gallery.offsetWidth - card.offsetWidth) / 2;
            gallery.scrollTo({
                left: scrollLeft,
                behavior: 'smooth'
            });
            updateDots(index);
            currentCard = index;
        }
    }

    function updateDots(activeIndex) {
        dotLinks.forEach((dot, i) => {
            dot.classList.toggle('current', i === activeIndex);
        });
    }

    function startAutoScroll() {
        autoScrollInterval = setInterval(() => {
            currentCard = (currentCard + 1) % cards.length;
            scrollToCard(currentCard);
        }, 4000);
    }

    function stopAutoScroll() {
        clearInterval(autoScrollInterval);
    }

    // Dot click handlers
    dotLinks.forEach((dot, index) => {
        dot.addEventListener('click', (e) => {
            e.preventDefault();
            stopAutoScroll();
            scrollToCard(index);
            if (isPlaying) startAutoScroll();
        });
    });

    // Play/Pause button
    const playPauseBtn = document.querySelector('.play-pause-button');
    if (playPauseBtn) {
        playPauseBtn.addEventListener('click', () => {
            isPlaying = !isPlaying;
            if (isPlaying) {
                startAutoScroll();
                playPauseBtn.querySelector('.play-icon').style.display = 'block';
                playPauseBtn.querySelector('.pause-icon').style.display = 'none';
            } else {
                stopAutoScroll();
                playPauseBtn.querySelector('.play-icon').style.display = 'none';
                playPauseBtn.querySelector('.pause-icon').style.display = 'block';
            }
        });
    }

    // Manual scroll detection
    let scrollTimeout;
    if (gallery) {
        gallery.addEventListener('scroll', () => {
            stopAutoScroll();
            clearTimeout(scrollTimeout);
            
            // Find which card is most visible
            const scrollCenter = gallery.scrollLeft + gallery.offsetWidth / 2;
            let closestCard = 0;
            let closestDistance = Infinity;
            
            cards.forEach((card, index) => {
                const cardCenter = card.offsetLeft + card.offsetWidth / 2;
                const distance = Math.abs(scrollCenter - cardCenter);
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestCard = index;
                }
            });
            
            currentCard = closestCard;
            updateDots(currentCard);
            
            scrollTimeout = setTimeout(() => {
                if (isPlaying) startAutoScroll();
            }, 2000);
        });
    }

    // Start auto-scroll if gallery exists
    if (gallery && cards.length > 0) {
        startAutoScroll();
    }

    // Intersection Observer for animations
    const animateElements = document.querySelectorAll('.step-item, .caption-tile, .feature-banner-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    animateElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(el);
    });

    // Typing animation for search demo
    const typingText = document.querySelector('.typing-text');
    if (typingText) {
        const text = typingText.textContent;
        typingText.textContent = '';
        let charIndex = 0;
        
        function typeChar() {
            if (charIndex < text.length) {
                typingText.textContent += text.charAt(charIndex);
                charIndex++;
                setTimeout(typeChar, 100);
            } else {
                // Reset after a pause
                setTimeout(() => {
                    typingText.textContent = '';
                    charIndex = 0;
                    setTimeout(typeChar, 500);
                }, 3000);
            }
        }
        
        // Start typing when element is visible
        const typingObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                typeChar();
                typingObserver.disconnect();
            }
        });
        typingObserver.observe(typingText);
    }

    // Navigation background on scroll
    const globalHeader = document.getElementById('globalheader');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            globalHeader.style.background = 'rgba(251, 251, 253, 0.95)';
        } else {
            globalHeader.style.background = 'rgba(251, 251, 253, 0.8)';
        }
    });

    // Parallax effect for hero devices
    const devicesLockup = document.querySelector('.devices-lockup');
    
    if (devicesLockup) {
        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            const rate = scrolled * 0.3;
            
            if (scrolled < 800) {
                devicesLockup.style.transform = `translateY(${rate}px)`;
            }
        });
    }

    console.log('LokAlert Mockup loaded successfully!');
});
