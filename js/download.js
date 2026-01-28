/**
 * LokAlert Download - REQUIRES SIGNUP
 */
const APK_CONFIG = {
    version: "1.0.0",
    filename: "LokAlert Demo 1.apk",
    fileSize: 17188901,
    releaseDate: "2026-01-26",
    releaseNotes: "Initial release - LokAlert Demo\n• Location-based arrival alerts\n• GPS tracking\n• Customizable radius\n• Background monitoring",
    downloadUrl: "releases/LokAlert%20Demo%201.apk"
};

const STORAGE_KEYS = {
    USERS: "LOKALERT_REGISTERED_USERS",
    CURRENT_USER: "LOKALERT_CURRENT_USER"
};

let currentUser = null;

document.addEventListener("DOMContentLoaded", () => {
    checkUserSession();
    initModalEvents();
    setupAuthForms();
});

function checkUserSession() {
    const saved = localStorage.getItem(STORAGE_KEYS.CURRENT_USER);
    if (saved) { try { currentUser = JSON.parse(saved); } catch(e) {} }
}

function getUsers() {
    const u = localStorage.getItem(STORAGE_KEYS.USERS);
    return u ? JSON.parse(u) : [];
}

function saveUser(user) {
    const users = getUsers();
    users.push(user);
    localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users));
}

function findUser(email) {
    return getUsers().find(u => u.email.toLowerCase() === email.toLowerCase());
}

function simpleHash(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) {
        h = ((h << 5) - h) + str.charCodeAt(i);
        h = h & h;
    }
    return h.toString(16);
}

function initModalEvents() {
    const modal = document.getElementById("downloadModal");
    const closeBtn = document.getElementById("modalClose");
    if (closeBtn) closeBtn.onclick = closeDownloadModal;
    if (modal) modal.onclick = (e) => { if (e.target === modal) closeDownloadModal(); };
    document.onkeydown = (e) => { if (e.key === "Escape") closeDownloadModal(); };
    const dlBtn = document.getElementById("downloadBtn");
    if (dlBtn) dlBtn.onclick = handleDownloadClick;
}

function openDownloadModal() {
    const modal = document.getElementById("downloadModal");
    if (modal) {
        modal.classList.add("active");
        document.body.style.overflow = "hidden";
        showSection("download");
        loadVersionInfo();
        updateDownloadUI();
    }
}

function closeDownloadModal() {
    const modal = document.getElementById("downloadModal");
    if (modal) {
        modal.classList.remove("active");
        document.body.style.overflow = "";
    }
}

function showSection(s) {
    const dl = document.getElementById("downloadSection");
    const lg = document.getElementById("loginSection");
    const rg = document.getElementById("registerSection");
    if (dl) dl.style.display = s === "download" ? "block" : "none";
    if (lg) lg.style.display = s === "login" ? "block" : "none";
    if (rg) rg.style.display = s === "register" ? "block" : "none";
}

function setupAuthForms() {
    const lg = document.getElementById("loginSection");
    const rg = document.getElementById("registerSection");
    
    if (lg && !lg.innerHTML.trim()) {
        lg.innerHTML = `
            <div class="modal-header">
                <div class="modal-icon">🔐</div>
                <h2>Welcome Back</h2>
                <p>Sign in to download</p>
            </div>
            <form id="loginForm">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="loginEmail" required placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="loginPassword" required placeholder="Your password">
                </div>
                <div id="loginError" style="color:#ef4444;font-size:14px;margin:10px 0;"></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
            </form>
            <p style="text-align:center;margin-top:20px;color:#94a3b8;">
                No account? <a href="#" id="switchToRegister" style="color:#3b82f6;">Sign Up</a>
            </p>
            <p style="text-align:center;margin-top:10px;">
                <a href="#" id="backToDownload" style="color:#64748b;font-size:14px;">← Back</a>
            </p>
        `;
    }
    
    if (rg && !rg.innerHTML.trim()) {
        rg.innerHTML = `
            <div class="modal-header">
                <div class="modal-icon">📝</div>
                <h2>Create Account</h2>
                <p>Sign up to download LokAlert</p>
            </div>
            <form id="registerForm">
                <div class="form-group">
                    <label>Name <span style="color:#64748b;font-size:12px;">(optional)</span></label>
                    <input type="text" id="regName" placeholder="Your name">
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#ef4444;">*</span></label>
                    <input type="email" id="regEmail" required placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label>Password <span style="color:#ef4444;">*</span></label>
                    <input type="password" id="regPassword" required minlength="6" placeholder="Min 6 chars">
                </div>
                <div id="registerError" style="color:#ef4444;font-size:14px;margin:10px 0;"></div>
                <div id="registerSuccess" style="color:#22c55e;font-size:14px;margin:10px 0;"></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
            </form>
            <p style="text-align:center;margin-top:20px;color:#94a3b8;">
                Have account? <a href="#" id="switchToLogin" style="color:#3b82f6;">Sign In</a>
            </p>
            <p style="text-align:center;margin-top:10px;">
                <a href="#" id="backToDownload2" style="color:#64748b;font-size:14px;">← Back</a>
            </p>
        `;
    }
    
    setTimeout(attachFormHandlers, 100);
}

function attachFormHandlers() {
    const lf = document.getElementById("loginForm");
    const rf = document.getElementById("registerForm");
    if (lf) lf.onsubmit = handleLogin;
    if (rf) rf.onsubmit = handleRegister;
    
    const sr = document.getElementById("switchToRegister");
    const sl = document.getElementById("switchToLogin");
    const b1 = document.getElementById("backToDownload");
    const b2 = document.getElementById("backToDownload2");
    
    if (sr) sr.onclick = (e) => { e.preventDefault(); showSection("register"); };
    if (sl) sl.onclick = (e) => { e.preventDefault(); showSection("login"); };
    if (b1) b1.onclick = (e) => { e.preventDefault(); showSection("download"); };
    if (b2) b2.onclick = (e) => { e.preventDefault(); showSection("download"); };
}

function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById("loginEmail").value;
    const password = document.getElementById("loginPassword").value;
    const err = document.getElementById("loginError");
    err.textContent = "";
    
    const user = findUser(email);
    if (!user) { err.textContent = "No account found."; return; }
    if (user.password !== simpleHash(password)) { err.textContent = "Wrong password."; return; }
    
    currentUser = { email: user.email, name: user.name };
    localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(currentUser));
    showSection("download");
    updateDownloadUI();
}

function handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById("regName").value;
    const email = document.getElementById("regEmail").value;
    const password = document.getElementById("regPassword").value;
    const err = document.getElementById("registerError");
    const suc = document.getElementById("registerSuccess");
    err.textContent = "";
    suc.textContent = "";
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { err.textContent = "Invalid email."; return; }
    if (findUser(email)) { err.textContent = "Email already registered."; return; }
    
    const newUser = {
        id: Date.now(),
        name: name || "",
        email: email.toLowerCase(),
        password: simpleHash(password),
        createdAt: new Date().toISOString()
    };
    saveUser(newUser);
    
    currentUser = { email: newUser.email, name: newUser.name };
    localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(currentUser));
    suc.textContent = "Account created!";
    setTimeout(() => { showSection("download"); updateDownloadUI(); }, 1000);
}

// CRITICAL GATEKEEPER
function handleDownloadClick() {
    console.log("[DOWNLOAD] Click - checking auth...");
    if (!currentUser) {
        console.log("[DOWNLOAD] No user - show register");
        alert("Please sign up or log in to download LokAlert.");
        showSection("register");
        return;
    }
    console.log("[DOWNLOAD] User OK:", currentUser.email);
    downloadAPK();
}

function updateDownloadUI() {
    const btn = document.getElementById("downloadBtn");
    const note = document.querySelector("#downloadSection p[style*='margin-top: 20px']");
    
    if (currentUser) {
        if (btn) btn.innerHTML = "<span>📥</span> Download APK";
        if (note) note.innerHTML = "✅ Logged in as <strong>" + currentUser.email + "</strong> | <a href='#' onclick='logout();return false;' style='color:#3b82f6;'>Logout</a>";
    } else {
        if (btn) btn.innerHTML = "<span>👤</span> Sign Up to Download";
        if (note) note.innerHTML = "📱 Android only • <strong style='color:#f59e0b;'>Account required</strong>";
    }
}

function logout() {
    currentUser = null;
    localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
    updateDownloadUI();
}

function loadVersionInfo() {
    const vi = document.getElementById("versionInfo");
    if (!vi) return;
    vi.innerHTML = `
        <div class="version-card">
            <div class="version-badge">v${APK_CONFIG.version}</div>
            <div class="version-details">
                <span class="version-size">${formatSize(APK_CONFIG.fileSize)}</span>
                <span class="version-date">${formatDate(APK_CONFIG.releaseDate)}</span>
            </div>
            <p class="version-notes">${APK_CONFIG.releaseNotes.replace(/\n/g, "<br>")}</p>
            <div class="version-downloads"><span>📥</span> Available for download</div>
        </div>
    `;
}

function downloadAPK() {
    console.log("[DOWNLOAD] Starting for:", currentUser?.email);
    const a = document.createElement("a");
    a.href = APK_CONFIG.downloadUrl;
    a.download = APK_CONFIG.filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    alert("Download started!");
}

function formatSize(bytes) {
    if (!bytes) return "Unknown";
    const s = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(2) + " " + s[i];
}

function formatDate(d) {
    if (!d) return "";
    return new Date(d).toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
}

window.logout = logout;
window.openDownloadModal = openDownloadModal;
