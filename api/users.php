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
        case 'audit-log':
            getAuditLog();
            break;
        case 'settings':
            if ($method === 'PUT' || $method === 'POST') {
                updateSettings();
            } else {
                getSettings();
            }
            break;
        case 'table-data':
            getTableData();
            break;
        case 'table-record':
            handleTableRecord();
            break;
        case 'table-stats':
            getTableStats();
            break;
        case 'backup-history':
            getBackupHistory();
            break;
        case 'invite-admin':
            inviteAdmin();
            break;
        case 'list-invites':
            listInvites();
            break;
        case 'invalidate-invite':
            invalidateInvite();
            break;
        case 'maintenance-check':
            maintenanceCheck();
            break;
        case 'promote-admin':
            promoteToAdmin();
            break;
        case 'demote-admin':
            demoteFromAdmin();
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
        
        // Total download logs
        $totalDownloadLogs = 0;
        try {
            $stmt = $db->query("SELECT COUNT(*) as total FROM download_logs");
            $totalDownloadLogs = $stmt->fetch()['total'];
        } catch (PDOException $e) {}
        
        jsonResponse([
            'total_users' => (int)$totalUsers,
            'verified_users' => (int)$verifiedUsers,
            'unverified_users' => (int)$unverifiedUsers,
            'users_with_names' => (int)$usersWithNames,
            'users_with_downloads' => (int)$usersWithDownloads,
            'total_user_downloads' => (int)$totalUserDownloads,
            'total_downloads' => (int)$totalDownloadLogs,
            'recent_signups' => (int)$recentSignups,
            'recent_users' => $recentUsers
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Get recent audit log entries
 */
function getAuditLog() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("
            SELECT al.id, al.action, al.table_name, al.record_id, al.ip_address, al.created_at,
                   u.username
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 30
        ");
        $logs = $stmt->fetchAll();
        
        jsonResponse($logs);
        
    } catch (PDOException $e) {
        // Table might not exist yet
        jsonResponse([]);
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
        
        // Use transaction to ensure atomicity
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $is_admin]);
            
            $userId = $db->lastInsertId();
            
            // Audit log
            logAudit('CREATE_USER', 'users', $userId, null, ['username' => $username, 'email' => $email, 'is_admin' => $is_admin]);
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'User created successfully',
                'id' => $userId
            ], 201);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        
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
        
        // Delete user inside a transaction
        $db->beginTransaction();
        try {
            // Get user info for audit before deleting
            $infoStmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
            $infoStmt->execute([$id]);
            $deletedUser = $infoStmt->fetch();
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            // Audit log
            logAudit('DELETE_USER', 'users', $id, $deletedUser, null);
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        
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

// ============================================================
// SETTINGS MANAGEMENT
// ============================================================

/**
 * Get all admin settings
 */
function getSettings() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM settings ORDER BY id ASC");
        jsonResponse($stmt->fetchAll());
    } catch (PDOException $e) {
        jsonResponse([]);
    }
}

/**
 * Update admin settings
 */
function updateSettings() {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($data)) {
        jsonResponse(['error' => 'Invalid data'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        foreach ($data as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        logAudit('UPDATE_SETTINGS', 'settings', null, null, $data);
        
        jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// TABLE BROWSER (Interactive CRUD)
// ============================================================

/**
 * Get paginated data from any allowed table
 */
function getTableData() {
    requireAdmin();
    
    $allowedTables = ['roles', 'users', 'apk_versions', 'download_logs', 'contact_messages', 'email_logs', 'login_attempts', 'audit_logs', 'settings', 'admin_invites', 'apk_file_chunks'];
    
    $table = sanitize($_GET['table'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    
    if (!in_array($table, $allowedTables)) {
        jsonResponse(['error' => 'Invalid table name'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get total count
        $countStmt = $db->query("SELECT COUNT(*) as total FROM `{$table}`");
        $total = $countStmt->fetch()['total'];
        
        // Get columns info
        $colStmt = $db->query("SHOW COLUMNS FROM `{$table}`");
        $columns = $colStmt->fetchAll();
        
        // Get data - hide password for users table
        if ($table === 'users') {
            $dataStmt = $db->prepare("SELECT id, username, email, role_id, is_admin, is_verified, download_count, last_download_at, created_at FROM `{$table}` ORDER BY id DESC LIMIT ? OFFSET ?");
        } else {
            $dataStmt = $db->prepare("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT ? OFFSET ?");
        }
        $dataStmt->execute([$limit, $offset]);
        $rows = $dataStmt->fetchAll();
        
        // Get indexes info
        $idxStmt = $db->query("SHOW INDEX FROM `{$table}`");
        $indexes = $idxStmt->fetchAll();
        
        jsonResponse([
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'indexes' => $indexes,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => max(1, ceil($total / $limit))
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle CRUD operations on individual table records
 */
function handleTableRecord() {
    requireAdmin();
    
    global $method;
    $table = sanitize($_GET['table'] ?? '');
    $recordId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['record_id']) ? (int)$_GET['record_id'] : null);
    
    $allowedTables = ['roles', 'users', 'apk_versions', 'download_logs', 'contact_messages', 'email_logs', 'login_attempts', 'audit_logs', 'settings', 'admin_invites', 'apk_file_chunks'];
    
    if (!in_array($table, $allowedTables)) {
        jsonResponse(['error' => 'Invalid table name'], 400);
    }
    
    switch ($method) {
        case 'DELETE':
            deleteTableRecord($table, $recordId);
            break;
        case 'PUT':
            updateTableRecord($table, $recordId);
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Delete a record from a table
 */
function deleteTableRecord($table, $recordId) {
    if (!$recordId) {
        jsonResponse(['error' => 'Record ID required'], 400);
    }
    
    // Prevent deleting admin user
    if ($table === 'users') {
        $db = Database::getInstance()->getConnection();
        $check = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $check->execute([$recordId]);
        $user = $check->fetch();
        if ($user && $user['is_admin']) {
            jsonResponse(['error' => 'Cannot delete admin user'], 400);
        }
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Capture old values for audit
        $oldStmt = $db->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $oldStmt->execute([$recordId]);
        $oldRecord = $oldStmt->fetch();
        
        if (!$oldRecord) {
            jsonResponse(['error' => 'Record not found'], 404);
        }
        
        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $stmt->execute([$recordId]);
        
        logAudit('DELETE_RECORD', $table, $recordId, $oldRecord, null);
        
        jsonResponse(['success' => true, 'message' => "Record #{$recordId} deleted from {$table}"]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Update a record in a table
 */
function updateTableRecord($table, $recordId) {
    if (!$recordId) {
        jsonResponse(['error' => 'Record ID required'], 400);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data)) {
        jsonResponse(['error' => 'No data provided'], 400);
    }
    
    // Never allow updating password directly through table browser
    unset($data['password']);
    unset($data['id']);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Build UPDATE query from provided fields
        $updates = [];
        $params = [];
        foreach ($data as $col => $val) {
            // Sanitize column name (only allow alphanumeric + underscore)
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $updates[] = "`{$safeCol}` = ?";
            $params[] = $val;
        }
        
        $params[] = $recordId;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logAudit('UPDATE_RECORD', $table, $recordId, null, $data);
        
        jsonResponse(['success' => true, 'message' => "Record #{$recordId} updated in {$table}"]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// TABLE STATISTICS
// ============================================================

/**
 * Get detailed statistics for all tables
 */
function getTableStats() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        $tables = $db->query("SHOW TABLE STATUS")->fetchAll();
        
        $stats = [];
        foreach ($tables as $t) {
            $stats[] = [
                'name' => $t['Name'],
                'rows' => (int)($t['Rows'] ?? 0),
                'data_length' => (int)($t['Data_length'] ?? 0),
                'index_length' => (int)($t['Index_length'] ?? 0),
                'engine' => $t['Engine'] ?? 'InnoDB',
                'collation' => $t['Collation'] ?? 'utf8mb4_general_ci',
                'auto_increment' => $t['Auto_increment'] ?? null,
                'create_time' => $t['Create_time'] ?? null,
                'update_time' => $t['Update_time'] ?? null
            ];
        }
        
        jsonResponse($stats);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// BACKUP HISTORY
// ============================================================

/**
 * Get backup history from audit logs
 */
function getBackupHistory() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT al.id, al.ip_address, al.created_at, u.username
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.action = 'DATABASE_BACKUP'
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        jsonResponse($stmt->fetchAll());
    } catch (PDOException $e) {
        jsonResponse([]);
    }
}

// ============================================================
// ADMIN INVITE SYSTEM
// ============================================================

/**
 * Invite a new admin user
 */
function inviteAdmin() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = isset($data['name']) ? sanitize($data['name']) : '';
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $expiryHours = isset($data['expiry_hours']) ? max(1, (int)$data['expiry_hours']) : 48;
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if email already exists as a user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'A user with this email already exists'], 400);
        }
        
        // Check if there's already a pending invite for this email
        $stmt = $db->prepare("SELECT id FROM admin_invites WHERE email = ? AND used_at IS NULL AND invalidated_at IS NULL AND expires_at > NOW()");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'An active invite already exists for this email'], 400);
        }
        
        // Generate invite token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
        $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
        
        $stmt = $db->prepare("
            INSERT INTO admin_invites (name, email, token, password_hash, created_by, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $token, $passwordHash, $_SESSION['user_id'] ?? null, $expiresAt]);
        
        $inviteId = $db->lastInsertId();
        $inviteLink = SITE_URL . '/admin.html?invite=' . $token;
        
        // Try to send invite email
        $emailSent = false;
        try {
            require_once '../includes/email_service.php';
            $emailService = new EmailService();
            $subject = 'You are invited to LokAlert Admin';
            $htmlBody = '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;background:#1e293b;color:#e2e8f0;padding:30px;border-radius:12px;">';
            $htmlBody .= '<h2 style="color:#818cf8;text-align:center;">LokAlert Admin Invitation</h2>';
            $htmlBody .= '<p>Hello ' . ($name ?: 'there') . ',</p>';
            $htmlBody .= '<p>You have been invited to join the LokAlert Admin Panel.</p>';
            if (!empty($password)) {
                $htmlBody .= '<p>A password has been pre-set for you. Click the link below to activate your account:</p>';
            } else {
                $htmlBody .= '<p>Click the link below to set your password and activate your account:</p>';
            }
            $htmlBody .= '<p style="text-align:center;margin:20px 0;"><a href="' . $inviteLink . '" style="background:#6366f1;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">Accept Invitation</a></p>';
            $htmlBody .= '<p style="font-size:12px;color:#94a3b8;">This invitation expires in ' . $expiryHours . ' hours.</p>';
            $htmlBody .= '</div>';
            $plainText = "You have been invited to LokAlert Admin. Visit: {$inviteLink}";
            
            $result = $emailService->sendEmail(null, $email, $subject, $htmlBody, $plainText, 'admin_invite');
            $emailSent = !empty($result['success']);
        } catch (Exception $e) {
            // Email sending is best-effort
        }
        
        logAudit('ADMIN_INVITE', 'admin_invites', $inviteId, null, ['email' => $email, 'name' => $name]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Admin invitation created successfully',
            'invite_id' => $inviteId,
            'invite_link' => $inviteLink,
            'token' => $token,
            'email_sent' => $emailSent,
            'expires_at' => $expiresAt
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * List all admin invites
 */
function listInvites() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT ai.*, u.username as invited_by
            FROM admin_invites ai
            LEFT JOIN users u ON ai.created_by = u.id
            ORDER BY ai.created_at DESC
            LIMIT 50
        ");
        $invites = $stmt->fetchAll();
        
        // Add status field
        foreach ($invites as &$inv) {
            if ($inv['used_at']) {
                $inv['status'] = 'accepted';
            } elseif ($inv['invalidated_at']) {
                $inv['status'] = 'invalidated';
            } elseif (strtotime($inv['expires_at']) < time()) {
                $inv['status'] = 'expired';
            } else {
                $inv['status'] = 'pending';
            }
            // Never expose password hash or full token
            unset($inv['password_hash']);
            $inv['token_preview'] = substr($inv['token'], 0, 8) . '...';
        }
        
        jsonResponse($invites);
    } catch (PDOException $e) {
        jsonResponse([]);
    }
}

/**
 * Invalidate an admin invite
 */
function invalidateInvite() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $inviteId = isset($data['invite_id']) ? (int)$data['invite_id'] : null;
    
    if (!$inviteId) {
        jsonResponse(['error' => 'Invite ID is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, used_at FROM admin_invites WHERE id = ?");
        $stmt->execute([$inviteId]);
        $invite = $stmt->fetch();
        
        if (!$invite) {
            jsonResponse(['error' => 'Invite not found'], 404);
        }
        
        if ($invite['used_at']) {
            jsonResponse(['error' => 'This invite has already been used'], 400);
        }
        
        $stmt = $db->prepare("UPDATE admin_invites SET invalidated_at = NOW() WHERE id = ?");
        $stmt->execute([$inviteId]);
        
        logAudit('INVALIDATE_INVITE', 'admin_invites', $inviteId);
        
        jsonResponse(['success' => true, 'message' => 'Invite invalidated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Public maintenance mode check (no auth required)
 */
function maintenanceCheck() {
    try {
        $mode = getSetting('site_maintenance_mode', '0');
        $message = getSetting('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
        
        jsonResponse([
            'maintenance' => $mode === '1',
            'message' => $message
        ]);
    } catch (Exception $e) {
        jsonResponse(['maintenance' => false, 'message' => '']);
    }
}

/**
 * Promote an existing user to admin
 */
function promoteToAdmin() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    
    if (!$userId) {
        jsonResponse(['error' => 'User ID is required'], 400);
    }
    
    // Prevent promoting yourself (already admin)
    if ($_SESSION['user_id'] == $userId) {
        jsonResponse(['error' => 'You are already an admin'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        if ($user['is_admin']) {
            jsonResponse(['error' => 'User is already an admin'], 400);
        }
        
        $stmt = $db->prepare("UPDATE users SET is_admin = 1, role_id = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        
        logAudit('PROMOTE_ADMIN', 'users', $userId, ['is_admin' => 0], ['is_admin' => 1]);
        
        jsonResponse([
            'success' => true,
            'message' => 'User promoted to admin successfully',
            'user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Demote an admin back to regular user
 */
function demoteFromAdmin() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    
    if (!$userId) {
        jsonResponse(['error' => 'User ID is required'], 400);
    }
    
    // Prevent demoting yourself
    if ($_SESSION['user_id'] == $userId) {
        jsonResponse(['error' => 'You cannot demote yourself'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        if (!$user['is_admin']) {
            jsonResponse(['error' => 'User is not an admin'], 400);
        }
        
        $stmt = $db->prepare("UPDATE users SET is_admin = 0, role_id = 2 WHERE id = ?");
        $stmt->execute([$userId]);
        
        logAudit('DEMOTE_ADMIN', 'users', $userId, ['is_admin' => 1], ['is_admin' => 0]);
        
        jsonResponse([
            'success' => true,
            'message' => 'User demoted from admin successfully',
            'user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email']]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
