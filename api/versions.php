<?php
/**
 * LokAlert API - APK Versions CRUD
 * Full CRUD operations for APK version management
 * Supports external download URLs (GitHub Releases, Google Drive, etc.)
 */

require_once '../includes/config.php';

// Auto-migrate database schema
ensureDatabaseMigrated();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            getVersion($id);
        } else {
            getVersions();
        }
        break;
    case 'POST':
        createVersion();
        break;
    case 'PUT':
        if ($id) {
            updateVersion($id);
        } else {
            jsonResponse(['error' => 'Version ID required'], 400);
        }
        break;
    case 'DELETE':
        if ($id) {
            deleteVersion($id);
        } else {
            jsonResponse(['error' => 'Version ID required'], 400);
        }
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

/**
 * Ensure apk_versions table exists with download_url column
 */
function ensureVersionsTable() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Create table if not exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS `apk_versions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `version` VARCHAR(20) NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `file_size` BIGINT DEFAULT 0,
                `download_url` VARCHAR(500) NULL,
                `release_notes` TEXT NULL,
                `is_latest` TINYINT(1) DEFAULT 0,
                `download_count` INT DEFAULT 0,
                `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_is_latest` (`is_latest`),
                INDEX `idx_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Add missing columns for existing tables
        $columnsToAdd = [
            "ALTER TABLE `apk_versions` ADD COLUMN `file_size` BIGINT DEFAULT 0 AFTER `filename`",
            "ALTER TABLE `apk_versions` ADD COLUMN `download_url` VARCHAR(500) NULL AFTER `file_size`",
            "ALTER TABLE `apk_versions` ADD COLUMN `release_notes` TEXT NULL AFTER `download_url`",
            "ALTER TABLE `apk_versions` ADD COLUMN `is_latest` TINYINT(1) DEFAULT 0 AFTER `release_notes`",
            "ALTER TABLE `apk_versions` ADD COLUMN `download_count` INT DEFAULT 0 AFTER `is_latest`",
            "ALTER TABLE `apk_versions` ADD COLUMN `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `download_count`"
        ];
        
        foreach ($columnsToAdd as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Column already exists, ignore
            }
        }
        
    } catch (PDOException $e) {
        // Ignore - best effort
    }
}

/**
 * READ - Get all versions
 */
function getVersions() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("SELECT * FROM apk_versions ORDER BY upload_date DESC");
        $versions = $stmt->fetchAll();
        
        jsonResponse($versions);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get single version or latest
 */
function getVersion($id) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($id === 'latest' || $id === 0) {
            $stmt = $db->query("SELECT * FROM apk_versions WHERE is_latest = 1 LIMIT 1");
        } else {
            $stmt = $db->prepare("SELECT * FROM apk_versions WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        $version = $stmt->fetch();
        
        if (!$version) {
            jsonResponse(['error' => 'Version not found'], 404);
        }
        
        jsonResponse($version);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * CREATE - Add new APK version with external download URL
 */
function createVersion() {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $version = sanitize($data['version'] ?? '');
    $download_url = sanitize($data['download_url'] ?? '');
    $release_notes = sanitize($data['release_notes'] ?? '');
    $file_size = isset($data['file_size']) ? (int)$data['file_size'] : 0;
    $is_latest = isset($data['is_latest']) ? (int)$data['is_latest'] : 0;
    
    // Validation
    if (empty($version)) {
        jsonResponse(['error' => 'Version number is required'], 400);
    }
    
    if (empty($download_url)) {
        jsonResponse(['error' => 'Download URL is required (GitHub Releases, Google Drive, etc.)'], 400);
    }
    
    // Validate URL format
    if (!filter_var($download_url, FILTER_VALIDATE_URL)) {
        jsonResponse(['error' => 'Invalid download URL format'], 400);
    }
    
    $filename = 'LokAlert-v' . $version . '.apk';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if version already exists
        $stmt = $db->prepare("SELECT id FROM apk_versions WHERE version = ?");
        $stmt->execute([$version]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Version already exists'], 400);
        }
        
        // If this is latest, unset previous latest
        if ($is_latest) {
            $db->exec("UPDATE apk_versions SET is_latest = 0");
        }
        
        $stmt = $db->prepare("
            INSERT INTO apk_versions (version, filename, file_size, download_url, release_notes, is_latest) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$version, $filename, $file_size, $download_url, $release_notes, $is_latest]);
        
        $versionId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'APK version created successfully',
            'id' => $versionId
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * UPDATE - Modify APK version
 */
function updateVersion($id) {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if version exists
        $stmt = $db->prepare("SELECT * FROM apk_versions WHERE id = ?");
        $stmt->execute([$id]);
        $version = $stmt->fetch();
        
        if (!$version) {
            jsonResponse(['error' => 'Version not found'], 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (isset($data['version'])) {
            $updates[] = "version = ?";
            $params[] = sanitize($data['version']);
        }
        
        if (isset($data['download_url'])) {
            if (!empty($data['download_url']) && !filter_var($data['download_url'], FILTER_VALIDATE_URL)) {
                jsonResponse(['error' => 'Invalid download URL format'], 400);
            }
            $updates[] = "download_url = ?";
            $params[] = sanitize($data['download_url']);
        }
        
        if (isset($data['file_size'])) {
            $updates[] = "file_size = ?";
            $params[] = (int)$data['file_size'];
        }
        
        if (isset($data['release_notes'])) {
            $updates[] = "release_notes = ?";
            $params[] = sanitize($data['release_notes']);
        }
        
        if (isset($data['is_latest'])) {
            // If setting as latest, unset previous latest first
            if ($data['is_latest']) {
                $db->exec("UPDATE apk_versions SET is_latest = 0");
            }
            $updates[] = "is_latest = ?";
            $params[] = (int)$data['is_latest'];
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE apk_versions SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true,
            'message' => 'Version updated successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * DELETE - Remove APK version
 */
function deleteVersion($id) {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if version exists
        $stmt = $db->prepare("SELECT * FROM apk_versions WHERE id = ?");
        $stmt->execute([$id]);
        $version = $stmt->fetch();
        
        if (!$version) {
            jsonResponse(['error' => 'Version not found'], 404);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM apk_versions WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Version deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
