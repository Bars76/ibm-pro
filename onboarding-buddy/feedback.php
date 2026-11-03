<?php
// ============================================================
// Feedback — Survei kepuasan onboarding
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

// ─── POST: Submit feedback ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('feedback', 'Token tidak valid.', 'error');
        redirect(APP_URL . '/feedback.php');
    }
    $assignId   = (int)$_POST['assignment_id'];
    $tipe       = $_POST['tipe'] ?? '';
    $skor1      = (int)$_POST['skor_keseluruhan'];
    $skor2      = (int)$_POST['skor_komunikasi'];
    $skor3      = (int)$_POST['skor_keterlibatan'];
    $komentar   = trim($_POST['komentar'] ?? '');

    $allowedTipe = ['karyawan_ke_buddy','buddy_ke_karyawan','karyawan_ke_program'];
    if (!in_array($tipe, $allowedTipe, true)) {
        flash('feedback', 'Tipe feedback tidak valid.', 'error');
        redirect(APP_URL . '/feedback.php');
    }

    // Cek duplikasi
    $existing = Database::count(
        'SELECT COUNT(*) FROM feedback WHERE assignment_id = ? AND dari_user_id = ? AND tipe = ?',
        [$assignId, $user['id'], $tipe]
    );
    if ($existing > 0) {
        flash('feedback', 'Anda sudah mengisi feedback ini.', 'warning');
        redirect(APP_URL . '/feedback.php');
    }

    Database::execute(
        'INSERT INTO feedback (assignment_id, dari_user_id, tipe, skor_keseluruhan, skor_komunikasi, skor_keterlibatan, komentar)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$assignId, $user['id'], $tipe, $skor1 ?: null, $skor2 ?: null, $skor3 ?: null, $komentar]
    );
    flash('feedback', 'Feedback berhasil dikirim. Terima kasih!', 'success');
    redirect(APP_URL . '/feedback.php');
}

// ─── GET: Ambil data assignment user ────────────────────────
if ($user['role'] === 'karyawan_baru') {
    $assignments = Database::rows(
        "SELECT ba.id, ba.status, b.nama AS buddy_nama
         FROM buddy_assignments ba
         JOIN users b ON b.id = ba.buddy_id
         WHERE ba.karyawan_baru_id = ?
         ORDER BY ba.created_at DESC",
        [$user['id']]
    );
    $tipeOptions = [
        'karyawan_ke_buddy'   => 'Feedback untuk Buddy',
        'karyawan_ke_program' => 'Feedback Program Onboarding',
    ];
} elseif ($user['role'] === 'buddy') {
    $assignments = Database::rows(
        "SELECT ba.id, ba.status, kb.nama AS karyawan_nama
         FROM buddy_assignments ba
         JOIN users kb ON kb.id = ba.karyawan_baru_id
         WHERE ba.buddy_id = ?
         ORDER BY ba.created_at DESC",
        [$user['id']]
    );
    $tipeOptions = ['buddy_ke_karyawan' => 'Feedback untuk Karyawan Baru'];
} elseif (in_array($user['role'], ['hr','admin','manajer'])) {
    // HR/Manajer: lihat semua feedback
    $assignments = [];
    $allFeedback = Database::rows(
        "SELECT f.*, u.nama AS dari_nama, u.role AS dari_role,
                kb.nama AS karyawan_nama, b.nama AS buddy_nama
         FROM feedback f
         JOIN users u  ON u.id  = f.dari_user_id
         JOIN buddy_assignments ba ON ba.id = f.assignment_id
         JOIN users kb ON kb.id = ba.karyawan_baru_id
         JOIN users b  ON b.id  = ba.buddy_id
         " . ($user['role'] === 'manajer' ? "JOIN users kbm ON kbm.id = ba.karyawan_baru_id AND kbm.divisi_id = {$user['divisi_id']}" : "") . "
         ORDER BY f.submitted_at DESC"
    );
    $tipeOptions = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-chat-square-heart me-2"></i>Feedback Onboarding</h4>
</div>

<?= showFlash('feedback') ?>

<?php if (in_array($user['role'], ['hr','admin','manajer'])): ?>
<!-- ── HR/Manajer: Lihat Semua Feedback ─────────────────────── -->
<div class="card shadow-sm">
  <div class="card-header fw-semibold">Semua Feedback Diterima</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Dari</th><th>Karyawan Baru</th><th>Buddy</th><th>Tipe</th>
        <th>Skor</th><th>Komentar</th><th>Tanggal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($allFeedback as $f): ?>
      <tr>
        <td><?= clean($f['dari_nama']) ?><br><?= badgeRole($f['dari_role']) ?></td>
        <td><?= clean($f['karyawan_nama']) ?></td>
        <td><?= clean($f['buddy_nama']) ?></td>
        <td><span class="badge bg-light text-dark border"><?= match($f['tipe']) {
            'karyawan_ke_buddy'   => 'ke Buddy',
            'buddy_ke_karyawan'   => 'ke Karyawan',
            'karyawan_ke_program' => 'ke Program',
            default => $f['tipe'],
        } ?></span></td>
        <td>
          <?php if ($f['skor_keseluruhan']): ?>
          <small>
            Keseluruhan: <strong><?= $f['skor_keseluruhan'] ?>/5</strong><br>
            Komunikasi: <?= $f['skor_komunikasi'] ?>/5<br>
            Keterlibatan: <?= $f['skor_keterlibatan'] ?>/5
          </small>
          <?php endif; ?>
        </td>
        <td><small><?= $f['komentar'] ? clean($f['komentar']) : '-' ?></small></td>
        <td><small><?= tglIndo($f['submitted_at']) ?></small></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$allFeedback): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada feedback.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ── Karyawan Baru / Buddy: Isi Feedback ───────────────────── -->

<?php foreach ($assignments as $a): ?>
<?php
    // Cek feedback sudah diisi
    $sudahIsi = [];
    foreach ($tipeOptions as $tipeKey => $_) {
        $sudahIsi[$tipeKey] = Database::count(
            'SELECT COUNT(*) FROM feedback WHERE assignment_id = ? AND dari_user_id = ? AND tipe = ?',
            [$a['id'], $user['id'], $tipeKey]
        ) > 0;
    }
?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold">
    Assignment dengan <?= $user['role'] === 'karyawan_baru' ? clean($a['buddy_nama']) : clean($a['karyawan_nama']) ?>
    — <?= badgeStatus($a['status']) ?>
  </div>
  <div class="card-body">
    <?php foreach ($tipeOptions as $tipeKey => $tipeLabel): ?>
    <div class="mb-3 p-3 border rounded bg-light">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><?= $tipeLabel ?></h6>
        <?php if ($sudahIsi[$tipeKey]): ?>
          <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Sudah Diisi</span>
        <?php endif; ?>
      </div>
      <?php if (!$sudahIsi[$tipeKey]): ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
        <input type="hidden" name="tipe" value="<?= $tipeKey ?>">
        <div class="row g-3 mb-3">
          <?php foreach ([
            'skor_keseluruhan'  => 'Keseluruhan',
            'skor_komunikasi'   => 'Komunikasi',
            'skor_keterlibatan' => 'Keterlibatan',
          ] as $field => $label): ?>
          <div class="col-md-4">
            <label class="form-label small"><?= $label ?></label>
            <select name="<?= $field ?>" class="form-select form-select-sm">
              <option value="">--</option>
              <?php for ($s = 1; $s <= 5; $s++): ?>
              <option value="<?= $s ?>"><?= $s ?> — <?= ['','Sangat Kurang','Kurang','Cukup','Baik','Sangat Baik'][$s] ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mb-3">
          <label class="form-label small">Komentar (opsional)</label>
          <textarea name="komentar" class="form-control form-control-sm" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Kirim Feedback</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$assignments): ?>
  <div class="alert alert-info">Belum ada assignment untuk mengisi feedback.</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
