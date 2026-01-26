<?php
/**
 * LokAlert API - APK Versions CRUD
 * Full CRUD operations for APK version management
 */

require_once '../includes/config.php';

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
 * CREATE - Add new APK version
 */
function createVersion() {
    requireAdmin();
    
    // Check if data comes from form or JSON
    if (!empty($_POST)) {
        $version = sanitize($_POST['version'] ?? '');
        $release_notes = sanitize($_POST['release_notes'] ?? '');
        $is_latest = isset($_POST['is_latest']) ? 1 : 0;
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $version = sanitize($data['version'] ?? '');
        $release_notes = sanitize($data['release_notes'] ?? '');
        $is_latest = isset($data['is_latest']) ? (int)$data['is_latest'] : 0;
    }
    
    // Validation
    if (empty($version)) {
        jsonResponse(['error' => 'Version number is required'], 400);
    }
    
    // Handle file upload
    $filename = '';
    $file_size = 0;
    
    if (isset($_FILES['apk']) && $_FILES['apk']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = UPLOAD_DIR;
        
        // Create upload directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'LokAlert-v' . $version . '.apk';
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['apk']['tmp_name'], $filepath)) {
            $file_size = $_FILES['apk']['size'];
        } else {
            jsonResponse(['error' => 'Failed to upload file'], 500);
        }
    } else {
        // For demo/testing without actual file
        $filename = 'LokAlert-v' . $version . '.apk';
        $file_size = rand(15000000, 20000000); // Random size for demo
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // If this is latest, unset previous latest
        if ($is_latest) {
            $db->exec("UPDATE apk_versions SET is_latest = 0");
        }
        
        $stmt = $db->prepare("INSERT INTO apk_versions (version, filename, file_size, release_notes, is_latest) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$version, $filename, $file_size, $release_notes, $is_latest]);
        
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
        
        // Get version info for file deletion
        $stmt = $db->prepare("SELECT filename FROM apk_versions WHERE id = ?");
        $stmt->execute([$id]);
        $version = $stmt->fetch();
        
        if (!$version) {
            jsonResponse(['error' => 'Version not found'], 404);
        }
        
        // Delete the file if exists
        $filepath = UPLOAD_DIR . $version['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
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
