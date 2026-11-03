<?php
// ============================================================
// Auth — Ganti Password
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireLogin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('password', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/auth/ganti_password.php');
    }

    $lama  = $_POST['password_lama'] ?? '';
    $baru  = $_POST['password_baru'] ?? '';
    $ulang = $_POST['password_ulang'] ?? '';

    if (strlen($baru) < 8) {
        flash('password', 'Password baru minimal 8 karakter.', 'error');
        redirect(APP_URL . '/auth/ganti_password.php');
    }
    if ($baru !== $ulang) {
        flash('password', 'Konfirmasi password tidak cocok.', 'error');
        redirect(APP_URL . '/auth/ganti_password.php');
    }

    $row = Database::row('SELECT password FROM users WHERE id = ?', [$user['id']]);
    if (!$row || !password_verify($lama, $row['password'])) {
        flash('password', 'Password lama salah.', 'error');
        redirect(APP_URL . '/auth/ganti_password.php');
    }

    $hash = password_hash($baru, PASSWORD_BCRYPT);
    Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $user['id']]);
    flash('password', 'Password berhasil diubah.', 'success');
    redirect(APP_URL . '/auth/ganti_password.php');
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4" style="max-width:500px;">
  <h4 class="fw-bold mb-4"><i class="bi bi-lock-fill me-2"></i>Ganti Password</h4>
  <?= showFlash('password') ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Password Lama</label>
          <input type="password" name="password_lama" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Password Baru</label>
          <input type="password" name="password_baru" class="form-control" required />
          <div class="form-text">Minimal 8 karakter.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi Password Baru</label>
          <input type="password" name="password_ulang" class="form-control" required />
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-link">Batal</a>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
