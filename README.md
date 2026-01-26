# LokAlert - Web Programming Final Project

A dynamic website with **PHP** and **MySQL** backend featuring full **CRUD** (Create, Read, Update, Delete) operations.

## 📋 Project Overview

LokAlert is a mobile application landing page with a complete admin panel for managing:
- APK versions (upload, edit, delete)
- User management (create, edit, delete users)
- Contact messages (read, mark as read, delete)
- Download tracking and statistics

## 🛠️ Technologies Used

| Technology | Purpose |
|------------|---------|
| **HTML5** | Content structure |
| **CSS3** | Styling and responsive design |
| **JavaScript** | Client-side interactivity and validation |
| **PHP** | Server-side logic and CRUD operations |
| **MySQL** | Database management |

## 📁 Folder Structure

```
LokAlert/
├── index.php              # Main landing page (PHP)
├── index.html             # Static version (backup)
├── admin.php              # Admin panel with CRUD
├── contact.php            # Contact form (CREATE operation)
├── css/
│   └── style.css          # Main stylesheet
├── js/
│   ├── main.js            # Main JavaScript
│   └── download.js        # Download modal functionality
├── api/                   # REST API endpoints
│   ├── auth.php           # Authentication (login/register)
│   ├── users.php          # Users CRUD
│   ├── versions.php       # APK versions CRUD
│   ├── messages.php       # Contact messages CRUD
│   └── downloads.php      # Download logging
├── includes/
│   └── config.php         # Database configuration
├── database/
│   └── lokalert_db.sql    # Database export file
├── uploads/               # APK file uploads
├── screenshots/           # Required screenshots
└── README.md              # Documentation
```

## 🗄️ Database Schema

### Tables

1. **users** - User accounts
   - id, username, email, password, is_admin, created_at

2. **apk_versions** - APK file versions
   - id, version, filename, file_size, release_notes, download_count, is_latest, upload_date

3. **download_logs** - Download tracking
   - id, user_id, version_id, ip_address, user_agent, download_date

4. **contact_messages** - Contact form submissions
   - id, name, email, subject, message, is_read, created_at

## ✨ CRUD Operations

### CREATE
- ✅ Add new APK versions (admin.php)
- ✅ Add new users (admin.php)
- ✅ Submit contact messages (contact.php)
- ✅ User registration (api/auth.php)

### READ
- ✅ Display APK versions list
- ✅ Display users list
- ✅ Display contact messages
- ✅ Display download logs
- ✅ View statistics dashboard

### UPDATE
- ✅ Edit APK version details
- ✅ Edit user information
- ✅ Mark messages as read
- ✅ Set version as latest

### DELETE
- ✅ Delete APK versions
- ✅ Delete users
- ✅ Delete contact messages

## 🚀 Local Development Setup

### Prerequisites
- XAMPP, WAMP, MAMP, or similar PHP development environment
- PHP 7.4 or higher
- MySQL 5.7 or higher

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
   - Import `database/lokalert_db.sql`

4. **Configure database connection**
   - Edit `includes/config.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'lokalert_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

5. **Access the website**
   - Main site: http://localhost/LokAlert/index.php
   - Admin panel: http://localhost/LokAlert/admin.php
   - Contact form: http://localhost/LokAlert/contact.php

### Default Admin Credentials
- **Username:** admin
- **Password:** password

⚠️ **Change these credentials in production!**

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
4. Upload `database/lokalert_db.sql`

### Step 4: Update Configuration
Edit `includes/config.php` with InfinityFree credentials:
```php
define('DB_HOST', 'sql###.infinityfree.com');  // Your SQL server
define('DB_NAME', 'epiz_XXXXXXXX_lokalert');    // Your database name
define('DB_USER', 'epiz_XXXXXXXX');             // Your username
define('DB_PASS', 'your_password');              // Your password
```

### Step 5: Upload Files
1. Go to **Control Panel** → **File Manager**
2. Navigate to `htdocs` folder
3. Upload all project files maintaining folder structure
4. Or use FTP client (FileZilla) with credentials from control panel

### Step 6: Test Deployment
1. Visit your InfinityFree URL
2. Test all CRUD operations:
   - Submit contact form
   - Login to admin panel
   - Add/Edit/Delete APK versions
   - Add/Edit/Delete users

## 📸 Required Screenshots

Place these in the `screenshots/` folder:

1. **infinityfree_filemanager.png** - File Manager showing uploaded files
2. **phpmyadmin_tables.png** - Database tables in phpMyAdmin
3. **crud_create.png** - Adding new APK version
4. **crud_read.png** - Dashboard displaying data
5. **crud_update.png** - Editing a record
6. **crud_delete.png** - Delete confirmation

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

- [x] HTML - Content structure
- [x] CSS - Layout and responsive design
- [x] JavaScript - Client-side validation and interactivity
- [x] PHP - Server-side logic
- [x] MySQL - Database management
- [x] CREATE - Add records to database
- [x] READ - Display data from database
- [x] UPDATE - Edit existing records
- [x] DELETE - Remove records from database
- [x] User authentication
- [x] Admin panel
- [x] Contact form
- [x] Download tracking

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
