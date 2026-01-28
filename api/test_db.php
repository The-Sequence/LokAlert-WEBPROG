<?php
/**
 * Quick database verification script
 * Access via: http://localhost/LokAlert/api/test_db.php
 */

require_once '../includes/config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>LokAlert Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a2e; color: #fff; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; }
        th { background: #2d2d44; }
        .btn { background: #3b82f6; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
<div class="container">
    <h1>LokAlert Database Verification</h1>
    
<?php
try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✓ Database connection successful!</p>";
    
    // Check table structure
    echo "<h2>Users Table Structure</h2>";
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Status</th></tr>";
    
    $requiredColumns = [
        'is_verified' => 'Verification status',
        'verification_code' => 'Verification code storage',
        'verification_expires' => 'Code expiry timestamp',
        'reset_token' => 'Password reset token',
        'reset_expires' => 'Reset token expiry',
        'last_download_at' => 'Download cooldown tracking',
        'download_count' => 'Download count'
    ];
    
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($columns as $col) {
        $status = isset($requiredColumns[$col['Field']]) ? "✓ Required" : "";
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td class='success'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for missing columns
    $missing = [];
    foreach ($requiredColumns as $col => $desc) {
        if (!in_array($col, $foundColumns)) {
            $missing[$col] = $desc;
        }
    }
    
    if (!empty($missing)) {
        echo "<h2 class='error'>Missing Columns</h2>";
        echo "<p>The following required columns are missing:</p>";
        echo "<ul>";
        foreach ($missing as $col => $desc) {
            echo "<li class='error'><strong>{$col}</strong> - {$desc}</li>";
        }
        echo "</ul>";
        echo "<p><a href='auth.php?action=debug-db' class='btn'>Run Auto-Migration</a></p>";
    } else {
        echo "<h2 class='success'>✓ All required columns present!</h2>";
    }
    
    // Show user count
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(is_verified) as verified FROM users");
    $stats = $stmt->fetch();
    echo "<h2>User Statistics</h2>";
    echo "<p>Total users: <strong>{$stats['total']}</strong></p>";
    echo "<p>Verified users: <strong>" . ($stats['verified'] ?? 0) . "</strong></p>";
    
    // Show sample users (without sensitive data)
    $stmt = $db->query("SELECT id, username, email, is_admin, is_verified, created_at FROM users LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (!empty($users)) {
        echo "<h2>Recent Users</h2>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Admin</th><th>Verified</th><th>Created</th></tr>";
        foreach ($users as $user) {
            $verified = isset($user['is_verified']) ? ($user['is_verified'] ? '✓' : '✗') : 'N/A';
            $verifiedClass = $verified === '✓' ? 'success' : ($verified === '✗' ? 'error' : 'warning');
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>" . ($user['is_admin'] ? '✓' : '✗') . "</td>";
            echo "<td class='{$verifiedClass}'>{$verified}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<h2>Quick Actions</h2>
<p>
    <a href="auth.php?action=debug-db" class="btn">Check DB Schema (JSON)</a>
    <a href="../database/run_migration.php" class="btn">Run Migration Script</a>
</p>

<h2>Config Status</h2>
<ul>
    <li>Email Enabled: <strong class="<?= EMAIL_ENABLED ? 'success' : 'warning' ?>"><?= EMAIL_ENABLED ? 'Yes' : 'No (Development Mode)' ?></strong></li>
    <li>Verification Expiry: <strong><?= VERIFICATION_EXPIRY_MINUTES ?> minutes</strong></li>
    <li>Download Cooldown: <strong><?= DOWNLOAD_COOLDOWN_HOURS ?> hours</strong></li>
</ul>

<?php if (!EMAIL_ENABLED): ?>
<p class="warning">⚠️ Email is disabled. Verification codes will be shown in browser alerts.</p>
<?php endif; ?>

</div>
</body>
</html>
