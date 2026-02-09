/**
 * LokAlert Download System v2.0
 * Features: Signup, Email Verification, Download Tracking, Rate Limiting
 */

// Production API URL - use relative path for same-origin (works both locally and in production)
const API_BASE = 'api';

// Check if we're in production (cross-origin)
const IS_PRODUCTION = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

// Fetch options for cross-origin requests
const fetchOptions = IS_PRODUCTION ? { credentials: 'include' } : {};

/**
 * Helper function to make API calls with proper CORS handling
 */
async function apiCall(url, options = {}) {
    const defaultOptions = {
        ...fetchOptions,
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    };
    return fetch(url, { ...defaultOptions, ...options });
}

// State
let currentUser = null;
let currentDownloadToken = null;
let isDownloading = false;
let cooldownInterval = null;

// DOM Elements
let modal, modalContent;

document.addEventListener('DOMContentLoaded', () => {
    initModal();
    checkAuthStatus();
    setupEventListeners();
});

/**
 * Initialize modal and create structure
 */
function initModal() {
    modal = document.getElementById('downloadModal');
    modalContent = document.querySelector('.modal-content');
    
    // Build modal structure
    if (modalContent) {
        modalContent.innerHTML = `
            <button class="modal-close" onclick="closeModal()">&times;</button>
            
            <!-- Main Download Section -->
            <div class="modal-section" id="downloadSection">
                <div class="modal-header">
                    <div class="modal-icon">📥</div>
                    <h2>Download LokAlert</h2>
                    <p>Get the latest version of our app</p>
                </div>
                <div class="version-info" id="versionInfo">
                    <div class="version-loading">Loading version info...</div>
                </div>
                <div id="downloadArea"></div>
            </div>
            
            <!-- Signup Section -->
            <div class="modal-section" id="signupSection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">📝</div>
                    <h2>Create Account</h2>
                    <p>Quick signup to download</p>
                </div>
                <form id="signupForm" onsubmit="handleSignup(event)">
                    <div class="form-group">
                        <label>Name (Optional)</label>
                        <input type="text" id="signupName" placeholder="Enter your name or nickname">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" id="signupEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" id="signupPassword" placeholder="Create a password (min 6 chars)" required minlength="6">
                    </div>
                    <div class="form-error" id="signupError"></div>
                    <button type="submit" class="btn btn-primary btn-full" id="signupBtn">
                        <span>Create Account</span>
                    </button>
                </form>
                <p class="form-footer">
                    Already have an account? <a href="#" onclick="showSection('login'); return false;">Log in</a>
                </p>
            </div>
            
            <!-- Login Section -->
            <div class="modal-section" id="loginSection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">🔐</div>
                    <h2>Welcome Back</h2>
                    <p>Log in to download</p>
                </div>
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="loginEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="loginPassword" placeholder="Enter your password" required>
                    </div>
                    <div class="form-error" id="loginError"></div>
                    <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                        <span>Log In</span>
                    </button>
                </form>
                <p class="form-footer">
                    Don't have an account? <a href="#" onclick="showSection('signup'); return false;">Sign up</a><br>
                    <a href="#" onclick="showSection('forgot'); return false;">Forgot password?</a>
                </p>
            </div>
            
            <!-- Verification Section -->
            <div class="modal-section" id="verifySection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">✉️</div>
                    <h2>Verify Your Email</h2>
                    <p>We sent a code to <span id="verifyEmail"></span></p>
                </div>
                <form id="verifyForm" onsubmit="handleVerification(event)">
                    <div class="form-group">
                        <label>Verification Code</label>
                        <input type="text" id="verifyCode" placeholder="Enter 6-digit code" required maxlength="6" pattern="[0-9]{6}" class="code-input">
                    </div>
                    <div class="form-error" id="verifyError"></div>
                    <button type="submit" class="btn btn-primary btn-full" id="verifyBtn">
                        <span>Verify Email</span>
                    </button>
                </form>
                <p class="form-footer">
                    Didn't receive the code? <a href="#" onclick="resendCode(); return false;">Resend</a>
                </p>
            </div>
            
            <!-- Forgot Password Section -->
            <div class="modal-section" id="forgotSection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">🔑</div>
                    <h2>Reset Password</h2>
                    <p>We'll send you a reset link</p>
                </div>
                <form id="forgotForm" onsubmit="handleForgotPassword(event)">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="forgotEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="form-error" id="forgotError"></div>
                    <div class="form-success" id="forgotSuccess"></div>
                    <button type="submit" class="btn btn-primary btn-full" id="forgotBtn">
                        <span>Send Reset Link</span>
                    </button>
                </form>
                <p class="form-footer">
                    <a href="#" onclick="showSection('login'); return false;">Back to login</a>
                </p>
            </div>
        `;
    }
    
    loadVersionInfo();
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Download buttons
    document.querySelectorAll('.nav-download-btn, .mobile-download-btn, .floating-download, [onclick*="openDownloadModal"]').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            openDownloadModal();
        };
    });
    
    // Close modal on overlay click
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }
    
    // ESC key to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal?.classList.contains('active')) {
            closeModal();
        }
    });
}

/**
 * Check authentication status
 */
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=check`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.authenticated && data.user) {
            currentUser = data.user;
            updateUIForUser();
        } else {
            currentUser = null;
        }
    } catch (error) {
        console.error('Auth check failed:', error);
    }
}

/**
 * Update UI based on user status
 */
function updateUIForUser() {
    const downloadArea = document.getElementById('downloadArea');
    if (!downloadArea) return;
    
    if (!currentUser) {
        // Not logged in - show signup/login buttons
        downloadArea.innerHTML = `
            <div class="auth-prompt">
                <p style="color: #94a3b8; margin-bottom: 20px;">Create a free account to download</p>
                <button class="btn btn-primary btn-full" onclick="showSection('signup')">
                    <span>📝</span> Sign Up
                </button>
                <button class="btn btn-secondary btn-full" onclick="showSection('login')" style="margin-top: 10px;">
                    <span>🔐</span> Log In
                </button>
            </div>
        `;
    } else if (!currentUser.is_verified) {
        // Logged in but not verified
        downloadArea.innerHTML = `
            <div class="verify-prompt">
                <p style="color: #f59e0b; margin-bottom: 15px;">⚠️ Please verify your email to download</p>
                <button class="btn btn-primary btn-full" onclick="showSection('verify')">
                    <span>✉️</span> Enter Verification Code
                </button>
            </div>
        `;
        document.getElementById('verifyEmail').textContent = currentUser.email;
    } else if (!currentUser.can_download) {
        // Verified but on cooldown
        showCooldownUI(currentUser.cooldown_remaining);
    } else {
        // Ready to download
        downloadArea.innerHTML = `
            <div class="download-ready">
                <p style="color: #94a3b8; margin-bottom: 10px;">
                    Welcome, <strong>${currentUser.username || currentUser.email}</strong>!
                </p>
                <button class="btn btn-primary btn-full btn-download" onclick="startDownload()">
                    <span>📥</span> Download APK
                </button>
                <button class="btn btn-text" onclick="handleLogout()" style="margin-top: 10px;">
                    Log out
                </button>
            </div>
        `;
    }
}

/**
 * Show cooldown UI
 */
function showCooldownUI(seconds) {
    const downloadArea = document.getElementById('downloadArea');
    if (!downloadArea) return;
    
    downloadArea.innerHTML = `
        <div class="cooldown-active">
            <div class="cooldown-icon">⏱️</div>
            <p style="color: #f59e0b; margin-bottom: 10px;">Download cooldown active</p>
            <div class="cooldown-timer" id="cooldownTimer">${formatTime(seconds)}</div>
            <p style="color: #64748b; font-size: 13px; margin-top: 10px;">
                Please wait before downloading again
            </p>
            <button class="btn btn-text" onclick="handleLogout()" style="margin-top: 15px;">
                Log out
            </button>
        </div>
    `;
    
    startCooldownTimer(seconds);
}

/**
 * Start cooldown timer
 */
function startCooldownTimer(seconds) {
    if (cooldownInterval) clearInterval(cooldownInterval);
    
    let remaining = seconds;
    cooldownInterval = setInterval(() => {
        remaining--;
        const timer = document.getElementById('cooldownTimer');
        if (timer) timer.textContent = formatTime(remaining);
        
        if (remaining <= 0) {
            clearInterval(cooldownInterval);
            checkAuthStatus();
        }
    }, 1000);
}

/**
 * Format seconds to MM:SS
 */
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Load version info
 */
async function loadVersionInfo() {
    const versionInfo = document.getElementById('versionInfo');
    if (!versionInfo) return;
    
    try {
        const response = await fetch(`${API_BASE}/downloads.php?action=latest`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success && data.version) {
            const v = data.version;
            const isExternal = v.download_url && v.download_url.startsWith('http');
            const hostInfo = isExternal ? getHostFromUrl(v.download_url) : 'Local Server';
            
            versionInfo.innerHTML = `
                <div class="version-card">
                    <div class="version-header">
                        <span class="version-number">v${v.version}</span>
                        <span class="version-size">${v.file_size_formatted || 'APK'}</span>
                    </div>
                    <div class="version-stats">
                        <span>📥 ${(v.download_count || 0).toLocaleString()} downloads</span>
                        <span style="margin-left: 12px; color: #94a3b8;">📦 ${hostInfo}</span>
                    </div>
                    <div class="version-notes">${(v.release_notes || 'Latest stable release').replace(/\n/g, '<br>')}</div>
                </div>
            `;
        }
    } catch (error) {
        versionInfo.innerHTML = '<p style="color: #ef4444;">Failed to load version info</p>';
    }
}

/**
 * Extract host name from URL for display
 */
function getHostFromUrl(url) {
    try {
        const hostname = new URL(url).hostname;
        if (hostname.includes('github')) return 'GitHub';
        if (hostname.includes('drive.google')) return 'Google Drive';
        if (hostname.includes('dropbox')) return 'Dropbox';
        return hostname;
    } catch (e) {
        return 'External';
    }
}

/**
 * Show specific section
 */
function showSection(section) {
    // Hide all sections
    document.querySelectorAll('.modal-section').forEach(s => s.style.display = 'none');
    
    // Show requested section
    const sectionEl = document.getElementById(section + 'Section');
    if (sectionEl) sectionEl.style.display = 'block';
    
    // Clear errors
    document.querySelectorAll('.form-error').forEach(e => e.textContent = '');
    document.querySelectorAll('.form-success').forEach(e => e.textContent = '');
}

/**
 * Open download modal
 */
function openDownloadModal() {
    if (!modal) return;
    
    checkAuthStatus().then(() => {
        updateUIForUser();
        showSection('download');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
}

/**
 * Close modal
 */
function closeModal() {
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Handle signup
 */
async function handleSignup(e) {
    e.preventDefault();
    
    const name = document.getElementById('signupName').value.trim();
    const email = document.getElementById('signupEmail').value.trim();
    const password = document.getElementById('signupPassword').value;
    const errorEl = document.getElementById('signupError');
    const btn = document.getElementById('signupBtn');
    
    errorEl.textContent = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Creating account...';
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=signup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ name, email, password })
        });
        
        const data = await response.json();
        
        // Check for database errors
        if (data.error && data.error.includes('Database error')) {
            errorEl.textContent = 'Database setup incomplete. Please run the migration script.';
            console.error('Database error:', data.error);
            return;
        }
        
        if (data.success || data.requires_verification) {
            currentUser = { email, is_verified: false };
            document.getElementById('verifyEmail').textContent = email;
            
            // Show verification code if provided (email disabled or failed)
            if (data.debug_code) {
                console.log('Verification code:', data.debug_code);
                const title = data.email_sent === false ? 'Email could not be sent' : 'Verification Code';
                alert(`${title}\n\nYour code: ${data.debug_code}\n\nEnter this code in the next step to verify your email.`);
            }
            
            showSection('verify');
        } else {
            errorEl.textContent = data.error || 'Signup failed';
        }
    } catch (error) {
        console.error('Signup error:', error);
        errorEl.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span>Create Account</span>';
    }
}

/**
 * Handle login
 */
async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const errorEl = document.getElementById('loginError');
    const btn = document.getElementById('loginBtn');
    
    errorEl.textContent = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Logging in...';
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            await checkAuthStatus();
            showSection('download');
            updateUIForUser();
            notifyAuthChange(currentUser);
        } else if (data.requires_verification) {
            currentUser = { email: data.email, is_verified: false };
            document.getElementById('verifyEmail').textContent = data.email;
            
            if (data.debug_code) {
                console.log('Verification code:', data.debug_code);
                const title = data.email_sent === false ? 'Email could not be sent' : 'Verification Code';
                alert(`${title}\n\nYour code: ${data.debug_code}`);
            }
            
            showSection('verify');
        } else {
            errorEl.textContent = data.error || 'Login failed';
        }
    } catch (error) {
        errorEl.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span>Log In</span>';
    }
}

/**
 * Handle email verification
 */
async function handleVerification(e) {
    e.preventDefault();
    
    const code = document.getElementById('verifyCode').value.trim();
    const email = currentUser?.email || document.getElementById('verifyEmail').textContent;
    const errorEl = document.getElementById('verifyError');
    const btn = document.getElementById('verifyBtn');
    
    errorEl.textContent = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Verifying...';
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=verify`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ email, code })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            await checkAuthStatus();
            showSection('download');
            updateUIForUser();
            notifyAuthChange(currentUser);
        } else {
            errorEl.textContent = data.error || 'Verification failed';
        }
    } catch (error) {
        errorEl.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span>Verify Email</span>';
    }
}

/**
 * Resend verification code
 */
async function resendCode() {
    const email = currentUser?.email || document.getElementById('verifyEmail').textContent;
    const errorEl = document.getElementById('verifyError');
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=resend-code`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ email })
        });
        
        const data = await response.json();
        
        if (data.success) {
            errorEl.style.color = '#22c55e';
            errorEl.textContent = 'New code sent!';
            
            if (data.debug_code) {
                const title = data.email_sent === false ? 'Email could not be sent' : 'New Verification Code';
                alert(`${title}\n\nYour code: ${data.debug_code}`);
            }
            
            setTimeout(() => {
                errorEl.style.color = '';
                errorEl.textContent = '';
            }, 3000);
        } else {
            errorEl.textContent = data.error || 'Failed to resend code';
        }
    } catch (error) {
        errorEl.textContent = 'Network error';
    }
}

/**
 * Handle forgot password
 */
async function handleForgotPassword(e) {
    e.preventDefault();
    
    const email = document.getElementById('forgotEmail').value.trim();
    const errorEl = document.getElementById('forgotError');
    const successEl = document.getElementById('forgotSuccess');
    const btn = document.getElementById('forgotBtn');
    
    errorEl.textContent = '';
    successEl.textContent = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Sending...';
    
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=forgot-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ email })
        });
        
        const data = await response.json();
        
        if (data.success) {
            successEl.textContent = data.message || 'Reset link sent!';
            
            if (data.debug_token) {
                console.log('Debug reset token:', data.debug_token);
                alert(`Development Mode - Reset link: ${window.location.origin}/reset-password.html?token=${data.debug_token}`);
            }
        } else {
            errorEl.textContent = data.error || 'Request failed';
        }
    } catch (error) {
        errorEl.textContent = 'Network error';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span>Send Reset Link</span>';
    }
}

/**
 * Handle logout
 */
async function handleLogout() {
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`, {
            credentials: 'include'
        });
    } catch (e) {}
    
    currentUser = null;
    if (cooldownInterval) clearInterval(cooldownInterval);
    updateUIForUser();
    notifyAuthChange(null);
}

/**
 * Start download with tracking
 * Supports external download URLs (GitHub Releases, Google Drive, etc.)
 */
async function startDownload() {
    if (isDownloading) return;
    
    const downloadArea = document.getElementById('downloadArea');
    if (!downloadArea) return;
    
    isDownloading = true;
    
    // Show downloading state
    downloadArea.innerHTML = `
        <div class="download-progress">
            <div class="progress-icon">📥</div>
            <p>Initializing download...</p>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            <p class="progress-text" id="progressText">Preparing...</p>
        </div>
    `;
    
    try {
        // 1. Initialize download - get token and download URL
        const initResponse = await fetch(`${API_BASE}/downloads.php?action=init`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({})
        });
        
        const initData = await initResponse.json();
        
        if (!initData.success) {
            throw new Error(initData.error || 'Failed to initialize download');
        }
        
        currentDownloadToken = initData.download_token;
        const downloadUrl = initData.download_url;
        const expectedSize = initData.file_size || 0;
        const filename = initData.filename;
        
        // 2. Check if we have an external download URL
        if (downloadUrl && downloadUrl.startsWith('http')) {
            // External download (GitHub Releases, Google Drive, etc.)
            downloadArea.innerHTML = `
                <div class="download-progress">
                    <div class="progress-icon">📥</div>
                    <p>Redirecting to download...</p>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 50%"></div>
                    </div>
                    <p class="progress-text">Opening external download link...</p>
                </div>
            `;
            
            // Open external download in new tab/window
            window.open(downloadUrl, '_blank');
            
            // Mark download as complete immediately (we can't track external downloads)
            try {
                const completeResp = await fetch(`${API_BASE}/downloads.php?action=complete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        download_token: currentDownloadToken,
                        bytes_downloaded: expectedSize || 1,
                        verified: true
                    })
                });
                const completeData = await completeResp.json();
                console.log('Download marked complete:', completeData);
            } catch (e) {
                console.log('Could not mark download complete:', e);
            }
            
            // Show success
            downloadArea.innerHTML = `
                <div class="download-success">
                    <div class="success-icon">✅</div>
                    <h3>Download Started!</h3>
                    <p style="color: #94a3b8; margin-bottom: 15px;">
                        The APK should be downloading in a new tab.<br>
                        If it didn't start, <a href="${downloadUrl}" target="_blank" style="color: #6366f1;">click here</a>.
                    </p>
                    <p style="color: #64748b; font-size: 13px;">
                        Thank you for downloading LokAlert!
                    </p>
                </div>
            `;
            
            isDownloading = false;
            currentDownloadToken = null;
            
            // Refresh auth status
            setTimeout(() => checkAuthStatus(), 2000);
            
            return;
        }
        
        // 3. Local file download (fallback) - original logic
        const fileResponse = await fetch(`releases/${encodeURIComponent(filename)}`);
        
        if (!fileResponse.ok) {
            throw new Error('File not found on server');
        }
        
        const reader = fileResponse.body.getReader();
        const chunks = [];
        let receivedLength = 0;
        
        while (true) {
            const { done, value } = await reader.read();
            
            if (done) break;
            
            chunks.push(value);
            receivedLength += value.length;
            
            // Update progress UI
            const progress = expectedSize > 0 ? Math.round((receivedLength / expectedSize) * 100) : 50;
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            if (progressFill) progressFill.style.width = progress + '%';
            if (progressText) progressText.textContent = `${progress}% (${formatBytes(receivedLength)} / ${formatBytes(expectedSize)})`;
        }
        
        // 4. Download complete - notify server
        const completeResponse = await fetch(`${API_BASE}/downloads.php?action=complete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                download_token: currentDownloadToken,
                bytes_downloaded: receivedLength,
                verified: true
            })
        });
        
        const completeData = await completeResponse.json();
        
        if (!completeData.success) {
            throw new Error(completeData.error || 'Failed to verify download');
        }
        
        // 4. Create blob and trigger actual download
        const blob = new Blob(chunks);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        // 5. Show success
        downloadArea.innerHTML = `
            <div class="download-success">
                <div class="success-icon">✅</div>
                <h3>Download Complete!</h3>
                <p style="color: #94a3b8; margin-bottom: 15px;">
                    Total downloads: ${completeData.download_count?.toLocaleString() || 'N/A'}
                </p>
                <p style="color: #64748b; font-size: 13px;">
                    You can download again in ${completeData.cooldown_minutes || 5} minutes
                </p>
            </div>
        `;
        
        // Refresh auth status after a moment
        setTimeout(() => checkAuthStatus(), 2000);
        
    } catch (error) {
        console.error('Download error:', error);
        
        // Cancel the download on server
        if (currentDownloadToken) {
            try {
                await fetch(`${API_BASE}/downloads.php?action=cancel`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        download_token: currentDownloadToken,
                        reason: 'error'
                    })
                });
            } catch (e) {}
        }
        
        downloadArea.innerHTML = `
            <div class="download-error">
                <div class="error-icon">❌</div>
                <h3>Download Failed</h3>
                <p style="color: #ef4444; margin-bottom: 15px;">${error.message}</p>
                <button class="btn btn-primary" onclick="startDownload()">
                    <span>🔄</span> Try Again
                </button>
            </div>
        `;
    } finally {
        isDownloading = false;
        currentDownloadToken = null;
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Global function for modal
window.openDownloadModal = openDownloadModal;
window.closeModal = closeModal;

/**
 * Open Login Popup — shows auth modal on the login section
 */
function openLoginPopup() {
    if (!modal) {
        modal = document.getElementById('downloadModal');
        if (!modal) return;
    }
    
    checkAuthStatus().then(() => {
        if (currentUser) {
            // Already logged in — update greeting and nav
            if (typeof updateGreeting === 'function') updateGreeting(currentUser);
            if (typeof updateNavAuth === 'function') updateNavAuth(currentUser);
            return;
        }
        showSection('login');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
}
window.openLoginPopup = openLoginPopup;

/**
 * Notify page of auth state changes (greeting, nav)
 */
function notifyAuthChange(user) {
    if (typeof updateGreeting === 'function') updateGreeting(user);
    if (typeof updateNavAuth === 'function') updateNavAuth(user);
}
