<?php
/**
 * LokAlert API - GitHub Releases Integration
 * Handles APK upload and automatic GitHub Release publishing
 */

require_once '../includes/config.php';

// Auto-migrate database schema
ensureDatabaseMigrated();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$action = $_GET['action'] ?? 'upload';

// check-token is a GET request, others require POST
if ($action === 'check-token') {
    checkGitHubToken();
    exit;
}

// Only allow POST requests for other actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Require admin authentication
requireAdmin();

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Check if GitHub token is configured
 */
function checkGitHubToken() {
    try {
        $hasToken = defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN);
        $hasOwner = defined('GITHUB_OWNER') && !empty(GITHUB_OWNER);
        $hasRepo = defined('GITHUB_REPO') && !empty(GITHUB_REPO);
        
        jsonResponse([
            'configured' => $hasToken && $hasOwner && $hasRepo,
            'has_token' => $hasToken,
            'has_owner' => $hasOwner,
            'has_repo' => $hasRepo,
            'owner' => $hasOwner ? GITHUB_OWNER : null,
            'repo' => $hasRepo ? GITHUB_REPO : null
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'configured' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle APK upload and publish to GitHub Releases
 */
function handleUpload() {
    // Verify GitHub credentials are configured
    if (!defined('GITHUB_TOKEN') || empty(GITHUB_TOKEN)) {
        jsonResponse(['error' => 'GitHub token not configured. Add GITHUB_TOKEN to credentials.php'], 400);
    }
    
    if (!defined('GITHUB_OWNER') || !defined('GITHUB_REPO')) {
        jsonResponse(['error' => 'GitHub owner/repo not configured'], 400);
    }
    
    // Check for uploaded file
    if (!isset($_FILES['apk']) || $_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = isset($_FILES['apk']) ? getUploadErrorMessage($_FILES['apk']['error']) : 'No file uploaded';
        jsonResponse(['error' => 'File upload failed: ' . $errorMsg], 400);
    }
    
    $file = $_FILES['apk'];
    $version = sanitize($_POST['version'] ?? '');
    $releaseNotes = $_POST['release_notes'] ?? '';
    $isLatest = isset($_POST['is_latest']) && $_POST['is_latest'] === '1';
    
    // Validation
    if (empty($version)) {
        jsonResponse(['error' => 'Version number is required'], 400);
    }
    
    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
        jsonResponse(['error' => 'Invalid version format. Use X.Y or X.Y.Z'], 400);
    }
    
    // Validate file
    $allowedTypes = ['application/vnd.android.package-archive', 'application/octet-stream'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($extension !== 'apk') {
        jsonResponse(['error' => 'Only APK files are allowed'], 400);
    }
    
    // Read file content
    $fileContent = file_get_contents($file['tmp_name']);
    $fileSize = $file['size'];
    $filename = "LokAlert-v{$version}.apk";
    
    // 1. Create GitHub Release
    $tagName = "v{$version}";
    $releaseName = "LokAlert v{$version}";
    
    $releaseData = [
        'tag_name' => $tagName,
        'name' => $releaseName,
        'body' => $releaseNotes ?: "Release v{$version}",
        'draft' => false,
        'prerelease' => false
    ];
    
    $createResult = githubApiCall(
        "https://api.github.com/repos/" . GITHUB_OWNER . "/" . GITHUB_REPO . "/releases",
        'POST',
        $releaseData
    );
    
    if (!$createResult['success']) {
        // Check if release already exists
        if (strpos($createResult['error'], 'already_exists') !== false) {
            jsonResponse(['error' => "Release v{$version} already exists on GitHub"], 400);
        }
        jsonResponse(['error' => 'Failed to create GitHub release: ' . $createResult['error']], 500);
    }
    
    $release = $createResult['data'];
    $releaseId = $release['id'];
    $uploadUrl = str_replace('{?name,label}', '', $release['upload_url']);
    
    // 2. Upload APK as release asset
    $uploadResult = uploadAssetToGitHub($uploadUrl, $filename, $fileContent, $fileSize);
    
    if (!$uploadResult['success']) {
        // Try to delete the release since upload failed
        githubApiCall(
            "https://api.github.com/repos/" . GITHUB_OWNER . "/" . GITHUB_REPO . "/releases/{$releaseId}",
            'DELETE'
        );
        jsonResponse(['error' => 'Failed to upload APK: ' . $uploadResult['error']], 500);
    }
    
    $asset = $uploadResult['data'];
    $downloadUrl = $asset['browser_download_url'];
    
    // 3. Save to database
    try {
        $db = Database::getInstance()->getConnection();
        
        // If this is latest, unset previous latest
        if ($isLatest) {
            $db->exec("UPDATE apk_versions SET is_latest = 0");
        }
        
        $stmt = $db->prepare("
            INSERT INTO apk_versions (version, filename, file_size, download_url, release_notes, is_latest) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$version, $filename, $fileSize, $downloadUrl, $releaseNotes, $isLatest ? 1 : 0]);
        
        $versionId = $db->lastInsertId();
        
    } catch (PDOException $e) {
        // Database error, but release was created successfully
        jsonResponse([
            'success' => true,
            'warning' => 'Release created on GitHub but database save failed',
            'download_url' => $downloadUrl,
            'github_error' => false,
            'db_error' => $e->getMessage()
        ]);
    }
    
    jsonResponse([
        'success' => true,
        'message' => "APK v{$version} published to GitHub Releases!",
        'version_id' => $versionId,
        'download_url' => $downloadUrl,
        'release_url' => $release['html_url'],
        'file_size' => $fileSize,
        'file_size_formatted' => formatBytes($fileSize)
    ]);
}

/**
 * Make GitHub API call
 */
function githubApiCall($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . GITHUB_TOKEN,
        'User-Agent: LokAlert-Admin',
        'X-GitHub-Api-Version: 2022-11-28'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $responseData];
    }
    
    $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'HTTP ' . $httpCode;
    if (isset($responseData['errors'])) {
        $errorMsg .= ': ' . json_encode($responseData['errors']);
    }
    
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Upload asset to GitHub Release
 */
function uploadAssetToGitHub($uploadUrl, $filename, $fileContent, $fileSize) {
    $url = $uploadUrl . '?name=' . urlencode($filename);
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . GITHUB_TOKEN,
            'User-Agent: LokAlert-Admin',
            'Content-Type: application/vnd.android.package-archive',
            'Content-Length: ' . $fileSize,
            'X-GitHub-Api-Version: 2022-11-28'
        ],
        CURLOPT_TIMEOUT => 300  // 5 minutes for large files
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 201) {
        return ['success' => true, 'data' => $responseData];
    }
    
    $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'HTTP ' . $httpCode;
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    return $errors[$errorCode] ?? 'Unknown error';
}
