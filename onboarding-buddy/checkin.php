<?php
// ============================================================
// Check-In — Jadwal & Catatan Check-In Buddy
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

$assignmentId = isset($_GET['assignment']) ? (int)$_GET['assignment'] : 0;

// ─── POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('checkin', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/checkin.php' . ($assignmentId ? "?assignment=$assignmentId" : ''));
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'jadwalkan') {
        $assignId = (int)$_POST['assignment_id'];
        $jadwal   = $_POST['jadwal'] ?? '';
        if (!$jadwal) {
            flash('checkin', 'Jadwal wajib diisi.', 'error');
            redirect(APP_URL . "/checkin.php?assignment=$assignId");
        }
        Database::execute(
            "INSERT INTO check_ins (assignment_id, jadwal) VALUES (?, ?)",
            [$assignId, $jadwal]
        );
        // Notif ke karyawan & buddy
        $ba = Database::row(
            "SELECT karyawan_baru_id, buddy_id FROM buddy_assignments WHERE id = ?", [$assignId]
        );
        if ($ba) {
            buatNotif($ba['karyawan_baru_id'], 'Check-In Dijadwalkan',
                'Check-in bersama buddy dijadwalkan pada ' . dtIndo($jadwal),
                'reminder', APP_URL . "/checkin.php?assignment=$assignId");
            buatNotif($ba['buddy_id'], 'Check-In Dijadwalkan',
                'Check-in dijadwalkan pada ' . dtIndo($jadwal),
                'reminder', APP_URL . "/checkin.php?assignment=$assignId");
        }
        flash('checkin', 'Check-in berhasil dijadwalkan.', 'success');
        redirect(APP_URL . "/checkin.php?assignment=$assignId");
    }

    if ($action === 'selesaikan') {
        $ciId           = (int)$_POST['ci_id'];
        $assignId       = (int)$_POST['assignment_id'];
        $catatanBuddy   = trim($_POST['catatan_buddy'] ?? '');
        $catatanKaryawan = trim($_POST['catatan_karyawan'] ?? '');
        Database::execute(
            "UPDATE check_ins SET status='selesai', catatan_buddy=?, catatan_karyawan=?, selesai_pada=NOW()
             WHERE id = ?",
            [$catatanBuddy, $catatanKaryawan, $ciId]
        );
        flash('checkin', 'Check-in ditandai selesai.', 'success');
        redirect(APP_URL . "/checkin.php?assignment=$assignId");
    }

    if ($action === 'lewati') {
        $ciId     = (int)$_POST['ci_id'];
        $assignId = (int)$_POST['assignment_id'];
        Database::execute("UPDATE check_ins SET status='dilewati' WHERE id = ?", [$ciId]);
        flash('checkin', 'Check-in ditandai dilewati.', 'warning');
        redirect(APP_URL . "/checkin.php?assignment=$assignId");
    }
}

// ─── GET: Data ───────────────────────────────────────────────
if ($assignmentId) {
    $assignment = null;
    if (in_array($user['role'], ['hr','admin'])) {
        $assignment = Database::row(
            "SELECT ba.*, kb.nama AS karyawan_nama, b.nama AS buddy_nama
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
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
    if (!$assignment) redirect(APP_URL . '/checkin.php');

    $checkIns = Database::rows(
        "SELECT * FROM check_ins WHERE assignment_id = ? ORDER BY jadwal DESC",
        [$assignmentId]
    );
} else {
    // Cari assignment sendiri
    if ($user['role'] === 'karyawan_baru') {
        $myAssign = Database::row(
            "SELECT id FROM buddy_assignments WHERE karyawan_baru_id = ? AND status = 'aktif' LIMIT 1",
            [$user['id']]
        );
        if ($myAssign) redirect(APP_URL . '/checkin.php?assignment=' . $myAssign['id']);
    } elseif ($user['role'] === 'buddy') {
        $assignmentList = Database::rows(
            "SELECT ba.id, kb.nama AS karyawan_nama, ba.status
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             WHERE ba.buddy_id = ? ORDER BY ba.status = 'aktif' DESC",
            [$user['id']]
        );
    } else {
        $assignmentList = Database::rows(
            "SELECT ba.id, kb.nama AS karyawan_nama, b.nama AS buddy_nama, ba.status
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             ORDER BY ba.status = 'aktif' DESC, ba.created_at DESC"
        );
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>Check-In Buddy</h4>
  <?php if ($assignmentId): ?>
  <a href="<?= APP_URL ?>/checkin.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
  <?php endif; ?>
</div>

<?= showFlash('checkin') ?>

<?php if (!$assignmentId): ?>
<!-- Pilih assignment -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Karyawan Baru</th><?= $user['role'] !== 'buddy' ? '<th>Buddy</th>' : '' ?><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($assignmentList ?? [] as $a): ?>
      <tr>
        <td><?= clean($a['karyawan_nama']) ?></td>
        <?php if ($user['role'] !== 'buddy'): ?><td><?= clean($a['buddy_nama'] ?? '') ?></td><?php endif; ?>
        <td><?= badgeStatus($a['status']) ?></td>
        <td><a href="?assignment=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Lihat</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- Detail check-in assignment -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col">
        <p class="mb-0"><strong>Karyawan:</strong> <?= clean($assignment['karyawan_nama']) ?></p>
        <p class="mb-0"><strong>Buddy:</strong> <?= clean($assignment['buddy_nama']) ?></p>
      </div>
      <div class="col-auto">
        <?php if (in_array($user['role'], ['buddy','hr','admin'])): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalJadwal">
          <i class="bi bi-plus-lg me-1"></i>Jadwalkan Check-In
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header fw-semibold">Riwayat Check-In</div>
  <?php if ($checkIns): ?>
  <div class="list-group list-group-flush">
    <?php foreach ($checkIns as $ci): ?>
    <div class="list-group-item">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="flex-grow-1">
          <p class="mb-1 fw-semibold"><?= dtIndo($ci['jadwal']) ?></p>
          <?= badgeStatus($ci['status']) ?>
          <?php if ($ci['catatan_buddy']): ?>
            <p class="mt-2 mb-0 small"><strong>Catatan Buddy:</strong> <?= clean($ci['catatan_buddy']) ?></p>
          <?php endif; ?>
          <?php if ($ci['catatan_karyawan']): ?>
            <p class="mb-0 small"><strong>Catatan Karyawan:</strong> <?= clean($ci['catatan_karyawan']) ?></p>
          <?php endif; ?>
          <?php if ($ci['selesai_pada']): ?>
            <small class="text-muted">Selesai: <?= dtIndo($ci['selesai_pada']) ?></small>
          <?php endif; ?>
        </div>
        <?php if ($ci['status'] === 'terjadwal' && in_array($user['role'], ['buddy','hr','admin'])): ?>
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-outline-success btn-selesai"
            data-bs-toggle="modal" data-bs-target="#modalSelesai"
            data-ci="<?= $ci['id'] ?>" data-assign="<?= $assignmentId ?>">
            Selesai
          </button>
          <form method="POST" onsubmit="return confirm('Lewati check-in ini?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="lewati">
            <input type="hidden" name="ci_id" value="<?= $ci['id'] ?>">
            <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Lewati</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p class="p-3 text-muted">Belum ada check-in dijadwalkan.</p>
  <?php endif; ?>
</div>

<!-- Modal: Jadwalkan -->
<div class="modal fade" id="modalJadwal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Jadwalkan Check-In</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="jadwalkan">
        <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">
        <div class="modal-body">
          <label class="form-label">Tanggal & Waktu</label>
          <input type="datetime-local" name="jadwal" class="form-control" required
                 min="<?= date('Y-m-d\TH:i') ?>" />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">Jadwalkan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Selesaikan -->
<div class="modal fade" id="modalSelesai" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Tandai Check-In Selesai</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="selesaikan">
        <input type="hidden" name="ci_id" id="selesaiCiId">
        <input type="hidden" name="assignment_id" id="selesaiAssignId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Catatan Buddy</label>
            <textarea name="catatan_buddy" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label">Catatan Karyawan</label>
            <textarea name="catatan_karyawan" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success btn-sm">Tandai Selesai</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.btn-selesai').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('selesaiCiId').value    = this.dataset.ci;
    document.getElementById('selesaiAssignId').value = this.dataset.assign;
  });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
