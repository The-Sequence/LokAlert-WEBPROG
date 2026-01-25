/**
 * LokAlert Server
 * APK Download Management with User Authentication
 */

const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const multer = require('multer');
const Database = require('better-sqlite3');

const app = express();
const PORT = process.env.PORT || 3000;
const JWT_SECRET = process.env.JWT_SECRET || 'lokalert-secret-key-change-in-production';

// Database setup
const dbPath = path.join(__dirname, 'db', 'lokalert.db');
const db = new Database(dbPath);

// Initialize database tables
db.exec(`
    -- Users table
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_admin INTEGER DEFAULT 0
    );

    -- APK Versions table
    CREATE TABLE IF NOT EXISTS apk_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        version TEXT NOT NULL,
        filename TEXT NOT NULL,
        release_notes TEXT,
        file_size INTEGER,
        upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_latest INTEGER DEFAULT 0,
        download_count INTEGER DEFAULT 0
    );

    -- Download logs table
    CREATE TABLE IF NOT EXISTS download_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        apk_version_id INTEGER,
        download_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT,
        user_agent TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (apk_version_id) REFERENCES apk_versions(id)
    );

    -- Download statistics table
    CREATE TABLE IF NOT EXISTS download_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date DATE UNIQUE,
        total_downloads INTEGER DEFAULT 0
    );
`);

// Create default admin user if not exists
const adminExists = db.prepare('SELECT id FROM users WHERE username = ?').get('admin');
if (!adminExists) {
    const hashedPassword = bcrypt.hashSync('admin123', 10);
    db.prepare('INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)').run('admin', 'admin@lokalert.com', hashedPassword, 1);
    console.log('Default admin user created (username: admin, password: admin123)');
}

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '..')));
app.use('/downloads', express.static(path.join(__dirname, '..', 'downloads')));

// File upload configuration
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, path.join(__dirname, '..', 'downloads'));
    },
    filename: (req, file, cb) => {
        cb(null, file.originalname);
    }
});
const upload = multer({ storage });

// Auth middleware
const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    
    if (!token) {
        return res.status(401).json({ error: 'Access token required' });
    }
    
    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) return res.status(403).json({ error: 'Invalid token' });
        req.user = user;
        next();
    });
};

const requireAdmin = (req, res, next) => {
    if (!req.user.is_admin) {
        return res.status(403).json({ error: 'Admin access required' });
    }
    next();
};

// ============================================
// AUTH ROUTES
// ============================================

// Register
app.post('/api/auth/register', (req, res) => {
    try {
        const { username, email, password } = req.body;
        
        if (!username || !email || !password) {
            return res.status(400).json({ error: 'All fields are required' });
        }
        
        const hashedPassword = bcrypt.hashSync(password, 10);
        
        const stmt = db.prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        const result = stmt.run(username, email, hashedPassword);
        
        const token = jwt.sign({ id: result.lastInsertRowid, username, is_admin: 0 }, JWT_SECRET, { expiresIn: '7d' });
        
        res.json({ message: 'Registration successful', token, user: { id: result.lastInsertRowid, username, email } });
    } catch (error) {
        if (error.message.includes('UNIQUE constraint')) {
            return res.status(400).json({ error: 'Username or email already exists' });
        }
        res.status(500).json({ error: 'Registration failed' });
    }
});

// Login
app.post('/api/auth/login', (req, res) => {
    try {
        const { username, password } = req.body;
        
        const user = db.prepare('SELECT * FROM users WHERE username = ? OR email = ?').get(username, username);
        
        if (!user || !bcrypt.compareSync(password, user.password)) {
            return res.status(401).json({ error: 'Invalid credentials' });
        }
        
        const token = jwt.sign({ id: user.id, username: user.username, is_admin: user.is_admin }, JWT_SECRET, { expiresIn: '7d' });
        
        res.json({ message: 'Login successful', token, user: { id: user.id, username: user.username, email: user.email, is_admin: user.is_admin } });
    } catch (error) {
        res.status(500).json({ error: 'Login failed' });
    }
});

// Get current user
app.get('/api/auth/me', authenticateToken, (req, res) => {
    const user = db.prepare('SELECT id, username, email, is_admin, created_at FROM users WHERE id = ?').get(req.user.id);
    res.json(user);
});

// ============================================
// APK VERSION ROUTES
// ============================================

// Get all versions
app.get('/api/versions', (req, res) => {
    const versions = db.prepare('SELECT * FROM apk_versions ORDER BY upload_date DESC').all();
    res.json(versions);
});

// Get latest version
app.get('/api/versions/latest', (req, res) => {
    const latest = db.prepare('SELECT * FROM apk_versions WHERE is_latest = 1').get();
    if (!latest) {
        const fallback = db.prepare('SELECT * FROM apk_versions ORDER BY upload_date DESC LIMIT 1').get();
        return res.json(fallback || null);
    }
    res.json(latest);
});

// Upload new version (admin only)
app.post('/api/versions', authenticateToken, requireAdmin, upload.single('apk'), (req, res) => {
    try {
        const { version, release_notes, is_latest } = req.body;
        const file = req.file;
        
        if (!file || !version) {
            return res.status(400).json({ error: 'APK file and version required' });
        }
        
        // If this is latest, unset previous latest
        if (is_latest === 'true' || is_latest === true) {
            db.prepare('UPDATE apk_versions SET is_latest = 0').run();
        }
        
        const stmt = db.prepare('INSERT INTO apk_versions (version, filename, release_notes, file_size, is_latest) VALUES (?, ?, ?, ?, ?)');
        const result = stmt.run(version, file.originalname, release_notes || '', file.size, is_latest === 'true' || is_latest === true ? 1 : 0);
        
        res.json({ message: 'Version uploaded successfully', id: result.lastInsertRowid });
    } catch (error) {
        res.status(500).json({ error: 'Upload failed: ' + error.message });
    }
});

// Delete version (admin only)
app.delete('/api/versions/:id', authenticateToken, requireAdmin, (req, res) => {
    try {
        const version = db.prepare('SELECT * FROM apk_versions WHERE id = ?').get(req.params.id);
        
        if (!version) {
            return res.status(404).json({ error: 'Version not found' });
        }
        
        // Delete file
        const filePath = path.join(__dirname, '..', 'downloads', version.filename);
        if (fs.existsSync(filePath)) {
            fs.unlinkSync(filePath);
        }
        
        db.prepare('DELETE FROM apk_versions WHERE id = ?').run(req.params.id);
        res.json({ message: 'Version deleted successfully' });
    } catch (error) {
        res.status(500).json({ error: 'Delete failed' });
    }
});

// ============================================
// DOWNLOAD ROUTES
// ============================================

// Download APK (tracks download)
app.get('/api/download/:id', (req, res) => {
    try {
        const version = db.prepare('SELECT * FROM apk_versions WHERE id = ?').get(req.params.id);
        
        if (!version) {
            return res.status(404).json({ error: 'Version not found' });
        }
        
        const filePath = path.join(__dirname, '..', 'downloads', version.filename);
        
        if (!fs.existsSync(filePath)) {
            return res.status(404).json({ error: 'File not found' });
        }
        
        // Log download
        const token = req.headers['authorization']?.split(' ')[1];
        let userId = null;
        
        if (token) {
            try {
                const decoded = jwt.verify(token, JWT_SECRET);
                userId = decoded.id;
            } catch (e) {}
        }
        
        db.prepare('INSERT INTO download_logs (user_id, apk_version_id, ip_address, user_agent) VALUES (?, ?, ?, ?)').run(
            userId,
            version.id,
            req.ip || req.connection.remoteAddress,
            req.headers['user-agent'] || ''
        );
        
        // Update download count
        db.prepare('UPDATE apk_versions SET download_count = download_count + 1 WHERE id = ?').run(version.id);
        
        // Update daily stats
        const today = new Date().toISOString().split('T')[0];
        db.prepare('INSERT OR IGNORE INTO download_stats (date, total_downloads) VALUES (?, 0)').run(today);
        db.prepare('UPDATE download_stats SET total_downloads = total_downloads + 1 WHERE date = ?').run(today);
        
        res.download(filePath, version.filename);
    } catch (error) {
        res.status(500).json({ error: 'Download failed' });
    }
});

// Download latest APK
app.get('/api/download/latest', (req, res) => {
    const latest = db.prepare('SELECT * FROM apk_versions WHERE is_latest = 1').get();
    if (!latest) {
        const fallback = db.prepare('SELECT * FROM apk_versions ORDER BY upload_date DESC LIMIT 1').get();
        if (!fallback) {
            return res.status(404).json({ error: 'No APK available' });
        }
        return res.redirect(`/api/download/${fallback.id}`);
    }
    res.redirect(`/api/download/${latest.id}`);
});

// ============================================
// STATISTICS ROUTES
// ============================================

// Get download statistics (admin only)
app.get('/api/stats', authenticateToken, requireAdmin, (req, res) => {
    const totalDownloads = db.prepare('SELECT SUM(download_count) as total FROM apk_versions').get();
    const totalUsers = db.prepare('SELECT COUNT(*) as count FROM users').get();
    const totalVersions = db.prepare('SELECT COUNT(*) as count FROM apk_versions').get();
    const recentDownloads = db.prepare('SELECT * FROM download_stats ORDER BY date DESC LIMIT 30').all();
    const topVersions = db.prepare('SELECT version, download_count FROM apk_versions ORDER BY download_count DESC LIMIT 5').all();
    
    res.json({
        total_downloads: totalDownloads.total || 0,
        total_users: totalUsers.count,
        total_versions: totalVersions.count,
        recent_downloads: recentDownloads,
        top_versions: topVersions
    });
});

// Get all users (admin only)
app.get('/api/users', authenticateToken, requireAdmin, (req, res) => {
    const users = db.prepare('SELECT id, username, email, is_admin, created_at FROM users ORDER BY created_at DESC').all();
    res.json(users);
});

// Get download logs (admin only)
app.get('/api/logs', authenticateToken, requireAdmin, (req, res) => {
    const logs = db.prepare(`
        SELECT dl.*, u.username, av.version 
        FROM download_logs dl 
        LEFT JOIN users u ON dl.user_id = u.id 
        LEFT JOIN apk_versions av ON dl.apk_version_id = av.id 
        ORDER BY dl.download_date DESC 
        LIMIT 100
    `).all();
    res.json(logs);
});

// ============================================
// START SERVER
// ============================================

app.listen(PORT, () => {
    console.log(`
╔════════════════════════════════════════════════════════════╗
║           LokAlert Server Started Successfully             ║
╠════════════════════════════════════════════════════════════╣
║  Server running at: http://localhost:${PORT}                  ║
║  Admin login: username: admin, password: admin123          ║
╚════════════════════════════════════════════════════════════╝
    `);
});
