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
    private function sendEmail($userId, $to, $subject, $htmlBody, $plainBody, $type) {
        // Log the email attempt
        $logId = $this->logEmail($userId, $to, $type, $subject, 'pending');
        
        // If email is disabled, simulate success for development
        if (!EMAIL_ENABLED) {
            $this->updateEmailLog($logId, 'sent', 'Email disabled - simulated send');
            return [
                'success' => true,
                'message' => 'Email simulated (SMTP not configured)',
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
        
        // Try to send with PHPMailer if available
        $phpmailerPath = __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
        if (file_exists($phpmailerPath)) {
            $result = $this->sendWithPHPMailer($logId, $to, $subject, $htmlBody, $plainBody);
            if (!$result['success']) {
                $result['email_failed'] = true;
            }
            return $result;
        }
        
        // Fallback to native mail()
        $result = $this->sendWithNativeMail($logId, $to, $subject, $htmlBody, $plainBody);
        if (!$result['success']) {
            $result['email_failed'] = true;
        }
        return $result;
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer($logId, $to, $subject, $htmlBody, $plainBody) {
        try {
            require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';
            require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
            
            $mail->send();
            
            $this->updateEmailLog($logId, 'sent');
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $error = $mail->ErrorInfo ?? $e->getMessage();
            $this->updateEmailLog($logId, 'failed', $error);
            return ['success' => false, 'error' => 'Failed to send email: ' . $error];
        }
    }
    
    /**
     * Send email using native mail()
     */
    private function sendWithNativeMail($logId, $to, $subject, $htmlBody, $plainBody) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
            'Reply-To: ' . SMTP_FROM_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        if ($result) {
            $this->updateEmailLog($logId, 'sent');
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            $this->updateEmailLog($logId, 'failed', 'Native mail() failed');
            return ['success' => false, 'error' => 'Failed to send email'];
        }
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
