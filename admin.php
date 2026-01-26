<?php
/**
 * LokAlert - Admin Panel
 * Complete admin dashboard with PHP + MySQL CRUD operations
 */

require_once 'includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_admin = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                header('Location: admin.php');
                exit;
            } else {
                $loginError = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $loginError = 'Database error. Please try again.';
        }
    } else {
        $loginError = 'Please enter username and password';
    }
}

// Handle CRUD Operations
$message = '';
$messageType = '';

if (isAdmin()) {
    $db = Database::getInstance()->getConnection();
    
    // CREATE - Add new APK version
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_version'])) {
        $version = sanitize($_POST['version'] ?? '');
        $release_notes = sanitize($_POST['release_notes'] ?? '');
        $is_latest = isset($_POST['is_latest']) ? 1 : 0;
        
        if (!empty($version)) {
            try {
                if ($is_latest) {
                    $db->exec("UPDATE apk_versions SET is_latest = 0");
                }
                
                $filename = 'LokAlert-v' . $version . '.apk';
                $file_size = rand(15000000, 20000000);
                
                $stmt = $db->prepare("INSERT INTO apk_versions (version, filename, file_size, release_notes, is_latest) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$version, $filename, $file_size, $release_notes, $is_latest]);
                
                $message = 'APK version added successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding version: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // UPDATE - Edit APK version
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_version'])) {
        $id = (int)$_POST['version_id'];
        $version = sanitize($_POST['version'] ?? '');
        $release_notes = sanitize($_POST['release_notes'] ?? '');
        $is_latest = isset($_POST['is_latest']) ? 1 : 0;
        
        if (!empty($version) && $id > 0) {
            try {
                if ($is_latest) {
                    $db->exec("UPDATE apk_versions SET is_latest = 0");
                }
                
                $stmt = $db->prepare("UPDATE apk_versions SET version = ?, release_notes = ?, is_latest = ? WHERE id = ?");
                $stmt->execute([$version, $release_notes, $is_latest, $id]);
                
                $message = 'Version updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating version: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // DELETE - Remove APK version
    if (isset($_GET['delete_version'])) {
        $id = (int)$_GET['delete_version'];
        try {
            $stmt = $db->prepare("DELETE FROM apk_versions WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Version deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting version: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // CREATE - Add new user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if (!empty($username) && !empty($email) && !empty($password)) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $is_admin]);
                
                $message = 'User added successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding user. Username or email may already exist.';
                $messageType = 'error';
            }
        }
    }
    
    // UPDATE - Edit user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        if (!empty($username) && !empty($email) && $id > 0) {
            try {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, is_admin = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $hashedPassword, $is_admin, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, is_admin = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $is_admin, $id]);
                }
                
                $message = 'User updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating user: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // DELETE - Remove user
    if (isset($_GET['delete_user'])) {
        $id = (int)$_GET['delete_user'];
        if ($id != $_SESSION['user_id']) {
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'User deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting user: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Cannot delete your own account!';
            $messageType = 'error';
        }
    }
    
    // DELETE - Remove message
    if (isset($_GET['delete_message'])) {
        $id = (int)$_GET['delete_message'];
        try {
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Message deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting message: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // UPDATE - Mark message as read
    if (isset($_GET['mark_read'])) {
        $id = (int)$_GET['mark_read'];
        try {
            $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {}
    }
    
    // Fetch data for dashboard
    $stats = [
        'downloads' => $db->query("SELECT COALESCE(SUM(download_count), 0) as total FROM apk_versions")->fetch()['total'],
        'users' => $db->query("SELECT COUNT(*) as total FROM users")->fetch()['total'],
        'versions' => $db->query("SELECT COUNT(*) as total FROM apk_versions")->fetch()['total'],
        'messages' => $db->query("SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0")->fetch()['total']
    ];
    
    $versions = $db->query("SELECT * FROM apk_versions ORDER BY upload_date DESC")->fetchAll();
    $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    $messages_list = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    $logs = $db->query("
        SELECT dl.*, u.username, av.version 
        FROM download_logs dl 
        LEFT JOIN users u ON dl.user_id = u.id 
        LEFT JOIN apk_versions av ON dl.version_id = av.id 
        ORDER BY dl.download_date DESC LIMIT 50
    ")->fetchAll();
    
    // Get version for editing
    $editVersion = null;
    if (isset($_GET['edit_version'])) {
        $stmt = $db->prepare("SELECT * FROM apk_versions WHERE id = ?");
        $stmt->execute([(int)$_GET['edit_version']]);
        $editVersion = $stmt->fetch();
    }
    
    // Get user for editing
    $editUser = null;
    if (isset($_GET['edit_user'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$_GET['edit_user']]);
        $editUser = $stmt->fetch();
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes <= 0) return '-';
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LokAlert</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #22d3ee;
            --bg-dark: #0f172a;
            --bg-darker: #020617;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --card-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 255, 255, 0.1);
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
            min-height: 100vh;
            color: var(--text-light);
        }

        /* Login Styles */
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }

        .login-box h1 {
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-box > p {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-danger:hover { background: #dc2626; }

        .btn-warning {
            background: var(--warning);
            color: var(--bg-dark);
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-full { width: 100%; }

        .error-msg, .success-msg {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .success-msg {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        /* Dashboard Styles */
        .header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info { color: var(--text-muted); }

        .btn-logout {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-logout:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        .main-content {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            backdrop-filter: blur(10px);
        }

        .stat-card h3 {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 16px;
            font-weight: 500;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
            text-decoration: none;
        }

        .tab-btn:hover {
            color: var(--text-light);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: var(--text-light);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }

        .card h2 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        /* Table */
        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 14px;
        }

        tr:hover { background: rgba(255, 255, 255, 0.02); }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
        }

        .badge-primary {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; padding: 20px; }
            .main-content { padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            th, td { padding: 8px; font-size: 14px; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php if (!isAdmin()): ?>
    <!-- Login Page -->
    <div class="login-container">
        <div class="login-box">
            <h1>🔐 Admin Panel</h1>
            <p>LokAlert Management System</p>
            
            <?php if ($loginError): ?>
                <div class="error-msg"><?php echo $loginError; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-full">Login</button>
            </form>
            
            <p style="margin-top: 20px; font-size: 12px; color: var(--text-muted);">
                Default: admin / password
            </p>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Dashboard -->
    <header class="header">
        <h1>📱 LokAlert Admin</h1>
        <div class="header-actions">
            <span class="user-info">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </header>

    <main class="main-content">
        <?php if ($message): ?>
            <div class="<?php echo $messageType === 'success' ? 'success-msg' : 'error-msg'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📥 Total Downloads</h3>
                <div class="value"><?php echo number_format($stats['downloads']); ?></div>
            </div>
            <div class="stat-card">
                <h3>👤 Registered Users</h3>
                <div class="value"><?php echo number_format($stats['users']); ?></div>
            </div>
            <div class="stat-card">
                <h3>📦 APK Versions</h3>
                <div class="value"><?php echo number_format($stats['versions']); ?></div>
            </div>
            <div class="stat-card">
                <h3>✉️ Unread Messages</h3>
                <div class="value"><?php echo number_format($stats['messages']); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=versions" class="tab-btn <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'versions') ? 'active' : ''; ?>">APK Versions</a>
            <a href="?tab=users" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'active' : ''; ?>">Users</a>
            <a href="?tab=messages" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'messages') ? 'active' : ''; ?>">Messages</a>
            <a href="?tab=logs" class="tab-btn <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'logs') ? 'active' : ''; ?>">Download Logs</a>
        </div>

        <!-- Versions Tab -->
        <div class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'versions') ? 'active' : ''; ?>">
            <!-- Add/Edit Version Form -->
            <div class="card">
                <h2><?php echo $editVersion ? '✏️ Edit APK Version' : '➕ Add New APK Version'; ?></h2>
                <form method="POST" action="?tab=versions">
                    <?php if ($editVersion): ?>
                        <input type="hidden" name="version_id" value="<?php echo $editVersion['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Version Number *</label>
                            <input type="text" name="version" placeholder="e.g., 1.0.0" 
                                   value="<?php echo $editVersion ? htmlspecialchars($editVersion['version']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_latest" id="is_latest" 
                                       <?php echo ($editVersion && $editVersion['is_latest']) ? 'checked' : (!$editVersion ? 'checked' : ''); ?>>
                                <label for="is_latest">Set as latest version</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Release Notes</label>
                        <textarea name="release_notes" placeholder="What's new in this version?"><?php echo $editVersion ? htmlspecialchars($editVersion['release_notes']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" name="<?php echo $editVersion ? 'update_version' : 'add_version'; ?>" class="btn btn-primary">
                        <?php echo $editVersion ? '💾 Update Version' : '🚀 Add Version'; ?>
                    </button>
                    
                    <?php if ($editVersion): ?>
                        <a href="?tab=versions" class="btn btn-small" style="margin-left: 10px;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Versions Table -->
            <div class="card">
                <h2>📦 APK Versions</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Version</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Downloads</th>
                                <th>Status</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($versions as $v): ?>
                            <tr>
                                <td><?php echo $v['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($v['version']); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['filename']); ?></td>
                                <td><?php echo formatFileSize($v['file_size']); ?></td>
                                <td><?php echo number_format($v['download_count']); ?></td>
                                <td>
                                    <?php if ($v['is_latest']): ?>
                                        <span class="badge badge-success">Latest</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($v['upload_date'])); ?></td>
                                <td class="actions">
                                    <a href="?tab=versions&edit_version=<?php echo $v['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                                    <a href="?tab=versions&delete_version=<?php echo $v['id']; ?>" 
                                       class="btn btn-danger btn-small" 
                                       onclick="return confirm('Are you sure you want to delete this version?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Users Tab -->
        <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'active' : ''; ?>">
            <!-- Add/Edit User Form -->
            <div class="card">
                <h2><?php echo $editUser ? '✏️ Edit User' : '➕ Add New User'; ?></h2>
                <form method="POST" action="?tab=users">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" placeholder="Username" 
                                   value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" placeholder="Email address" 
                                   value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <?php echo $editUser ? '(leave blank to keep current)' : '*'; ?></label>
                            <input type="password" name="password" placeholder="Password" <?php echo $editUser ? '' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="is_admin" id="is_admin" 
                                       <?php echo ($editUser && $editUser['is_admin']) ? 'checked' : ''; ?>>
                                <label for="is_admin">Admin privileges</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="<?php echo $editUser ? 'update_user' : 'add_user'; ?>" class="btn btn-primary">
                        <?php echo $editUser ? '💾 Update User' : '➕ Add User'; ?>
                    </button>
                    
                    <?php if ($editUser): ?>
                        <a href="?tab=users" class="btn btn-small" style="margin-left: 10px;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Users Table -->
            <div class="card">
                <h2>👥 Registered Users</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <?php if ($u['is_admin']): ?>
                                        <span class="badge badge-primary">Admin</span>
                                    <?php else: ?>
                                        <span class="badge">User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="?tab=users&edit_user=<?php echo $u['id']; ?>" class="btn btn-warning btn-small">Edit</a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="?tab=users&delete_user=<?php echo $u['id']; ?>" 
                                           class="btn btn-danger btn-small" 
                                           onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Messages Tab -->
        <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'messages') ? 'active' : ''; ?>">
            <div class="card">
                <h2>✉️ Contact Messages</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages_list as $m): ?>
                            <tr style="<?php echo !$m['is_read'] ? 'background: rgba(99, 102, 241, 0.1);' : ''; ?>">
                                <td><?php echo $m['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($m['email']); ?></td>
                                <td><?php echo htmlspecialchars($m['subject']); ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($m['message']); ?>
                                </td>
                                <td>
                                    <?php if (!$m['is_read']): ?>
                                        <span class="badge badge-warning">Unread</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Read</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($m['created_at'])); ?></td>
                                <td class="actions">
                                    <?php if (!$m['is_read']): ?>
                                        <a href="?tab=messages&mark_read=<?php echo $m['id']; ?>" class="btn btn-warning btn-small">Mark Read</a>
                                    <?php endif; ?>
                                    <a href="?tab=messages&delete_message=<?php echo $m['id']; ?>" 
                                       class="btn btn-danger btn-small" 
                                       onclick="return confirm('Are you sure you want to delete this message?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Logs Tab -->
        <div class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'logs') ? 'active' : ''; ?>">
            <div class="card">
                <h2>📊 Download Logs</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>User</th>
                                <th>Version</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?php echo $l['id']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($l['download_date'])); ?></td>
                                <td><?php echo $l['username'] ? htmlspecialchars($l['username']) : '<em>Guest</em>'; ?></td>
                                <td><?php echo $l['version'] ? htmlspecialchars($l['version']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($l['ip_address']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <?php endif; ?>
</body>
</html>
