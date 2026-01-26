/**
 * LokAlert Download & Authentication System
 * Updated for GitHub Pages static hosting
 */

// APK Download Configuration - Direct link to GitHub releases folder
const APK_CONFIG = {
    version: '1.0.0',
    filename: 'LokAlert Demo 1.apk',
    fileSize: 17188901,
    releaseDate: '2026-01-26',
    releaseNotes: 'Initial release - LokAlert Demo\n• Location-based arrival alerts\n• GPS tracking\n• Customizable radius\n• Background monitoring',
    downloadUrl: 'releases/LokAlert%20Demo%201.apk'
};

// DOM Elements
const downloadModal = document.getElementById('downloadModal');
const modalClose = document.getElementById('modalClose');
const downloadSection = document.getElementById('downloadSection');
const loginSection = document.getElementById('loginSection');
const registerSection = document.getElementById('registerSection');
const authButtons = document.getElementById('authButtons');
const userInfoModal = document.getElementById('userInfoModal');
const versionInfo = document.getElementById('versionInfo');

// State
let currentUser = JSON.parse(localStorage.getItem('currentUser') || 'null');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initDownloadButtons();
    initModalEvents();
    initAuthForms();
    checkUserSession();
});

// Open modal from download buttons
function initDownloadButtons() {
    // Desktop nav download button
    const navDownloadBtn = document.querySelector('.nav-download-btn');
    if (navDownloadBtn) {
        navDownloadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openDownloadModal();
        });
    }

    // Mobile download button
    const mobileDownloadBtn = document.querySelector('.mobile-download-btn');
    if (mobileDownloadBtn) {
        mobileDownloadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openDownloadModal();
            // Close mobile menu
            document.getElementById('mobileMenu')?.classList.remove('active');
            document.getElementById('mobileMenuBtn')?.classList.remove('active');
        });
    }
}

// Modal Events
function initModalEvents() {
    // Close button
    modalClose.addEventListener('click', closeDownloadModal);
    
    // Click outside to close
    downloadModal.addEventListener('click', (e) => {
        if (e.target === downloadModal) {
            closeDownloadModal();
        }
    });
    
    // Escape key to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && downloadModal.classList.contains('active')) {
            closeDownloadModal();
        }
    });

    // Navigation between sections
    document.getElementById('showLoginBtn')?.addEventListener('click', () => showSection('login'));
    document.getElementById('showRegisterBtn')?.addEventListener('click', () => showSection('register'));
    document.getElementById('switchToRegister')?.addEventListener('click', (e) => {
        e.preventDefault();
        showSection('register');
    });
    document.getElementById('switchToLogin')?.addEventListener('click', (e) => {
        e.preventDefault();
        showSection('login');
    });
    document.getElementById('backToDownload')?.addEventListener('click', (e) => {
        e.preventDefault();
        showSection('download');
    });
    document.getElementById('backToDownload2')?.addEventListener('click', (e) => {
        e.preventDefault();
        showSection('download');
    });
    
    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    
    // Download button
    document.getElementById('downloadBtn')?.addEventListener('click', downloadAPK);
}

// Auth Forms
function initAuthForms() {
    // Login form
    document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        
        try {
            const res = await fetch(`${API_URL}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await res.json();
            
            if (!res.ok) {
                throw new Error(data.error || 'Login failed');
            }
            
            userToken = data.token;
            currentUser = data.user;
            localStorage.setItem('userToken', userToken);
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            updateUIForUser();
            showSection('download');
            document.getElementById('loginForm').reset();
        } catch (error) {
            document.getElementById('loginError').textContent = error.message;
        }
    });
    
    // Register form
    document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('regUsername').value;
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        
        try {
            const res = await fetch(`${API_URL}/auth/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, password })
            });
            
            const data = await res.json();
            
            if (!res.ok) {
                throw new Error(data.error || 'Registration failed');
            }
            
            userToken = data.token;
            currentUser = data.user;
            localStorage.setItem('userToken', userToken);
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            updateUIForUser();
            showSection('download');
            document.getElementById('registerForm').reset();
        } catch (error) {
            document.getElementById('registerError').textContent = error.message;
        }
    });
}

// Open Modal
function openDownloadModal() {
    downloadModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    showSection('download');
    loadLatestVersion();
    updateUIForUser();
}

// Close Modal
function closeDownloadModal() {
    downloadModal.classList.remove('active');
    document.body.style.overflow = '';
    // Clear errors
    document.getElementById('loginError').textContent = '';
    document.getElementById('registerError').textContent = '';
}

// Show specific section
function showSection(section) {
    downloadSection.style.display = section === 'download' ? 'block' : 'none';
    loginSection.style.display = section === 'login' ? 'block' : 'none';
    registerSection.style.display = section === 'register' ? 'block' : 'none';
}

// Check user session
function checkUserSession() {
    if (userToken && currentUser) {
        updateUIForUser();
    }
}

// Update UI based on user state
function updateUIForUser() {
    if (currentUser) {
        authButtons.style.display = 'none';
        userInfoModal.style.display = 'block';
        document.getElementById('userDisplayName').textContent = currentUser.username;
    } else {
        authButtons.style.display = 'block';
        userInfoModal.style.display = 'none';
    }
}

// Logout
function logout() {
    userToken = null;
    currentUser = null;
    localStorage.removeItem('userToken');
    localStorage.removeItem('currentUser');
    updateUIForUser();
}

// Load latest version info - Static version for GitHub Pages
async function loadLatestVersion() {
    // Use static APK config for GitHub Pages
    versionInfo.innerHTML = `
        <div class="version-card">
            <div class="version-badge">v${APK_CONFIG.version}</div>
            <div class="version-details">
                <span class="version-size">${formatFileSize(APK_CONFIG.fileSize)}</span>
                <span class="version-date">${formatDate(APK_CONFIG.releaseDate)}</span>
            </div>
            <p class="version-notes">${APK_CONFIG.releaseNotes.replace(/\n/g, '<br>')}</p>
            <div class="version-downloads">
                <span>&#x1F4E5;</span> Available for download
            </div>
        </div>
    `;
}

// Download APK - Direct download for GitHub Pages
async function downloadAPK() {
    // Create download link
    const link = document.createElement('a');
    link.href = APK_CONFIG.downloadUrl;
    link.download = APK_CONFIG.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    alert('Download started! Check your downloads folder.');
}

// Utility functions
function formatFileSize(bytes) {
    if (!bytes) return 'Unknown size';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
