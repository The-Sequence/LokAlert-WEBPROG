<?php
/**
 * LokAlert API - Authentication
 * Handles user signup, login, email verification, and password reset
 */

require_once '../includes/config.php';
require_once '../includes/email_service.php';

// Auto-migrate database schema
ensureDatabaseMigrated();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['status' => 'ok']);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'signup':
        handleSignup();
        break;
    case 'verify':
        handleVerification();
        break;
    case 'resend-code':
        handleResendCode();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkAuth();
        break;
    case 'forgot-password':
        handleForgotPassword();
        break;
    case 'reset-password':
        handleResetPassword();
        break;
    case 'change-password':
        handleChangePassword();
        break;
    case 'debug-db':
        debugDatabase();
        break;
    // Legacy support
    case 'register':
        handleSignup();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

/**
 * Debug function to check database structure
 */
function debugDatabase() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get users table structure
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        $columnNames = array_column($columns, 'Field');
        
        $requiredColumns = ['is_verified', 'verification_code', 'verification_expires', 'last_download_at', 'download_count'];
        $missingColumns = array_diff($requiredColumns, $columnNames);
        
        jsonResponse([
            'database_ok' => empty($missingColumns),
            'current_columns' => $columnNames,
            'missing_columns' => array_values($missingColumns),
            'message' => empty($missingColumns) 
                ? 'Database structure is correct!' 
                : 'MISSING COLUMNS! Run database/run_migration.php to fix.',
            'migration_url' => SITE_URL . '/database/run_migration.php'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Check and auto-migrate database if needed
 */
function ensureDatabaseSchema($db) {
    try {
        $stmt = $db->query("DESCRIBE users");
        $columns = array_column($stmt->fetchAll(), 'Field');
        
        $migrations = [];
        
        if (!in_array('is_verified', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0";
        }
        if (!in_array('verification_code', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `verification_code` VARCHAR(6) NULL";
        }
        if (!in_array('verification_expires', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `verification_expires` DATETIME NULL";
        }
        if (!in_array('reset_token', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) NULL";
        }
        if (!in_array('reset_expires', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `reset_expires` DATETIME NULL";
        }
        if (!in_array('last_download_at', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `last_download_at` DATETIME NULL";
        }
        if (!in_array('download_count', $columns)) {
            $migrations[] = "ALTER TABLE `users` ADD COLUMN `download_count` INT DEFAULT 0";
        }
        
        foreach ($migrations as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
        }
        
        // Make username nullable if it's currently NOT NULL
        try {
            $db->exec("ALTER TABLE `users` MODIFY COLUMN `username` VARCHAR(50) NULL");
        } catch (PDOException $e) {
            // Ignore errors
        }
        
        // Mark admin as verified
        try {
            $db->exec("UPDATE `users` SET `is_verified` = 1 WHERE `is_admin` = 1");
        } catch (PDOException $e) {
            // Ignore errors
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Handle user signup with email verification
 */
function handleSignup() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = isset($data['name']) ? sanitize($data['name']) : null;
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    // Validation
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    if (empty($password)) {
        jsonResponse(['error' => 'Password is required'], 400);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    // Name validation (optional but if provided, check length)
    if ($name !== null && $name !== '' && strlen($name) < 2) {
        jsonResponse(['error' => 'Name must be at least 2 characters'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Auto-migrate database schema if columns are missing
        ensureDatabaseSchema($db);
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            if ($existingUser['is_verified']) {
                jsonResponse(['error' => 'Email already registered'], 400);
            } else {
                // User exists but not verified - update and resend code
                $verificationCode = generateVerificationCode();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_EXPIRY_MINUTES . ' minutes'));
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET username = ?, password = ?, verification_code = ?, verification_expires = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $hashedPassword, $verificationCode, $expiresAt, $existingUser['id']]);
                
                // Send verification email
                $emailService = new EmailService();
                $emailResult = $emailService->sendVerificationCode($existingUser['id'], $email, $verificationCode);
                
                // Include code if email is disabled OR if email sending failed
                $includeCode = !EMAIL_ENABLED || !empty($emailResult['email_failed']) || !empty($emailResult['email_disabled']);
                
                jsonResponse([
                    'success' => true,
                    'message' => $includeCode ? 'Your verification code is shown below' : 'Verification code sent to your email',
                    'requires_verification' => true,
                    'user_id' => $existingUser['id'],
                    'email' => $email,
                    'debug_code' => $includeCode ? $verificationCode : null,
                    'email_sent' => !$includeCode
                ]);
            }
        }
        
        // Create new user
        $verificationCode = generateVerificationCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_EXPIRY_MINUTES . ' minutes'));
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, verification_code, verification_expires, is_verified)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$name, $email, $hashedPassword, $verificationCode, $expiresAt]);
        
        $userId = $db->lastInsertId();
        
        // Send verification email
        $emailService = new EmailService();
        $emailResult = $emailService->sendVerificationCode($userId, $email, $verificationCode);
        
        // Include code if email is disabled OR if email sending failed
        $includeCode = !EMAIL_ENABLED || !empty($emailResult['email_failed']) || !empty($emailResult['email_disabled']);
        
        jsonResponse([
            'success' => true,
            'message' => $includeCode ? 'Account created! Your verification code is shown below.' : 'Account created! Please check your email for verification code.',
            'requires_verification' => true,
            'user_id' => $userId,
            'email' => $email,
            'debug_code' => $includeCode ? $verificationCode : null,
            'email_sent' => !$includeCode
        ], 201);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle email verification
 */
function handleVerification() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize($data['email'] ?? '');
    $code = sanitize($data['code'] ?? '');
    
    if (empty($email) || empty($code)) {
        jsonResponse(['error' => 'Email and verification code are required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Auto-migrate database schema if columns are missing
        ensureDatabaseSchema($db);
        
        $stmt = $db->prepare("
            SELECT id, username, email, is_admin, verification_code, verification_expires 
            FROM users 
            WHERE email = ? AND is_verified = 0
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'Invalid email or already verified'], 400);
        }
        
        // Check if code matches
        if ($user['verification_code'] !== $code) {
            jsonResponse(['error' => 'Invalid verification code'], 400);
        }
        
        // Check if code expired
        if (strtotime($user['verification_expires']) < time()) {
            jsonResponse(['error' => 'Verification code has expired. Please request a new one.'], 400);
        }
        
        // Verify the user
        $stmt = $db->prepare("
            UPDATE users 
            SET is_verified = 1, verification_code = NULL, verification_expires = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Auto-login after verification
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['is_verified'] = 1;
        
        jsonResponse([
            'success' => true,
            'message' => 'Email verified successfully! You can now download.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin'],
                'is_verified' => true
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle resending verification code
 */
function handleResendCode() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize($data['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Auto-migrate database schema if columns are missing
        ensureDatabaseSchema($db);
        
        $stmt = $db->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'Email not found'], 404);
        }
        
        if ($user['is_verified']) {
            jsonResponse(['error' => 'Email already verified'], 400);
        }
        
        // Generate new code
        $verificationCode = generateVerificationCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_EXPIRY_MINUTES . ' minutes'));
        
        $stmt = $db->prepare("
            UPDATE users 
            SET verification_code = ?, verification_expires = ?
            WHERE id = ?
        ");
        $stmt->execute([$verificationCode, $expiresAt, $user['id']]);
        
        // Send verification email
        $emailService = new EmailService();
        $emailResult = $emailService->sendVerificationCode($user['id'], $email, $verificationCode);
        
        // Include code if email is disabled OR if email sending failed
        $includeCode = !EMAIL_ENABLED || !empty($emailResult['email_failed']) || !empty($emailResult['email_disabled']);
        
        jsonResponse([
            'success' => true,
            'message' => $includeCode ? 'Your new verification code is shown below' : 'New verification code sent to your email',
            'debug_code' => $includeCode ? $verificationCode : null,
            'email_sent' => !$includeCode
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize($data['email'] ?? $data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Auto-migrate database schema if columns are missing
        ensureDatabaseSchema($db);
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['error' => 'Invalid email or password'], 401);
        }
        
        // Check if is_verified column exists in result, default to 0 if not
        $isVerified = isset($user['is_verified']) ? $user['is_verified'] : 0;
        
        // Check if user is verified (admins are auto-verified)
        if (!$isVerified && !$user['is_admin']) {
            // Generate new verification code
            $verificationCode = generateVerificationCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_EXPIRY_MINUTES . ' minutes'));
            
            $stmt = $db->prepare("
                UPDATE users 
                SET verification_code = ?, verification_expires = ?
                WHERE id = ?
            ");
            $stmt->execute([$verificationCode, $expiresAt, $user['id']]);
            
            // Send verification email
            $emailService = new EmailService();
            $emailResult = $emailService->sendVerificationCode($user['id'], $user['email'], $verificationCode);
            
            // Include code if email is disabled OR if email sending failed
            $includeCode = !EMAIL_ENABLED || !empty($emailResult['email_failed']) || !empty($emailResult['email_disabled']);
            
            jsonResponse([
                'success' => false,
                'requires_verification' => true,
                'message' => $includeCode ? 'Please verify your email. Your code is shown below.' : 'Please verify your email first. A new code has been sent.',
                'email' => $user['email'],
                'debug_code' => $includeCode ? $verificationCode : null,
                'email_sent' => !$includeCode
            ], 403);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['is_verified'] = $user['is_verified'];
        
        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin'],
                'is_verified' => (bool)$user['is_verified']
            ]
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle logout
 */
function handleLogout() {
    session_destroy();
    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Check authentication status
 */
function checkAuth() {
    if (isLoggedIn()) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Auto-migrate database schema if columns are missing
            ensureDatabaseSchema($db);
            
            $stmt = $db->prepare("SELECT id, username, email, is_admin, is_verified, last_download_at FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                session_destroy();
                jsonResponse(['authenticated' => false], 401);
            }
            
            $canDownload = canUserDownload($user['id']);
            $cooldownRemaining = getTimeUntilNextDownload($user['id']);
            
            jsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'is_admin' => (bool)$user['is_admin'],
                    'is_verified' => (bool)$user['is_verified'],
                    'can_download' => $canDownload,
                    'cooldown_remaining' => $cooldownRemaining
                ]
            ]);
        } catch (PDOException $e) {
            jsonResponse(['authenticated' => false, 'error' => $e->getMessage()], 500);
        }
    } else {
        jsonResponse(['authenticated' => false]);
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitize($data['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Always return success to prevent email enumeration
        if (!$user) {
            jsonResponse([
                'success' => true,
                'message' => 'If the email exists, a password reset link has been sent.'
            ]);
        }
        
        // Generate reset token
        $resetToken = generateResetToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY_HOURS . ' hours'));
        
        $stmt = $db->prepare("
            UPDATE users 
            SET reset_token = ?, reset_expires = ?
            WHERE id = ?
        ");
        $stmt->execute([$resetToken, $expiresAt, $user['id']]);
        
        // Send reset email
        $emailService = new EmailService();
        $emailResult = $emailService->sendPasswordResetEmail($user['id'], $email, $resetToken);
        
        jsonResponse([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.',
            'debug_token' => EMAIL_ENABLED ? null : $resetToken
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle password reset with token
 */
function handleResetPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = sanitize($data['token'] ?? '');
    $newPassword = $data['password'] ?? '';
    
    if (empty($token) || empty($newPassword)) {
        jsonResponse(['error' => 'Token and new password are required'], 400);
    }
    
    if (strlen($newPassword) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT id, email 
            FROM users 
            WHERE reset_token = ? AND reset_expires > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'Invalid or expired reset token'], 400);
        }
        
        // Update password and clear reset token
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_expires = NULL, is_verified = 1
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Password reset successful. You can now log in with your new password.'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle password change for logged-in users
 */
function handleChangePassword() {
    requireLogin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        jsonResponse(['error' => 'Current and new passwords are required'], 400);
    }
    
    if (strlen($newPassword) < 6) {
        jsonResponse(['error' => 'New password must be at least 6 characters'], 400);
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            jsonResponse(['error' => 'Current password is incorrect'], 400);
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
        
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
?>
