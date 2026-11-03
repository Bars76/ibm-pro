<?php
// ============================================================
// Admin — Kelola User
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole(['admin', 'hr']);
$user = currentUser();

// ─── POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('users', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/admin/users.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $nama         = trim($_POST['nama'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $role         = $_POST['role'] ?? 'karyawan_baru';
        $divisiId     = $_POST['divisi_id'] ?: null;
        $tglBergabung = $_POST['tanggal_bergabung'] ?: null;
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if ($nama === '' || $email === '') {
            flash('users', 'Nama dan email wajib diisi.', 'error');
            redirect(APP_URL . '/admin/users.php');
        }

        $allowedRoles = ['karyawan_baru','buddy','hr','manajer','admin'];
        if (!in_array($role, $allowedRoles, true)) $role = 'karyawan_baru';

        if ($action === 'tambah') {
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 8) {
                flash('users', 'Password minimal 8 karakter.', 'error');
                redirect(APP_URL . '/admin/users.php');
            }
            // Cek duplikasi email
            if (Database::count('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
                flash('users', 'Email sudah digunakan.', 'error');
                redirect(APP_URL . '/admin/users.php');
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            Database::execute(
                'INSERT INTO users (nama, email, password, role, divisi_id, tanggal_bergabung, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$nama, $email, $hash, $role, $divisiId, $tglBergabung, 1]
            );
            flash('users', 'User berhasil ditambahkan.', 'success');
        } else {
            $id = (int)$_POST['id'];
            // Reset password jika diisi
            $passwordSql = '';
            $params = [$nama, $email, $role, $divisiId, $tglBergabung, $isActive];
            if (!empty($_POST['password']) && strlen($_POST['password']) >= 8) {
                $passwordSql = ', password = ?';
                $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            }
            $params[] = $id;
            Database::execute(
                "UPDATE users SET nama=?, email=?, role=?, divisi_id=?, tanggal_bergabung=?, is_active=? $passwordSql WHERE id=?",
                $params
            );
            flash('users', 'User berhasil diperbarui.', 'success');
        }
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'toggle_aktif') {
        $id      = (int)$_POST['id'];
        $current = (int)$_POST['current'];
        Database::execute('UPDATE users SET is_active = ? WHERE id = ?', [$current ? 0 : 1, $id]);
        flash('users', 'Status user diperbarui.', 'success');
        redirect(APP_URL . '/admin/users.php');
    }
}

// ─── GET ─────────────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$filterRole = $_GET['role'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$sql = 'SELECT u.*, d.nama AS divisi_nama FROM users u LEFT JOIN divisi d ON d.id = u.divisi_id WHERE 1=1';
$params = [];
if ($search) {
    $sql .= ' AND (u.nama LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterRole) {
    $sql .= ' AND u.role = ?';
    $params[] = $filterRole;
}
$totalRows = Database::count(str_replace('SELECT u.*, d.nama AS divisi_nama', 'SELECT COUNT(*)', $sql), $params);
$sql .= " ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset";
$users = Database::rows($sql, $params);

$divisiList = Database::rows('SELECT id, nama FROM divisi ORDER BY nama');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2"></i>Manajemen User</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
    <i class="bi bi-plus-lg me-1"></i>Tambah User
  </button>
</div>

<?= showFlash('users') ?>

<!-- Filter -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-5">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama / email..."
               value="<?= clean($search) ?>" />
      </div>
      <div class="col-md-3">
        <select name="role" class="form-select form-select-sm">
          <option value="">Semua Role</option>
          <?php foreach (['karyawan_baru','buddy','hr','manajer','admin'] as $r): ?>
          <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= labelRole($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Cari</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Daftar User (<?= $totalRows ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Nama</th><th>Email</th><th>Role</th><th>Divisi</th><th>Bergabung</th><th>Status</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <span class="obs-avatar obs-avatar-sm"><?= strtoupper(substr($u['nama'],0,1)) ?></span>
            <?= clean($u['nama']) ?>
          </div>
        </td>
        <td><?= clean($u['email']) ?></td>
        <td><?= badgeRole($u['role']) ?></td>
        <td><?= $u['divisi_nama'] ?? '-' ?></td>
        <td><?= $u['tanggal_bergabung'] ? tglIndo($u['tanggal_bergabung']) : '-' ?></td>
        <td>
          <?= $u['is_active']
              ? '<span class="badge bg-success">Aktif</span>'
              : '<span class="badge bg-secondary">Nonaktif</span>' ?>
        </td>
        <td>
          <button class="btn btn-sm btn-outline-secondary btn-edit-user"
            data-bs-toggle="modal" data-bs-target="#modalEdit"
            data-id="<?= $u['id'] ?>" data-nama="<?= htmlspecialchars($u['nama']) ?>"
            data-email="<?= htmlspecialchars($u['email']) ?>" data-role="<?= $u['role'] ?>"
            data-divisi="<?= $u['divisi_id'] ?? '' ?>" data-tgl="<?= $u['tanggal_bergabung'] ?? '' ?>"
            data-active="<?= $u['is_active'] ?>">Edit</button>
          <?php if ($u['id'] !== $user['id']): ?>
          <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="toggle_aktif">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="current" value="<?= $u['is_active'] ?>">
            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
              <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <?= paginate($totalRows, $perPage, $page, '?q=' . urlencode($search) . '&role=' . $filterRole) ?>
  </div>
</div>

<!-- Modal: Tambah User -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tambah User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="tambah">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" name="password" class="form-control" required minlength="8" />
              <div class="form-text">Minimal 8 karakter.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select" required>
                <?php foreach (['karyawan_baru','buddy','hr','manajer','admin'] as $r): ?>
                <option value="<?= $r ?>"><?= labelRole($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Divisi</label>
              <select name="divisi_id" class="form-select">
                <option value="">-- Pilih Divisi --</option>
                <?php foreach ($divisiList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= clean($d['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Bergabung</label>
              <input type="date" name="tanggal_bergabung" class="form-control" value="<?= date('Y-m-d') ?>" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editUserId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="editUserNama" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="editUserEmail" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Password Baru</label>
              <input type="password" name="password" class="form-control" minlength="8" />
              <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="editUserRole" class="form-select" required>
                <?php foreach (['karyawan_baru','buddy','hr','manajer','admin'] as $r): ?>
                <option value="<?= $r ?>"><?= labelRole($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Divisi</label>
              <select name="divisi_id" id="editUserDivisi" class="form-select">
                <option value="">-- Pilih Divisi --</option>
                <?php foreach ($divisiList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= clean($d['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Bergabung</label>
              <input type="date" name="tanggal_bergabung" id="editUserTgl" class="form-control" />
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="is_active" id="editUserActive" class="form-check-input" value="1">
                <label class="form-check-label" for="editUserActive">Akun Aktif</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-edit-user').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('editUserId').value      = this.dataset.id;
    document.getElementById('editUserNama').value    = this.dataset.nama;
    document.getElementById('editUserEmail').value   = this.dataset.email;
    document.getElementById('editUserRole').value    = this.dataset.role;
    document.getElementById('editUserDivisi').value  = this.dataset.divisi || '';
    document.getElementById('editUserTgl').value     = this.dataset.tgl || '';
    document.getElementById('editUserActive').checked = this.dataset.active === '1';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
