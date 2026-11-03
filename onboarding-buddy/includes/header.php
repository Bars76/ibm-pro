<?php
// includes/header.php — Layout header & navbar
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireLogin();
$user      = currentUser();
$notifCount = countNotif($user['id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css" />
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= APP_URL ?>/dashboard.php">
      <span class="obs-nav-logo">OB</span>
      <span class="d-none d-md-inline"><?= APP_NAME ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">

        <?php if ($user['role'] === 'karyawan_baru'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/checklist.php"><i class="bi bi-check2-square me-1"></i>Checklist</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/checkin.php"><i class="bi bi-calendar-check me-1"></i>Check-In</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/feedback.php"><i class="bi bi-chat-square-heart me-1"></i>Feedback</a></li>

        <?php elseif ($user['role'] === 'buddy'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/buddy.php"><i class="bi bi-people-fill me-1"></i>Karyawan Baru</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/checkin.php"><i class="bi bi-calendar-check me-1"></i>Check-In</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/feedback.php"><i class="bi bi-chat-square-heart me-1"></i>Feedback</a></li>

        <?php elseif ($user['role'] === 'hr'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/buddy.php"><i class="bi bi-person-check-fill me-1"></i>Buddy Assignment</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/checklist.php"><i class="bi bi-list-check me-1"></i>Master Checklist</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/progress.php"><i class="bi bi-graph-up me-1"></i>Progres</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/laporan.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan</a></li>

        <?php elseif ($user['role'] === 'manajer'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/progress.php"><i class="bi bi-graph-up me-1"></i>Progres Tim</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/laporan.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan</a></li>

        <?php elseif ($user['role'] === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/admin/users.php"><i class="bi bi-person-gear me-1"></i>Users</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/admin/divisi.php"><i class="bi bi-diagram-3 me-1"></i>Divisi</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/admin/checklist.php"><i class="bi bi-list-check me-1"></i>Checklist</a></li>
        <?php endif; ?>

      </ul>

      <!-- Notifikasi & user menu -->
      <ul class="navbar-nav ms-auto align-items-center gap-1">
        <li class="nav-item">
          <a class="nav-link position-relative" href="<?= APP_URL ?>/notifikasi.php">
            <i class="bi bi-bell-fill"></i>
            <?php if ($notifCount > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem;">
                <?= $notifCount > 99 ? '99+' : $notifCount ?>
              </span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
            <span class="obs-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></span>
            <span class="d-none d-md-inline small"><?= clean($user['nama']) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= labelRole($user['role']) ?></span></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/profil.php"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/auth/ganti_password.php"><i class="bi bi-lock me-2"></i>Ganti Password</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="obs-main container-fluid px-4 py-4">
