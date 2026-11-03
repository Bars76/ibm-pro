<?php
// ============================================================
// Profil Pengguna
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('profil', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/profil.php');
    }
    $nama  = trim($_POST['nama'] ?? '');
    if ($nama === '') {
        flash('profil', 'Nama wajib diisi.', 'error');
        redirect(APP_URL . '/profil.php');
    }
    Database::execute('UPDATE users SET nama = ? WHERE id = ?', [$nama, $user['id']]);
    // Update session name
    $_SESSION['user_nama'] = $nama;
    flash('profil', 'Profil berhasil diperbarui.', 'success');
    redirect(APP_URL . '/profil.php');
}

$profil = Database::row(
    'SELECT u.*, d.nama AS divisi_nama FROM users u LEFT JOIN divisi d ON d.id = u.divisi_id WHERE u.id = ?',
    [$user['id']]
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7">
    <h4 class="fw-bold mb-4"><i class="bi bi-person-circle me-2"></i>Profil Saya</h4>
    <?= showFlash('profil') ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body d-flex align-items-center gap-4 py-4">
        <div class="obs-avatar obs-avatar-lg"><?= strtoupper(substr($profil['nama'], 0, 1)) ?></div>
        <div>
          <h5 class="mb-0 fw-bold"><?= clean($profil['nama']) ?></h5>
          <p class="mb-0 text-muted"><?= clean($profil['email']) ?></p>
          <div class="mt-2"><?= badgeRole($profil['role']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold">Informasi</div>
      <div class="card-body">
        <div class="row g-3 text-muted">
          <div class="col-md-6"><strong>Divisi:</strong><br><?= $profil['divisi_nama'] ?? '-' ?></div>
          <div class="col-md-6"><strong>Tanggal Bergabung:</strong><br><?= $profil['tanggal_bergabung'] ? tglIndo($profil['tanggal_bergabung']) : '-' ?></div>
          <div class="col-md-6"><strong>Terdaftar:</strong><br><?= tglIndo($profil['created_at']) ?></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Edit Nama</div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" value="<?= clean($profil['nama']) ?>" required />
          </div>
          <button type="submit" class="btn btn-primary">Simpan</button>
          <a href="<?= APP_URL ?>/auth/ganti_password.php" class="btn btn-outline-secondary ms-2">Ganti Password</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
