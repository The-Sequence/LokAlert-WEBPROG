# LokAlert Deployment Guide - InfinityFree + Email Verification

## 🔴 Current Issue: Network Error on Localhost

Your local web server (MAMP/XAMPP) is **not running**. 

### Quick Fix for Localhost:
1. Open **MAMP** or **XAMPP**
2. Click **Start Servers** (Apache + MySQL)
3. Access: `http://localhost/LokAlert/`

---

## 📧 Email Verification Setup Guide

### Option A: Gmail SMTP (Recommended for Testing)

1. **Enable 2-Factor Authentication** on your Gmail account
2. Go to: https://myaccount.google.com/apppasswords
3. Create an **App Password** (select "Mail" and "Mac")
4. Copy the 16-character password

### Option B: Free Email Services for Production

| Service | Free Tier | Best For |
|---------|-----------|----------|
| **Brevo (Sendinblue)** | 300 emails/day | Production |
| **Mailjet** | 200 emails/day | Production |
| **Gmail SMTP** | 500 emails/day | Testing |

---

## 🚀 InfinityFree Deployment Steps

### Step 1: Create InfinityFree Account
1. Go to https://infinityfree.com
2. Sign up for free account
3. Create a new hosting account

### Step 2: Create MySQL Database
1. In InfinityFree control panel, go to **MySQL Databases**
2. Create a new database (note the name: `epiz_XXXXXXXX_lokalert`)
3. Note your credentials:
   - **Host**: `sql###.infinityfree.com` (shown in panel)
   - **Username**: `epiz_XXXXXXXX`
   - **Password**: (the one you set)
   - **Database**: `epiz_XXXXXXXX_lokalert`

### Step 3: Import Database Schema
1. Go to **phpMyAdmin** in control panel
2. Select your database
3. Click **Import**
4. Upload `database/lokalert_db_v2.sql`

### Step 4: Update Configuration

Edit `includes/config.php` with your InfinityFree credentials:

```php
// Production Database (InfinityFree)
if ($isProduction) {
    define('DB_HOST', 'sql309.infinityfree.com');     // Your actual SQL host
    define('DB_NAME', 'epiz_12345678_lokalert');      // Your actual DB name
    define('DB_USER', 'epiz_12345678');               // Your actual username
    define('DB_PASS', 'YourActualPassword');          // Your actual password
}

// Email Configuration
define('SMTP_USER', 'your-email@gmail.com');          // Your Gmail
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx');           // Gmail App Password (16 chars)
```

### Step 5: Upload Files via FTP
1. In InfinityFree, go to **FTP Details**
2. Use FileZilla or similar FTP client:
   - **Host**: `ftpupload.net`
   - **Username**: `epiz_XXXXXXXX`
   - **Password**: Your FTP password
   - **Port**: 21
3. Upload ALL files to `htdocs/` folder

### Step 6: Update Frontend API URL

Edit `js/download.js` line 11:

```javascript
const API_BASE = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? 'api'
    : 'https://YOUR-SUBDOMAIN.infinityfreeapp.com/api';  // Your InfinityFree URL
```

### Step 7: Test
1. Visit your InfinityFree URL
2. Try signing up with a real email
3. Check email for verification code

---

## 🔧 Troubleshooting

### "Network Error" on Localhost
- **Cause**: Web server not running
- **Fix**: Start MAMP/XAMPP

### "Database Error" on Signup
- **Cause**: Missing columns in database
- **Fix**: Visit `http://yoursite.com/api/auth.php?action=debug-db` to auto-migrate

### "Email Not Received"
- **Cause**: SMTP not configured
- **Fix**: Check SMTP credentials in config.php
- **Fallback**: The code will be shown in an alert if email fails

### CORS Errors
- **Cause**: Browser blocking cross-origin requests
- **Fix**: Ensure `includes/config.php` has your domain in allowed origins

---

## 📁 Files to Upload to InfinityFree

```
htdocs/
├── api/
│   ├── auth.php
│   ├── downloads.php
│   ├── messages.php
│   ├── users.php
│   └── versions.php
├── css/
│   └── style.css
├── database/
│   └── lokalert_db_v2.sql (import this to phpMyAdmin)
├── includes/
│   ├── config.php (UPDATE WITH REAL CREDENTIALS)
│   └── email_service.php
├── js/
│   ├── download.js (UPDATE API_BASE URL)
│   └── main.js
├── releases/
│   └── (your APK files)
├── uploads/
├── index.html
├── admin.html
└── reset-password.html
```

---

## ⚠️ Important Notes for InfinityFree

1. **Free hosting limitations**:
   - No shell/SSH access
   - Limited PHP execution time
   - Some PHP functions disabled

2. **PHPMailer**: InfinityFree supports `mail()` function but SMTP is more reliable. The code falls back to showing the verification code if email fails.

3. **Database**: Run the auto-migration by visiting any auth endpoint - it will create missing columns automatically.

---

## 🧪 Testing Checklist

- [ ] Local server running (MAMP/XAMPP)
- [ ] Database imported with v2 schema
- [ ] Signup shows verification code (dev mode) or sends email (production)
- [ ] Verification code works
- [ ] Login works after verification
- [ ] Download works after login
