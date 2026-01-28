# LokAlert - Web Programming Final Project

A dynamic website with **PHP** and **MySQL** backend featuring full **CRUD** operations, **user authentication with email verification**, and **secure download tracking**.

## 📋 Project Overview

LokAlert is a mobile application landing page with a complete authentication system and admin panel for managing:
- **User Registration** with email verification
- **Secure Downloads** with rate limiting and tracking
- APK versions (upload, edit, delete)
- User management (full CRUD with password reset)
- Contact messages (read, mark as read, delete)
- Comprehensive download statistics

## 🔐 Authentication & Verification System

### User Registration Flow
1. User signs up with **email** (required) and optional **name/nickname**
2. A **6-digit verification code** is sent to their email
3. User enters the code to verify their account
4. Once verified, user can download the app

### Security Features
- **Email verification required** before downloads
- **5-minute cooldown** between downloads (prevents abuse)
- **Download tracking** - only counts SUCCESSFUL downloads
- **Password hashing** using PHP's `password_hash()`
- **Session-based authentication**
- **Self-service password reset** via email

### Admin Password Reset Options
- Send reset email to user (self-service link)
- Set temporary password (with optional email notification)

## 🛠️ Technologies Used

| Technology | Purpose |
|------------|---------|
| **HTML5** | Content structure |
| **CSS3** | Styling, modals, responsive design |
| **JavaScript** | SPA-like auth flow, progress tracking |
| **PHP 7.4+** | Server-side logic and CRUD operations |
| **MySQL** | Database management |
| **PHPMailer** | Email service (optional) |

## 📁 Folder Structure

```
LokAlert/
├── index.php              # Main landing page
├── index.html             # Static fallback
├── admin.php              # Admin panel entry
├── admin.html             # Admin dashboard with user management
├── contact.php            # Contact form
├── reset-password.html    # Self-service password reset page
├── css/
│   └── style.css          # Main stylesheet (includes auth forms)
├── js/
│   ├── main.js            # Main JavaScript
│   └── download.js        # Auth flow & download tracking
├── api/                   # REST API endpoints
│   ├── auth.php           # Signup, verify, login, password reset
│   ├── users.php          # Users CRUD + admin functions
│   ├── versions.php       # APK versions CRUD
│   ├── messages.php       # Contact messages CRUD
│   └── downloads.php      # Download tracking with tokens
├── includes/
│   ├── config.php         # Configuration & helper functions
│   └── email_service.php  # Email sending service
├── database/
│   ├── lokalert_db.sql    # Original database schema
│   └── lokalert_db_v2.sql # Updated schema with verification
├── uploads/               # APK file uploads
├── releases/              # Release files
├── screenshots/           # Documentation screenshots
└── README.md              # This file
```

## 🗄️ Database Schema

### Tables

1. **users** - User accounts with verification
   - id, username, email, password (hashed)
   - is_admin, is_verified
   - verification_code, verification_expires
   - reset_token, reset_expires
   - download_count, last_download_at
   - created_at

2. **apk_versions** - APK file versions
   - id, version, filename, file_size
   - release_notes, download_count
   - is_latest, upload_date

3. **download_logs** - Enhanced download tracking
   - id, user_id, version_id
   - ip_address, user_agent
   - download_token, status (started/completed/failed/cancelled)
   - started_at, completed_at

4. **email_logs** - Email sending history
   - id, user_id, email_type
   - recipient_email, subject
   - status, sent_at

5. **contact_messages** - Contact form submissions
   - id, name, email, subject, message
   - is_read, created_at

## ✨ CRUD Operations

### CREATE
- ✅ User signup with email verification
- ✅ Add new APK versions (admin)
- ✅ Submit contact messages
- ✅ Log downloads with status tracking

### READ
- ✅ Display user list with stats (name, verified status, download count)
- ✅ Display APK versions
- ✅ Display contact messages
- ✅ View download logs with success/failure status
- ✅ Admin dashboard with comprehensive stats

### UPDATE
- ✅ Edit user name/email (admin)
- ✅ Reset user passwords (admin)
- ✅ Update download status (completed/failed)
- ✅ Edit APK version details
- ✅ Mark messages as read

### DELETE
- ✅ Delete users (admin)
- ✅ Delete APK versions
- ✅ Delete contact messages

## 🚀 Local Development Setup

### Prerequisites
- XAMPP, WAMP, MAMP, or similar PHP development environment
- PHP 7.4 or higher
- MySQL 5.7 or higher
- (Optional) Composer for PHPMailer

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/LokAlert.git
   ```

2. **Move to web server directory**
   ```bash
   # For XAMPP (Mac)
   cp -r LokAlert /Applications/XAMPP/htdocs/
   
   # For MAMP
   cp -r LokAlert /Applications/MAMP/htdocs/
   ```

3. **Create the database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `lokalert_db`
   - Import `database/lokalert_db_v2.sql` (updated schema)

4. **Configure database connection**
   - Edit `includes/config.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'lokalert_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

5. **Configure email (optional)**
   - Edit `includes/config.php`
   - Set `EMAIL_ENABLED` to `true` for production
   - Configure SMTP settings:
     ```php
     define('SMTP_HOST', 'smtp.gmail.com');
     define('SMTP_USER', 'your-email@gmail.com');
     define('SMTP_PASS', 'your-app-password');
     ```
   - In development mode (`EMAIL_ENABLED = false`), verification codes are shown in alerts

6. **Access the website**
   - Main site: http://localhost/LokAlert/index.php
   - Admin panel: http://localhost/LokAlert/admin.php
   - Password reset: http://localhost/LokAlert/reset-password.html?token=...

### Default Admin Credentials
- **Username:** admin
- **Password:** password

⚠️ **Change these credentials in production!**

## 🔑 API Endpoints

### Authentication (`/api/auth.php`)
| Method | Action | Description |
|--------|--------|-------------|
| POST | `signup` | Register new user |
| POST | `verify` | Verify email with code |
| POST | `resend-code` | Resend verification code |
| POST | `login` | User login |
| POST | `logout` | User logout |
| GET | `check` | Check auth status |
| POST | `forgot-password` | Request password reset |
| POST | `reset-password` | Reset password with token |

### Downloads (`/api/downloads.php`)
| Method | Action | Description |
|--------|--------|-------------|
| POST | `init` | Initialize download (returns token) |
| POST | `progress` | Update download progress |
| POST | `complete` | Mark download as successful |
| POST | `cancel` | Cancel/fail download |
| GET | `check-cooldown` | Check if user can download |
| GET | `latest` | Get latest version info |

### Users (`/api/users.php`) - Admin Only
| Method | Action | Description |
|--------|--------|-------------|
| GET | - | List all users |
| GET | `stats` | Get user statistics |
| PUT | - | Update user |
| DELETE | - | Delete user |
| POST | `reset-password` | Reset user's password |
| POST | `send-reset-email` | Send reset email to user |

## 🌐 InfinityFree Deployment

### Step 1: Create InfinityFree Account
1. Go to [infinityfree.net](https://infinityfree.net)
2. Create a free account
3. Create a new hosting account

### Step 2: Create Database
1. Go to **Control Panel** → **MySQL Databases**
2. Create a new database (note the name: `epiz_XXXXXXXX_lokalert`)
3. Note your database credentials:
   - Host: `sql###.infinityfree.com`
   - Username: `epiz_XXXXXXXX`
   - Password: (your password)

### Step 3: Import Database
1. Go to **Control Panel** → **phpMyAdmin**
2. Select your database
3. Click **Import**
4. Upload `database/lokalert_db_v2.sql`

### Step 4: Update Configuration
Edit `includes/config.php` with InfinityFree credentials:
```php
define('DB_HOST', 'sql###.infinityfree.com');
define('DB_NAME', 'epiz_XXXXXXXX_lokalert');
define('DB_USER', 'epiz_XXXXXXXX');
define('DB_PASS', 'your_password');

// Enable email for production
define('EMAIL_ENABLED', true);
```

### Step 5: Upload Files
1. Go to **Control Panel** → **File Manager**
2. Navigate to `htdocs` folder
3. Upload all project files maintaining folder structure
4. Or use FTP client (FileZilla) with credentials from control panel

### Step 6: Test Deployment
1. Visit your InfinityFree URL
2. Test the authentication flow:
   - Sign up with email
   - Receive verification code
   - Verify account
   - Download app (wait 5 mins for cooldown test)
3. Test admin panel:
   - View registered users (count + names)
   - Reset user passwords
   - Delete/update users

## 📸 Required Screenshots

Place these in the `screenshots/` folder:

1. **signup_form.png** - User registration modal
2. **verification_code.png** - Email verification step
3. **download_progress.png** - Download progress tracking
4. **admin_users.png** - Admin user management panel
5. **password_reset.png** - Password reset flow
6. **crud_create.png** - Adding new APK version
7. **crud_read.png** - Dashboard displaying data
8. **crud_update.png** - Editing a record
9. **crud_delete.png** - Delete confirmation

## 🔗 Submission Links

### GitHub Repository
```
https://github.com/YOUR_USERNAME/LokAlert
```

### InfinityFree Deployed Website
```
http://YOUR_SUBDOMAIN.infinityfreeapp.com
```

## 📝 Features Checklist

### Core Requirements
- [x] HTML - Content structure
- [x] CSS - Layout and responsive design
- [x] JavaScript - Client-side validation and interactivity
- [x] PHP - Server-side logic
- [x] MySQL - Database management
- [x] CREATE - Add records to database
- [x] READ - Display data from database
- [x] UPDATE - Edit existing records
- [x] DELETE - Remove records from database

### Authentication & Security
- [x] User signup with email + optional name
- [x] Email verification (6-digit code)
- [x] Secure password hashing
- [x] Session-based authentication
- [x] Self-service password reset via email
- [x] Admin password reset (temporary or email link)

### Download System
- [x] Verification required before download
- [x] Download tracking with tokens
- [x] Only count SUCCESSFUL downloads
- [x] 5-minute cooldown between downloads
- [x] Progress tracking UI

### Admin Panel
- [x] View registered user count
- [x] View user names (NOT passwords)
- [x] View verification status
- [x] View download counts per user
- [x] Reset user passwords
- [x] Delete users
- [x] Update user information
- [x] Full CRUD operations

## ⚙️ Configuration Options

In `includes/config.php`:

```php
// Email settings
define('EMAIL_ENABLED', false);        // Set true in production
define('VERIFICATION_CODE_LENGTH', 6); // 6-digit codes
define('VERIFICATION_CODE_EXPIRY', 15); // Minutes

// Security settings
define('RESET_TOKEN_EXPIRY', 24);      // Hours
define('DOWNLOAD_COOLDOWN_MINUTES', 5); // Between downloads

// SMTP (when EMAIL_ENABLED = true)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
```

## 👥 Team Members

- Alemana, Onyx Herod
- Mabahin, Ryan
- Adamos, Eurika
- Billones, Gerald
- Crisologo, Terence Joefrey
- Royo, Aenard Ollyer

## 📄 License

This project is created for educational purposes as part of the Web Programming course.

---

**LokAlert** - Arrive Smart. Stay Alert. 📍
