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
$action = $_GET['action'] ?? '';

// Handle specific actions first
if ($action) {
    switch ($action) {
        case 'upload-to-db':
            handleUploadToDB();
            break;
        case 'download-from-db':
            handleDownloadFromDB();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
    exit;
}

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
        
        // Audit log
        logAudit('CREATE_VERSION', 'apk_versions', $versionId, null, ['version' => $version, 'download_url' => $download_url]);
        
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
        
        // Delete from database inside transaction
        $db->beginTransaction();
        try {
            // Also delete any stored APK chunks
            $stmt = $db->prepare("DELETE FROM apk_file_chunks WHERE version_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM apk_versions WHERE id = ?");
            $stmt->execute([$id]);
            
            // Audit log
            logAudit('DELETE_VERSION', 'apk_versions', $id, ['version' => $version['version']], null);
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'Version deleted successfully'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// APK DATABASE STORAGE
// ============================================================

/**
 * Upload APK file directly to database (chunked LONGBLOB)
 * This bypasses InfinityFree's .apk file deletion by storing in MySQL.
 * 
 * Limitations:
 * - PHP upload_max_filesize (set to 50MB via .htaccess)
 * - MySQL max_allowed_packet (typically 1-4MB)
 * - We chunk the file into 512KB pieces to stay within MySQL limits
 */
function handleUploadToDB() {
    requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    // Check if DB storage is enabled
    $dbStorageEnabled = getSetting('apk_db_storage_enabled', '0');
    if ($dbStorageEnabled !== '1') {
        jsonResponse(['error' => 'APK database storage is not enabled. Enable it in Settings.'], 400);
    }
    
    if (!isset($_FILES['apk']) || $_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        ];
        $errCode = $_FILES['apk']['error'] ?? UPLOAD_ERR_NO_FILE;
        $msg = $errorMessages[$errCode] ?? 'Upload error';
        jsonResponse(['error' => $msg], 400);
    }
    
    $file = $_FILES['apk'];
    $version = sanitize($_POST['version'] ?? '');
    $releaseNotes = sanitize($_POST['release_notes'] ?? '');
    $isLatest = ($_POST['is_latest'] ?? '0') === '1' ? 1 : 0;
    
    if (empty($version)) {
        jsonResponse(['error' => 'Version number is required'], 400);
    }
    
    // Validate it's an APK
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'apk') {
        jsonResponse(['error' => 'Only .apk files are allowed'], 400);
    }
    
    $fileSize = $file['size'];
    $filename = $file['name'];
    $tmpPath = $file['tmp_name'];
    
    // Read the file content
    $fileData = file_get_contents($tmpPath);
    if ($fileData === false) {
        jsonResponse(['error' => 'Failed to read uploaded file'], 500);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if version already exists
        $stmt = $db->prepare("SELECT id FROM apk_versions WHERE version = ?");
        $stmt->execute([$version]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Version already exists'], 400);
        }
        
        $db->beginTransaction();
        try {
            // Unset previous latest if needed
            if ($isLatest) {
                $db->exec("UPDATE apk_versions SET is_latest = 0");
            }
            
            // Create version record
            $downloadUrl = SITE_URL . '/api/versions.php?action=download-from-db&version=' . urlencode($version);
            $stmt = $db->prepare("
                INSERT INTO apk_versions (version, filename, file_size, download_url, release_notes, is_latest, stored_in_db)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$version, $filename, $fileSize, $downloadUrl, $releaseNotes, $isLatest]);
            $versionId = $db->lastInsertId();
            
            // Chunk the file data (512KB chunks to stay within MySQL max_allowed_packet)
            $chunkSize = 512 * 1024; // 512KB
            $totalChunks = max(1, ceil(strlen($fileData) / $chunkSize));
            
            $chunkStmt = $db->prepare("
                INSERT INTO apk_file_chunks (version_id, chunk_index, chunk_data, chunk_size, total_chunks, filename, total_size)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunk = substr($fileData, $i * $chunkSize, $chunkSize);
                $chunkStmt->execute([$versionId, $i, $chunk, strlen($chunk), $totalChunks, $filename, $fileSize]);
            }
            
            logAudit('UPLOAD_APK_TO_DB', 'apk_versions', $versionId, null, [
                'version' => $version,
                'filename' => $filename,
                'file_size' => $fileSize,
                'chunks' => $totalChunks
            ]);
            
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => "APK v{$version} stored in database ({$totalChunks} chunks, " . formatBytes($fileSize) . ")",
                'version_id' => $versionId,
                'chunks' => $totalChunks,
                'download_url' => $downloadUrl
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to store APK in database: ' . $e->getMessage()], 500);
        }
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Download APK file from database (reassembles chunks)
 */
function handleDownloadFromDB() {
    $versionParam = sanitize($_GET['version'] ?? '');
    $idParam = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Find the version
        if ($versionParam) {
            $stmt = $db->prepare("SELECT id, version, filename, file_size FROM apk_versions WHERE version = ? AND stored_in_db = 1");
            $stmt->execute([$versionParam]);
        } elseif ($idParam) {
            $stmt = $db->prepare("SELECT id, version, filename, file_size FROM apk_versions WHERE id = ? AND stored_in_db = 1");
            $stmt->execute([$idParam]);
        } else {
            // Get latest
            $stmt = $db->query("SELECT id, version, filename, file_size FROM apk_versions WHERE stored_in_db = 1 AND is_latest = 1 LIMIT 1");
        }
        
        $version = $stmt->fetch();
        if (!$version) {
            http_response_code(404);
            echo 'APK not found in database';
            exit;
        }
        
        // Retrieve all chunks in order
        $stmt = $db->prepare("
            SELECT chunk_data FROM apk_file_chunks
            WHERE version_id = ?
            ORDER BY chunk_index ASC
        ");
        $stmt->execute([$version['id']]);
        $chunks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($chunks)) {
            http_response_code(404);
            echo 'APK file data not found';
            exit;
        }
        
        // Reassemble
        $fileData = implode('', $chunks);
        
        // Send file
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $version['filename'] . '"');
        header('Content-Length: ' . strlen($fileData));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo $fileData;
        exit;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Database error';
        exit;
    }
}
?>
