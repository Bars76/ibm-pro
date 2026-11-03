<?php
// ============================================================
// Buddy Assignment — HR mengelola penugasan buddy
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireRole(['hr', 'admin', 'buddy', 'karyawan_baru']);
$user = currentUser();
$isHR = in_array($user['role'], ['hr', 'admin']);

// ─── POST: Assign, edit, selesaikan (HR) ─────────────────────
if ($isHR && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('buddy', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/buddy.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $karyawanId   = (int)$_POST['karyawan_id'];
        $buddyId      = (int)$_POST['buddy_id'];
        $tglMulai     = $_POST['tanggal_mulai'];
        $tglSelesai   = $_POST['tanggal_selesai'] ?: null;
        $catatan      = trim($_POST['catatan'] ?? '');

        // Cek karyawan belum punya assignment aktif
        $existing = Database::count(
            "SELECT COUNT(*) FROM buddy_assignments WHERE karyawan_baru_id = ? AND status = 'aktif'",
            [$karyawanId]
        );
        if ($existing > 0) {
            flash('buddy', 'Karyawan ini sudah memiliki buddy aktif.', 'error');
            redirect(APP_URL . '/buddy.php');
        }

        $assignId = Database::execute(
            'INSERT INTO buddy_assignments (karyawan_baru_id, buddy_id, hr_id, tanggal_mulai, tanggal_selesai, catatan)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$karyawanId, $buddyId, $user['id'], $tglMulai, $tglSelesai, $catatan]
        );

        // Auto-buat checklist items dari template (sesuai divisi karyawan + umum)
        $karyawan = Database::row('SELECT divisi_id FROM users WHERE id = ?', [$karyawanId]);
        $templates = Database::rows(
            "SELECT id FROM checklist_templates WHERE is_active = 1
             AND (divisi_id IS NULL OR divisi_id = ?)
             ORDER BY minggu_ke, urutan",
            [$karyawan['divisi_id']]
        );
        foreach ($templates as $tmpl) {
            Database::execute(
                'INSERT IGNORE INTO checklist_items (assignment_id, template_id) VALUES (?, ?)',
                [$assignId, $tmpl['id']]
            );
        }

        // Notifikasi ke karyawan & buddy
        $karyawanInfo = Database::row('SELECT nama FROM users WHERE id = ?', [$karyawanId]);
        $buddyInfo    = Database::row('SELECT nama FROM users WHERE id = ?', [$buddyId]);
        buatNotif($karyawanId, 'Buddy Anda Telah Ditugaskan',
            'Buddy Anda adalah ' . $buddyInfo['nama'] . '. Selamat memulai onboarding!',
            'info', APP_URL . '/dashboard.php');
        buatNotif($buddyId, 'Karyawan Baru Ditugaskan ke Anda',
            $karyawanInfo['nama'] . ' telah ditugaskan sebagai karyawan baru Anda.',
            'tugas', APP_URL . '/buddy.php');

        flash('buddy', 'Buddy berhasil ditugaskan.', 'success');
        redirect(APP_URL . '/buddy.php');
    }

    if ($action === 'selesaikan') {
        $id = (int)$_POST['id'];
        Database::execute(
            "UPDATE buddy_assignments SET status = 'selesai', tanggal_selesai = CURDATE() WHERE id = ?",
            [$id]
        );
        flash('buddy', 'Assignment onboarding diselesaikan.', 'success');
        redirect(APP_URL . '/buddy.php');
    }

    if ($action === 'batalkan') {
        $id = (int)$_POST['id'];
        Database::execute(
            "UPDATE buddy_assignments SET status = 'dibatalkan' WHERE id = ?",
            [$id]
        );
        flash('buddy', 'Assignment dibatalkan.', 'warning');
        redirect(APP_URL . '/buddy.php');
    }
}

// ─── GET: Ambil data ─────────────────────────────────────────
$tab = $_GET['tab'] ?? 'aktif';

if ($isHR) {
    // Daftar assignment per status
    $assignments = Database::rows(
        "SELECT ba.id, ba.tanggal_mulai, ba.tanggal_selesai, ba.status, ba.catatan,
                kb.nama AS karyawan_nama, kb.email AS karyawan_email,
                b.nama  AS buddy_nama,   b.email  AS buddy_email,
                d.nama  AS divisi_nama
         FROM buddy_assignments ba
         JOIN users kb ON kb.id = ba.karyawan_baru_id
         JOIN users b  ON b.id  = ba.buddy_id
         LEFT JOIN divisi d ON d.id = kb.divisi_id
         WHERE ba.status = ?
         ORDER BY ba.created_at DESC",
        [$tab === 'semua' ? 'aktif' : $tab]  // simplified — filter handled below
    );

    // Override: semua status
    if ($tab === 'semua') {
        $assignments = Database::rows(
            "SELECT ba.id, ba.tanggal_mulai, ba.tanggal_selesai, ba.status, ba.catatan,
                    kb.nama AS karyawan_nama, kb.email AS karyawan_email,
                    b.nama  AS buddy_nama,   b.email  AS buddy_email,
                    d.nama  AS divisi_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             LEFT JOIN divisi d ON d.id = kb.divisi_id
             ORDER BY ba.created_at DESC"
        );
    }

    foreach ($assignments as &$a) {
        $a['progres'] = hitungProgres($a['id']);
    }
    unset($a);

    // Daftar karyawan baru belum di-assign
    $unassigned = Database::rows(
        "SELECT u.id, u.nama, u.email, u.tanggal_bergabung, d.nama AS divisi_nama
         FROM users u
         LEFT JOIN divisi d ON d.id = u.divisi_id
         WHERE u.role = 'karyawan_baru' AND u.is_active = 1
           AND u.id NOT IN (SELECT karyawan_baru_id FROM buddy_assignments WHERE status = 'aktif')
         ORDER BY u.tanggal_bergabung DESC"
    );

    // Daftar buddy tersedia
    $buddyList = Database::rows(
        "SELECT u.id, u.nama, u.email, d.nama AS divisi_nama,
                COUNT(ba.id) AS jumlah_karyawan
         FROM users u
         LEFT JOIN divisi d ON d.id = u.divisi_id
         LEFT JOIN buddy_assignments ba ON ba.buddy_id = u.id AND ba.status = 'aktif'
         WHERE u.role = 'buddy' AND u.is_active = 1
         GROUP BY u.id
         ORDER BY u.nama"
    );

    // Pre-select karyawan jika ada parameter assign
    $preAssign = isset($_GET['assign']) ? (int)$_GET['assign'] : null;

} else {
    // Role buddy/karyawan_baru: lihat detail assignment sendiri
    $assignmentId = isset($_GET['assignment']) ? (int)$_GET['assignment'] : null;

    if ($user['role'] === 'buddy') {
        if ($assignmentId) {
            $detail = Database::row(
                "SELECT ba.*, kb.nama AS karyawan_nama, kb.email AS karyawan_email,
                         d.nama AS divisi_nama
                 FROM buddy_assignments ba
                 JOIN users kb ON kb.id = ba.karyawan_baru_id
                 LEFT JOIN divisi d ON d.id = kb.divisi_id
                 WHERE ba.id = ? AND ba.buddy_id = ?",
                [$assignmentId, $user['id']]
            );
        }
        $myKaryawan = Database::rows(
            "SELECT ba.id AS assignment_id, kb.nama, kb.email, ba.tanggal_mulai, ba.status
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             WHERE ba.buddy_id = ?
             ORDER BY ba.created_at DESC",
            [$user['id']]
        );
        foreach ($myKaryawan as &$k) {
            $k['progres'] = hitungProgres($k['assignment_id']);
        }
        unset($k);
    } else {
        // karyawan_baru
        $myAssignment = Database::row(
            "SELECT ba.*, b.nama AS buddy_nama, b.email AS buddy_email
             FROM buddy_assignments ba
             JOIN users b ON b.id = ba.buddy_id
             WHERE ba.karyawan_baru_id = ? AND ba.status = 'aktif'
             LIMIT 1",
            [$user['id']]
        );
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-check-fill me-2"></i>
    <?= $isHR ? 'Buddy Assignment' : ($user['role'] === 'buddy' ? 'Karyawan Baru Saya' : 'Info Buddy Saya') ?>
  </h4>
</div>

<?= showFlash('buddy') ?>

<?php if ($isHR): ?>
<!-- ── HR View ──────────────────────────────────────────────── -->

<!-- Tab nav -->
<ul class="nav nav-tabs mb-4">
  <?php foreach (['aktif'=>'Aktif','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan','semua'=>'Semua'] as $k => $v): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $v ?></a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Tabel assignment -->
    <div class="card shadow-sm">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Daftar Assignment</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssign">
          <i class="bi bi-plus-lg me-1"></i>Assign Buddy
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Karyawan Baru</th><th>Buddy</th><th>Divisi</th><th>Mulai</th><th>Progres</th><th>Status</th><th>Aksi</th>
          </tr></thead>
          <tbody>
          <?php if ($assignments): ?>
            <?php foreach ($assignments as $a): ?>
            <tr>
              <td>
                <p class="mb-0 fw-semibold"><?= clean($a['karyawan_nama']) ?></p>
                <small class="text-muted"><?= clean($a['karyawan_email']) ?></small>
              </td>
              <td>
                <p class="mb-0"><?= clean($a['buddy_nama']) ?></p>
                <small class="text-muted"><?= clean($a['buddy_email']) ?></small>
              </td>
              <td><?= $a['divisi_nama'] ? clean($a['divisi_nama']) : '-' ?></td>
              <td><?= tglIndo($a['tanggal_mulai']) ?></td>
              <td style="min-width:110px;">
                <div class="progress" style="height:7px;">
                  <div class="progress-bar bg-success" style="width:<?= $a['progres'] ?>%"></div>
                </div>
                <small><?= $a['progres'] ?>%</small>
              </td>
              <td><?= badgeStatus($a['status']) ?></td>
              <td>
                <?php if ($a['status'] === 'aktif'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Selesaikan assignment ini?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="selesaikan">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-success">Selesai</button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Batalkan assignment ini?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="batalkan">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Batal</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Karyawan belum di-assign -->
    <div class="card shadow-sm">
      <div class="card-header fw-semibold text-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>Belum Di-Assign (<?= count($unassigned) ?>)
      </div>
      <?php if ($unassigned): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($unassigned as $u2): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
          <div>
            <p class="mb-0 fw-semibold small"><?= clean($u2['nama']) ?></p>
            <small class="text-muted"><?= $u2['divisi_nama'] ?? '-' ?></small>
          </div>
          <button class="btn btn-sm btn-outline-warning btn-quick-assign"
            data-bs-toggle="modal" data-bs-target="#modalAssign"
            data-id="<?= $u2['id'] ?>" data-nama="<?= htmlspecialchars($u2['nama']) ?>">
            Assign
          </button>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="p-3 text-muted small">Semua karyawan sudah di-assign.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: Assign Buddy -->
<div class="modal fade" id="modalAssign" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Assign Buddy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="assign">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Karyawan Baru <span class="text-danger">*</span></label>
            <select name="karyawan_id" id="selectKaryawan" class="form-select" required>
              <option value="">-- Pilih Karyawan Baru --</option>
              <?php foreach ($unassigned as $u2): ?>
              <option value="<?= $u2['id'] ?>" <?= ($preAssign ?? null) == $u2['id'] ? 'selected' : '' ?>>
                <?= clean($u2['nama']) ?> — <?= $u2['divisi_nama'] ?? 'Tanpa Divisi' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Buddy <span class="text-danger">*</span></label>
            <select name="buddy_id" class="form-select" required>
              <option value="">-- Pilih Buddy --</option>
              <?php foreach ($buddyList as $b): ?>
              <option value="<?= $b['id'] ?>">
                <?= clean($b['nama']) ?> (<?= $b['jumlah_karyawan'] ?> karyawan aktif)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Selesai</label>
              <input type="date" name="tanggal_selesai" class="form-control"
                     value="<?= date('Y-m-d', strtotime('+' . ONBOARDING_WEEKS . ' weeks')) ?>" />
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Catatan</label>
            <textarea name="catatan" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-quick-assign').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('selectKaryawan').value = this.dataset.id;
  });
});
</script>

<?php elseif ($user['role'] === 'buddy'): ?>
<!-- ── Buddy View ────────────────────────────────────────────── -->

<?php if (isset($detail) && $detail): ?>
<!-- Detail karyawan tertentu -->
<div class="mb-3">
  <a href="<?= APP_URL ?>/buddy.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<div class="row g-4">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Info Karyawan</h6>
        <p><strong>Nama:</strong> <?= clean($detail['karyawan_nama']) ?></p>
        <p><strong>Email:</strong> <?= clean($detail['karyawan_email']) ?></p>
        <p><strong>Divisi:</strong> <?= $detail['divisi_nama'] ?? '-' ?></p>
        <p><strong>Mulai:</strong> <?= tglIndo($detail['tanggal_mulai']) ?></p>
        <p class="mb-0"><strong>Status:</strong> <?= badgeStatus($detail['status']) ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">Progres Checklist</h6>
        <?php $p = hitungProgres($detail['id']); ?>
        <div class="progress mb-2" style="height:12px;">
          <div class="progress-bar bg-success" style="width:<?= $p ?>%"></div>
        </div>
        <p class="mb-3 text-muted small"><?= $p ?>% selesai</p>
        <a href="<?= APP_URL ?>/checklist.php?assignment=<?= $detail['id'] ?>" class="btn btn-sm btn-outline-primary">Lihat Checklist</a>
        <a href="<?= APP_URL ?>/progress.php?assignment=<?= $detail['id'] ?>" class="btn btn-sm btn-outline-info ms-2">Catat Progres</a>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Nama</th><th>Mulai</th><th>Progres</th><th>Status</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php foreach ($myKaryawan as $k): ?>
      <tr>
        <td><p class="mb-0 fw-semibold"><?= clean($k['nama']) ?></p><small class="text-muted"><?= clean($k['email']) ?></small></td>
        <td><?= tglIndo($k['tanggal_mulai']) ?></td>
        <td style="min-width:120px;">
          <div class="progress" style="height:7px;">
            <div class="progress-bar bg-success" style="width:<?= $k['progres'] ?>%"></div>
          </div>
          <small><?= $k['progres'] ?>%</small>
        </td>
        <td><?= badgeStatus($k['status']) ?></td>
        <td><a href="?assignment=<?= $k['assignment_id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Karyawan Baru: Info Buddy ─────────────────────────────── -->
<?php if ($myAssignment ?? null): ?>
<div class="card shadow-sm" style="max-width:500px;">
  <div class="card-body">
    <h6 class="fw-semibold mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Buddy Anda</h6>
    <p class="fw-semibold mb-1"><?= clean($myAssignment['buddy_nama']) ?></p>
    <p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i><?= clean($myAssignment['buddy_email']) ?></p>
    <p class="text-muted small mb-3"><i class="bi bi-calendar me-1"></i>Mulai: <?= tglIndo($myAssignment['tanggal_mulai']) ?></p>
    <?= badgeStatus($myAssignment['status']) ?>
    <?php if ($myAssignment['catatan']): ?>
      <hr><p class="small text-muted mb-0"><?= clean($myAssignment['catatan']) ?></p>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">Buddy Anda belum ditugaskan. Hubungi HR.</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
