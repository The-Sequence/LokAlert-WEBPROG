<?php
/**
 * LokAlert - Contact Form Handler
 * Processes contact form submissions
 */

require_once 'includes/config.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 10) {
        $error = 'Message must be at least 10 characters long.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success = true;
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - LokAlert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .contact-section {
            padding: 120px 0 80px;
            min-height: 100vh;
        }
        
        .contact-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .contact-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #22d3ee 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .contact-header p {
            color: #94a3b8;
        }
        
        .contact-form {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #94a3b8;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #f8fafc;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            padding-right: 45px;
        }
        
        .form-group select option {
            background: #1e293b;
            color: #f8fafc;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1 0%, #22d3ee 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #22c55e;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 30px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #6366f1;
        }
        
        .required {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav id="navbar">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">
                <span class="logo-icon">📍</span>
                <span class="logo-text">LokAlert</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.html#features">Features</a></li>
                <li><a href="index.html#why">Why LokAlert</a></li>
                <li><a href="index.html#tech">Technology</a></li>
                <li><a href="index.html#team">Team</a></li>
                <li><a href="contact.php" class="nav-download-btn">Contact</a></li>
            </ul>
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <main>
        <section class="contact-section">
            <div class="contact-container">
                <a href="index.html" class="back-link">← Back to Home</a>
                
                <div class="contact-header">
                    <h1>📬 Contact Us</h1>
                    <p>Have questions or feedback? We'd love to hear from you!</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✅ Thank you for your message! We'll get back to you soon.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        ⚠️ <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="contact-form">
                    <form method="POST" action="" id="contactForm">
                        <div class="form-group">
                            <label for="name">Your Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" placeholder="Enter your full name" 
                                   value="<?php echo isset($_POST['name']) && !$success ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" placeholder="your@email.com" 
                                   value="<?php echo isset($_POST['email']) && !$success ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject <span class="required">*</span></label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry' && !$success) ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Feature Request" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feature Request' && !$success) ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="Bug Report" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Bug Report' && !$success) ? 'selected' : ''; ?>>Bug Report</option>
                                <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Partnership' && !$success) ? 'selected' : ''; ?>>Partnership</option>
                                <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other' && !$success) ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your Message <span class="required">*</span></label>
                            <textarea id="message" name="message" placeholder="Write your message here..." required><?php echo isset($_POST['message']) && !$success ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            📤 Send Message
                        </button>
                    </form>
                </div>
                
                <p style="text-align: center; margin-top: 30px; color: #64748b; font-size: 14px;">
                    Or email us directly at <a href="mailto:contact@lokalert.com" style="color: #6366f1;">contact@lokalert.com</a>
                </p>
            </div>
        </section>
    </main>

    <script>
        // Client-side form validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value.trim();
            
            if (!name || !email || !subject || !message) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (message.length < 10) {
                e.preventDefault();
                alert('Message must be at least 10 characters long.');
                return;
            }
        });
    </script>
</body>
</html>
