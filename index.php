<?php
/**
 * LokAlert - Main Landing Page
 * PHP Version with dynamic content from MySQL database
 */

// Include database configuration
require_once 'includes/config.php';

// Fetch latest APK version
$latestVersion = null;
$totalDownloads = 0;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get latest version
    $stmt = $db->query("SELECT * FROM apk_versions WHERE is_latest = 1 LIMIT 1");
    $latestVersion = $stmt->fetch();
    
    // Get total downloads
    $stmt = $db->query("SELECT COALESCE(SUM(download_count), 0) as total FROM apk_versions");
    $totalDownloads = $stmt->fetch()['total'];
} catch (PDOException $e) {
    // Database not connected yet - use defaults
    $latestVersion = [
        'version' => '1.2.0',
        'filename' => 'LokAlert-v1.2.0.apk',
        'file_size' => 17825792,
        'download_count' => 425
    ];
    $totalDownloads = 855;
}

// Helper function for file size
function formatSize($bytes) {
    if ($bytes <= 0) return '0 MB';
    return round($bytes / 1024 / 1024, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LokAlert - Smart Location-Based Arrival Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav id="navbar">
        <div class="nav-container">
            <a href="#" class="nav-logo">
                <span class="logo-icon">&#x1F4CD;</span>
                <span class="logo-text">LokAlert</span>
            </a>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#why">Why LokAlert</a></li>
                <li><a href="#tech">Technology</a></li>
                <li><a href="#team">Team</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="#" class="nav-download-btn">Download</a></li>
            </ul>
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="#features">Features</a>
            <a href="#why">Why LokAlert</a>
            <a href="#tech">Technology</a>
            <a href="#team">Team</a>
            <a href="contact.php">Contact</a>
            <a href="#" class="mobile-download-btn">Download App</a>
        </div>
    </nav>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-bg">
                <div class="hero-gradient"></div>
                <div class="hero-particles">
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                </div>
            </div>
            <div class="hero-content">
                <div class="hero-badge animate-fade-in">
                    <span class="badge-icon">&#x1F680;</span>
                    <span>Smart Location Technology</span>
                </div>
                <h1 class="hero-title animate-fade-up">
                    <span class="title-main">LokAlert</span>
                    <span class="title-sub">Smart Location-Based Arrival Alert</span>
                </h1>
                <p class="hero-tagline animate-fade-up delay-1">Arrive Smart. Stay Alert.</p>
                <p class="hero-description animate-fade-up delay-2">
                    LokAlert is a mobile application designed to notify users automatically when they arrive at a selected destination. Whether you're commuting, traveling, or multitasking, LokAlert ensures you're alerted exactly when you reach your location&#8212;no constant checking required.
                </p>
                
                <!-- Dynamic Stats from Database -->
                <div class="hero-stats animate-fade-up delay-2" style="display: flex; gap: 40px; justify-content: center; margin: 20px 0;">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #6366f1, #22d3ee); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            <?php echo number_format($totalDownloads); ?>+
                        </div>
                        <div style="color: #94a3b8; font-size: 0.9rem;">Downloads</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #6366f1, #22d3ee); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            v<?php echo $latestVersion ? htmlspecialchars($latestVersion['version']) : '1.2.0'; ?>
                        </div>
                        <div style="color: #94a3b8; font-size: 0.9rem;">Latest Version</div>
                    </div>
                </div>
                
                <div class="hero-cta animate-fade-up delay-3">
                    <a href="#" class="btn btn-primary" id="heroDownloadBtn">
                        <span>Download Now</span>
                        <span class="btn-arrow">&#x2192;</span>
                    </a>
                    <a href="#features" class="btn btn-secondary">Explore Features</a>
                </div>
                <div class="hero-visual animate-float">
                    <div class="phone-mockup">
                        <div class="phone-screen">
                            <div class="screen-map">
                                <div class="map-grid"></div>
                                <div class="location-pulse"></div>
                                <div class="location-marker">&#x1F4CD;</div>
                                <div class="geofence-ring"></div>
                            </div>
                            <div class="screen-card">
                                <div class="card-icon">&#x1F3E0;</div>
                                <div class="card-info">
                                    <span class="card-title">Home</span>
                                    <span class="card-status">200m &#8226; Active</span>
                                </div>
                                <div class="card-toggle active"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="scroll-indicator animate-bounce">
                <span>Scroll</span>
                <div class="scroll-arrow">&#x2193;</div>
            </div>
        </section>

        <!-- About Section -->
        <section class="about">
            <div class="container">
                <div class="about-content">
                    <div class="about-icon animate-on-scroll">
                        <div class="icon-ring"></div>
                        <span>&#x1F3AF;</span>
                    </div>
                    <h2 class="section-title animate-on-scroll">How It Works</h2>
                    <p class="about-text animate-on-scroll">
                        LokAlert uses real-time GPS tracking and location-based services to monitor a user's movement and trigger an alert once they enter a predefined arrival radius. The app runs efficiently in the background, making it reliable, user-friendly, and practical for everyday use.
                    </p>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-badge">&#x2728; Key Features</span>
                    <h2 class="section-title">Everything You Need</h2>
                    <p class="section-subtitle">Powerful features designed for effortless arrival alerts</p>
                </div>
                
                <div class="features-grid">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x1F4CD;</span>
                        </div>
                        <h3>Location Search</h3>
                        <p>Quickly search and select destinations using place names or addresses.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x1F5FA;&#xFE0F;</span>
                        </div>
                        <h3>Map View</h3>
                        <p>Interactive map interface for easy navigation and destination selection.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x1F4CF;</span>
                        </div>
                        <h3>Set Arrival Radius</h3>
                        <p>Customize the distance for alert triggers to match your needs.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x25B6;&#xFE0F;</span>
                        </div>
                        <h3>Start Location Tracking</h3>
                        <p>Begin tracking with one tap using GPS technology.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x1F514;</span>
                        </div>
                        <h3>Arrival Alert / Alarm</h3>
                        <p>Instant alert when destination is reached. Never miss your stop again.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x1F504;</span>
                        </div>
                        <h3>Background Monitoring</h3>
                        <p>Works even when minimized, with battery-optimized performance.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x2B50;</span>
                        </div>
                        <h3>Saved Locations</h3>
                        <p>Save frequent destinations like home or work for quick access.</p>
                    </div>
                    
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <span>&#x274C;</span>
                        </div>
                        <h3>Cancel Tracking</h3>
                        <p>Stop tracking anytime with full control over your alerts.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why LokAlert Section -->
        <section id="why" class="why-section">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-badge">&#x1F4A1; Why LokAlert?</span>
                    <h2 class="section-title">Built for Real Life</h2>
                    <p class="section-subtitle">Designed to solve everyday commuting challenges</p>
                </div>
                
                <div class="why-grid">
                    <div class="why-card animate-on-scroll">
                        <div class="why-number">01</div>
                        <h3>Avoid Missing Your Stop</h3>
                        <p>Perfect for public transport users who often get distracted or fall asleep during long commutes.</p>
                    </div>
                    
                    <div class="why-card animate-on-scroll">
                        <div class="why-number">02</div>
                        <h3>Stay Focused</h3>
                        <p>No need to constantly check your location &#8212; LokAlert does it for you automatically.</p>
                    </div>
                    
                    <div class="why-card animate-on-scroll">
                        <div class="why-number">03</div>
                        <h3>Save Time & Energy</h3>
                        <p>Arrive at your destination stress-free without the anxiety of missing your stop.</p>
                    </div>
                    
                    <div class="why-card animate-on-scroll">
                        <div class="why-number">04</div>
                        <h3>Universal Use</h3>
                        <p>Works for buses, trains, cars, and any mode of transportation worldwide.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Technology Section -->
        <section id="tech" class="tech-section">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-badge">&#x2699;&#xFE0F; Technology</span>
                    <h2 class="section-title">Powered By Innovation</h2>
                    <p class="section-subtitle">Built with modern technologies for reliability and performance</p>
                </div>
                
                <div class="tech-grid">
                    <div class="tech-card animate-on-scroll">
                        <div class="tech-icon">
                            <div class="tech-icon-bg"></div>
                            <span>&#x1F4F1;</span>
                        </div>
                        <h3>Flutter Framework</h3>
                        <p>Cross-platform development ensuring consistent experience on all devices</p>
                    </div>
                    
                    <div class="tech-card animate-on-scroll">
                        <div class="tech-icon">
                            <div class="tech-icon-bg"></div>
                            <span>&#x1F4E1;</span>
                        </div>
                        <h3>Real-time GPS Tracking</h3>
                        <p>Precise location monitoring with high accuracy positioning</p>
                    </div>
                    
                    <div class="tech-card animate-on-scroll">
                        <div class="tech-icon">
                            <div class="tech-icon-bg"></div>
                            <span>&#x26A1;</span>
                        </div>
                        <h3>Geofencing Technology</h3>
                        <p>Virtual boundaries that trigger alerts when crossed</p>
                    </div>
                    
                    <div class="tech-card animate-on-scroll">
                        <div class="tech-icon">
                            <div class="tech-icon-bg"></div>
                            <span>&#x1F50B;</span>
                        </div>
                        <h3>Background Service Optimization</h3>
                        <p>Efficient background processing with minimal battery drain</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Team Section -->
        <section id="team" class="team-section">
            <div class="container">
                <div class="section-header animate-on-scroll">
                    <span class="section-badge">&#x1F465; Our Team</span>
                    <h2 class="section-title">Meet the Members</h2>
                    <p class="section-subtitle">The talented people behind LokAlert</p>
                </div>
                
                <div class="team-grid">
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>OA</span>
                        </div>
                        <h3>Alemana, Onyx Herod</h3>
                    </div>
                    
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>RM</span>
                        </div>
                        <h3>Mabahin, Ryan</h3>
                    </div>
                    
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>EA</span>
                        </div>
                        <h3>Adamos, Eurika</h3>
                    </div>
                    
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>GB</span>
                        </div>
                        <h3>Billones, Gerald</h3>
                    </div>
                    
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>TC</span>
                        </div>
                        <h3>Crisologo, Terence Joefrey</h3>
                    </div>
                    
                    <div class="team-card animate-on-scroll">
                        <div class="team-avatar">
                            <span>AR</span>
                        </div>
                        <h3>Royo, Aenard Ollyer</h3>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact CTA Section -->
        <section class="cta-section" style="padding: 80px 0; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(34, 211, 238, 0.1));">
            <div class="container" style="text-align: center;">
                <h2 class="section-title animate-on-scroll">Have Questions?</h2>
                <p class="section-subtitle animate-on-scroll" style="margin-bottom: 30px;">
                    We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>
                <a href="contact.php" class="btn btn-primary animate-on-scroll">
                    <span>&#x2709;&#xFE0F; Contact Us</span>
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-logo">
                        <span class="logo-icon">&#x1F4CD;</span>
                        <span class="logo-text">LokAlert</span>
                    </div>
                    <p class="footer-tagline">Arrive Smart. Stay Alert.</p>
                    <div style="margin: 20px 0;">
                        <a href="contact.php" style="color: #94a3b8; text-decoration: none; margin: 0 15px;">Contact</a>
                        <a href="admin.php" style="color: #94a3b8; text-decoration: none; margin: 0 15px;">Admin</a>
                    </div>
                    <p class="footer-copyright">&copy; <?php echo date('Y'); ?> LokAlert. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Download Modal -->
    <div class="modal-overlay" id="downloadModal">
        <div class="modal-content">
            <button class="modal-close" id="modalClose">&times;</button>
            
            <!-- Download Section -->
            <div class="modal-section" id="downloadSection">
                <div class="modal-header">
                    <div class="modal-icon">&#x1F4E5;</div>
                    <h2>Download LokAlert</h2>
                    <p>Get the latest version of our app</p>
                </div>
                <div class="version-info" id="versionInfo">
                    <?php if ($latestVersion): ?>
                    <div class="version-card">
                        <div class="version-badge">Latest</div>
                        <div class="version-number">v<?php echo htmlspecialchars($latestVersion['version']); ?></div>
                        <div class="version-details">
                            <span>📦 <?php echo formatSize($latestVersion['file_size']); ?></span>
                            <span>📥 <?php echo number_format($latestVersion['download_count']); ?> downloads</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="version-card">
                        <div class="version-badge">Latest</div>
                        <div class="version-number">v1.2.0</div>
                        <div class="version-details">
                            <span>📦 17.0 MB</span>
                            <span>📥 425 downloads</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="download-buttons">
                    <button class="btn btn-primary btn-download" id="downloadBtn">
                        <span>&#x1F4E5;</span> Download APK
                    </button>
                </div>
                <div class="modal-divider">
                    <span>or</span>
                </div>
                <div class="auth-buttons" id="authButtons">
                    <p class="auth-text">Sign in to track your downloads</p>
                    <button class="btn btn-secondary" id="showLoginBtn">Login</button>
                    <button class="btn btn-outline" id="showRegisterBtn">Register</button>
                </div>
                <div class="user-info-modal" id="userInfoModal" style="display: none;">
                    <p>Logged in as <strong id="userDisplayName"></strong></p>
                    <button class="btn btn-outline btn-small" id="logoutBtn">Logout</button>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="modal-section" id="loginSection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">&#x1F511;</div>
                    <h2>Login</h2>
                    <p>Access your account</p>
                </div>
                <form id="loginForm" class="auth-form">
                    <div class="form-group">
                        <label>Username or Email</label>
                        <input type="text" id="loginUsername" placeholder="Enter username or email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="loginPassword" placeholder="Enter password" required>
                    </div>
                    <div class="form-error" id="loginError"></div>
                    <button type="submit" class="btn btn-primary">Login</button>
                    <p class="form-footer">Don't have an account? <a href="#" id="switchToRegister">Register</a></p>
                    <p class="form-footer"><a href="#" id="backToDownload">&#x2190; Back to download</a></p>
                </form>
            </div>
            
            <!-- Register Form -->
            <div class="modal-section" id="registerSection" style="display: none;">
                <div class="modal-header">
                    <div class="modal-icon">&#x1F4DD;</div>
                    <h2>Create Account</h2>
                    <p>Join LokAlert community</p>
                </div>
                <form id="registerForm" class="auth-form">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="regUsername" placeholder="Choose a username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="regEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="regPassword" placeholder="Create a password" required>
                    </div>
                    <div class="form-error" id="registerError"></div>
                    <button type="submit" class="btn btn-primary">Register</button>
                    <p class="form-footer">Already have an account? <a href="#" id="switchToLogin">Login</a></p>
                    <p class="form-footer"><a href="#" id="backToDownload2">&#x2190; Back to download</a></p>
                </form>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script src="js/download.js"></script>
</body>
</html>
