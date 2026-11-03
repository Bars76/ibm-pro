<?php
// ============================================================
// Admin — Kelola Master Checklist (shortcut ke checklist.php)
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole(['admin', 'hr']);
redirect(APP_URL . '/checklist.php');
