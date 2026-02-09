<?php
/**
 * LokAlert - Email Service
 * Handles sending verification codes and password reset emails
 * Uses PHPMailer or native mail() as fallback
 */

require_once __DIR__ . '/config.php';

class EmailService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Send verification code email
     */
    public function sendVerificationCode($userId, $email, $code) {
        $subject = "Your LokAlert Verification Code: " . $code;
        
        $body = $this->getVerificationEmailTemplate($code);
        $plainText = "Your LokAlert verification code is: $code\n\nThis code will expire in " . VERIFICATION_EXPIRY_MINUTES . " minutes.\n\nIf you didn't request this code, please ignore this email.";
        
        return $this->sendEmail($userId, $email, $subject, $body, $plainText, 'verification');
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userId, $email, $token) {
        $resetLink = SITE_URL . "/reset-password.html?token=" . $token;
        $subject = "Reset Your LokAlert Password";
        
        $body = $this->getPasswordResetEmailTemplate($resetLink);
        $plainText = "Click the link below to reset your LokAlert password:\n\n$resetLink\n\nThis link will expire in " . RESET_TOKEN_EXPIRY_HOURS . " hours.\n\nIf you didn't request this, please ignore this email.";
        
        return $this->sendEmail($userId, $email, $subject, $body, $plainText, 'password_reset');
    }
    
    /**
     * Send temporary password email (admin-initiated)
     */
    public function sendTemporaryPasswordEmail($userId, $email, $tempPassword) {
        $subject = "Your LokAlert Temporary Password";
        
        $body = $this->getTempPasswordEmailTemplate($tempPassword);
        $plainText = "Your LokAlert temporary password is: $tempPassword\n\nPlease log in and change your password immediately.\n\nIf you didn't request this, please contact support.";
        
        return $this->sendEmail($userId, $email, $subject, $body, $plainText, 'notification');
    }
    
    /**
     * Core email sending function
     */
    public function sendEmail($userId, $to, $subject, $htmlBody, $plainBody, $type) {
        // Log the email attempt
        $logId = $this->logEmail($userId, $to, $type, $subject, 'pending');
        
        // If email is disabled, simulate success for development
        if (!EMAIL_ENABLED) {
            $this->updateEmailLog($logId, 'sent', 'Email disabled - simulated send');
            return [
                'success' => true,
                'message' => 'Email simulated (development mode)',
                'debug' => true,
                'email_disabled' => true
            ];
        }
        
        // Check if SMTP credentials are configured
        if (empty(SMTP_USER) || empty(SMTP_PASS)) {
            $this->updateEmailLog($logId, 'failed', 'SMTP credentials not configured');
            return [
                'success' => false,
                'error' => 'SMTP not configured',
                'email_failed' => true
            ];
        }
        
        // Send using raw SMTP sockets (works on InfinityFree)
        $result = $this->sendWithRawSMTP($logId, $to, $subject, $htmlBody, $plainBody);
        if (!$result['success']) {
            $result['email_failed'] = true;
        }
        return $result;
    }
    
    /**
     * Send email using raw SMTP sockets (works on InfinityFree)
     */
    private function sendWithRawSMTP($logId, $to, $subject, $htmlBody, $plainBody) {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USER;
        $password = str_replace(' ', '', SMTP_PASS); // Remove spaces from app password
        $from = SMTP_USER; // Gmail requires FROM to match authenticated user
        $fromName = SMTP_FROM_NAME;
        
        try {
            // Connect to SMTP server
            $socket = @fsockopen($host, $port, $errno, $errstr, 30);
            
            if (!$socket) {
                // Try SSL connection as fallback (port 465)
                $socket = @fsockopen('ssl://' . $host, 465, $errno, $errstr, 30);
                if (!$socket) {
                    $this->updateEmailLog($logId, 'failed', "Connection failed: $errstr ($errno)");
                    return ['success' => false, 'error' => "Failed to connect to SMTP: $errstr"];
                }
                $port = 465; // SSL mode, skip STARTTLS
            }
            
            stream_set_timeout($socket, 30);
            
            // Read greeting
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "Invalid greeting: $response");
                return ['success' => false, 'error' => 'Invalid SMTP greeting'];
            }
            
            // EHLO
            $this->smtpSendCommand($socket, "EHLO lokalert.infinityfree.me");
            $response = $this->smtpGetResponse($socket);
            
            // STARTTLS if on port 587
            if ($port == 587) {
                $this->smtpSendCommand($socket, "STARTTLS");
                $response = $this->smtpGetResponse($socket);
                if (substr($response, 0, 3) != '220') {
                    fclose($socket);
                    $this->updateEmailLog($logId, 'failed', "STARTTLS failed: $response");
                    return ['success' => false, 'error' => 'STARTTLS failed'];
                }
                
                // Enable TLS
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    $this->updateEmailLog($logId, 'failed', 'TLS encryption failed');
                    return ['success' => false, 'error' => 'TLS encryption failed'];
                }
                
                // EHLO again after STARTTLS
                $this->smtpSendCommand($socket, "EHLO lokalert.infinityfree.me");
                $response = $this->smtpGetResponse($socket);
            }
            
            // AUTH LOGIN
            $this->smtpSendCommand($socket, "AUTH LOGIN");
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '334') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "AUTH failed: $response");
                return ['success' => false, 'error' => 'AUTH command failed'];
            }
            
            // Username (base64)
            $this->smtpSendCommand($socket, base64_encode($username));
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '334') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "Username rejected: $response");
                return ['success' => false, 'error' => 'Username rejected'];
            }
            
            // Password (base64)
            $this->smtpSendCommand($socket, base64_encode($password));
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '235') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "Auth failed: $response");
                return ['success' => false, 'error' => 'Authentication failed - check app password'];
            }
            
            // MAIL FROM
            $this->smtpSendCommand($socket, "MAIL FROM:<$from>");
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '250') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "MAIL FROM rejected: $response");
                return ['success' => false, 'error' => 'Sender rejected'];
            }
            
            // RCPT TO
            $this->smtpSendCommand($socket, "RCPT TO:<$to>");
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '250' && substr($response, 0, 3) != '251') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "RCPT TO rejected: $response");
                return ['success' => false, 'error' => 'Recipient rejected'];
            }
            
            // DATA
            $this->smtpSendCommand($socket, "DATA");
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '354') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "DATA rejected: $response");
                return ['success' => false, 'error' => 'DATA command rejected'];
            }
            
            // Build email
            $boundary = md5(uniqid(time()));
            $headers = [
                "From: $fromName <$from>",
                "To: $to",
                "Subject: $subject",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"$boundary\"",
                "Date: " . date('r'),
                "Message-ID: <" . md5(uniqid()) . "@lokalert.infinityfree.me>"
            ];
            
            $message = implode("\r\n", $headers) . "\r\n\r\n";
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $message .= $plainBody . "\r\n\r\n";
            $message .= "--$boundary\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            $message .= "--$boundary--\r\n.";
            
            fputs($socket, $message . "\r\n");
            $response = $this->smtpGetResponse($socket);
            if (substr($response, 0, 3) != '250') {
                fclose($socket);
                $this->updateEmailLog($logId, 'failed', "Message rejected: $response");
                return ['success' => false, 'error' => 'Message was rejected'];
            }
            
            // QUIT
            $this->smtpSendCommand($socket, "QUIT");
            fclose($socket);
            
            $this->updateEmailLog($logId, 'sent');
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $this->updateEmailLog($logId, 'failed', $e->getMessage());
            return ['success' => false, 'error' => 'SMTP error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send SMTP command
     */
    private function smtpSendCommand($socket, $command) {
        fputs($socket, $command . "\r\n");
    }
    
    /**
     * Get SMTP response
     */
    private function smtpGetResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] == ' ') break;
        }
        return trim($response);
    }

    /**
     * Log email to database
     */
    private function logEmail($userId, $emailTo, $type, $subject, $status) {
        try {
            // Ensure email_logs table exists
            $this->ensureEmailLogsTable();
            
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (user_id, email_to, email_type, subject, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $emailTo, $type, $subject, $status]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            // Silently continue without logging
            return null;
        }
    }
    
    /**
     * Ensure email_logs table exists
     */
    private function ensureEmailLogsTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `email_logs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NULL,
                    `email_to` VARCHAR(255) NOT NULL,
                    `email_type` VARCHAR(50) NOT NULL,
                    `subject` VARCHAR(255) NOT NULL,
                    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                    `error_message` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // Ignore - table might already exist or we don't have permissions
        }
    }
    
    /**
     * Update email log status
     */
    private function updateEmailLog($logId, $status, $error = null) {
        if (!$logId) return;
        
        try {
            $stmt = $this->db->prepare("
                UPDATE email_logs 
                SET status = ?, error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $error, $logId]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }
    
    /**
     * Email template for verification code
     */
    private function getVerificationEmailTemplate($code) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0f172a;">
            <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 20px; padding: 40px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 40px;">📍</span>
                        <h1 style="color: #f8fafc; margin: 10px 0 5px; font-size: 28px;">LokAlert</h1>
                        <p style="color: #94a3b8; margin: 0;">Email Verification</p>
                    </div>
                    
                    <div style="background: rgba(99, 102, 241, 0.1); border-radius: 16px; padding: 30px; text-align: center; margin-bottom: 30px;">
                        <p style="color: #94a3b8; margin: 0 0 15px; font-size: 14px;">Your verification code is:</p>
                        <div style="font-size: 42px; font-weight: 700; letter-spacing: 8px; background: linear-gradient(135deg, #6366f1 0%, #22d3ee 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">' . $code . '</div>
                    </div>
                    
                    <p style="color: #94a3b8; text-align: center; font-size: 14px; margin-bottom: 20px;">
                        This code will expire in <strong style="color: #f8fafc;">' . VERIFICATION_EXPIRY_MINUTES . ' minutes</strong>.
                    </p>
                    
                    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 30px;">
                        <p style="color: #64748b; font-size: 12px; text-align: center; margin: 0;">
                            If you didn\'t request this code, please ignore this email.<br>
                            © ' . date('Y') . ' LokAlert. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Email template for password reset
     */
    private function getPasswordResetEmailTemplate($resetLink) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0f172a;">
            <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 20px; padding: 40px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 40px;">🔐</span>
                        <h1 style="color: #f8fafc; margin: 10px 0 5px; font-size: 28px;">Password Reset</h1>
                        <p style="color: #94a3b8; margin: 0;">LokAlert Account</p>
                    </div>
                    
                    <p style="color: #94a3b8; text-align: center; margin-bottom: 30px;">
                        Click the button below to reset your password. This link will expire in <strong style="color: #f8fafc;">' . RESET_TOKEN_EXPIRY_HOURS . ' hours</strong>.
                    </p>
                    
                    <div style="text-align: center; margin-bottom: 30px;">
                        <a href="' . $resetLink . '" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #22d3ee 100%); color: white; text-decoration: none; padding: 16px 40px; border-radius: 50px; font-weight: 600; font-size: 16px;">Reset Password</a>
                    </div>
                    
                    <p style="color: #64748b; font-size: 12px; text-align: center; word-break: break-all;">
                        Or copy this link: <br>
                        <span style="color: #6366f1;">' . $resetLink . '</span>
                    </p>
                    
                    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 30px;">
                        <p style="color: #64748b; font-size: 12px; text-align: center; margin: 0;">
                            If you didn\'t request this reset, please ignore this email.<br>
                            © ' . date('Y') . ' LokAlert. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Email template for temporary password
     */
    private function getTempPasswordEmailTemplate($tempPassword) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0f172a;">
            <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
                <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 20px; padding: 40px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <span style="font-size: 40px;">🔑</span>
                        <h1 style="color: #f8fafc; margin: 10px 0 5px; font-size: 28px;">Temporary Password</h1>
                        <p style="color: #94a3b8; margin: 0;">LokAlert Account</p>
                    </div>
                    
                    <p style="color: #94a3b8; text-align: center; margin-bottom: 20px;">
                        An administrator has reset your password. Your temporary password is:
                    </p>
                    
                    <div style="background: rgba(245, 158, 11, 0.1); border-radius: 16px; padding: 20px; text-align: center; margin-bottom: 30px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <div style="font-size: 24px; font-weight: 700; color: #f59e0b; font-family: monospace;">' . $tempPassword . '</div>
                    </div>
                    
                    <p style="color: #ef4444; text-align: center; font-size: 14px; margin-bottom: 20px;">
                        ⚠️ Please log in and change your password immediately!
                    </p>
                    
                    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 30px;">
                        <p style="color: #64748b; font-size: 12px; text-align: center; margin: 0;">
                            If you didn\'t request this, please contact support.<br>
                            © ' . date('Y') . ' LokAlert. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>
