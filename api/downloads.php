<?php
/**
 * LokAlert API - Download Management
 * Handles download tracking with success verification and rate limiting
 */

require_once '../includes/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'init':
        initDownload();
        break;
    case 'progress':
        updateProgress();
        break;
    case 'complete':
        completeDownload();
        break;
    case 'cancel':
        cancelDownload();
        break;
    case 'stats':
        getStats();
        break;
    case 'logs':
        getLogs();
        break;
    case 'check-cooldown':
        checkCooldown();
        break;
    case 'latest':
        getLatestVersion();
        break;
    default:
        // Legacy support - log download directly
        if ($method === 'POST') {
            logDownloadLegacy();
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
}

/**
 * Initialize a download - creates a download token
 * This is called when user clicks download
 */
function initDownload() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    // Require verified user
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required. Please sign up or log in.'], 401);
    }
    
    if (!isVerified()) {
        jsonResponse(['error' => 'Email verification required before downloading.'], 403);
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check cooldown
    if (!canUserDownload($userId)) {
        $remaining = getTimeUntilNextDownload($userId);
        $minutes = ceil($remaining / 60);
        jsonResponse([
            'error' => "Please wait $minutes minute(s) before downloading again.",
            'cooldown_remaining' => $remaining,
            'cooldown_active' => true
        ], 429);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $versionId = isset($data['version_id']) ? (int)$data['version_id'] : null;
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get version info
        if ($versionId) {
            $stmt = $db->prepare("SELECT id, filename, file_size FROM apk_versions WHERE id = ?");
            $stmt->execute([$versionId]);
        } else {
            $stmt = $db->query("SELECT id, filename, file_size FROM apk_versions WHERE is_latest = 1 LIMIT 1");
        }
        $version = $stmt->fetch();
        
        if (!$version) {
            jsonResponse(['error' => 'Version not found'], 404);
        }
        
        // Generate unique download token
        $downloadToken = generateDownloadToken();
        $ipAddress = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Create download log entry with 'started' status
        $stmt = $db->prepare("
            INSERT INTO download_logs (user_id, version_id, ip_address, user_agent, download_token, status, file_size, bytes_downloaded, started_at)
            VALUES (?, ?, ?, ?, ?, 'started', ?, 0, NOW())
        ");
        $stmt->execute([$userId, $version['id'], $ipAddress, $userAgent, $downloadToken, $version['file_size']]);
        
        $logId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'download_token' => $downloadToken,
            'log_id' => $logId,
            'file_size' => (int)$version['file_size'],
            'filename' => $version['filename'],
            'message' => 'Download initialized. Complete the download to update your count.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Update download progress (for tracking partial downloads)
 */
function updateProgress() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $downloadToken = sanitize($data['download_token'] ?? '');
    $bytesDownloaded = isset($data['bytes_downloaded']) ? (int)$data['bytes_downloaded'] : 0;
    
    if (empty($downloadToken)) {
        jsonResponse(['error' => 'Download token required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            UPDATE download_logs 
            SET bytes_downloaded = ?
            WHERE download_token = ? AND status = 'started'
        ");
        $stmt->execute([$bytesDownloaded, $downloadToken]);
        
        jsonResponse(['success' => true, 'bytes_downloaded' => $bytesDownloaded]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Complete download - ONLY THIS INCREMENTS THE COUNTER
 * Called when download is verified as successful
 */
function completeDownload() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $downloadToken = sanitize($data['download_token'] ?? '');
    $bytesDownloaded = isset($data['bytes_downloaded']) ? (int)$data['bytes_downloaded'] : 0;
    $verified = isset($data['verified']) ? (bool)$data['verified'] : false;
    
    if (empty($downloadToken)) {
        jsonResponse(['error' => 'Download token required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get download log
        $stmt = $db->prepare("
            SELECT dl.*, av.file_size as expected_size 
            FROM download_logs dl
            JOIN apk_versions av ON dl.version_id = av.id
            WHERE dl.download_token = ? AND dl.status = 'started'
        ");
        $stmt->execute([$downloadToken]);
        $download = $stmt->fetch();
        
        if (!$download) {
            jsonResponse(['error' => 'Invalid or already processed download token'], 400);
        }
        
        // Verify download integrity
        // Allow some tolerance (98% of file size) to account for browser reporting differences
        $minRequired = (int)($download['expected_size'] * 0.98);
        
        if ($bytesDownloaded < $minRequired && !$verified) {
            // Download not complete - mark as failed
            $stmt = $db->prepare("
                UPDATE download_logs 
                SET status = 'failed', bytes_downloaded = ?, completed_at = NOW()
                WHERE download_token = ?
            ");
            $stmt->execute([$bytesDownloaded, $downloadToken]);
            
            jsonResponse([
                'success' => false,
                'error' => 'Download incomplete. Please try again.',
                'bytes_downloaded' => $bytesDownloaded,
                'bytes_expected' => $download['expected_size']
            ], 400);
        }
        
        // Download successful - update everything
        $db->beginTransaction();
        
        try {
            // 1. Mark download as completed
            $stmt = $db->prepare("
                UPDATE download_logs 
                SET status = 'completed', bytes_downloaded = ?, completed_at = NOW()
                WHERE download_token = ?
            ");
            $stmt->execute([$bytesDownloaded > 0 ? $bytesDownloaded : $download['expected_size'], $downloadToken]);
            
            // 2. Increment version download count
            $stmt = $db->prepare("
                UPDATE apk_versions 
                SET download_count = download_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$download['version_id']]);
            
            // 3. Update user's last download time and count
            $stmt = $db->prepare("
                UPDATE users 
                SET last_download_at = NOW(), download_count = download_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$download['user_id']]);
            
            $db->commit();
            
            // Get updated stats
            $stmt = $db->prepare("SELECT download_count FROM apk_versions WHERE id = ?");
            $stmt->execute([$download['version_id']]);
            $versionStats = $stmt->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Download completed successfully!',
                'download_count' => $versionStats['download_count'],
                'cooldown_minutes' => DOWNLOAD_COOLDOWN_MINUTES
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Cancel/fail a download
 */
function cancelDownload() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $downloadToken = sanitize($data['download_token'] ?? '');
    $reason = sanitize($data['reason'] ?? 'cancelled');
    
    if (empty($downloadToken)) {
        jsonResponse(['error' => 'Download token required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $status = ($reason === 'error' || $reason === 'failed') ? 'failed' : 'cancelled';
        
        $stmt = $db->prepare("
            UPDATE download_logs 
            SET status = ?, completed_at = NOW()
            WHERE download_token = ? AND status = 'started'
        ");
        $stmt->execute([$status, $downloadToken]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Download cancelled. No count incremented.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Check cooldown status for current user
 */
function checkCooldown() {
    if (!isLoggedIn()) {
        jsonResponse(['authenticated' => false, 'can_download' => false]);
    }
    
    $userId = $_SESSION['user_id'];
    $canDownload = canUserDownload($userId);
    $remaining = getTimeUntilNextDownload($userId);
    
    jsonResponse([
        'authenticated' => true,
        'is_verified' => isVerified(),
        'can_download' => $canDownload && isVerified(),
        'cooldown_active' => !$canDownload,
        'cooldown_remaining' => $remaining,
        'cooldown_minutes' => DOWNLOAD_COOLDOWN_MINUTES
    ]);
}

/**
 * Get latest version info
 */
function getLatestVersion() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("
            SELECT id, version, filename, file_size, release_notes, download_count, upload_date 
            FROM apk_versions 
            WHERE is_latest = 1 
            LIMIT 1
        ");
        $version = $stmt->fetch();
        
        if (!$version) {
            // Get most recent version
            $stmt = $db->query("
                SELECT id, version, filename, file_size, release_notes, download_count, upload_date 
                FROM apk_versions 
                ORDER BY upload_date DESC 
                LIMIT 1
            ");
            $version = $stmt->fetch();
        }
        
        if (!$version) {
            jsonResponse(['error' => 'No versions available'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'version' => [
                'id' => $version['id'],
                'version' => $version['version'],
                'filename' => $version['filename'],
                'file_size' => (int)$version['file_size'],
                'file_size_formatted' => formatBytes($version['file_size']),
                'release_notes' => $version['release_notes'],
                'download_count' => (int)$version['download_count'],
                'upload_date' => $version['upload_date']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get download statistics
 */
function getStats() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Total downloads (only completed)
        $stmt = $db->query("SELECT COALESCE(SUM(download_count), 0) as total FROM apk_versions");
        $totalDownloads = $stmt->fetch()['total'];
        
        // Total registered users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
        $totalUsers = $stmt->fetch()['total'];
        
        // Verified users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND is_verified = 1");
        $verifiedUsers = $stmt->fetch()['total'];
        
        // Total versions
        $stmt = $db->query("SELECT COUNT(*) as total FROM apk_versions");
        $totalVersions = $stmt->fetch()['total'];
        
        // Unread messages
        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0");
        $unreadMessages = $stmt->fetch()['total'];
        
        // Recent downloads (last 7 days, completed only)
        $stmt = $db->query("SELECT COUNT(*) as total FROM download_logs WHERE status = 'completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $recentDownloads = $stmt->fetch()['total'];
        
        // Failed downloads
        $stmt = $db->query("SELECT COUNT(*) as total FROM download_logs WHERE status IN ('failed', 'cancelled')");
        $failedDownloads = $stmt->fetch()['total'];
        
        jsonResponse([
            'total_downloads' => (int)$totalDownloads,
            'total_users' => (int)$totalUsers,
            'verified_users' => (int)$verifiedUsers,
            'total_versions' => (int)$totalVersions,
            'unread_messages' => (int)$unreadMessages,
            'recent_downloads' => (int)$recentDownloads,
            'failed_downloads' => (int)$failedDownloads
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get download logs (admin only)
 */
function getLogs() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("
            SELECT 
                dl.*,
                u.username,
                u.email as user_email,
                av.version
            FROM download_logs dl
            LEFT JOIN users u ON dl.user_id = u.id
            LEFT JOIN apk_versions av ON dl.version_id = av.id
            ORDER BY dl.started_at DESC
            LIMIT 100
        ");
        
        $logs = $stmt->fetchAll();
        
        jsonResponse($logs);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Legacy download logging (for backwards compatibility)
 */
function logDownloadLegacy() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $version_id = isset($data['version_id']) ? (int)$data['version_id'] : null;
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (!$version_id) {
        jsonResponse(['error' => 'Version ID is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Insert download log (but don't count - for legacy tracking only)
        $downloadToken = generateDownloadToken();
        $stmt = $db->prepare("
            INSERT INTO download_logs (user_id, version_id, ip_address, user_agent, download_token, status, started_at)
            VALUES (?, ?, ?, ?, ?, 'started', NOW())
        ");
        $stmt->execute([$user_id, $version_id, $ip_address, $user_agent, $downloadToken]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Download initiated',
            'download_token' => $downloadToken
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
