<?php
// ============================================================
// Dashboard — Role-Based
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

// ── Data berdasarkan role ────────────────────────────────────
switch ($user['role']) {

    case 'karyawan_baru':
        $assignment = Database::row(
            "SELECT ba.*, u.nama AS buddy_nama, u.email AS buddy_email
             FROM buddy_assignments ba
             JOIN users u ON u.id = ba.buddy_id
             WHERE ba.karyawan_baru_id = ? AND ba.status = 'aktif'
             ORDER BY ba.created_at DESC LIMIT 1",
            [$user['id']]
        );
        $progres = $assignment ? hitungProgres($assignment['id']) : 0;

        $nextCheckin = $assignment ? Database::row(
            "SELECT * FROM check_ins WHERE assignment_id = ? AND status = 'terjadwal' ORDER BY jadwal ASC LIMIT 1",
            [$assignment['id']]
        ) : null;

        $totalChecklist = $assignment ? Database::count(
            'SELECT COUNT(*) FROM checklist_items WHERE assignment_id = ?', [$assignment['id']]
        ) : 0;
        $selesaiChecklist = $assignment ? Database::count(
            "SELECT COUNT(*) FROM checklist_items WHERE assignment_id = ? AND status = 'selesai'", [$assignment['id']]
        ) : 0;
        break;

    case 'buddy':
        $karyawanList = Database::rows(
            "SELECT ba.id AS assignment_id, u.nama, u.email, u.divisi_id, ba.tanggal_mulai
             FROM buddy_assignments ba
             JOIN users u ON u.id = ba.karyawan_baru_id
             WHERE ba.buddy_id = ? AND ba.status = 'aktif'
             ORDER BY ba.tanggal_mulai DESC",
            [$user['id']]
        );
        foreach ($karyawanList as &$k) {
            $k['progres'] = hitungProgres($k['assignment_id']);
        }
        unset($k);

        $upcomingCheckins = Database::rows(
            "SELECT ci.*, u.nama AS karyawan_nama
             FROM check_ins ci
             JOIN buddy_assignments ba ON ba.id = ci.assignment_id
             JOIN users u ON u.id = ba.karyawan_baru_id
             WHERE ba.buddy_id = ? AND ci.status = 'terjadwal' AND ci.jadwal >= NOW()
             ORDER BY ci.jadwal ASC LIMIT 5",
            [$user['id']]
        );
        break;

    case 'hr':
        $totalKaryawanBaru = Database::count(
            "SELECT COUNT(*) FROM users WHERE role = 'karyawan_baru' AND is_active = 1"
        );
        $totalAktif = Database::count(
            "SELECT COUNT(*) FROM buddy_assignments WHERE status = 'aktif'"
        );
        $avgProgres = Database::row(
            "SELECT AVG(progres_pct) AS avg_progres FROM (
               SELECT ba.id, 
                 ROUND(SUM(ci.status = 'selesai') / COUNT(ci.id) * 100, 0) AS progres_pct
               FROM buddy_assignments ba
               JOIN checklist_items ci ON ci.assignment_id = ba.id
               WHERE ba.status = 'aktif'
               GROUP BY ba.id
             ) t"
        );
        $recentAssignments = Database::rows(
            "SELECT ba.id, kb.nama AS karyawan_nama, b.nama AS buddy_nama, ba.tanggal_mulai, ba.status
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             ORDER BY ba.created_at DESC LIMIT 8"
        );
        $unassigned = Database::rows(
            "SELECT u.id, u.nama, u.email, u.tanggal_bergabung
             FROM users u
             WHERE u.role = 'karyawan_baru' AND u.is_active = 1
               AND u.id NOT IN (SELECT karyawan_baru_id FROM buddy_assignments WHERE status = 'aktif')
             ORDER BY u.created_at DESC LIMIT 5"
        );
        break;

    case 'manajer':
        $divisiId = $user['divisi_id'];
        $progresData = Database::rows(
            "SELECT ba.id AS assignment_id, kb.nama AS karyawan_nama, b.nama AS buddy_nama,
                    ba.tanggal_mulai
             FROM buddy_assignments ba
             JOIN users kb ON kb.id = ba.karyawan_baru_id
             JOIN users b  ON b.id  = ba.buddy_id
             WHERE kb.divisi_id = ? AND ba.status = 'aktif'
             ORDER BY ba.tanggal_mulai DESC",
            [$divisiId]
        );
        foreach ($progresData as &$p) {
            $p['progres'] = hitungProgres($p['assignment_id']);
        }
        unset($p);
        break;

    case 'admin':
        $stats = [
            'users'      => Database::count('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'karyawan'   => Database::count("SELECT COUNT(*) FROM users WHERE role='karyawan_baru' AND is_active=1"),
            'buddy'      => Database::count("SELECT COUNT(*) FROM users WHERE role='buddy' AND is_active=1"),
            'divisi'     => Database::count('SELECT COUNT(*) FROM divisi'),
            'assignment' => Database::count("SELECT COUNT(*) FROM buddy_assignments WHERE status='aktif'"),
        ];
        $recentUsers = Database::rows(
            "SELECT id, nama, email, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 10"
        );
        break;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Selamat datang, <?= clean($user['nama']) ?>!</h4>
    <p class="text-muted small mb-0"><?= labelRole($user['role']) ?> — <?= date('l, d F Y') ?></p>
  </div>
  <?= badgeRole($user['role']) ?>
</div>

<?= showFlash('dashboard') ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($user['role'] === 'karyawan_baru'): ?>
<!-- Dashboard: Karyawan Baru -->

  <?php if (!$assignment): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Buddy Anda belum ditugaskan. Hubungi HR untuk informasi lebih lanjut.</div>
  <?php else: ?>

  <div class="row g-4 mb-4">
    <!-- Progres -->
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body text-center py-4">
          <div class="obs-progress-circle mb-3" data-pct="<?= $progres ?>">
            <span><?= $progres ?>%</span>
          </div>
          <h6 class="fw-semibold">Progres Onboarding</h6>
          <p class="text-muted small mb-0"><?= $selesaiChecklist ?>/<?= $totalChecklist ?> item selesai</p>
        </div>
      </div>
    </div>
    <!-- Buddy info -->
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Buddy Anda</h6>
          <p class="mb-1 fw-semibold"><?= clean($assignment['buddy_nama']) ?></p>
          <p class="small text-muted mb-2"><i class="bi bi-envelope me-1"></i><?= clean($assignment['buddy_email']) ?></p>
          <p class="small text-muted mb-0"><i class="bi bi-calendar me-1"></i>Mulai: <?= tglIndo($assignment['tanggal_mulai']) ?></p>
        </div>
      </div>
    </div>
    <!-- Check-in berikutnya -->
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-calendar-check me-2 text-success"></i>Check-In Berikutnya</h6>
          <?php if ($nextCheckin): ?>
            <p class="fw-semibold mb-1"><?= dtIndo($nextCheckin['jadwal']) ?></p>
            <?= badgeStatus($nextCheckin['status']) ?>
          <?php else: ?>
            <p class="text-muted small">Belum ada jadwal.</p>
          <?php endif; ?>
          <a href="<?= APP_URL ?>/checkin.php" class="btn btn-sm btn-outline-primary mt-3">Lihat Semua</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Checklist cepat -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-check2-square me-2"></i>Checklist Terbaru</div>
    <div class="card-body p-0">
      <?php
        $items = Database::rows(
            "SELECT ci.id, ct.judul, ci.status, ct.minggu_ke
             FROM checklist_items ci
             JOIN checklist_templates ct ON ct.id = ci.template_id
             WHERE ci.assignment_id = ?
             ORDER BY ct.minggu_ke, ct.urutan
             LIMIT 6",
            [$assignment['id']]
        );
      ?>
      <?php if ($items): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $item): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><?= clean($item['judul']) ?></span>
          <?= badgeStatus($item['status']) ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="p-3 text-muted small">Belum ada checklist.</p>
      <?php endif; ?>
    </div>
    <div class="card-footer"><a href="<?= APP_URL ?>/checklist.php" class="btn btn-sm btn-primary">Lihat Semua Checklist</a></div>
  </div>

  <?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<?php elseif ($user['role'] === 'buddy'): ?>
<!-- Dashboard: Buddy -->

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card shadow-sm text-center py-3">
      <div class="card-body">
        <h2 class="fw-bold text-primary"><?= count($karyawanList) ?></h2>
        <p class="mb-0 text-muted small">Karyawan Baru Aktif</p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm text-center py-3">
      <div class="card-body">
        <h2 class="fw-bold text-success"><?= count($upcomingCheckins) ?></h2>
        <p class="mb-0 text-muted small">Check-In Mendatang</p>
      </div>
    </div>
  </div>
</div>

<!-- Daftar karyawan baru -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-people me-2"></i>Karyawan Baru Saya</div>
  <div class="card-body p-0">
    <?php if ($karyawanList): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Nama</th><th>Mulai Bergabung</th><th>Progres</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($karyawanList as $k): ?>
        <tr>
          <td><?= clean($k['nama']) ?></td>
          <td><?= tglIndo($k['tanggal_mulai']) ?></td>
          <td style="min-width:140px;">
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-success" style="width:<?= $k['progres'] ?>%"></div>
            </div>
            <small class="text-muted"><?= $k['progres'] ?>%</small>
          </td>
          <td>
            <a href="<?= APP_URL ?>/buddy.php?assignment=<?= $k['assignment_id'] ?>" class="btn btn-sm btn-outline-primary">Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="p-3 text-muted">Belum ada karyawan baru yang ditugaskan.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Upcoming check-ins -->
<?php if ($upcomingCheckins): ?>
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-calendar-event me-2"></i>Check-In Mendatang</div>
  <ul class="list-group list-group-flush">
    <?php foreach ($upcomingCheckins as $ci): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
      <div>
        <p class="mb-0 fw-semibold"><?= clean($ci['karyawan_nama']) ?></p>
        <small class="text-muted"><?= dtIndo($ci['jadwal']) ?></small>
      </div>
      <?= badgeStatus($ci['status']) ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<?php elseif ($user['role'] === 'hr'): ?>
<!-- Dashboard: HR -->

<div class="row g-4 mb-4">
  <?php
  $statsCards = [
    ['Karyawan Baru Aktif', $totalKaryawanBaru,                   'people-fill',        'primary'],
    ['Assignment Aktif',    $totalAktif,                           'person-check-fill',  'success'],
    ['Rata-rata Progres',   round($avgProgres['avg_progres'] ?? 0) . '%', 'graph-up', 'info'],
    ['Belum Di-assign',     count($unassigned),                    'exclamation-circle', 'warning'],
  ];
  foreach ($statsCards as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="obs-stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>">
          <i class="bi bi-<?= $icon ?>"></i>
        </div>
        <div>
          <h4 class="mb-0 fw-bold"><?= $val ?></h4>
          <p class="mb-0 small text-muted"><?= $label ?></p>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Assignment Terbaru</span>
        <a href="<?= APP_URL ?>/buddy.php" class="btn btn-sm btn-primary">Kelola</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Karyawan</th><th>Buddy</th><th>Mulai</th><th>Status</th>
          </tr></thead>
          <tbody>
          <?php foreach ($recentAssignments as $a): ?>
          <tr>
            <td><?= clean($a['karyawan_nama']) ?></td>
            <td><?= clean($a['buddy_nama']) ?></td>
            <td><?= tglIndo($a['tanggal_mulai']) ?></td>
            <td><?= badgeStatus($a['status']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-circle me-2 text-warning"></i>Belum Di-assign</span>
        <a href="<?= APP_URL ?>/buddy.php?tab=assign" class="btn btn-sm btn-warning">Assign</a>
      </div>
      <?php if ($unassigned): ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($unassigned as $u2): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <p class="mb-0 fw-semibold"><?= clean($u2['nama']) ?></p>
            <small class="text-muted"><?= clean($u2['email']) ?></small>
          </div>
          <a href="<?= APP_URL ?>/buddy.php?assign=<?= $u2['id'] ?>" class="btn btn-sm btn-outline-warning">Assign</a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="p-3 text-muted small">Semua karyawan baru sudah di-assign.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<?php elseif ($user['role'] === 'manajer'): ?>
<!-- Dashboard: Manajer -->

<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-graph-up me-2"></i>Progres Adaptasi Tim</div>
  <div class="card-body p-0">
    <?php if ($progresData): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Karyawan Baru</th><th>Buddy</th><th>Mulai</th><th>Progres</th>
        </tr></thead>
        <tbody>
        <?php foreach ($progresData as $p): ?>
        <tr>
          <td><?= clean($p['karyawan_nama']) ?></td>
          <td><?= clean($p['buddy_nama']) ?></td>
          <td><?= tglIndo($p['tanggal_mulai']) ?></td>
          <td style="min-width:150px;">
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-<?= $p['progres'] >= 80 ? 'success' : ($p['progres'] >= 40 ? 'warning' : 'danger') ?>"
                   style="width:<?= $p['progres'] ?>%"></div>
            </div>
            <small><?= $p['progres'] ?>%</small>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="p-3 text-muted">Tidak ada data untuk divisi ini.</p>
    <?php endif; ?>
  </div>
</div>
<div class="mt-3">
  <a href="<?= APP_URL ?>/laporan.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>Lihat Laporan</a>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<?php elseif ($user['role'] === 'admin'): ?>
<!-- Dashboard: Admin -->

<div class="row g-4 mb-4">
  <?php
  $adminCards = [
    ['Total User Aktif',   $stats['users'],      'people-fill',   'primary'],
    ['Karyawan Baru',      $stats['karyawan'],   'person-plus',   'success'],
    ['Buddy Aktif',        $stats['buddy'],      'person-heart',  'info'],
    ['Divisi',             $stats['divisi'],     'diagram-3',     'warning'],
    ['Assignment Aktif',   $stats['assignment'], 'check-circle',  'secondary'],
  ];
  foreach ($adminCards as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-2">
    <div class="card shadow-sm text-center py-3">
      <i class="bi bi-<?= $icon ?> fs-3 text-<?= $color ?>"></i>
      <h4 class="fw-bold mt-1 mb-0"><?= $val ?></h4>
      <p class="small text-muted mb-0"><?= $label ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clock-history me-2"></i>User Terbaru</span>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-sm btn-primary">Kelola User</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Terdaftar</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recentUsers as $ru): ?>
      <tr>
        <td><?= clean($ru['nama']) ?></td>
        <td><?= clean($ru['email']) ?></td>
        <td><?= badgeRole($ru['role']) ?></td>
        <td><?= $ru['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
        <td><?= tglIndo($ru['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
