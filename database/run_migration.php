<?php
/**
 * LokAlert Database Migration Script
 * Run this once to add verification columns to existing database
 * 
 * Usage: 
 *   - Via browser: http://localhost/LokAlert/database/run_migration.php
 *   - Via CLI: php database/run_migration.php
 */

require_once __DIR__ . '/../includes/config.php';

echo "<pre style='font-family: monospace; background: #1e293b; color: #f8fafc; padding: 20px; border-radius: 10px;'>\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         LokAlert Database Migration v1 -> v2                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check current users table structure
    echo "📊 Checking current database structure...\n\n";
    
    $columns = [];
    $stmt = $db->query("DESCRIBE users");
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
    }
    
    echo "Current columns in users table:\n";
    echo "  " . implode(', ', $columns) . "\n\n";
    
    $migrations = [];
    
    // Check and add is_verified column
    if (!in_array('is_verified', $columns)) {
        $migrations[] = [
            'name' => 'Add is_verified column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0 AFTER `is_admin`"
        ];
    }
    
    // Check and add verification_code column
    if (!in_array('verification_code', $columns)) {
        $migrations[] = [
            'name' => 'Add verification_code column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `verification_code` VARCHAR(6) NULL"
        ];
    }
    
    // Check and add verification_expires column
    if (!in_array('verification_expires', $columns)) {
        $migrations[] = [
            'name' => 'Add verification_expires column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `verification_expires` DATETIME NULL"
        ];
    }
    
    // Check and add reset_token column
    if (!in_array('reset_token', $columns)) {
        $migrations[] = [
            'name' => 'Add reset_token column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) NULL"
        ];
    }
    
    // Check and add reset_expires column
    if (!in_array('reset_expires', $columns)) {
        $migrations[] = [
            'name' => 'Add reset_expires column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `reset_expires` DATETIME NULL"
        ];
    }
    
    // Check and add last_download_at column
    if (!in_array('last_download_at', $columns)) {
        $migrations[] = [
            'name' => 'Add last_download_at column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `last_download_at` DATETIME NULL"
        ];
    }
    
    // Check and add download_count column
    if (!in_array('download_count', $columns)) {
        $migrations[] = [
            'name' => 'Add download_count column',
            'sql' => "ALTER TABLE `users` ADD COLUMN `download_count` INT DEFAULT 0"
        ];
    }
    
    // Run migrations
    if (empty($migrations)) {
        echo "✅ All verification columns already exist! No migration needed.\n\n";
    } else {
        echo "🔧 Running " . count($migrations) . " migrations...\n\n";
        
        foreach ($migrations as $migration) {
            echo "  → " . $migration['name'] . "... ";
            try {
                $db->exec($migration['sql']);
                echo "✅ Done\n";
            } catch (PDOException $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
    }
    
    // Make username nullable (may fail if already nullable)
    echo "🔧 Making username nullable...\n";
    try {
        $db->exec("ALTER TABLE `users` MODIFY COLUMN `username` VARCHAR(50) NULL");
        echo "  ✅ Username column is now nullable\n\n";
    } catch (PDOException $e) {
        echo "  ⚠️ Could not modify username column (may already be nullable)\n\n";
    }
    
    // Mark admin users as verified
    echo "🔧 Marking admin users as verified...\n";
    $stmt = $db->exec("UPDATE `users` SET `is_verified` = 1 WHERE `is_admin` = 1");
    echo "  ✅ Admin users are now verified\n\n";
    
    // Check download_logs table
    echo "📊 Checking download_logs table...\n\n";
    
    $dlColumns = [];
    $stmt = $db->query("DESCRIBE download_logs");
    while ($row = $stmt->fetch()) {
        $dlColumns[] = $row['Field'];
    }
    
    $dlMigrations = [];
    
    if (!in_array('download_token', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add download_token column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `download_token` VARCHAR(64) NULL"
        ];
    }
    
    if (!in_array('status', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add status column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `status` VARCHAR(20) DEFAULT 'started'"
        ];
    }
    
    if (!in_array('file_size', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add file_size column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `file_size` BIGINT DEFAULT 0"
        ];
    }
    
    if (!in_array('bytes_downloaded', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add bytes_downloaded column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `bytes_downloaded` BIGINT DEFAULT 0"
        ];
    }
    
    if (!in_array('started_at', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add started_at column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `started_at` DATETIME NULL"
        ];
    }
    
    if (!in_array('completed_at', $dlColumns)) {
        $dlMigrations[] = [
            'name' => 'Add completed_at column',
            'sql' => "ALTER TABLE `download_logs` ADD COLUMN `completed_at` DATETIME NULL"
        ];
    }
    
    if (!empty($dlMigrations)) {
        echo "🔧 Running " . count($dlMigrations) . " download_logs migrations...\n\n";
        
        foreach ($dlMigrations as $migration) {
            echo "  → " . $migration['name'] . "... ";
            try {
                $db->exec($migration['sql']);
                echo "✅ Done\n";
            } catch (PDOException $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
    }
    
    // Create email_logs table
    echo "🔧 Creating email_logs table (if not exists)...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `email_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `email_to` VARCHAR(100) NOT NULL,
            `email_type` VARCHAR(50) NOT NULL,
            `subject` VARCHAR(200) NOT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✅ email_logs table ready\n\n";
    
    // Final verification
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    MIGRATION COMPLETE                        ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    
    // Show final structure
    echo "📊 Final users table structure:\n\n";
    $stmt = $db->query("DESCRIBE users");
    while ($row = $stmt->fetch()) {
        $nullable = $row['Null'] === 'YES' ? '(nullable)' : '(required)';
        echo "  • {$row['Field']}: {$row['Type']} {$nullable}\n";
    }
    
    echo "\n✅ Migration successful! Verification system is now active.\n";
    echo "\n⚠️  DELETE THIS FILE after running migration for security!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "\nMake sure your database connection is configured in includes/config.php\n";
}

echo "</pre>";
?>
