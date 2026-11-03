<?php
// ============================================================
// Admin — Kelola Divisi
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole(['admin', 'hr']);
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('divisi', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/admin/divisi.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $nama      = trim($_POST['nama'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if ($nama === '') {
            flash('divisi', 'Nama divisi wajib diisi.', 'error');
            redirect(APP_URL . '/admin/divisi.php');
        }
        if ($action === 'tambah') {
            Database::execute('INSERT INTO divisi (nama, deskripsi) VALUES (?, ?)', [$nama, $deskripsi]);
            flash('divisi', 'Divisi berhasil ditambahkan.', 'success');
        } else {
            $id = (int)$_POST['id'];
            Database::execute('UPDATE divisi SET nama=?, deskripsi=? WHERE id=?', [$nama, $deskripsi, $id]);
            flash('divisi', 'Divisi berhasil diperbarui.', 'success');
        }
    } elseif ($action === 'hapus') {
        $id = (int)$_POST['id'];
        // Cek ada user di divisi ini
        $count = Database::count('SELECT COUNT(*) FROM users WHERE divisi_id = ?', [$id]);
        if ($count > 0) {
            flash('divisi', "Tidak dapat menghapus divisi — masih ada $count user di divisi ini.", 'error');
        } else {
            Database::execute('DELETE FROM divisi WHERE id = ?', [$id]);
            flash('divisi', 'Divisi berhasil dihapus.', 'success');
        }
    }
    redirect(APP_URL . '/admin/divisi.php');
}

$divisiList = Database::rows(
    "SELECT d.*, COUNT(u.id) AS jumlah_user
     FROM divisi d
     LEFT JOIN users u ON u.divisi_id = d.id AND u.is_active = 1
     GROUP BY d.id
     ORDER BY d.nama"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>Manajemen Divisi</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
    <i class="bi bi-plus-lg me-1"></i>Tambah Divisi
  </button>
</div>

<?= showFlash('divisi') ?>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>#</th><th>Nama Divisi</th><th>Deskripsi</th><th>Jumlah User</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($divisiList as $i => $d): ?>
      <tr>
        <td class="text-muted"><?= $i + 1 ?></td>
        <td class="fw-semibold"><?= clean($d['nama']) ?></td>
        <td class="text-muted"><?= $d['deskripsi'] ? clean($d['deskripsi']) : '-' ?></td>
        <td><span class="badge bg-primary"><?= $d['jumlah_user'] ?></span></td>
        <td>
          <button class="btn btn-sm btn-outline-secondary btn-edit-divisi"
            data-bs-toggle="modal" data-bs-target="#modalEdit"
            data-id="<?= $d['id'] ?>" data-nama="<?= htmlspecialchars($d['nama']) ?>"
            data-deskripsi="<?= htmlspecialchars($d['deskripsi'] ?? '') ?>">Edit</button>
          <?php if ($d['jumlah_user'] == 0): ?>
          <form method="POST" class="d-inline" onsubmit="return confirm('Hapus divisi ini?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tambah Divisi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="tambah">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Divisi <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" required />
          </div>
          <div class="mb-2">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Divisi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editDivisiId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Divisi <span class="text-danger">*</span></label>
            <input type="text" name="nama" id="editDivisiNama" class="form-control" required />
          </div>
          <div class="mb-2">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" id="editDivisiDesk" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-edit-divisi').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('editDivisiId').value   = this.dataset.id;
    document.getElementById('editDivisiNama').value = this.dataset.nama;
    document.getElementById('editDivisiDesk').value = this.dataset.deskripsi;
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
