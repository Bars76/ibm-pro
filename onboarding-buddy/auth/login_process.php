<?php
// ============================================================
// Auth — Proses Login
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/login.php');
}

// Verifikasi CSRF
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('login', 'Token tidak valid. Silakan coba lagi.', 'error');
    redirect(APP_URL . '/login.php');
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    flash('login', 'Email dan password wajib diisi.', 'error');
    redirect(APP_URL . '/login.php');
}

$user = Database::row(
    'SELECT id, nama, email, password, role, divisi_id, is_active FROM users WHERE email = ?',
    [$email]
);

if (!$user || !password_verify($password, $user['password'])) {
    flash('login', 'Email atau password salah.', 'error');
    redirect(APP_URL . '/login.php');
}

if (!$user['is_active']) {
    flash('login', 'Akun Anda dinonaktifkan. Hubungi administrator.', 'warning');
    redirect(APP_URL . '/login.php');
}

setSession($user);
redirect(APP_URL . '/dashboard.php');
