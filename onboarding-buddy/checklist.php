<?php
// ============================================================
// Checklist — HR: Master Checklist | Karyawan Baru: Checklist Saya
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireRole(['hr', 'admin', 'karyawan_baru', 'buddy']);
$user = currentUser();
$isHR = in_array($user['role'], ['hr', 'admin']);

// ─── POST: Tambah/edit template (HR/Admin) ───────────────────
if ($isHR && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('checklist', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/checklist.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $judul     = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $divisiId  = $_POST['divisi_id'] ?: null;
        $minggu    = (int)($_POST['minggu_ke'] ?? 1);
        $urutan    = (int)($_POST['urutan'] ?? 0);

        if ($judul === '') {
            flash('checklist', 'Judul wajib diisi.', 'error');
            redirect(APP_URL . '/checklist.php');
        }

        if ($action === 'tambah') {
            Database::execute(
                'INSERT INTO checklist_templates (divisi_id, judul, deskripsi, minggu_ke, urutan, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$divisiId, $judul, $deskripsi, $minggu, $urutan, $user['id']]
            );
            flash('checklist', 'Item checklist berhasil ditambahkan.', 'success');
        } else {
            $id = (int)$_POST['id'];
            Database::execute(
                'UPDATE checklist_templates SET divisi_id=?, judul=?, deskripsi=?, minggu_ke=?, urutan=?, is_active=?
                 WHERE id=?',
                [$divisiId, $judul, $deskripsi, $minggu, $urutan, (int)isset($_POST['is_active']), $id]
            );
            flash('checklist', 'Item checklist berhasil diperbarui.', 'success');
        }

    } elseif ($action === 'hapus') {
        $id = (int)$_POST['id'];
        Database::execute('DELETE FROM checklist_templates WHERE id = ?', [$id]);
        flash('checklist', 'Item checklist dihapus.', 'success');
    }

    redirect(APP_URL . '/checklist.php');
}

// ─── POST: Update status checklist (Karyawan Baru / Buddy) ──
if (!$isHR && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('checklist', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/checklist.php');
    }
    $itemId  = (int)($_POST['item_id'] ?? 0);
    $status  = $_POST['status'] ?? 'belum';
    $catatan = trim($_POST['catatan'] ?? '');

    if (!in_array($status, ['belum', 'sedang', 'selesai'], true)) $status = 'belum';

    $selesaiPada = $status === 'selesai' ? date('Y-m-d H:i:s') : null;

    Database::execute(
        'UPDATE checklist_items SET status=?, catatan=?, selesai_pada=? WHERE id=?',
        [$status, $catatan, $selesaiPada, $itemId]
    );
    flash('checklist', 'Status checklist diperbarui.', 'success');
    redirect(APP_URL . '/checklist.php');
}

// ─── GET: Ambil data ─────────────────────────────────────────
$divisiList = Database::rows('SELECT id, nama FROM divisi ORDER BY nama');

if ($isHR) {
    $filterDivisi = isset($_GET['divisi']) ? (int)$_GET['divisi'] : null;
    $filterMinggu = isset($_GET['minggu']) ? (int)$_GET['minggu'] : null;

    $sql    = 'SELECT ct.*, d.nama AS divisi_nama, u.nama AS creator_nama
               FROM checklist_templates ct
               LEFT JOIN divisi d ON d.id = ct.divisi_id
               LEFT JOIN users u ON u.id = ct.created_by
               WHERE 1=1';
    $params = [];

    if ($filterDivisi) { $sql .= ' AND ct.divisi_id = ?'; $params[] = $filterDivisi; }
    if ($filterMinggu) { $sql .= ' AND ct.minggu_ke = ?'; $params[] = $filterMinggu; }

    $sql .= ' ORDER BY ct.minggu_ke, ct.urutan';
    $templates = Database::rows($sql, $params);

} else {
    // Ambil assignment aktif user (atau buddy melihat karyawan)
    $assignmentId = isset($_GET['assignment']) ? (int)$_GET['assignment'] : null;

    if ($user['role'] === 'buddy' && $assignmentId) {
        // Buddy melihat checklist karyawan binaannya
        $assignment = Database::row(
            "SELECT ba.*, u.nama AS karyawan_nama
             FROM buddy_assignments ba
             JOIN users u ON u.id = ba.karyawan_baru_id
             WHERE ba.id = ? AND ba.buddy_id = ? AND ba.status = 'aktif'",
            [$assignmentId, $user['id']]
        );
    } else {
        $assignment = Database::row(
            "SELECT ba.*, u.nama AS buddy_nama
             FROM buddy_assignments ba
             JOIN users u ON u.id = ba.buddy_id
             WHERE ba.karyawan_baru_id = ? AND ba.status = 'aktif'
             ORDER BY ba.created_at DESC LIMIT 1",
            [$user['id']]
        );
    }

    $items = [];
    if ($assignment) {
        $items = Database::rows(
            "SELECT ci.id, ct.judul, ct.deskripsi, ct.minggu_ke, ct.urutan,
                    ci.status, ci.catatan, ci.selesai_pada
             FROM checklist_items ci
             JOIN checklist_templates ct ON ct.id = ci.template_id
             WHERE ci.assignment_id = ?
             ORDER BY ct.minggu_ke, ct.urutan",
            [$assignment['id']]
        );
        // group by minggu
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['minggu_ke']][] = $item;
        }
    }
    $progres = $assignment ? hitungProgres($assignment['id']) : 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">
    <i class="bi bi-check2-square me-2"></i>
    <?= $isHR ? 'Master Checklist Onboarding' : 'Checklist Onboarding Saya' ?>
  </h4>
  <?php if ($isHR): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
    <i class="bi bi-plus-lg me-1"></i>Tambah Item
  </button>
  <?php endif; ?>
</div>

<?= showFlash('checklist') ?>

<?php if ($isHR): ?>
<!-- ── HR: Filter & Tabel Template ───────────────────────────── -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-4">
        <label class="form-label">Divisi</label>
        <select name="divisi" class="form-select form-select-sm">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisiList as $d): ?>
          <option value="<?= $d['id'] ?>" <?= (int)($filterDivisi ?? 0) === $d['id'] ? 'selected' : '' ?>>
            <?= clean($d['nama']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Minggu ke-</label>
        <select name="minggu" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php for ($w = 1; $w <= ONBOARDING_WEEKS; $w++): ?>
          <option value="<?= $w ?>" <?= (int)($filterMinggu ?? 0) === $w ? 'selected' : '' ?>>Minggu <?= $w ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>#</th><th>Judul</th><th>Divisi</th><th>Minggu</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php if ($templates): ?>
        <?php foreach ($templates as $i => $t): ?>
        <tr>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td>
            <p class="mb-0 fw-semibold"><?= clean($t['judul']) ?></p>
            <small class="text-muted"><?= clean($t['deskripsi']) ?></small>
          </td>
          <td><?= $t['divisi_nama'] ? clean($t['divisi_nama']) : '<span class="text-muted">Umum</span>' ?></td>
          <td>Minggu <?= $t['minggu_ke'] ?></td>
          <td><?= $t['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary btn-edit-checklist" data-bs-toggle="modal" data-bs-target="#modalEdit"
              data-id="<?= $t['id'] ?>" data-judul="<?= htmlspecialchars($t['judul']) ?>"
              data-deskripsi="<?= htmlspecialchars($t['deskripsi']) ?>" data-divisi="<?= $t['divisi_id'] ?>"
              data-minggu="<?= $t['minggu_ke'] ?>" data-urutan="<?= $t['urutan'] ?>" data-active="<?= $t['is_active'] ?>">
              Edit
            </button>
            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus item ini?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="hapus">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada item checklist.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tambah Item Checklist</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="tambah">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Judul <span class="text-danger">*</span></label>
            <input type="text" name="judul" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="2"></textarea>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Divisi</label>
              <select name="divisi_id" class="form-select">
                <option value="">Umum (semua divisi)</option>
                <?php foreach ($divisiList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= clean($d['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Minggu ke-</label>
              <select name="minggu_ke" class="form-select">
                <?php for ($w = 1; $w <= ONBOARDING_WEEKS; $w++): ?>
                <option value="<?= $w ?>">Minggu <?= $w ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Urutan</label>
              <input type="number" name="urutan" class="form-control" value="0" min="0" />
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

<!-- Modal: Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Item Checklist</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Judul <span class="text-danger">*</span></label>
            <input type="text" name="judul" id="editJudul" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" id="editDeskripsi" class="form-control" rows="2"></textarea>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Divisi</label>
              <select name="divisi_id" id="editDivisi" class="form-select">
                <option value="">Umum (semua divisi)</option>
                <?php foreach ($divisiList as $d): ?>
                <option value="<?= $d['id'] ?>"><?= clean($d['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Minggu ke-</label>
              <select name="minggu_ke" id="editMinggu" class="form-select">
                <?php for ($w = 1; $w <= ONBOARDING_WEEKS; $w++): ?>
                <option value="<?= $w ?>">Minggu <?= $w ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Urutan</label>
              <input type="number" name="urutan" id="editUrutan" class="form-control" min="0" />
            </div>
          </div>
          <div class="form-check mt-3">
            <input type="checkbox" name="is_active" id="editActive" class="form-check-input" value="1">
            <label class="form-check-label" for="editActive">Aktif</label>
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
document.querySelectorAll('.btn-edit-checklist').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('editId').value = this.dataset.id;
    document.getElementById('editJudul').value = this.dataset.judul;
    document.getElementById('editDeskripsi').value = this.dataset.deskripsi;
    document.getElementById('editDivisi').value = this.dataset.divisi || '';
    document.getElementById('editMinggu').value = this.dataset.minggu;
    document.getElementById('editUrutan').value = this.dataset.urutan;
    document.getElementById('editActive').checked = this.dataset.active === '1';
  });
});
</script>

<?php else: ?>
<!-- ── Karyawan Baru / Buddy: Checklist Progress ───────────── -->

<?php if (!$assignment ?? true): ?>
  <div class="alert alert-info">Belum ada assignment aktif.</div>
<?php else: ?>

<div class="d-flex align-items-center gap-3 mb-4">
  <div class="flex-grow-1">
    <div class="progress" style="height:14px; border-radius:8px;">
      <div class="progress-bar bg-success" style="width:<?= $progres ?>%;">
        <?= $progres >= 10 ? $progres . '%' : '' ?>
      </div>
    </div>
  </div>
  <span class="fw-bold text-success"><?= $progres ?>% selesai</span>
</div>

<?php foreach (($grouped ?? []) as $minggu => $mingguItems): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold bg-light">
    <i class="bi bi-calendar-week me-2"></i>Minggu <?= $minggu ?>
  </div>
  <div class="list-group list-group-flush">
    <?php foreach ($mingguItems as $item): ?>
    <div class="list-group-item">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="flex-grow-1">
          <p class="mb-0 fw-semibold"><?= clean($item['judul']) ?></p>
          <?php if ($item['deskripsi']): ?>
            <small class="text-muted"><?= clean($item['deskripsi']) ?></small>
          <?php endif; ?>
          <?php if ($item['selesai_pada']): ?>
            <br><small class="text-success"><i class="bi bi-check-circle me-1"></i>Selesai <?= dtIndo($item['selesai_pada']) ?></small>
          <?php endif; ?>
          <?php if ($item['catatan']): ?>
            <br><small class="text-muted"><i class="bi bi-chat me-1"></i><?= clean($item['catatan']) ?></small>
          <?php endif; ?>
        </div>
        <?= badgeStatus($item['status']) ?>
        <?php if ($user['role'] === 'karyawan_baru'): ?>
        <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal"
          data-bs-target="#modalStatus"
          data-item-id="<?= $item['id'] ?>"
          data-status="<?= $item['status'] ?>"
          data-catatan="<?= htmlspecialchars($item['catatan'] ?? '') ?>">
          Update
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Modal: Update Status (Karyawan Baru) -->
<?php if ($user['role'] === 'karyawan_baru'): ?>
<div class="modal fade" id="modalStatus" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Update Status</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="item_id" id="statusItemId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="statusSelect" class="form-select">
              <option value="belum">Belum</option>
              <option value="sedang">Sedang</option>
              <option value="selesai">Selesai</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Catatan (opsional)</label>
            <textarea name="catatan" id="statusCatatan" class="form-control" rows="2"></textarea>
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
document.querySelectorAll('[data-bs-target="#modalStatus"]').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('statusItemId').value  = this.dataset.itemId;
    document.getElementById('statusSelect').value  = this.dataset.status;
    document.getElementById('statusCatatan').value = this.dataset.catatan;
  });
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
