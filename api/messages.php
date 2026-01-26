<?php
/**
 * LokAlert API - Contact Messages CRUD
 * Full CRUD operations for contact message management
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
            getMessage($id);
        } else {
            getMessages();
        }
        break;
    case 'POST':
        createMessage();
        break;
    case 'PUT':
        if ($id) {
            updateMessage($id);
        } else {
            jsonResponse(['error' => 'Message ID required'], 400);
        }
        break;
    case 'DELETE':
        if ($id) {
            deleteMessage($id);
        } else {
            jsonResponse(['error' => 'Message ID required'], 400);
        }
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

/**
 * READ - Get all messages (admin only)
 */
function getMessages() {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
        $messages = $stmt->fetchAll();
        
        jsonResponse($messages);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * READ - Get single message
 */
function getMessage($id) {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        $message = $stmt->fetch();
        
        if (!$message) {
            jsonResponse(['error' => 'Message not found'], 404);
        }
        
        // Mark as read
        $updateStmt = $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $updateStmt->execute([$id]);
        
        jsonResponse($message);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * CREATE - Submit contact message (public)
 */
function createMessage() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Also handle form data
    if (empty($data)) {
        $data = $_POST;
    }
    
    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $subject = sanitize($data['subject'] ?? '');
    $message = sanitize($data['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    if (strlen($message) < 10) {
        jsonResponse(['error' => 'Message must be at least 10 characters'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);
        
        $messageId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Message sent successfully! We will get back to you soon.',
            'id' => $messageId
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * UPDATE - Mark message as read/unread
 */
function updateMessage($id) {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if message exists
        $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        $message = $stmt->fetch();
        
        if (!$message) {
            jsonResponse(['error' => 'Message not found'], 404);
        }
        
        // Update read status
        $is_read = isset($data['is_read']) ? (int)$data['is_read'] : 1;
        
        $stmt = $db->prepare("UPDATE contact_messages SET is_read = ? WHERE id = ?");
        $stmt->execute([$is_read, $id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Message updated successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * DELETE - Remove message
 */
function deleteMessage($id) {
    requireAdmin();
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if message exists
        $stmt = $db->prepare("SELECT id FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Message not found'], 404);
        }
        
        // Delete message
        $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
