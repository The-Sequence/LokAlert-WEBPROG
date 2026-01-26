<?php
/**
 * LokAlert API - Download Logs
 * Track and manage download statistics
 */

require_once '../includes/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            getStats();
        } else {
            getLogs();
        }
        break;
    case 'POST':
        logDownload();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
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
                av.version
            FROM download_logs dl
            LEFT JOIN users u ON dl.user_id = u.id
            LEFT JOIN apk_versions av ON dl.version_id = av.id
            ORDER BY dl.download_date DESC
            LIMIT 100
        ");
        
        $logs = $stmt->fetchAll();
        
        jsonResponse($logs);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get statistics
 */
function getStats() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Total downloads
        $stmt = $db->query("SELECT COALESCE(SUM(download_count), 0) as total FROM apk_versions");
        $totalDownloads = $stmt->fetch()['total'];
        
        // Total users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $stmt->fetch()['total'];
        
        // Total versions
        $stmt = $db->query("SELECT COUNT(*) as total FROM apk_versions");
        $totalVersions = $stmt->fetch()['total'];
        
        // Unread messages
        $stmt = $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0");
        $unreadMessages = $stmt->fetch()['total'];
        
        // Recent downloads (last 7 days)
        $stmt = $db->query("SELECT COUNT(*) as total FROM download_logs WHERE download_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $recentDownloads = $stmt->fetch()['total'];
        
        jsonResponse([
            'total_downloads' => (int)$totalDownloads,
            'total_users' => (int)$totalUsers,
            'total_versions' => (int)$totalVersions,
            'unread_messages' => (int)$unreadMessages,
            'recent_downloads' => (int)$recentDownloads
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * CREATE - Log a download
 */
function logDownload() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $version_id = isset($data['version_id']) ? (int)$data['version_id'] : null;
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (!$version_id) {
        jsonResponse(['error' => 'Version ID is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Insert download log
        $stmt = $db->prepare("INSERT INTO download_logs (user_id, version_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $version_id, $ip_address, $user_agent]);
        
        // Increment download count
        $stmt = $db->prepare("UPDATE apk_versions SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$version_id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Download logged successfully'
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
