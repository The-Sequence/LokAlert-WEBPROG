<?php
/**
 * LokAlert - Database Backup API Endpoint
 * 
 * Creates a SQL dump of the entire lokalert_db database.
 * Only accessible to authenticated admin users.
 * 
 * Usage:
 *   GET /api/backup.php           → downloads .sql file
 *   GET /api/backup.php?preview=1 → shows SQL in browser (JSON)
 */

require_once '../includes/config.php';

// Ensure database is migrated
ensureDatabaseMigrated();

// Require admin authentication
if (!isAdmin()) {
    jsonResponse(['error' => 'Access denied. Admin authentication required.'], 403);
}

/**
 * Generate a full SQL dump of the database using PDO queries
 * (No shell access required — works on shared hosting like InfinityFree)
 */
function generateDatabaseBackup() {
    $db = Database::getInstance()->getConnection();
    $output = [];

    $output[] = '-- ============================================================';
    $output[] = '-- LokAlert Database Backup';
    $output[] = '-- Generated: ' . date('Y-m-d H:i:s');
    $output[] = '-- DBMS: MySQL / MariaDB';
    $output[] = '-- Database: ' . DB_NAME;
    $output[] = '-- ============================================================';
    $output[] = '';
    $output[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $output[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
    $output[] = '';

    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $output[] = '-- -----------------------------------------------------------';
        $output[] = "-- Table structure for `{$table}`";
        $output[] = '-- -----------------------------------------------------------';
        $output[] = "DROP TABLE IF EXISTS `{$table}`;";

        // Get CREATE TABLE statement
        $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $output[] = $createStmt['Create Table'] . ';';
        $output[] = '';

        // Get table data
        $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $output[] = "-- Data for `{$table}` (" . count($rows) . " rows)";
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $db->quote($value);
                    }
                }
                $output[] = "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");";
            }
            $output[] = '';
        }
    }

    $output[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    $output[] = '';
    $output[] = '-- End of backup';

    return implode("\n", $output);
}

// ---- Main Execution ----

try {
    $backupSql = generateDatabaseBackup();
    $filename  = 'lokalert_backup_' . date('Y-m-d_His') . '.sql';

    // Log the backup action in audit_logs
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, ip_address, created_at)
            VALUES (?, 'DATABASE_BACKUP', 'ALL', ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'] ?? null, getClientIP()]);
    } catch (PDOException $e) {
        // Audit log failure should not prevent backup
    }

    // Preview mode — return JSON with success status
    if (isset($_GET['preview'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Backup SQL generated successfully.',
            'filename' => $filename,
            'size' => strlen($backupSql),
            'sql' => $backupSql
        ]);
    } else {
        // Download mode
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($backupSql));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $backupSql;
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Backup failed: ' . $e->getMessage()
    ]);
}
