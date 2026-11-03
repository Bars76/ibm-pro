<?php
// ============================================================
// Auth — Logout
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

destroySession();
flash('login', 'Anda telah berhasil keluar.', 'success');
redirect(APP_URL . '/login.php');
