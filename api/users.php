<?php
/**
 * LokAlert API - Users CRUD
 * Full CRUD operations for user management with admin features
 */

require_once '../includes/config.php';
require_once '../includes/email_service.php';

// Auto-migrate database schema
ensureDatabaseMigrated();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle specific actions first
if ($action) {
    switch ($action) {
        case 'stats':
            getUserStats();
            break;
        case 'reset-password':
            adminResetPassword();
            break;
        case 'send-reset-email':
            adminSendResetEmail();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
    exit;
}

switch ($method) {
    case 'GET':
        if ($id) {
            getUser($id);
        } else {
            getUsers();
        }
        break;
    case 'POST':
        createUser();
        break;
    case 'PUT':
        if ($id) {
            updateUser($id);
        } else {
            jsonResponse(['error' => 'User ID required'], 400);
        }
        break;
    case 'DELETE':
        if ($id) {
            deleteUser($id);
        } else {
            jsonResponse(['error' => 'User ID required'], 400);
        }
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

/**
 * READ - Get all users (admin only)
 * DOES NOT return passwords
 */
function getUsers() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("
            SELECT 
                id, 
                username, 
                email, 
                is_admin, 
                is_verified,
                download_count,
                last_download_at,
                created_at 
            FROM users 
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll();
        
        jsonResponse($users);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Get user statistics for admin dashboard
 */
function getUserStats() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Total registered users (excluding admin)
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
        $totalUsers = $stmt->fetch()['total'];
        
        // Verified users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND is_verified = 1");
        $verifiedUsers = $stmt->fetch()['total'];
        
        // Unverified users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND is_verified = 0");
        $unverifiedUsers = $stmt->fetch()['total'];
        
        // Users with names
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND username IS NOT NULL AND username != ''");
        $usersWithNames = $stmt->fetch()['total'];
        
        // Users who downloaded at least once
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND download_count > 0");
        $usersWithDownloads = $stmt->fetch()['total'];
        
        // Total user downloads
        $stmt = $db->query("SELECT COALESCE(SUM(download_count), 0) as total FROM users WHERE is_admin = 0");
        $totalUserDownloads = $stmt->fetch()['total'];
        
        // Recent signups (last 7 days)
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $recentSignups = $stmt->fetch()['total'];
        
        // Get recent users list (last 10)
        $stmt = $db->query("
            SELECT 
                id, 
                username, 
                email, 
                is_verified,
                download_count,
                created_at 
            FROM users 
            WHERE is_admin = 0
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $recentUsers = $stmt->fetchAll();
        
        jsonResponse([
            'total_users' => (int)$totalUsers,
            'verified_users' => (int)$verifiedUsers,
            'unverified_users' => (int)$unverifiedUsers,
            'users_with_names' => (int)$usersWithNames,
            'users_with_downloads' => (int)$usersWithDownloads,
            'total_user_downloads' => (int)$totalUserDownloads,
            'recent_signups' => (int)$recentSignups,
            'recent_users' => $recentUsers
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get single user (without password)
 */
function getUser($id) {
    requireLogin();
    
    // Users can only view their own profile unless admin
    if (!isAdmin() && $_SESSION['user_id'] != $id) {
        jsonResponse(['error' => 'Access denied'], 403);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                id, 
                username, 
                email, 
                is_admin, 
                is_verified,
                download_count,
                last_download_at,
                created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        jsonResponse($user);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * CREATE - Add new user (admin only)
 */
function createUser() {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitize($data['username'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $is_admin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check for duplicate
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Username or email already exists'], 400);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $is_admin]);
        
        $userId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'User created successfully',
            'id' => $userId
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * UPDATE - Modify user
 */
function updateUser($id) {
    requireLogin();
    
    // Users can only update their own profile unless admin
    if (!isAdmin() && $_SESSION['user_id'] != $id) {
        jsonResponse(['error' => 'Access denied'], 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (!empty($data['username'])) {
            $updates[] = "username = ?";
            $params[] = sanitize($data['username']);
        }
        
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid email format'], 400);
            }
            $updates[] = "email = ?";
            $params[] = sanitize($data['email']);
        }
        
        if (!empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Only admin can change admin status
        if (isAdmin() && isset($data['is_admin'])) {
            $updates[] = "is_admin = ?";
            $params[] = (int)$data['is_admin'];
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * DELETE - Remove user
 */
function deleteUser($id) {
    requireAdmin();
    
    // Prevent self-deletion
    if ($_SESSION['user_id'] == $id) {
        jsonResponse(['error' => 'Cannot delete your own account'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Delete user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Admin reset password - sets a temporary password
 */
function adminResetPassword() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    $newPassword = $data['new_password'] ?? '';
    $sendEmail = isset($data['send_email']) ? (bool)$data['send_email'] : true;
    
    if (!$userId) {
        jsonResponse(['error' => 'User ID is required'], 400);
    }
    
    // Generate password if not provided
    if (empty($newPassword)) {
        $newPassword = bin2hex(random_bytes(4)); // 8 character random password
    }
    
    if (strlen($newPassword) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user
        $stmt = $db->prepare("SELECT id, email, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        // Send email with temporary password
        if ($sendEmail) {
            $emailService = new EmailService();
            $emailService->sendTemporaryPasswordEmail($userId, $user['email'], $newPassword);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Password reset successfully',
            'temp_password' => $newPassword,
            'email_sent' => $sendEmail
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Admin send password reset email to user (self-service)
 */
function adminSendResetEmail() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    
    if (!$userId) {
        jsonResponse(['error' => 'User ID is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user
        $stmt = $db->prepare("SELECT id, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Generate reset token
        $resetToken = generateResetToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY_HOURS . ' hours'));
        
        $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->execute([$resetToken, $expiresAt, $userId]);
        
        // Send reset email
        $emailService = new EmailService();
        $result = $emailService->sendPasswordResetEmail($userId, $user['email'], $resetToken);
        
        jsonResponse([
            'success' => true,
            'message' => 'Password reset email sent to user',
            'debug_token' => EMAIL_ENABLED ? null : $resetToken
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
