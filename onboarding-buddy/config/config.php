<?php
// ============================================================
// Onboarding Buddy System — Konfigurasi Aplikasi
// ============================================================

define('APP_NAME',    'Onboarding Buddy System');
define('APP_URL',     'http://localhost/onboarding-buddy');
define('APP_VERSION', '1.0.0');
define('COMPANY',     'hali');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'buddysistem');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_NAME',    'obs_session');
define('SESSION_LIFETIME', 7200); // 2 jam

// Email (PHPMailer)
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'noreply@ptx.co.id');
define('MAIL_PASSWORD', 'your_app_password');
define('MAIL_FROM',     'noreply@ptx.co.id');
define('MAIL_FROM_NAME', APP_NAME);

// Upload
define('UPLOAD_DIR',     __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB

// Onboarding
define('ONBOARDING_WEEKS', 4); // durasi default onboarding (minggu)
