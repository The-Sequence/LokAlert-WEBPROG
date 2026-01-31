<?php
/**
 * CREDENTIALS TEMPLATE
 * Copy this file to credentials.php and fill in your values
 * credentials.php should NEVER be committed to git
 */

// Production Database Credentials (InfinityFree)
define('PROD_DB_HOST', 'your-sql-host.infinityfree.com');
define('PROD_DB_NAME', 'your_database_name');
define('PROD_DB_USER', 'your_username');
define('PROD_DB_PASS', 'your_password');

// Email Credentials (Gmail SMTP)
// Use a Gmail App Password, not your regular password
// Generate at: https://myaccount.google.com/apppasswords
define('PROD_SMTP_USER', 'your-email@gmail.com');
define('PROD_SMTP_PASS', 'your-gmail-app-password');

// GitHub Releases Integration (for APK hosting)
// Create a Personal Access Token with 'repo' scope at:
// https://github.com/settings/tokens
define('GITHUB_TOKEN', 'ghp_your_github_personal_access_token');
define('GITHUB_OWNER', 'your-github-username');
define('GITHUB_REPO', 'your-repo-name');
