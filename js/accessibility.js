/**
 * LokAlert Accessibility Widget & Utilities
 * Shared across all pages for WCAG 2.1 AA compliance
 *
 * Features:
 * - Floating accessibility button with settings panel
 * - Font size adjustment (5 levels)
 * - High contrast mode
 * - Dyslexia-friendly font
 * - Underline links
 * - Large cursor
 * - Text spacing
 * - Settings persist in localStorage
 * - Skip navigation link injection
 * - Modal focus trapping
 * - Keyboard navigation enhancements
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'lokalert-a11y';
    var defaults = {
        fontSize: 'md',
        highContrast: false,
        dyslexiaFont: false,
        underlineLinks: false,
        largeCursor: false,
        textSpacing: false,
        readAloud: false
    };

    // --- State ---
    var settings = loadSettings();
    var panelOpen = false;

    // --- Load / Save ---
    function loadSettings() {
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                var parsed = JSON.parse(stored);
                // merge with defaults so new keys are always present
                var merged = {};
                for (var k in defaults) {
                    merged[k] = parsed.hasOwnProperty(k) ? parsed[k] : defaults[k];
                }
                return merged;
            }
        } catch (e) { }
        return JSON.parse(JSON.stringify(defaults));
    }

    function saveSettings() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
        } catch (e) { }
    }

    // --- Apply Settings to DOM ---
    function applySettings() {
        var body = document.body;
        var html = document.documentElement;
        // Font size — applied to html for zoom-based scaling
        var sizes = ['sm', 'md', 'lg', 'xl', 'xxl'];
        sizes.forEach(function (s) {
            html.classList.remove('a11y-font-' + s);
        });
        if (settings.fontSize && settings.fontSize !== 'md') {
            html.classList.add('a11y-font-' + settings.fontSize);
        }
        // Toggles
        body.classList.toggle('a11y-high-contrast', !!settings.highContrast);
        body.classList.toggle('a11y-dyslexia-font', !!settings.dyslexiaFont);
        body.classList.toggle('a11y-underline-links', !!settings.underlineLinks);
        body.classList.toggle('a11y-large-cursor', !!settings.largeCursor);
        body.classList.toggle('a11y-text-spacing', !!settings.textSpacing);

        // Update panel toggle buttons if open
        updatePanelState();
    }

    function updatePanelState() {
        var panel = document.getElementById('a11yPanel');
        if (!panel) return;

        // Font size buttons
        var sizes = ['sm', 'md', 'lg', 'xl', 'xxl'];
        sizes.forEach(function (s) {
            var btn = panel.querySelector('[data-font="' + s + '"]');
            if (btn) btn.classList.toggle('active', settings.fontSize === s);
        });

        // Toggles
        var toggles = ['highContrast', 'dyslexiaFont', 'underlineLinks', 'largeCursor', 'textSpacing', 'readAloud'];
        toggles.forEach(function (key) {
            var btn = panel.querySelector('[data-toggle="' + key + '"]');
            if (btn) {
                btn.classList.toggle('active', !!settings[key]);
                var stateEl = btn.querySelector('.a11y-toggle-state');
                if (stateEl) stateEl.textContent = settings[key] ? 'ON' : 'OFF';
            }
        });
    }

    // --- Create Widget DOM ---
    function createWidget() {
        // Floating button
        var btn = document.createElement('button');
        btn.className = 'a11y-widget-btn';
        btn.setAttribute('aria-label', 'Open accessibility settings');
        btn.setAttribute('title', 'Accessibility Settings');
        btn.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="4" r="2"/><path d="M19 13v-2c-1.54.02-3.09-.75-4.07-1.83l-1.29-1.43c-.17-.19-.38-.34-.61-.45-.01 0-.01-.01-.02-.01H13c-.35-.2-.75-.3-1.19-.26C10.76 7.11 10 8.04 10 9.09V15c0 1.1.9 2 2 2h5v5h2v-5.5c0-1.1-.9-2-2-2h-3v-3.45c1.29 1.07 3.25 1.94 5 1.95zm-6.17 5c-.41 1.16-1.52 2-2.83 2-1.66 0-3-1.34-3-3 0-1.31.84-2.41 2-2.83V12.1c-2.28.46-4 2.48-4 4.9 0 2.76 2.24 5 5 5 2.42 0 4.44-1.72 4.9-4h-2.07z"/></svg>';
        document.body.appendChild(btn);

        // Panel
        var panel = document.createElement('div');
        panel.id = 'a11yPanel';
        panel.className = 'a11y-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Accessibility settings');
        panel.setAttribute('aria-modal', 'false');
        panel.innerHTML =
            '<div class="a11y-panel-header">' +
                '<span class="a11y-panel-title">' +
                    '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="4" r="2"/><path d="M19 13v-2c-1.54.02-3.09-.75-4.07-1.83l-1.29-1.43c-.17-.19-.38-.34-.61-.45-.01 0-.01-.01-.02-.01H13c-.35-.2-.75-.3-1.19-.26C10.76 7.11 10 8.04 10 9.09V15c0 1.1.9 2 2 2h5v5h2v-5.5c0-1.1-.9-2-2-2h-3v-3.45c1.29 1.07 3.25 1.94 5 1.95zm-6.17 5c-.41 1.16-1.52 2-2.83 2-1.66 0-3-1.34-3-3 0-1.31.84-2.41 2-2.83V12.1c-2.28.46-4 2.48-4 4.9 0 2.76 2.24 5 5 5 2.42 0 4.44-1.72 4.9-4h-2.07z"/></svg>' +
                    ' Accessibility' +
                '</span>' +
                '<button class="a11y-panel-close" aria-label="Close accessibility settings">&times;</button>' +
            '</div>' +

            '<div class="a11y-section-label">Font Size</div>' +
            '<div class="a11y-font-controls">' +
                '<button class="a11y-font-btn" data-font="sm" aria-label="Small font size">A-</button>' +
                '<button class="a11y-font-btn" data-font="md" aria-label="Default font size">A</button>' +
                '<button class="a11y-font-btn" data-font="lg" aria-label="Large font size">A+</button>' +
                '<button class="a11y-font-btn" data-font="xl" aria-label="Extra large font size">A++</button>' +
                '<button class="a11y-font-btn" data-font="xxl" aria-label="Maximum font size">A+++</button>' +
            '</div>' +

            '<div class="a11y-section-label">Display</div>' +
            '<button class="a11y-toggle" data-toggle="highContrast" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">&#9681;</span>' +
                '<span class="a11y-toggle-label">High Contrast</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +
            '<button class="a11y-toggle" data-toggle="dyslexiaFont" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">Aa</span>' +
                '<span class="a11y-toggle-label">Dyslexia Font</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +
            '<button class="a11y-toggle" data-toggle="underlineLinks" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">&#818;U&#818;</span>' +
                '<span class="a11y-toggle-label">Underline Links</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +

            '<div class="a11y-section-label">Navigation</div>' +
            '<button class="a11y-toggle" data-toggle="largeCursor" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">&#10070;</span>' +
                '<span class="a11y-toggle-label">Large Cursor</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +
            '<button class="a11y-toggle" data-toggle="textSpacing" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">&#8646;</span>' +
                '<span class="a11y-toggle-label">Text Spacing</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +

            '<div class="a11y-section-label">Read Aloud</div>' +
            '<button class="a11y-toggle" data-toggle="readAloud" aria-pressed="false">' +
                '<span class="a11y-toggle-icon" aria-hidden="true">&#128266;</span>' +
                '<span class="a11y-toggle-label">Read Aloud</span>' +
                '<span class="a11y-toggle-state">OFF</span>' +
            '</button>' +

            '<button class="a11y-reset-btn" aria-label="Reset all accessibility settings">Reset All Settings</button>';

        document.body.appendChild(panel);

        // --- Event Handlers ---
        btn.addEventListener('click', function () {
            panelOpen = !panelOpen;
            panel.classList.toggle('open', panelOpen);
            btn.setAttribute('aria-expanded', panelOpen ? 'true' : 'false');
            if (panelOpen) {
                panel.querySelector('.a11y-panel-close').focus();
            }
        });

        panel.querySelector('.a11y-panel-close').addEventListener('click', function () {
            closePanel();
        });

        // Font size buttons
        panel.querySelectorAll('.a11y-font-btn').forEach(function (fbtn) {
            fbtn.addEventListener('click', function () {
                settings.fontSize = this.getAttribute('data-font');
                saveSettings();
                applySettings();
                announceChange('Font size changed to ' + settings.fontSize);
            });
        });

        // Toggle buttons
        panel.querySelectorAll('.a11y-toggle').forEach(function (tbtn) {
            tbtn.addEventListener('click', function () {
                var key = this.getAttribute('data-toggle');
                settings[key] = !settings[key];
                this.setAttribute('aria-pressed', settings[key] ? 'true' : 'false');
                saveSettings();
                applySettings();
                var label = this.querySelector('.a11y-toggle-label').textContent;
                announceChange(label + ' ' + (settings[key] ? 'enabled' : 'disabled'));
                // Handle read-aloud toggle
                if (key === 'readAloud') {
                    toggleReadAloudToolbar(settings[key]);
                }
            });
        });

        // Reset button
        panel.querySelector('.a11y-reset-btn').addEventListener('click', function () {
            settings = JSON.parse(JSON.stringify(defaults));
            saveSettings();
            applySettings();
            // Update aria-pressed on all toggles
            panel.querySelectorAll('.a11y-toggle').forEach(function (t) {
                t.setAttribute('aria-pressed', 'false');
            });
            // Stop and hide read-aloud
            toggleReadAloudToolbar(false);
            announceChange('All accessibility settings have been reset');
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panelOpen) {
                closePanel();
            }
        });

        // Close on click outside
        document.addEventListener('click', function (e) {
            if (panelOpen && !panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                closePanel();
            }
        });

        function closePanel() {
            panelOpen = false;
            panel.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.focus();
        }
    }

    // --- Skip Navigation ---
    function injectSkipNav() {
        // Determine the main content target
        var mainTarget = document.querySelector('main') ||
            document.getElementById('siteContent') ||
            document.getElementById('main-content');
        if (!mainTarget) return;

        if (!mainTarget.id) mainTarget.id = 'main-content';

        var skipLink = document.createElement('a');
        skipLink.href = '#' + mainTarget.id;
        skipLink.className = 'skip-nav';
        skipLink.textContent = 'Skip to main content';
        document.body.insertBefore(skipLink, document.body.firstChild);
    }

    // --- Live Region for Announcements ---
    var liveRegion;
    function createLiveRegion() {
        liveRegion = document.createElement('div');
        liveRegion.className = 'a11y-live-region';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        document.body.appendChild(liveRegion);
    }

    function announceChange(message) {
        if (!liveRegion) return;
        liveRegion.textContent = '';
        // Force re-announcement
        setTimeout(function () {
            liveRegion.textContent = message;
        }, 100);
    }

    // --- ARIA Enhancements ---
    function enhanceARIA() {
        // Add role="main" to main elements without it
        var mainEl = document.querySelector('main');
        if (mainEl && !mainEl.getAttribute('role')) {
            mainEl.setAttribute('role', 'main');
        }

        // Add role="navigation" to nav elements
        document.querySelectorAll('nav').forEach(function (nav) {
            if (!nav.getAttribute('role')) {
                nav.setAttribute('role', 'navigation');
            }
            if (!nav.getAttribute('aria-label')) {
                // Try to determine type of nav
                if (nav.classList.contains('sidebar-nav') || nav.closest('.sidebar')) {
                    nav.setAttribute('aria-label', 'Admin sidebar navigation');
                } else if (nav.classList.contains('g-nav') || nav.id === 'navbar') {
                    nav.setAttribute('aria-label', 'Main navigation');
                } else {
                    nav.setAttribute('aria-label', 'Navigation');
                }
            }
        });

        // Add role="contentinfo" to footer
        document.querySelectorAll('footer').forEach(function (footer) {
            if (!footer.getAttribute('role')) {
                footer.setAttribute('role', 'contentinfo');
            }
        });

        // Add role="banner" to header/topbar
        var topbar = document.querySelector('.topbar, header.topbar');
        if (topbar && !topbar.getAttribute('role')) {
            topbar.setAttribute('role', 'banner');
        }

        // Alert containers get aria-live
        var alertContainer = document.getElementById('alertContainer');
        if (alertContainer) {
            alertContainer.setAttribute('aria-live', 'assertive');
            alertContainer.setAttribute('role', 'alert');
        }

        // Session timer gets role="status"
        var sessionTimer = document.getElementById('sessionTimer');
        if (sessionTimer) {
            sessionTimer.setAttribute('role', 'status');
            sessionTimer.setAttribute('aria-live', 'polite');
        }

        // Contact form message area
        var cfMsg = document.getElementById('cfMsg');
        if (cfMsg) {
            cfMsg.setAttribute('aria-live', 'polite');
            cfMsg.setAttribute('role', 'status');
        }

        // Greeting banner
        var greetBanner = document.getElementById('greetingBanner');
        if (greetBanner) {
            greetBanner.setAttribute('role', 'status');
            greetBanner.setAttribute('aria-live', 'polite');
        }

        // Make decorative emojis hidden from screen readers
        document.querySelectorAll('.stat-icon, .nav-icon, .maint-icon, .logo-icon, .feature-icon span, .benefit-icon, .tech-icon span, .ba-icon, .contact-detail-icon').forEach(function (el) {
            el.setAttribute('aria-hidden', 'true');
        });

        // Add aria-hidden to decorative SVGs
        document.querySelectorAll('.scroll-hero-geo, .hero-particles, .particle, .floating-accent').forEach(function (el) {
            el.setAttribute('aria-hidden', 'true');
        });

        // Modals: add dialog role
        document.querySelectorAll('.modal-overlay').forEach(function (modal) {
            if (!modal.getAttribute('role')) {
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
            }
            // Try to find a heading for aria-label
            var heading = modal.querySelector('h2, h3');
            if (heading && !modal.getAttribute('aria-label')) {
                modal.setAttribute('aria-label', heading.textContent.replace(/[^\w\s]/g, '').trim());
            }
        });

        // Table headers: add scope
        document.querySelectorAll('th').forEach(function (th) {
            if (!th.getAttribute('scope')) {
                th.setAttribute('scope', 'col');
            }
        });

        // Table captions
        document.querySelectorAll('table').forEach(function (table) {
            if (table.querySelector('caption')) return;
            // Try to determine table purpose from nearest heading
            var card = table.closest('.card, .table-wrap');
            if (card) {
                var heading = card.querySelector('h2, h3');
                if (heading) {
                    var caption = document.createElement('caption');
                    caption.className = 'sr-only';
                    caption.textContent = heading.textContent.replace(/[^\w\s,.-]/g, '').trim();
                    table.insertBefore(caption, table.firstChild);
                }
            }
        });

        // Ensure all form inputs have associated labels
        document.querySelectorAll('input, select, textarea').forEach(function (input) {
            if (input.type === 'hidden' || input.type === 'submit') return;
            if (input.id) {
                var label = document.querySelector('label[for="' + input.id + '"]');
                if (label) return; // already associated
            }
            // Check if there's a parent label
            if (input.closest('label')) return;
            // Check for preceding label in same form-group
            var group = input.closest('.form-group, .cf-group, .checkbox-group, .setting-item');
            if (group) {
                var label = group.querySelector('label');
                if (label && !label.getAttribute('for') && input.id) {
                    label.setAttribute('for', input.id);
                }
            }
            // If still no label and has placeholder, add aria-label
            if (!input.getAttribute('aria-label') && !input.closest('label') && input.placeholder) {
                input.setAttribute('aria-label', input.placeholder);
            }
        });

        // Make div-based nav items keyboard accessible
        document.querySelectorAll('.nav-item[onclick]').forEach(function (item) {
            if (!item.getAttribute('role')) {
                item.setAttribute('role', 'button');
            }
            if (!item.hasAttribute('tabindex')) {
                item.setAttribute('tabindex', '0');
            }
            // Keyboard activation
            if (!item._a11yKeyHandler) {
                item._a11yKeyHandler = true;
                item.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            }
        });

        // Theme toggle keyboard accessibility
        document.querySelectorAll('.theme-toggle, .th-pill').forEach(function (toggle) {
            if (!toggle.hasAttribute('tabindex') && toggle.tagName !== 'BUTTON') {
                toggle.setAttribute('tabindex', '0');
                toggle.setAttribute('role', 'button');
            }
            if (!toggle._a11yKeyHandler) {
                toggle._a11yKeyHandler = true;
                toggle.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            }
        });

        // Backup action divs
        document.querySelectorAll('.backup-action[onclick]').forEach(function (el) {
            if (!el.getAttribute('role')) {
                el.setAttribute('role', 'button');
                el.setAttribute('tabindex', '0');
            }
            if (!el._a11yKeyHandler) {
                el._a11yKeyHandler = true;
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            }
        });
    }

    // --- Modal Focus Trap ---
    var focusTrapStack = [];

    function trapFocus(modalEl) {
        var focusable = modalEl.querySelectorAll(
            'a[href], button:not([disabled]), textarea, input:not([type="hidden"]):not([disabled]), select, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;

        var firstFocusable = focusable[0];
        var lastFocusable = focusable[focusable.length - 1];
        var previouslyFocused = document.activeElement;

        focusTrapStack.push({
            modal: modalEl,
            previousFocus: previouslyFocused,
            handler: handleTrapKeydown
        });

        function handleTrapKeydown(e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        }

        modalEl.addEventListener('keydown', handleTrapKeydown);
        firstFocusable.focus();
    }

    function releaseFocus(modalEl) {
        for (var i = focusTrapStack.length - 1; i >= 0; i--) {
            if (focusTrapStack[i].modal === modalEl) {
                modalEl.removeEventListener('keydown', focusTrapStack[i].handler);
                var prev = focusTrapStack[i].previousFocus;
                focusTrapStack.splice(i, 1);
                if (prev && prev.focus) {
                    try { prev.focus(); } catch (e) { }
                }
                break;
            }
        }
    }

    // Expose globally for use by other scripts
    window.a11yTrapFocus = trapFocus;
    window.a11yReleaseFocus = releaseFocus;

    // --- Watch for Modal Opens/Closes ---
    function observeModals() {
        // Use MutationObserver to detect when modals become active
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type !== 'attributes') return;
                var el = mutation.target;
                if (!el.classList.contains('modal-overlay')) return;

                if (el.classList.contains('active') || el.style.display === 'flex') {
                    trapFocus(el);
                } else {
                    releaseFocus(el);
                }
            });
        });

        document.querySelectorAll('.modal-overlay').forEach(function (modal) {
            observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
        });

        // Global Escape key for modals
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            // Find topmost open modal
            var openModals = document.querySelectorAll('.modal-overlay.active, .modal-overlay[style*="display: flex"]');
            if (openModals.length > 0) {
                var topModal = openModals[openModals.length - 1];
                // Try to find close button
                var closeBtn = topModal.querySelector('.modal-close, [onclick*="close"], button[class*="close"]');
                if (closeBtn) closeBtn.click();
            }
        });
    }

    // --- Video Descriptions for Hearing Impaired ---
    function addVideoDescriptions() {
        var videoDescriptions = {
            'hero-city': 'Aerial view of a vibrant city at night with glowing lights and busy streets, representing the urban commute experience.',
            'commuter-journey': 'A commuter traveling through the city on public transit, looking out the window at passing scenery.',
            'streets-walking': 'People walking through city streets, navigating their daily commute through urban environments.'
        };

        document.querySelectorAll('video').forEach(function (video) {
            // Add aria-label to video
            if (!video.getAttribute('aria-label')) {
                var src = video.querySelector('source');
                var srcVal = (src && src.getAttribute('src')) || video.getAttribute('src') || '';
                var matchedDesc = null;
                for (var key in videoDescriptions) {
                    if (srcVal.indexOf(key) !== -1) {
                        matchedDesc = videoDescriptions[key];
                        break;
                    }
                }
                if (matchedDesc) {
                    video.setAttribute('aria-label', matchedDesc);
                } else {
                    video.setAttribute('aria-label', 'Decorative background video');
                }
            }

            // Add a track element for descriptions
            if (!video.querySelector('track')) {
                var track = document.createElement('track');
                track.kind = 'descriptions';
                track.label = 'Descriptions';
                track.srclang = 'en';
                track.default = true;
                // Use an empty data URI since we don't have actual VTT files
                track.src = 'data:text/vtt,' + encodeURIComponent('WEBVTT\n\n');
                video.appendChild(track);
            }
        });
    }

    // ============================================================
    //  READ ALOUD — Web Speech API (SpeechSynthesis)
    //  Uses the browser's built-in text-to-speech engine.
    //  No audio files required. Works in all modern browsers.
    // ============================================================
    var raBar = null;           // toolbar DOM element
    var raState = {
        speaking: false,
        paused: false,
        utterance: null,
        currentEl: null,        // currently highlighted element
        queue: [],              // sequential reading queue
        queueIndex: -1,
        speed: 1,
        voiceIndex: 0,
        voices: [],
        clickHandler: null      // stored click handler ref for removal
    };

    var RA_PREFS_KEY = 'lokalert-ra-prefs';

    function loadRAPrefs() {
        try {
            var stored = localStorage.getItem(RA_PREFS_KEY);
            if (stored) {
                var parsed = JSON.parse(stored);
                if (parsed.speed) raState.speed = parsed.speed;
                if (typeof parsed.voiceIndex === 'number') raState.voiceIndex = parsed.voiceIndex;
            }
        } catch (e) { }
    }

    function saveRAPrefs() {
        try {
            localStorage.setItem(RA_PREFS_KEY, JSON.stringify({
                speed: raState.speed,
                voiceIndex: raState.voiceIndex
            }));
        } catch (e) { }
    }

    // Build the floating toolbar DOM
    function createReadAloudBar() {
        if (raBar) return; // already created

        raBar = document.createElement('div');
        raBar.className = 'a11y-ra-bar';
        raBar.setAttribute('role', 'toolbar');
        raBar.setAttribute('aria-label', 'Read aloud controls');
        raBar.innerHTML =
            '<div class="a11y-ra-bar-header">' +
                '<span class="a11y-ra-title">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>' +
                    ' Read Aloud' +
                '</span>' +
                '<button class="a11y-ra-close" aria-label="Close read aloud toolbar">&times;</button>' +
            '</div>' +
            '<div class="a11y-ra-controls">' +
                '<button class="a11y-ra-btn" id="raBtnPlay" aria-label="Play" title="Read page content">' +
                    '<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                '</button>' +
                '<button class="a11y-ra-btn" id="raBtnPause" aria-label="Pause" title="Pause reading" style="display:none">' +
                    '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>' +
                '</button>' +
                '<button class="a11y-ra-btn" id="raBtnResume" aria-label="Resume" title="Resume reading" style="display:none">' +
                    '<svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                '</button>' +
                '<span class="a11y-ra-status" id="raStatus">Click text or press play</span>' +
                '<button class="a11y-ra-btn" id="raBtnStop" aria-label="Stop" title="Stop reading">' +
                    '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="1"/></svg>' +
                '</button>' +
            '</div>' +
            '<div class="a11y-ra-speed-row">' +
                '<span class="a11y-ra-speed-label">Speed</span>' +
                '<input type="range" class="a11y-ra-speed-slider" id="raSpeed" min="0.5" max="2" step="0.25" value="1" aria-label="Speech speed">' +
                '<span class="a11y-ra-speed-val" id="raSpeedVal">1x</span>' +
            '</div>' +
            '<div class="a11y-ra-voice-row">' +
                '<span class="a11y-ra-voice-label">Voice</span>' +
                '<select class="a11y-ra-voice-select" id="raVoice" aria-label="Voice selection"></select>' +
            '</div>' +
            '<div class="a11y-ra-hint">Click any text on the page to read it aloud</div>';

        document.body.appendChild(raBar);

        // Wire up toolbar events
        raBar.querySelector('.a11y-ra-close').addEventListener('click', function () {
            settings.readAloud = false;
            saveSettings();
            applySettings();
            updatePanelState();
            toggleReadAloudToolbar(false);
        });

        document.getElementById('raBtnPlay').addEventListener('click', function () {
            startSequentialRead();
        });

        document.getElementById('raBtnPause').addEventListener('click', function () {
            pauseReading();
        });

        document.getElementById('raBtnResume').addEventListener('click', function () {
            resumeReading();
        });

        document.getElementById('raBtnStop').addEventListener('click', function () {
            stopReading();
        });

        // Speed slider
        var speedSlider = document.getElementById('raSpeed');
        var speedVal = document.getElementById('raSpeedVal');
        speedSlider.value = raState.speed;
        speedVal.textContent = raState.speed + 'x';

        speedSlider.addEventListener('input', function () {
            raState.speed = parseFloat(this.value);
            speedVal.textContent = raState.speed + 'x';
            saveRAPrefs();
            // If currently speaking, restart with new speed
            if (raState.speaking && !raState.paused && raState.currentEl) {
                var el = raState.currentEl;
                var qi = raState.queueIndex;
                var q = raState.queue.slice();
                stopReading();
                raState.queue = q;
                raState.queueIndex = qi;
                speakElement(el, true);
            }
        });

        // Voice selector - populate
        populateVoices();
        // Voices may load asynchronously
        if (window.speechSynthesis) {
            window.speechSynthesis.addEventListener('voiceschanged', populateVoices);
        }
    }

    var voiceChangeWired = false;

    function populateVoices() {
        if (!window.speechSynthesis) return;
        var voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) return;

        raState.voices = voices;
        var select = document.getElementById('raVoice');
        if (!select) return;

        select.innerHTML = '';
        // Prefer English voices, put them first
        var enVoices = [];
        var otherVoices = [];
        voices.forEach(function (v, i) {
            if (v.lang.indexOf('en') === 0) {
                enVoices.push({ voice: v, index: i });
            } else {
                otherVoices.push({ voice: v, index: i });
            }
        });

        var sorted = enVoices.concat(otherVoices);
        sorted.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.index;
            var name = item.voice.name;
            if (name.length > 28) name = name.substring(0, 26) + '...';
            opt.textContent = name;
            if (item.voice.default) opt.textContent += ' *';
            select.appendChild(opt);
        });

        // Restore saved voice preference
        if (raState.voiceIndex < voices.length) {
            select.value = raState.voiceIndex;
        }

        // Only wire the change listener once
        if (!voiceChangeWired) {
            voiceChangeWired = true;
            select.addEventListener('change', function () {
                raState.voiceIndex = parseInt(this.value, 10);
                saveRAPrefs();
            });
        }
    }

    // Toggle the toolbar visibility and click-to-read mode
    function toggleReadAloudToolbar(show) {
        if (!window.speechSynthesis) {
            if (show) {
                announceChange('Read aloud is not supported in this browser');
            }
            return;
        }

        if (show) {
            createReadAloudBar();
            // Force display:flex first (needed for transition)
            raBar.style.display = 'flex';
            // Trigger reflow for transition
            raBar.offsetHeight;
            raBar.classList.add('open');
            document.body.classList.add('a11y-ra-active');
            enableClickToRead();
        } else {
            stopReading();
            if (raBar) {
                raBar.classList.remove('open');
                setTimeout(function () {
                    if (raBar) raBar.style.display = 'none';
                }, 250);
            }
            document.body.classList.remove('a11y-ra-active');
            disableClickToRead();
        }
    }

    // Get readable text elements from the page
    function getReadableElements() {
        // Determine the main content container (varies by page)
        var container =
            document.getElementById('siteContent') ||
            document.getElementById('main-content') ||
            document.querySelector('main') ||
            document.body;

        var tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'td', 'th'];
        var elements = [];
        var seen = new Set();

        tags.forEach(function (tag) {
            try {
                container.querySelectorAll(tag).forEach(function (el) {
                    // Skip hidden elements, the a11y panel, and duplicates
                    if (seen.has(el)) return;
                    if (el.closest('.a11y-panel, .a11y-ra-bar, .a11y-widget-btn, [aria-hidden="true"]')) return;
                    if (el.offsetParent === null && !el.closest('[style*="visibility"]')) return;
                    var text = (el.textContent || '').trim();
                    if (text.length < 2) return;
                    // Skip elements that are only whitespace
                    if (/^[\s\u200B-\u200D\uFEFF]+$/.test(text)) return;
                    seen.add(el);
                    elements.push(el);
                });
            } catch (e) { }
        });

        // Sort by document order
        elements.sort(function (a, b) {
            var pos = a.compareDocumentPosition(b);
            if (pos & Node.DOCUMENT_POSITION_FOLLOWING) return -1;
            if (pos & Node.DOCUMENT_POSITION_PRECEDING) return 1;
            return 0;
        });

        return elements;
    }

    // Get readable text from an element (cleaned up)
    function getReadableText(el) {
        var text = (el.textContent || '').trim();
        // Remove excessive whitespace
        text = text.replace(/\s+/g, ' ');
        // Remove common non-readable characters
        text = text.replace(/[^\w\s.,!?;:'"()\-\u2014\u2013\u2019\u2018\u201C\u201D]/g, ' ');
        text = text.replace(/\s+/g, ' ').trim();
        return text;
    }

    // Speak a specific element
    function speakElement(el, fromQueue) {
        if (!window.speechSynthesis) return;

        var text = getReadableText(el);
        if (!text || text.length < 2) {
            // Skip empty, advance queue
            if (fromQueue && raState.queue.length > 0) {
                advanceQueue();
            }
            return;
        }

        // Cancel any current speech
        window.speechSynthesis.cancel();

        // Clear previous highlight
        clearHighlight();

        // Highlight current element
        el.classList.add('a11y-ra-highlight');
        raState.currentEl = el;

        // Scroll element into view if needed
        var rect = el.getBoundingClientRect();
        if (rect.top < 60 || rect.bottom > window.innerHeight - 60) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Create utterance
        var utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = raState.speed;
        utterance.pitch = 1;
        utterance.volume = 1;

        // Set voice
        if (raState.voices.length > 0 && raState.voiceIndex < raState.voices.length) {
            utterance.voice = raState.voices[raState.voiceIndex];
        }

        // Event handlers
        utterance.onstart = function () {
            raState.speaking = true;
            raState.paused = false;
            updateToolbarButtons('speaking');
            updateStatus('Reading...');
        };

        utterance.onend = function () {
            raState.speaking = false;
            raState.paused = false;
            clearHighlight();
            // Advance to next in queue
            if (fromQueue && raState.queue.length > 0) {
                advanceQueue();
            } else {
                updateToolbarButtons('idle');
                updateStatus('Click text or press play');
            }
        };

        utterance.onerror = function (e) {
            // 'interrupted' is normal when user stops/changes
            if (e.error !== 'interrupted' && e.error !== 'canceled') {
                raState.speaking = false;
                raState.paused = false;
                clearHighlight();
                updateToolbarButtons('idle');
                updateStatus('Error: ' + e.error);
            }
        };

        raState.utterance = utterance;
        window.speechSynthesis.speak(utterance);
    }

    // Start reading all page content sequentially
    function startSequentialRead() {
        stopReading();
        raState.queue = getReadableElements();
        if (raState.queue.length === 0) {
            updateStatus('No readable text found');
            return;
        }
        raState.queueIndex = 0;
        speakElement(raState.queue[0], true);
    }

    // Advance to next element in queue
    function advanceQueue() {
        raState.queueIndex++;
        if (raState.queueIndex < raState.queue.length) {
            speakElement(raState.queue[raState.queueIndex], true);
        } else {
            // Finished all
            raState.queue = [];
            raState.queueIndex = -1;
            updateToolbarButtons('idle');
            updateStatus('Finished reading');
            announceChange('Read aloud finished');
        }
    }

    // Pause current speech
    function pauseReading() {
        if (window.speechSynthesis && raState.speaking) {
            window.speechSynthesis.pause();
            raState.paused = true;
            updateToolbarButtons('paused');
            updateStatus('Paused');
        }
    }

    // Resume paused speech
    function resumeReading() {
        if (window.speechSynthesis && raState.paused) {
            window.speechSynthesis.resume();
            raState.paused = false;
            updateToolbarButtons('speaking');
            updateStatus('Reading...');
        }
    }

    // Stop all speech
    function stopReading() {
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        raState.speaking = false;
        raState.paused = false;
        raState.utterance = null;
        raState.queue = [];
        raState.queueIndex = -1;
        clearHighlight();
        updateToolbarButtons('idle');
        updateStatus('Click text or press play');
    }

    // Clear highlight from current element
    function clearHighlight() {
        if (raState.currentEl) {
            raState.currentEl.classList.remove('a11y-ra-highlight');
            raState.currentEl = null;
        }
        // Also clear any stale highlights
        document.querySelectorAll('.a11y-ra-highlight').forEach(function (el) {
            el.classList.remove('a11y-ra-highlight');
        });
    }

    // Update toolbar button visibility based on state
    function updateToolbarButtons(state) {
        var playBtn = document.getElementById('raBtnPlay');
        var pauseBtn = document.getElementById('raBtnPause');
        var resumeBtn = document.getElementById('raBtnResume');
        if (!playBtn) return;

        playBtn.style.display = 'none';
        pauseBtn.style.display = 'none';
        resumeBtn.style.display = 'none';

        if (state === 'speaking') {
            pauseBtn.style.display = 'flex';
        } else if (state === 'paused') {
            resumeBtn.style.display = 'flex';
        } else {
            playBtn.style.display = 'flex';
        }
    }

    // Update status text
    function updateStatus(text) {
        var statusEl = document.getElementById('raStatus');
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.classList.toggle('reading', raState.speaking && !raState.paused);
        }
    }

    // Enable click-to-read on text elements
    function enableClickToRead() {
        if (raState.clickHandler) return; // already enabled

        raState.clickHandler = function (e) {
            // Find closest readable text element
            var target = e.target.closest('p, h1, h2, h3, h4, h5, h6, li, td, th');
            if (!target) return;
            // Don't read accessibility controls
            if (target.closest('.a11y-panel, .a11y-ra-bar, .a11y-widget-btn')) return;
            // Don't read hidden elements
            if (target.closest('[aria-hidden="true"]')) return;

            var text = getReadableText(target);
            if (text && text.length >= 2) {
                e.preventDefault();
                e.stopPropagation();
                // Clear queue (click-to-read overrides sequential)
                raState.queue = [];
                raState.queueIndex = -1;
                speakElement(target, false);
            }
        };

        document.addEventListener('click', raState.clickHandler, true);
    }

    // Disable click-to-read
    function disableClickToRead() {
        if (raState.clickHandler) {
            document.removeEventListener('click', raState.clickHandler, true);
            raState.clickHandler = null;
        }
    }

    // Initialize read-aloud (called from init)
    function initReadAloud() {
        loadRAPrefs();
        // If readAloud was previously enabled, restore the toolbar
        if (settings.readAloud) {
            toggleReadAloudToolbar(true);
        }
    }

    // --- Init ---
    function init() {
        createWidget();
        createLiveRegion();
        injectSkipNav();
        applySettings();
        enhanceARIA();
        addVideoDescriptions();
        observeModals();
        initReadAloud();

        // Re-enhance ARIA when DOM changes (for SPAs like admin)
        var bodyObserver = new MutationObserver(function () {
            enhanceARIA();
        });
        bodyObserver.observe(document.body, { childList: true, subtree: true });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
