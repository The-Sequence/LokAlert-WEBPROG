<?php
/**
 * LokAlert API - Users CRUD
 * Full CRUD operations for user management
 */

require_once '../includes/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

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
 * READ - Get all users
 */
function getUsers() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("SELECT id, username, email, is_admin, created_at, updated_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        
        jsonResponse($users);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get single user
 */
function getUser($id) {
    requireLogin();
    
    // Users can only view their own profile unless admin
    if (!isAdmin() && $_SESSION['user_id'] != $id) {
        jsonResponse(['error' => 'Access denied'], 403);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, email, is_admin, created_at, updated_at FROM users WHERE id = ?");
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
?>
