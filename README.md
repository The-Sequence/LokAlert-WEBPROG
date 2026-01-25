# LokAlert APK Download System

## Features
- ✅ Simple APK Download folder
- ✅ Download Tracking Database
- ✅ Version Management System
- ✅ User Registration & Authentication
- ✅ Admin Panel for managing APKs and viewing stats

## Setup Instructions

### 1. Install Dependencies
```bash
cd server
npm install
```

### 2. Start the Server
```bash
cd server
node server.js
```

The server will start at `http://localhost:3000`

### 3. Default Admin Credentials
- **Username:** admin
- **Password:** admin123

⚠️ **Change these credentials in production!**

## Usage

### For Users
1. Visit the main website
2. Click the "Download" button in the navigation
3. Download as a guest or register/login for tracked downloads

### For Admins
1. Go to `http://localhost:3000/admin.html`
2. Login with admin credentials
3. Upload APK files with version information
4. View download statistics and user data

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - User login
- `GET /api/auth/me` - Get current user (requires token)

### APK Versions
- `GET /api/versions` - Get all versions
- `GET /api/versions/latest` - Get latest version
- `POST /api/versions` - Upload new version (admin only)
- `DELETE /api/versions/:id` - Delete version (admin only)

### Downloads
- `GET /api/download/:id` - Download specific version
- `GET /api/download/latest` - Download latest version

### Statistics (Admin Only)
- `GET /api/stats` - Get download statistics
- `GET /api/users` - Get all users
- `GET /api/logs` - Get download logs

## File Structure
```
LokAlert-WEBPROG/
├── index.html          # Main website
├── admin.html          # Admin panel
├── css/
│   └── style.css       # Styles
├── js/
│   ├── main.js         # Main website scripts
│   └── download.js     # Download modal & auth
├── downloads/          # APK files stored here
└── server/
    ├── package.json    # Node.js dependencies
    ├── server.js       # Express server
    └── db/
        └── lokalert.db # SQLite database (auto-created)
```

## Database Schema

### Users Table
- id, username, email, password, created_at, is_admin

### APK Versions Table
- id, version, filename, release_notes, file_size, upload_date, is_latest, download_count

### Download Logs Table
- id, user_id, apk_version_id, download_date, ip_address, user_agent

### Download Stats Table
- id, date, total_downloads

## Technologies Used
- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** Node.js, Express.js
- **Database:** SQLite (better-sqlite3)
- **Authentication:** JWT, bcryptjs
- **File Upload:** Multer
