# LokAlert – Project Documentation

---

## Cover Page

| | |
|---|---|
| **Project Title** | LokAlert |
| **System Description** | Web-Based Mobile Application Landing Page & APK Distribution System with Integrated Database Administration |
| **Course** | Database Management & Administration 2 |
| **Integrated Subject** | Web Programming |
| **Group Members** | Alemana, Onyx Herod · Mabahin, Ryan · Adamos, Eurika · Billones, Gerald · Crisologo, Terence Joefrey · Royo, Aenard Ollyer |
| **Instructor** | *(To be filled)* |
| **Date Submitted** | February 7, 2026 |

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Objectives of the Project](#2-objectives-of-the-project)
3. [Scope and Limitations](#3-scope-and-limitations)
4. [System Architecture](#4-system-architecture)
5. [Entity Relationship Diagram (ERD)](#5-entity-relationship-diagram-erd)
6. [Database Schema Diagram](#6-database-schema-diagram)
7. [Normalization Explanation](#7-normalization-explanation)
8. [Database Implementation](#8-database-implementation)
9. [User Roles and Access Control](#9-user-roles-and-access-control)
10. [Data Flow Diagram (DFD)](#10-data-flow-diagram-dfd)
11. [Database Transaction Flow Diagram](#11-database-transaction-flow-diagram)
12. [Use Case Diagram](#12-use-case-diagram)
13. [Security and Access Control Diagram](#13-security-and-access-control-diagram)
14. [Testing and Validation](#14-testing-and-validation)
15. [Conclusion and Recommendations](#15-conclusion-and-recommendations)
16. [Appendices](#16-appendices)

---

## 1. Introduction

LokAlert is a web-based platform that serves as a promotional landing page, secure download portal, and administrative dashboard for a location-based arrival alert mobile application. The system is built with PHP and MySQL and provides user registration with email verification, APK version management via GitHub Releases, download tracking with rate limiting, a public contact form, and a full-featured admin panel. The web platform is the primary integration point between the front-end user experience and the relational database that stores all operational data.

The problem LokAlert solves is twofold. First, commuters and travelers need a simple way to be alerted when they are approaching their destination so they never miss their stop. Second, distributing an Android APK outside the Play Store requires a secure, trackable download system with user authentication and abuse prevention. LokAlert addresses both needs by providing a polished landing page that funnels visitors through a verified signup flow before granting access to the APK download, while administrators can manage users, versions, messages, and download analytics through a centralized dashboard backed by a normalized MySQL database.

---

## 2. Objectives of the Project

- To design and implement a normalized relational database (up to 3NF) that supports all system entities including users, roles, APK versions, download logs, contact messages, email logs, login attempts, and audit logs.
- To apply database administration concepts such as role-based access control, foreign key constraints, transaction management (BEGIN / COMMIT / ROLLBACK), and audit logging.
- To integrate the database layer with a PHP-based web application through secure PDO prepared statements and server-side validation.
- To implement database security measures including password hashing (`PASSWORD_DEFAULT` / bcrypt), login rate limiting, input sanitization, and credential separation.
- To provide database backup and recovery capabilities through a SQL dump utility accessible to administrators.

---

## 3. Scope and Limitations

### 3.1 Scope

- **User authentication and role management** – Registration with email verification (6-digit code), login with brute-force protection, password reset via email token, and role-based access (Admin / User).
- **Full CRUD operations** – Create, Read, Update, and Delete operations on all major entities: Users, APK Versions, Contact Messages, and Download Logs.
- **Data validation and integrity enforcement** – Foreign key constraints between tables (e.g., `download_logs.user_id → users.id`, `users.role_id → roles.id`), input sanitization, email format validation, and NOT NULL / UNIQUE constraints.
- **Download tracking and rate limiting** – Token-based download verification, 5-minute cooldown between downloads, progress tracking, and only counting successfully completed downloads.
- **Audit logging** – All significant database changes (user creation, deletion, version management, login events) are recorded in the `audit_logs` table for accountability.
- **Database backup** – Administrators can generate a full SQL dump of the database via `database/backup.php`.

### 3.2 Limitations

- The system is limited to small-scale deployment (shared hosting on InfinityFree) and is not designed for high-concurrency production workloads.
- No real-time database replication or automated scheduled backups are implemented; backups are performed manually by the administrator.
- The platform currently supports only Android APK distribution; iOS is not yet supported.

---

## 4. System Architecture

### 4.1 System Architecture Diagram

*(Insert System Architecture Diagram here)*

```
┌──────────────┐       HTTPS        ┌──────────────────────┐        PDO/MySQL        ┌─────────────────┐
│              │  ──────────────►    │                      │  ──────────────────►     │                 │
│  User /      │                    │   Apache Web Server   │                          │   MySQL 5.7+    │
│  Browser     │  ◄──────────────   │   (PHP 7.4+)         │  ◄──────────────────     │   lokalert_db   │
│              │    HTML / JSON      │                      │     Query Results         │                 │
└──────────────┘                    │  ┌────────────────┐  │                          │  ┌───────────┐  │
                                    │  │ api/auth.php   │  │                          │  │ users     │  │
┌──────────────┐                    │  │ api/users.php  │  │                          │  │ roles     │  │
│              │  Admin Session      │  │ api/versions   │  │                          │  │ apk_ver.  │  │
│  Admin /     │  ──────────────►   │  │ api/downloads  │  │                          │  │ downloads │  │
│  Browser     │                    │  │ api/messages   │  │                          │  │ messages  │  │
│              │  ◄──────────────   │  │ api/github.php │  │                          │  │ email_log │  │
└──────────────┘    HTML / JSON     │  └────────────────┘  │                          │  │ login_att │  │
                                    │                      │                          │  │ audit_log │  │
┌──────────────┐   GitHub API       │  includes/           │                          │  └───────────┘  │
│  GitHub      │  ◄──────────────   │   config.php         │                          │                 │
│  Releases    │  ──────────────►   │   email_service.php  │                          └─────────────────┘
└──────────────┘   APK hosting      └──────────────────────┘
                                             │
                                             │ Raw SMTP
                                             ▼
                                    ┌──────────────────────┐
                                    │  Gmail SMTP Server   │
                                    │  (Verification &     │
                                    │   Reset Emails)      │
                                    └──────────────────────┘
```

### 4.2 Explanation

Data flows from the user's browser to the Apache/PHP web server via HTTPS requests. The PHP application layer (RESTful API endpoints in the `api/` folder) processes each request, performs input validation and authentication checks, and then communicates with the MySQL database through PDO prepared statements. Query results are returned to the PHP layer, which formats them as JSON responses and sends them back to the browser. For file distribution, the server integrates with the GitHub Releases API to host APK files, and for email communications (verification codes, password resets), it connects to Gmail's SMTP server using raw socket connections. The entire data path — from user input to database storage and back — is secured by session-based authentication, role-based access control, and parameterized queries that prevent SQL injection.

---

## 5. Entity Relationship Diagram (ERD)

### 5.1 ERD Diagram

*(Insert ERD Diagram here)*

```
┌─────────────┐        1    ┌─────────────────┐    M        ┌──────────────────┐
│   roles     │────────────►│     users        │◄────────────│  login_attempts  │
│             │  has many    │                  │  tracks      │                  │
│ PK: id      │             │ PK: id           │             │ PK: id           │
│ role_name   │             │ FK: role_id ─────┤             │ email            │
│ description │             │ username         │             │ ip_address       │
│ permissions │             │ email (UNIQUE)   │             │ success          │
│ created_at  │             │ password (hash)  │             │ attempted_at     │
└─────────────┘             │ is_admin         │             └──────────────────┘
                            │ is_verified      │
                            │ verification_code│
                            │ reset_token      │
                            │ download_count   │
                            │ created_at       │
                            └──────┬───────────┘
                                   │
                 ┌─────────────────┼─────────────────┬───────────────────┐
                 │ 1           M   │ 1            M  │ 1              M  │
                 ▼                 ▼                  ▼                   ▼
        ┌────────────────┐ ┌──────────────┐ ┌────────────────┐  ┌──────────────┐
        │ download_logs  │ │  email_logs  │ │  audit_logs    │  │              │
        │                │ │              │ │                │  │  (contact    │
        │ PK: id         │ │ PK: id       │ │ PK: id         │  │   _messages  │
        │ FK: user_id ───┤ │ FK: user_id  │ │ FK: user_id    │  │   is public) │
        │ FK: version_id │ │ recipient    │ │ action         │  │              │
        │ download_token │ │ email_type   │ │ table_name     │  │ PK: id       │
        │ status         │ │ status       │ │ record_id      │  │ name         │
        │ started_at     │ │ sent_at      │ │ old_values     │  │ email        │
        │ completed_at   │ │              │ │ new_values     │  │ subject      │
        └───────┬────────┘ └──────────────┘ │ ip_address     │  │ message      │
                │ M                         │ created_at     │  │ is_read      │
                │                           └────────────────┘  │ created_at   │
                │ 1                                             └──────────────┘
                ▼
        ┌────────────────┐
        │  apk_versions  │
        │                │
        │ PK: id         │
        │ version        │
        │ filename       │
        │ file_size      │
        │ download_url   │
        │ release_notes  │
        │ is_latest      │
        │ download_count │
        │ upload_date    │
        └────────────────┘
```

### 5.2 Explanation

The `roles` table has a **one-to-many (1:M)** relationship with the `users` table — each role can be assigned to many users, but each user has exactly one role (either "admin" or "user"). This is enforced by the foreign key `users.role_id → roles.id`. The `users` table is the central entity and has one-to-many relationships with `download_logs` (a user can have many download records, enforced by FK `download_logs.user_id → users.id`), `email_logs` (a user can receive many emails), `audit_logs` (actions by a user are tracked), and `login_attempts` (each login attempt is recorded). The `download_logs` table also has a many-to-one (M:1) relationship with `apk_versions` — many download records can reference the same APK version, enforced by FK `download_logs.version_id → apk_versions.id`. The `contact_messages` table is independent (no foreign key) since messages can be submitted by anonymous visitors who are not registered users.

---

## 6. Database Schema Diagram

### 6.1 Database Schema / Table Structures

*(Insert Database Schema Diagram here — or use the tables below)*

#### Table: `roles`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique role identifier |
| role_name | VARCHAR(50) | NOT NULL, UNIQUE | Role name (admin, user) |
| description | VARCHAR(255) | NULL | Human-readable role description |
| permissions | TEXT | NULL | JSON-encoded permission list |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation time |

#### Table: `users`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique user identifier |
| username | VARCHAR(50) | NULL | Display name (optional) |
| email | VARCHAR(100) | NOT NULL, UNIQUE | Login email address |
| password | VARCHAR(255) | NOT NULL | Bcrypt-hashed password |
| role_id | INT | FK → roles(id), NOT NULL, DEFAULT 2 | Assigned role |
| is_admin | TINYINT(1) | DEFAULT 0 | Admin flag (derived from role) |
| is_verified | TINYINT(1) | DEFAULT 0 | Email verified flag |
| verification_code | VARCHAR(10) | NULL | 6-digit email verification code |
| verification_expires | DATETIME | NULL | Verification code expiry time |
| reset_token | VARCHAR(100) | NULL | Password reset token (64 hex chars) |
| reset_expires | DATETIME | NULL | Reset token expiry time |
| download_count | INT | DEFAULT 0 | Total successful downloads |
| last_download_at | DATETIME | NULL | Last download timestamp |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation time |

#### Table: `apk_versions`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique version identifier |
| version | VARCHAR(20) | NOT NULL | Semantic version string (e.g., 2.0.1) |
| filename | VARCHAR(255) | NOT NULL | APK filename |
| file_size | BIGINT | DEFAULT 0 | File size in bytes |
| download_url | VARCHAR(500) | NULL | External download URL (GitHub Releases) |
| release_notes | TEXT | NULL | Version release description |
| is_latest | TINYINT(1) | DEFAULT 0 | Marks the latest release |
| download_count | INT | DEFAULT 0 | Total completed download count |
| upload_date | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Upload timestamp |

#### Table: `download_logs`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique log entry identifier |
| user_id | INT | FK → users(id) ON DELETE CASCADE, NOT NULL | The user who downloaded |
| version_id | INT | FK → apk_versions(id) ON DELETE SET NULL, NULL | The downloaded version |
| ip_address | VARCHAR(45) | NULL | Client IP address |
| user_agent | TEXT | NULL | Browser user-agent string |
| download_token | VARCHAR(64) | NULL | Unique token for this download |
| status | ENUM('started','completed','failed','cancelled') | DEFAULT 'started' | Download status |
| file_size | BIGINT | DEFAULT 0 | Expected file size in bytes |
| bytes_downloaded | BIGINT | DEFAULT 0 | Actual bytes downloaded |
| started_at | DATETIME | NULL | Download start timestamp |
| completed_at | DATETIME | NULL | Download completion timestamp |

#### Table: `contact_messages`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique message identifier |
| name | VARCHAR(100) | NOT NULL | Sender's name |
| email | VARCHAR(100) | NOT NULL | Sender's email address |
| subject | VARCHAR(200) | NOT NULL | Message subject line |
| message | TEXT | NOT NULL | Message body (min 10 chars) |
| is_read | TINYINT(1) | DEFAULT 0 | Read status flag |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Submission timestamp |

#### Table: `email_logs`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique log entry identifier |
| user_id | INT | FK → users(id) ON DELETE SET NULL, NULL | Recipient user |
| recipient_email | VARCHAR(100) | NOT NULL | Target email address |
| email_type | VARCHAR(50) | NOT NULL | Type: verification, password_reset, notification |
| subject | VARCHAR(200) | NULL | Email subject line |
| status | VARCHAR(20) | DEFAULT 'pending' | Delivery status |
| error_message | TEXT | NULL | Error details if delivery failed |
| sent_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Send timestamp |

#### Table: `login_attempts`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique attempt identifier |
| email | VARCHAR(100) | NOT NULL | Email used in attempt |
| ip_address | VARCHAR(45) | NOT NULL | Client IP address |
| attempted_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Time of attempt |
| success | TINYINT(1) | DEFAULT 0 | Whether login succeeded (1) or failed (0) |

#### Table: `audit_logs`

| Column | Data Type | Constraints | Description |
|--------|-----------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Unique audit entry identifier |
| user_id | INT | FK → users(id) ON DELETE SET NULL, NULL | The user who performed the action |
| action | VARCHAR(100) | NOT NULL | Action name (e.g., CREATE_USER, DELETE_VERSION, USER_LOGIN, DATABASE_BACKUP) |
| table_name | VARCHAR(100) | NULL | Affected database table |
| record_id | INT | NULL | ID of the affected record |
| old_values | TEXT | NULL | JSON snapshot of data before the change |
| new_values | TEXT | NULL | JSON snapshot of data after the change |
| ip_address | VARCHAR(45) | NULL | Client IP address |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Timestamp of the action |

---

## 7. Normalization Explanation

The LokAlert database design follows **Third Normal Form (3NF)** to eliminate redundancy and ensure data consistency.

- **1NF:** Every column contains only atomic (indivisible) values. There are no repeating groups — for example, each download event is a separate row in `download_logs` rather than a comma-separated list in the `users` table.
- **2NF:** All non-key attributes are fully functionally dependent on the entire primary key. Since all tables use a single-column surrogate primary key (`id`), partial dependency is automatically eliminated.
- **3NF:** There are no transitive dependencies. Role information (name, description, permissions) is stored in the dedicated `roles` table and referenced from `users` via the `role_id` foreign key, rather than being duplicated in every user row. Similarly, APK version metadata (filename, release notes, download URL) lives in `apk_versions` and is referenced from `download_logs` by `version_id`, avoiding redundant storage of version details in every download record.

This normalized structure ensures that updates to role definitions or version information need to be made in only one place, preserving data consistency across the system.

---

## 8. Database Implementation

### 8.1 DBMS Used

**MySQL 5.7+** (MariaDB 10.3+ compatible) was chosen as the relational database management system due to its wide compatibility with PHP web applications, native support on the InfinityFree shared hosting platform, robust transaction support with the InnoDB storage engine, and extensive tooling (phpMyAdmin, MySQL Workbench). Database access is handled exclusively through PHP's **PDO** (PHP Data Objects) extension with prepared statements to prevent SQL injection.

### 8.2 Sample SQL Scripts

#### CREATE TABLE — Users table with foreign key to roles

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `username`              VARCHAR(50) NULL,
    `email`                 VARCHAR(100) NOT NULL UNIQUE,
    `password`              VARCHAR(255) NOT NULL,
    `role_id`               INT NOT NULL DEFAULT 2,
    `is_admin`              TINYINT(1) DEFAULT 0,
    `is_verified`           TINYINT(1) DEFAULT 0,
    `verification_code`     VARCHAR(10) NULL,
    `verification_expires`  DATETIME NULL,
    `reset_token`           VARCHAR(100) NULL,
    `reset_expires`         DATETIME NULL,
    `download_count`        INT DEFAULT 0,
    `last_download_at`      DATETIME NULL,
    `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_email` (`email`),
    INDEX `idx_role_id` (`role_id`),

    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### CREATE TABLE — Download Logs with foreign keys

```sql
CREATE TABLE IF NOT EXISTS `download_logs` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`           INT NOT NULL,
    `version_id`        INT NULL,
    `download_token`    VARCHAR(64) NULL,
    `status`            ENUM('started','completed','failed','cancelled') DEFAULT 'started',
    `file_size`         BIGINT DEFAULT 0,
    `bytes_downloaded`  BIGINT DEFAULT 0,
    `started_at`        DATETIME NULL,
    `completed_at`      DATETIME NULL,

    CONSTRAINT `fk_downloads_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_downloads_version`
        FOREIGN KEY (`version_id`) REFERENCES `apk_versions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### CREATE TABLE — Audit Logs for tracking all database changes

```sql
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT NULL,
    `action`       VARCHAR(100) NOT NULL,
    `table_name`   VARCHAR(100) NULL,
    `record_id`    INT NULL,
    `old_values`   TEXT NULL,
    `new_values`   TEXT NULL,
    `ip_address`   VARCHAR(45) NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### INSERT — Seed default roles

```sql
INSERT INTO `roles` (`id`, `role_name`, `description`, `permissions`) VALUES
(1, 'admin', 'Full system access',
   '["users.read","users.create","users.update","users.delete","versions.read","versions.create","versions.update","versions.delete","messages.read","messages.delete","downloads.read","settings.manage","backup.create"]'),
(2, 'user', 'Standard authenticated user',
   '["versions.read","downloads.create","messages.create","profile.read","profile.update"]');
```

#### INSERT — Create default admin user

```sql
INSERT INTO `users` (`username`, `email`, `password`, `role_id`, `is_admin`, `is_verified`)
VALUES (
    'admin',
    'admin@lokalert.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1, 1, 1
);
```

#### SELECT — Get all verified users with their role names

```sql
SELECT u.id, u.username, u.email, r.role_name, u.download_count, u.created_at
FROM users u
JOIN roles r ON u.role_id = r.id
WHERE u.is_verified = 1
ORDER BY u.created_at DESC;
```

#### SELECT — Download statistics per version

```sql
SELECT av.version, av.download_count,
       COUNT(dl.id) AS log_entries
FROM apk_versions av
LEFT JOIN download_logs dl ON av.id = dl.version_id AND dl.status = 'completed'
GROUP BY av.id
ORDER BY av.upload_date DESC;
```

> **Note:** The complete SQL schema with all 8 tables is available in `database/lokalert_schema.sql`.

---

## 9. User Roles and Access Control

### 9.1 Roles

The system defines two user roles, stored in the `roles` table:

| Role | role_id | Access Level | Permissions |
|------|---------|-------------|-------------|
| **Admin** | 1 | Full Access | Manage users (CRUD), manage APK versions (CRUD), read/delete contact messages, view download logs and statistics, reset user passwords, upload APK to GitHub Releases, generate database backups, view audit logs |
| **User** | 2 | Limited Access | View own profile, download APK (after email verification), submit contact messages, change own password |

### 9.2 Access Control Implementation

Access control is enforced at the API layer through PHP middleware functions defined in `includes/config.php`:

| Function | HTTP Code on Fail | Purpose |
|----------|-------------------|---------|
| `requireLogin()` | 401 Unauthorized | Checks that the user has an active session |
| `requireVerified()` | 403 Forbidden | Checks that the user's email has been verified |
| `requireAdmin()` | 403 Forbidden | Checks that the session user has `is_admin = 1` |

Each API endpoint calls the appropriate middleware before executing any database operation. For example:
- All endpoints in `api/users.php` (except viewing own profile) call `requireAdmin()`.
- The download initialization endpoint `api/downloads.php?action=init` calls `requireVerified()`.
- The contact message submission `api/messages.php` (POST) is public — no middleware required.
- The database backup utility `database/backup.php` calls `isAdmin()` before generating the dump.

---

## 10. Data Flow Diagram (DFD)

### 10.1 Level 0 — Context Diagram

*(Insert DFD here)*

```
                              ┌─────────────────────────┐
  ┌──────────┐  Signup /      │                         │   SQL Queries    ┌────────────┐
  │          │  Login /       │                         │ ───────────────► │            │
  │  User    │  Download ───► │      LokAlert Web       │                  │  MySQL     │
  │ (Actor)  │                │      Application        │ ◄─────────────── │  Database  │
  │          │ ◄── APK File / │      (PHP)              │   Query Results  │ (Data      │
  └──────────┘    JSON resp.  │                         │                  │  Store)    │
                              │                         │                  └────────────┘
  ┌──────────┐  Manage Users  │      ┌──────────┐      │
  │          │  / Versions /  │      │ Process: │      │   SMTP           ┌────────────┐
  │  Admin   │  Backup   ───► │      │ Validate │      │ ───────────────► │            │
  │ (Actor)  │                │      │ & Route  │      │                  │  Gmail     │
  │          │ ◄── Dashboard  │      │ Requests │      │   Verification   │  SMTP      │
  └──────────┘    Data        │      └──────────┘      │   & Reset Emails │ (External) │
                              │                         │                  └────────────┘
                              │                         │
                              │                         │   GitHub API     ┌────────────┐
                              │                         │ ───────────────► │  GitHub    │
                              │                         │                  │  Releases  │
                              │                         │ ◄─────────────── │ (External) │
                              └─────────────────────────┘   Download URL   └────────────┘
```

### 10.2 Explanation

The Data Flow Diagram shows how data flows through the LokAlert system. Two external actors — **User** and **Admin** — interact with the central process (the PHP web application). Users submit registration data, login credentials, contact messages, and download requests. The application validates all input, performs authentication checks, and executes corresponding SQL operations (INSERT, SELECT, UPDATE, DELETE) against the **MySQL Database** data store. For user registration and password resets, the application sends emails via the **Gmail SMTP** external service. Administrators interact with the same application but have elevated privileges to manage all entities, trigger database backups, and upload APK files to the **GitHub Releases** external service. Data flows bidirectionally: user input flows into the system and is persisted in the database, while query results, JSON responses, and APK files flow back to the actors.

---

## 11. Database Transaction Flow Diagram

### 11.1 Transaction Flow Diagram

*(Insert Transaction Flow Diagram here)*

```
┌──────────────────────────────────────────────────────────────────┐
│                DOWNLOAD COMPLETION TRANSACTION                    │
│                (api/downloads.php → completeDownload)             │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│   START ──► $db->beginTransaction()                              │
│               │                                                  │
│               ▼                                                  │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 1: UPDATE download_logs                │               │
│   │          SET status = 'completed',            │               │
│   │              bytes_downloaded = ?,             │               │
│   │              completed_at = NOW()              │               │
│   │          WHERE download_token = ?              │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 2: UPDATE apk_versions                 │               │
│   │          SET download_count = count + 1       │               │
│   │          WHERE id = ?                         │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 3: UPDATE users                        │               │
│   │          SET download_count = count + 1,      │               │
│   │              last_download_at = NOW()          │               │
│   │          WHERE id = ?                         │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│              ┌─── All 3 Successful? ───┐                         │
│              │                         │                         │
│            YES                        NO                         │
│              │                         │                         │
│              ▼                         ▼                         │
│     $db->commit()             $db->rollBack()                    │
│       │                            │                             │
│       ▼                            ▼                             │
│   Return JSON:                Return JSON:                       │
│   { success: true,            { error: "...",                    │
│     download_count: N }         success: false }                 │
│                               (all 3 tables unchanged)           │
└──────────────────────────────────────────────────────────────────┘


┌──────────────────────────────────────────────────────────────────┐
│                USER DELETION TRANSACTION                         │
│                (api/users.php → deleteUser)                       │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│   START ──► $db->beginTransaction()                              │
│               │                                                  │
│               ▼                                                  │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 1: SELECT id, username, email           │               │
│   │          FROM users WHERE id = ?              │               │
│   │          (snapshot for audit log)              │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 2: DELETE FROM users WHERE id = ?       │               │
│   │          (FK CASCADE removes download_logs)   │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│   ┌──────────────────────────────────────────────┐               │
│   │  Step 3: INSERT INTO audit_logs               │               │
│   │          (action='DELETE_USER',               │               │
│   │           old_values=snapshot)                 │               │
│   └──────────────────┬───────────────────────────┘               │
│                      ▼                                           │
│              ┌─── All Successful? ───┐                           │
│              │                       │                           │
│            YES                      NO                           │
│              │                       │                           │
│              ▼                       ▼                           │
│     $db->commit()           $db->rollBack()                      │
│                             (user NOT deleted,                    │
│                              no orphaned records)                 │
└──────────────────────────────────────────────────────────────────┘
```

### 11.2 Explanation

Transactions are critical in LokAlert to maintain data consistency when multiple tables must be updated atomically. The **Download Completion Transaction** wraps three UPDATE operations: marking the download log as completed, incrementing the APK version's download count, and updating the user's download count and timestamp. If any step fails (e.g., a database connection error or constraint violation), the `ROLLBACK` command reverses all changes, ensuring the database never ends up in a partially updated state. The **User Deletion Transaction** first captures a snapshot of the user's data for the audit log, then deletes the user (which cascades to their download logs via the foreign key), and finally inserts the audit record — all committed together or rolled back entirely. This guarantees that either all changes succeed atomically, or none of them take effect, which is essential for maintaining referential integrity and a complete audit trail.

---

## 12. Use Case Diagram

### 12.1 Use Case Diagram

*(Insert Use Case Diagram here)*

```
                    ┌───────────────────────────────────────────────┐
                    │              LokAlert System                  │
                    │                                               │
                    │   ┌─────────────────────────────────────┐    │
                    │   │        User Actions                  │    │
                    │   │                                      │    │
  ┌──────────┐      │   │  ○ View Landing Page                 │    │
  │          │      │   │  ○ Register Account                  │    │
  │   User   │─────►│   │  ○ Verify Email                     │    │
  │ (Visitor/│      │   │  ○ Login / Logout                    │    │
  │  Signed  │      │   │  ○ Download APK                     │    │
  │  Up)     │      │   │  ○ Reset Password                   │    │
  │          │      │   │  ○ Submit Contact Message            │    │
  └──────────┘      │   └─────────────────────────────────────┘    │
                    │                                               │
                    │   ┌─────────────────────────────────────┐    │
                    │   │        Admin Actions                 │    │
                    │   │                                      │    │
  ┌──────────┐      │   │  ○ Login / Logout                    │    │
  │          │      │   │  ○ Manage Users (CRUD)               │    │
  │  Admin   │─────►│   │  ○ Manage APK Versions (CRUD)       │    │
  │          │      │   │  ○ Upload APK to GitHub Releases     │    │
  │          │      │   │  ○ View Download Statistics & Logs   │    │
  └──────────┘      │   │  ○ Read / Delete Contact Messages    │    │
                    │   │  ○ Reset User Passwords              │    │
                    │   │  ○ Generate Database Backup           │    │
                    │   └─────────────────────────────────────┘    │
                    │                                               │
                    └───────────────────────────────────────────────┘
```

### 12.2 Explanation

The use case diagram identifies two primary actors and their system actions:

**User (Visitor / Registered User)** — 7 actions:
1. View Landing Page — browse features, team, and technology sections
2. Register Account — sign up with email and password
3. Verify Email — enter 6-digit code to confirm identity
4. Login / Logout — authenticate or end session
5. Download APK — download the latest LokAlert application
6. Reset Password — recover account via email token
7. Submit Contact Message — send feedback through the contact form

**Admin (System Administrator)** — 8 actions:
1. Login / Logout — authenticate with admin credentials
2. Manage Users (CRUD) — create, view, update, and delete user accounts
3. Manage APK Versions (CRUD) — add, edit, and remove application versions
4. Upload APK to GitHub Releases — publish new APK to external hosting
5. View Download Statistics & Logs — monitor download activity and trends
6. Read / Delete Contact Messages — review and manage visitor inquiries
7. Reset User Passwords — set temporary passwords or send reset emails
8. Generate Database Backup — export full SQL dump for disaster recovery

---

## 13. Security and Access Control Diagram

### 13.1 Security Diagram

*(Insert Security and Access Control Diagram here)*

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     SECURITY & ACCESS CONTROL LAYERS                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  LAYER 1: AUTHENTICATION                                                │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  • Password hashing (bcrypt via password_hash / PASSWORD_DEFAULT) │  │
│  │  • Email verification (6-digit code, 15-min expiry)               │  │
│  │  • Session-based authentication (PHP sessions)                    │  │
│  │  • Login rate limiting (max 5 attempts → 15-min lockout)          │  │
│  │  • login_attempts table tracks every attempt with IP & timestamp  │  │
│  │  • Password reset via secure random token (64 hex chars, 24h exp) │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                            │                                            │
│                            ▼                                            │
│  LAYER 2: ROLE-BASED ACCESS CONTROL (RBAC)                              │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  ┌─────────┐    ┌──────────────────────────────────────────────┐  │  │
│  │  │  Admin   │───►│ Full: users.*, versions.*, messages.*,      │  │  │
│  │  │ (role 1) │    │ downloads.*, settings.*, backup.*           │  │  │
│  │  └─────────┘    └──────────────────────────────────────────────┘  │  │
│  │                                                                   │  │
│  │  ┌─────────┐    ┌──────────────────────────────────────────────┐  │  │
│  │  │  User    │───►│ Limited: versions.read, downloads.create,   │  │  │
│  │  │ (role 2) │    │ messages.create, profile.read/update        │  │  │
│  │  └─────────┘    └──────────────────────────────────────────────┘  │  │
│  │                                                                   │  │
│  │  PHP Middleware Chain:                                             │  │
│  │  requireLogin() → requireVerified() → requireAdmin()              │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                            │                                            │
│                            ▼                                            │
│  LAYER 3: DATABASE SECURITY                                             │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  • PDO prepared statements (prevents SQL injection)               │  │
│  │  • Input sanitization via htmlspecialchars() + strip_tags()       │  │
│  │  • Foreign key constraints (InnoDB referential integrity)         │  │
│  │  • Credential separation (credentials.php in .gitignore)         │  │
│  │  • Audit logging — audit_logs table records all changes           │  │
│  │  • ENUM constraints on status fields                              │  │
│  │  • UNIQUE constraints on email to prevent duplicate accounts      │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                            │                                            │
│                            ▼                                            │
│  LAYER 4: APPLICATION SECURITY                                          │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  • CORS headers with origin whitelist                             │  │
│  │  • Download rate limiting (5-min cooldown per user)               │  │
│  │  • Token-based download verification (64-char random tokens)      │  │
│  │  • Environment detection (prod vs dev error display)              │  │
│  │  • HTTPS enforcement on production                                │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 13.2 Explanation

The diagram illustrates the four defensive layers protecting the LokAlert database. **Layer 1 (Authentication)** ensures only verified identities can access protected resources — passwords are hashed with bcrypt, email verification uses time-limited 6-digit codes, and the `login_attempts` table enforces lockout after 5 failed attempts within 15 minutes. **Layer 2 (RBAC)** enforces role-based restrictions using the `roles` table and a chain of PHP middleware functions that gate every API endpoint according to the user's role. **Layer 3 (Database Security)** protects the data layer through PDO prepared statements (preventing injection), input sanitization, foreign key constraints for referential integrity, credential separation via gitignored files, and a complete audit trail in the `audit_logs` table. **Layer 4 (Application Security)** adds CORS origin restrictions, download rate limiting to prevent abuse, token-based download verification, and environment-aware error handling that suppresses detailed errors in production.

---

## 14. Testing and Validation

### 14.1 Test Cases

| # | Test Case | Description | Expected Result | Actual Result |
|---|-----------|-------------|-----------------|---------------|
| 1 | User Registration | Submit signup with valid email and password (≥ 6 chars) | Account created; 6-digit verification code sent or displayed | **Passed** |
| 2 | Email Verification | Enter correct 6-digit code within 15-minute window | User marked as verified; session created; download enabled | **Passed** |
| 3 | Login – Valid Credentials | Enter registered email and correct password | Login successful; session created; redirect to dashboard or download | **Passed** |
| 4 | Login – Invalid Password | Enter registered email and wrong password | Error "Invalid email or password"; attempt logged in `login_attempts` | **Passed** |
| 5 | Login – Rate Limiting | Submit 5+ failed login attempts within 15 minutes | Error "Too many failed attempts. Please try again in 15 minutes." (HTTP 429) | **Passed** |
| 6 | APK Download (Verified User) | Click download button after email verification and cooldown | Download token generated; APK download starts; count incremented atomically via transaction | **Passed** |
| 7 | Download Cooldown | Attempt second download within 5-minute window | Error "Please wait X minute(s) before downloading again." (HTTP 429) | **Passed** |
| 8 | Admin – Create User | Admin creates user via dashboard with username, email, password | User inserted with hashed password inside transaction; audit log recorded | **Passed** |
| 9 | Admin – Delete User | Admin deletes a non-admin user account | User removed; related download_logs cascade-deleted; audit log recorded; transaction committed | **Passed** |
| 10 | Contact Form – Validation | Submit form with missing required field | Error "All fields are required" (HTTP 400) | **Passed** |
| 11 | SQL Injection Prevention | Submit `' OR 1=1 --` as email in login form | Error "Invalid email or password" (prepared statements block injection) | **Passed** |
| 12 | Foreign Key Integrity | Attempt to insert a download_log with non-existent user_id | Database rejects INSERT with foreign key constraint violation | **Passed** |

### 14.2 Data Integrity Testing

- **Foreign key constraints** between `download_logs.user_id → users.id` and `download_logs.version_id → apk_versions.id` prevent orphaned download records. Deleting a user cascades to their download logs (`ON DELETE CASCADE`), while deleting a version sets `version_id` to NULL (`ON DELETE SET NULL`).
- **UNIQUE constraint** on `users.email` prevents duplicate registration.
- **NOT NULL constraints** on required fields (`email`, `password`, `message`) enforce mandatory data entry at the database level.
- **ENUM constraint** on `download_logs.status` restricts values to exactly four valid states: `started`, `completed`, `failed`, `cancelled`.
- **Transaction integrity** is verified by confirming that if any step in the download completion transaction fails, all three tables (`download_logs`, `apk_versions`, `users`) remain unchanged after rollback.

---

## 15. Conclusion and Recommendations

### 15.1 Conclusion

The LokAlert project successfully demonstrated the integration of database management and administration concepts with a functional web-based system. The relational database was designed following normalization principles up to Third Normal Form (3NF) and implemented in MySQL with **eight interconnected tables** (`roles`, `users`, `apk_versions`, `download_logs`, `contact_messages`, `email_logs`, `login_attempts`, `audit_logs`), foreign key constraints, indexes for performance, and InnoDB transactions for atomicity. Key DBA concepts applied include: role-based access control via the `roles` and `users` tables with middleware enforcement; audit logging in the `audit_logs` table for accountability; login attempt tracking in `login_attempts` for brute-force protection; and transaction management with `BEGIN` / `COMMIT` / `ROLLBACK` to ensure atomicity across multi-table operations like download completion and user deletion. The PHP API layer enforces all security policies before any data reaches the database, creating a layered defense-in-depth architecture.

### 15.2 Recommendations

- **Implement automated scheduled backups** — Use a cron job that invokes `database/backup.php` daily and stores the resulting SQL dump files with date-stamped filenames on remote cloud storage (e.g., Google Drive, AWS S3) for disaster recovery.
- **Add database performance indexing** — Analyze slow queries using `EXPLAIN` and add composite indexes on frequently filtered columns (e.g., `download_logs(user_id, status)`, `login_attempts(email, attempted_at)`) to improve query performance as data volume grows.
- **Implement database read replicas** — If the user base scales beyond what a single MySQL instance can handle, introduce read replicas to separate read-heavy analytics queries from write operations, improving both throughput and fault tolerance.

---

## 16. Appendices

### Appendix A — System Screenshots

*(Insert screenshots of the following:)*

1. Landing page — hero section with download CTA
2. User registration / signup modal
3. Email verification code entry screen
4. APK download in progress with progress indicator
5. Admin dashboard overview (statistics cards)
6. Admin — User management panel (list, create, edit, delete)
7. Admin — APK version management panel
8. Admin — Contact messages panel (read/unread)
9. Contact form page (`contact.php`)
10. Password reset page (`reset-password.html`)

### Appendix B — Complete SQL Scripts

The complete database schema with all CREATE TABLE statements, indexes, foreign key constraints, seed data, and sample queries is located at:

**File:** [`database/lokalert_schema.sql`](../database/lokalert_schema.sql)

Key contents:
- `CREATE TABLE roles` — with default admin/user seed data
- `CREATE TABLE users` — with FK to roles
- `CREATE TABLE apk_versions` — version metadata and download tracking
- `CREATE TABLE download_logs` — with FKs to users and apk_versions
- `CREATE TABLE contact_messages` — public contact form submissions
- `CREATE TABLE email_logs` — email delivery audit trail
- `CREATE TABLE login_attempts` — brute-force protection tracking
- `CREATE TABLE audit_logs` — comprehensive change tracking
- `INSERT` statements — default admin user and sample data
- `SELECT` statements — analytics and reporting queries

### Appendix C — All Diagrams

1. System Architecture Diagram — Section 4
2. Entity Relationship Diagram (ERD) — Section 5
3. Database Schema / Table Structures — Section 6
4. Data Flow Diagram (DFD) Level 0 — Section 10
5. Database Transaction Flow Diagram — Section 11
6. Use Case Diagram — Section 12
7. Security and Access Control Diagram — Section 13

---

*This documentation is aligned with the Database Management & Administration 2 project documentation template.*

**© 2026 LokAlert. All rights reserved.**
