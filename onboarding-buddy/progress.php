<?php
// ============================================================
// Progress Tracking — Catatan & Pemantauan Adaptasi
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

$assignmentId = isset($_GET['assignment']) ? (int)$_GET['assignment'] : 0;

// ─── POST: Tambah catatan progres ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('progress', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/progress.php' . ($assignmentId ? "?assignment=$assignmentId" : ''));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_catatan') {
        $assignId    = (int)$_POST['assignment_id'];
        $isi         = trim($_POST['isi'] ?? '');
        $adaptasi    = (int)($_POST['tingkat_adaptasi'] ?? 0);
        if ($isi === '') {
            flash('progress', 'Catatan wajib diisi.', 'error');
            redirect(APP_URL . "/progress.php?assignment=$assignId");
        }
        Database::execute(
            'INSERT INTO progress_notes (assignment_id, ditulis_oleh, isi, tingkat_adaptasi)
             VALUES (?, ?, ?, ?)',
            [$assignId, $user['id'], $isi, $adaptasi ?: null]
        );
        flash('progress', 'Catatan progres berhasil disimpan.', 'success');
        redirect(APP_URL . "/progress.php?assignment=$assignId");
    }

    if ($action === 'hapus_catatan') {
        $noteId = (int)$_POST['note_id'];
        // Hanya penulis atau HR/admin boleh hapus
        $note = Database::row('SELECT ditulis_oleh FROM progress_notes WHERE id = ?', [$noteId]);
        if ($note && ($note['ditulis_oleh'] === $user['id'] || in_array($user['role'], ['hr','admin']))) {
            Database::execute('DELETE FROM progress_notes WHERE id = ?', [$noteId]);
            flash('progress', 'Catatan dihapus.', 'success');
        }
        redirect(APP_URL . "/progress.php?assignment=$assignmentId");
    }
}

// ─── GET: Data ───────────────────────────────────────────────
if ($assignmentId) {
    // Validasi akses ke assignment ini
    $assignment = null;
    if (in_array($user['role'], ['hr','admin','manajer'])) {
        $assignment = Database::row(
            "SELECT ba.*, kb.nama AS karyawan_nama, kb.divisi_id,
                    b.nama AS buddy_nama, d.nama AS divisi_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             LEFT JOIN divisi d ON d.id = kb.divisi_id
             WHERE ba.id = ?",
            [$assignmentId]
        );
    } elseif ($user['role'] === 'buddy') {
        $assignment = Database::row(
            "SELECT ba.*, kb.nama AS karyawan_nama, b.nama AS buddy_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             WHERE ba.id = ? AND ba.buddy_id = ?",
            [$assignmentId, $user['id']]
        );
    } elseif ($user['role'] === 'karyawan_baru') {
        $assignment = Database::row(
            "SELECT ba.*, kb.nama AS karyawan_nama, b.nama AS buddy_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             WHERE ba.id = ? AND ba.karyawan_baru_id = ?",
            [$assignmentId, $user['id']]
        );
    }

    if (!$assignment) redirect(APP_URL . '/progress.php');

    $notes = Database::rows(
        "SELECT pn.*, u.nama AS penulis_nama, u.role AS penulis_role
         FROM progress_notes pn
         JOIN users u ON u.id = pn.ditulis_oleh
         WHERE pn.assignment_id = ?
         ORDER BY pn.created_at DESC",
        [$assignmentId]
    );

    $checklist = Database::rows(
        "SELECT ct.judul, ct.minggu_ke, ci.status, ci.selesai_pada
         FROM checklist_items ci
         JOIN checklist_templates ct ON ct.id = ci.template_id
         WHERE ci.assignment_id = ?
         ORDER BY ct.minggu_ke, ct.urutan",
        [$assignmentId]
    );

    $progres = hitungProgres($assignmentId);

    $checkIns = Database::rows(
        "SELECT * FROM check_ins WHERE assignment_id = ? ORDER BY jadwal DESC",
        [$assignmentId]
    );

} else {
    // List semua assignment yang boleh dilihat user
    if (in_array($user['role'], ['hr','admin'])) {
        $allAssignments = Database::rows(
            "SELECT ba.id, ba.status, ba.tanggal_mulai,
                    kb.nama AS karyawan_nama, b.nama AS buddy_nama, d.nama AS divisi_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             LEFT JOIN divisi d ON d.id = kb.divisi_id
             ORDER BY ba.status = 'aktif' DESC, ba.tanggal_mulai DESC"
        );
    } elseif ($user['role'] === 'manajer') {
        $allAssignments = Database::rows(
            "SELECT ba.id, ba.status, ba.tanggal_mulai,
                    kb.nama AS karyawan_nama, b.nama AS buddy_nama, d.nama AS divisi_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             LEFT JOIN divisi d ON d.id = kb.divisi_id
             WHERE kb.divisi_id = ?
             ORDER BY ba.tanggal_mulai DESC",
            [$user['divisi_id']]
        );
    } elseif ($user['role'] === 'buddy') {
        $allAssignments = Database::rows(
            "SELECT ba.id, ba.status, ba.tanggal_mulai,
                    kb.nama AS karyawan_nama, b.nama AS buddy_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             WHERE ba.buddy_id = ?
             ORDER BY ba.tanggal_mulai DESC",
            [$user['id']]
        );
    } else {
        // karyawan_baru: redirect ke assignment sendiri
        $myAssign = Database::row(
            "SELECT id FROM buddy_assignments WHERE karyawan_baru_id = ? AND status = 'aktif' LIMIT 1",
            [$user['id']]
        );
        if ($myAssign) redirect(APP_URL . '/progress.php?assignment=' . $myAssign['id']);
        $allAssignments = [];
    }
    foreach ($allAssignments as &$a) {
        $a['progres'] = hitungProgres($a['id']);
    }
    unset($a);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-graph-up me-2"></i>Progress Tracking</h4>
  <?php if ($assignmentId): ?>
  <a href="<?= APP_URL ?>/progress.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
  <?php endif; ?>
</div>

<?= showFlash('progress') ?>

<?php if (!$assignmentId): ?>
<!-- ── List Assignment ──────────────────────────────────────── -->
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Karyawan Baru</th><th>Buddy</th><?= in_array($user['role'],['hr','admin','manajer']) ? '<th>Divisi</th>' : '' ?><th>Mulai</th><th>Progres</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($allAssignments as $a): ?>
      <tr>
        <td><?= clean($a['karyawan_nama']) ?></td>
        <td><?= clean($a['buddy_nama']) ?></td>
        <?php if (in_array($user['role'],['hr','admin','manajer'])): ?>
        <td><?= $a['divisi_nama'] ?? '-' ?></td>
        <?php endif; ?>
        <td><?= tglIndo($a['tanggal_mulai']) ?></td>
        <td style="min-width:130px;">
          <div class="progress" style="height:7px;">
            <div class="progress-bar bg-<?= $a['progres'] >= 80 ? 'success' : ($a['progres'] >= 40 ? 'warning' : 'danger') ?>"
                 style="width:<?= $a['progres'] ?>%"></div>
          </div>
          <small><?= $a['progres'] ?>%</small>
        </td>
        <td><?= badgeStatus($a['status']) ?></td>
        <td><a href="?assignment=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$allAssignments): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ── Detail Progres Assignment ─────────────────────────────── -->

<div class="row g-4 mb-4">
  <!-- Info -->
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Informasi</h6>
        <p class="mb-1"><strong>Karyawan:</strong> <?= clean($assignment['karyawan_nama']) ?></p>
        <p class="mb-1"><strong>Buddy:</strong> <?= clean($assignment['buddy_nama']) ?></p>
        <?php if (isset($assignment['divisi_nama'])): ?>
        <p class="mb-1"><strong>Divisi:</strong> <?= $assignment['divisi_nama'] ?? '-' ?></p>
        <?php endif; ?>
        <p class="mb-1"><strong>Mulai:</strong> <?= tglIndo($assignment['tanggal_mulai']) ?></p>
        <p class="mb-3"><strong>Status:</strong> <?= badgeStatus($assignment['status']) ?></p>
        <!-- Progres bar -->
        <div class="progress mb-1" style="height:12px;">
          <div class="progress-bar bg-success" style="width:<?= $progres ?>%"></div>
        </div>
        <small class="text-muted"><?= $progres ?>% checklist selesai</small>
      </div>
    </div>
  </div>

  <!-- Checklist ringkasan -->
  <div class="col-md-4">
    <div class="card shadow-sm" style="max-height:320px; overflow-y:auto;">
      <div class="card-header fw-semibold small">Checklist</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($checklist as $c): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
          <small><?= clean($c['judul']) ?></small>
          <?= badgeStatus($c['status']) ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Check-ins -->
  <div class="col-md-4">
    <div class="card shadow-sm" style="max-height:320px; overflow-y:auto;">
      <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
        <span>Check-Ins</span>
        <a href="<?= APP_URL ?>/checkin.php?assignment=<?= $assignmentId ?>" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:2px 8px;">Kelola</a>
      </div>
      <?php if ($checkIns): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($checkIns as $ci): ?>
        <li class="list-group-item py-2">
          <small class="fw-semibold"><?= dtIndo($ci['jadwal']) ?></small>
          <br><?= badgeStatus($ci['status']) ?>
          <?php if ($ci['catatan_buddy']): ?><br><small class="text-muted"><?= clean($ci['catatan_buddy']) ?></small><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="p-3 small text-muted">Belum ada check-in.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Catatan Progres -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-journal-text me-1"></i>Catatan Progres Adaptasi</span>
    <?php if (in_array($user['role'], ['buddy','hr','admin'])): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCatatan">
      <i class="bi bi-plus-lg me-1"></i>Tambah Catatan
    </button>
    <?php endif; ?>
  </div>
  <?php if ($notes): ?>
  <div class="list-group list-group-flush">
    <?php foreach ($notes as $note): ?>
    <div class="list-group-item">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="fw-semibold small"><?= clean($note['penulis_nama']) ?></span>
          <?= badgeRole($note['penulis_role']) ?>
          <?php if ($note['tingkat_adaptasi']): ?>
          <span class="ms-2 badge bg-light text-dark border">
            Adaptasi: <?= str_repeat('⭐', $note['tingkat_adaptasi']) ?>
          </span>
          <?php endif; ?>
          <p class="mt-2 mb-0"><?= nl2br(clean($note['isi'])) ?></p>
          <small class="text-muted"><?= dtIndo($note['created_at']) ?></small>
        </div>
        <?php if ($note['ditulis_oleh'] == $user['id'] || in_array($user['role'], ['hr','admin'])): ?>
        <form method="POST" onsubmit="return confirm('Hapus catatan ini?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="hapus_catatan">
          <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
          <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
          <button type="submit" class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p class="p-3 text-muted small">Belum ada catatan progres.</p>
  <?php endif; ?>
</div>

<!-- Modal: Tambah Catatan -->
<?php if (in_array($user['role'], ['buddy','hr','admin'])): ?>
<div class="modal fade" id="modalCatatan" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tambah Catatan Progres</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="tambah_catatan">
        <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Catatan <span class="text-danger">*</span></label>
            <textarea name="isi" class="form-control" rows="4" required placeholder="Tuliskan catatan perkembangan adaptasi..."></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label">Tingkat Adaptasi</label>
            <select name="tingkat_adaptasi" class="form-select">
              <option value="">-- Pilih --</option>
              <option value="1">1 — Butuh banyak bantuan</option>
              <option value="2">2 — Mulai beradaptasi</option>
              <option value="3">3 — Cukup baik</option>
              <option value="4">4 — Baik</option>
              <option value="5">5 — Sangat baik</option>
            </select>
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
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
