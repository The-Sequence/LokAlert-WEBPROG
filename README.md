# LokAlert - Web Programming Final Project

A dynamic website with **PHP** and **MySQL** backend featuring full **CRUD** operations, **user authentication with email verification**, and **secure download tracking** with **GitHub Releases integration**.

**Live Demo:** [https://lokalert.infinityfree.me](https://lokalert.infinityfree.me)

## 📋 Project Overview

LokAlert is a mobile application landing page with a complete authentication system and admin panel for managing:
- **User Registration** with email verification
- **Secure Downloads** with rate limiting and tracking
- **APK hosting via GitHub Releases** (InfinityFree blocks APK files)
- APK versions (upload directly to GitHub, or add URL)
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
- **Credentials separated** in gitignored file

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
| **Raw SMTP** | Email service (works on InfinityFree) |
| **GitHub API** | APK hosting via Releases |

## 📁 Folder Structure

```
LokAlert/
├── index.html             # Main landing page (prioritized)
├── index.php              # PHP version with server-side rendering
├── admin.html             # Admin dashboard with GitHub upload
├── admin.php              # PHP admin panel
├── contact.php            # Contact form
├── reset-password.html    # Self-service password reset page
├── .htaccess              # Apache config (prioritizes .html)
├── .gitignore             # Git ignore rules
├── css/
│   └── style.css          # Main stylesheet
├── js/
│   ├── main.js            # Main JavaScript
│   └── download.js        # Auth flow & download tracking
├── api/                   # REST API endpoints
│   ├── auth.php           # Signup, verify, login, password reset
│   ├── users.php          # Users CRUD + admin functions
│   ├── versions.php       # APK versions CRUD
│   ├── messages.php       # Contact messages CRUD
│   ├── downloads.php      # Download tracking with tokens
│   └── github.php         # GitHub Releases upload
├── includes/
│   ├── config.php         # Configuration & helper functions
│   ├── credentials.php    # Sensitive credentials (GITIGNORED)
│   ├── credentials.example.php  # Template for credentials
│   └── email_service.php  # Raw SMTP email service
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
   - **download_url** (external URL for GitHub/Google Drive)
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
- ✅ Add new APK versions (admin) - via GitHub upload or URL
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

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/The-Sequence/LokAlert-WEBPROG.git
   ```

2. **Move to web server directory**
   ```bash
   # For XAMPP (Mac)
   cp -r LokAlert-WEBPROG /Applications/XAMPP/htdocs/LokAlert
   
   # For MAMP
   cp -r LokAlert-WEBPROG /Applications/MAMP/htdocs/LokAlert
   ```

3. **Create the database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `lokalert_db`
   - Run the SQL commands from the database schema section

4. **Create credentials file**
   - Copy `includes/credentials.example.php` to `includes/credentials.php`
   - Update with your local credentials (or leave empty for development)

5. **Access the website**
   - Main site: http://localhost/LokAlert/
   - Admin panel: http://localhost/LokAlert/admin.html
   - In development mode, verification codes are shown in alerts

### Default Admin Credentials
- **Username:** admin
- **Password:** lokalert2024

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
| POST | `init` | Initialize download (returns token + URL) |
| POST | `complete` | Mark download as successful |
| POST | `cancel` | Cancel/fail download |
| GET | `check-cooldown` | Check if user can download |
| GET | `latest` | Get latest version info |

### GitHub Integration (`/api/github.php`)
| Method | Action | Description |
|--------|--------|-------------|
| GET | `check-token` | Check GitHub configuration |
| POST | `upload` | Upload APK to GitHub Releases |

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

### Important Notes for InfinityFree
- **APK files are blocked** - Use GitHub Releases for hosting
- **PHPMailer is blocked** - Uses raw SMTP sockets instead
- **Credentials must be in credentials.php** - Not committed to git

### Step 1: Create InfinityFree Account
1. Go to [infinityfree.net](https://infinityfree.net)
2. Create a free account
3. Create a new hosting account

### Step 2: Create Database
1. Go to **Control Panel** → **MySQL Databases**
2. Create a new database
3. Note your database credentials:
   - Host: `sql###.infinityfree.com`
   - Username: `if0_XXXXXXXX`
   - Password: (your password)
   - Database: `if0_XXXXXXXX_databasename`

### Step 3: Create credentials.php
Create `includes/credentials.php` with your actual credentials:
```php
<?php
// Production Database Credentials
define('PROD_DB_HOST', 'sql###.infinityfree.com');
define('PROD_DB_NAME', 'if0_XXXXXXXX_databasename');
define('PROD_DB_USER', 'if0_XXXXXXXX');
define('PROD_DB_PASS', 'your_password');

// Email Credentials (Gmail with App Password)
define('PROD_SMTP_USER', 'your-email@gmail.com');
define('PROD_SMTP_PASS', 'your-gmail-app-password');

// GitHub Releases Integration
define('GITHUB_TOKEN', 'ghp_your_personal_access_token');
define('GITHUB_OWNER', 'your-github-username');
define('GITHUB_REPO', 'your-repo-name');
```

### Step 4: Upload Files
1. Go to **Control Panel** → **File Manager**
2. Navigate to `htdocs` folder
3. Upload all project files (including credentials.php)
4. **DO NOT upload credentials.php to GitHub**

### Step 5: Import Database
1. Go to **phpMyAdmin**
2. Select your database
3. Run the table creation SQL (auto-created by API on first access)

### Step 6: Test Deployment
1. Visit your InfinityFree URL
2. Test the authentication flow
3. Test admin panel and GitHub upload

## 🔧 Gmail App Password Setup

1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Enable 2-Factor Authentication
3. Go to [App Passwords](https://myaccount.google.com/apppasswords)
4. Generate a new app password for "Mail"
5. Use this 16-character password in `PROD_SMTP_PASS`

## 📦 GitHub Releases Setup

1. Go to [GitHub Personal Access Tokens](https://github.com/settings/tokens)
2. Generate new token (classic) with `repo` scope
3. Add to `credentials.php`:
   - `GITHUB_TOKEN`: Your personal access token
   - `GITHUB_OWNER`: Your GitHub username
   - `GITHUB_REPO`: Your repository name

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
- [x] Credentials separation (gitignored)

### Download System
- [x] Verification required before download
- [x] Download tracking with tokens
- [x] Only count SUCCESSFUL downloads
- [x] 5-minute cooldown between downloads
- [x] External URL support (GitHub Releases)

### Admin Panel
- [x] View registered user count
- [x] View user names (NOT passwords)
- [x] View verification status
- [x] View download counts per user
- [x] Reset user passwords
- [x] Delete users
- [x] Update user information
- [x] Direct APK upload to GitHub Releases
- [x] Add APK from URL

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
