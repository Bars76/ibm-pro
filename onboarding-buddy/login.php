<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

// Redirect jika sudah login
if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css" />
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="card shadow-sm" style="width:100%;max-width:420px;">
  <div class="card-body p-4">
    <div class="text-center mb-4">
      <div class="obs-logo-circle mx-auto mb-3">OB</div>
      <h4 class="fw-bold"><?= APP_NAME ?></h4>
      <p class="text-muted small"><?= COMPANY ?> — Masuk ke akun Anda</p>
    </div>

    <?= showFlash('login') ?>

    <form method="POST" action="<?= APP_URL ?>/auth/login_process.php" novalidate>
      <?= csrfField() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= clean($_POST['email'] ?? '') ?>"
               placeholder="nama@ptx.co.id" required autofocus />
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Password</label>
        <div class="input-group">
          <input type="password" name="password" id="pwdInput" class="form-control"
                 placeholder="••••••••" required />
          <button type="button" class="btn btn-outline-secondary" id="togglePwd" tabindex="-1">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button type="submit" class="btn btn-primary btn-lg">Masuk</button>
      </div>
    </form>

    <!-- Akun Default -->
    <div class="mt-4 p-3 rounded" style="background:#f8fafc;border:1px dashed #cbd5e1;">
      <p class="small fw-semibold text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Akun Default</p>
      <table class="table table-sm table-borderless mb-0" style="font-size:.8rem;">
        <thead><tr><th class="text-muted py-0">Email</th><th class="text-muted py-0">Password</th><th class="text-muted py-0">Role</th></tr></thead>
        <tbody>
          <tr>
            <td class="py-1"><a href="#" class="text-decoration-none obs-fill-login" data-email="admin@ptx.co.id" data-pass="password">admin@ptx.co.id</a></td>
            <td class="py-1">password</td>
            <td class="py-1"><span class="badge bg-danger">Admin</span></td>
          </tr>
          <tr>
            <td class="py-1"><a href="#" class="text-decoration-none obs-fill-login" data-email="hr@ptx.co.id" data-pass="password">hr@ptx.co.id</a></td>
            <td class="py-1">password</td>
            <td class="py-1"><span class="badge bg-info text-dark">HR</span></td>
          </tr>
        </tbody>
      </table>
      <p class="mb-0 mt-1" style="font-size:.72rem;color:#94a3b8;">Klik email untuk mengisi otomatis.</p>
    </div>
  </div>
  <div class="card-footer text-center py-2 text-muted small">
    &copy; <?= date('Y') ?> <?= COMPANY ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Klik email → isi form otomatis
  document.querySelectorAll('.obs-fill-login').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      document.querySelector('input[name="email"]').value    = this.dataset.email;
      document.getElementById('pwdInput').value              = this.dataset.pass;
    });
  });

  document.getElementById('togglePwd').addEventListener('click', function() {
    const input = document.getElementById('pwdInput');
    const icon  = this.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  });
</script>
</body>
</html>
