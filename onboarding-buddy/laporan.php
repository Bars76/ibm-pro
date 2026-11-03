<?php
// ============================================================
// Laporan & Evaluasi — HR dan Manajer
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireRole(['hr', 'admin', 'manajer']);
$user = currentUser();

// ─── Filter ────────────────────────────────────────────────
$filterDivisi = isset($_GET['divisi']) ? (int)$_GET['divisi'] : null;
$filterStatus = $_GET['status'] ?? 'semua';
$filterTahun  = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$divisiList = Database::rows('SELECT id, nama FROM divisi ORDER BY nama');

// ─── Statistik Ringkasan ────────────────────────────────────
$whereDiv = $filterDivisi ? 'AND kb.divisi_id = ' . $filterDivisi : '';
$whereYear = "AND YEAR(ba.tanggal_mulai) = $filterTahun";

$totalAssign = Database::count(
    "SELECT COUNT(*) FROM buddy_assignments ba
     JOIN users kb ON kb.id = ba.karyawan_baru_id WHERE 1=1 $whereDiv $whereYear"
);
$totalAktif = Database::count(
    "SELECT COUNT(*) FROM buddy_assignments ba
     JOIN users kb ON kb.id = ba.karyawan_baru_id
     WHERE ba.status = 'aktif' $whereDiv $whereYear"
);
$totalSelesai = Database::count(
    "SELECT COUNT(*) FROM buddy_assignments ba
     JOIN users kb ON kb.id = ba.karyawan_baru_id
     WHERE ba.status = 'selesai' $whereDiv $whereYear"
);

// Rata-rata progres keseluruhan
$avgRow = Database::row(
    "SELECT ROUND(AVG(pct),1) AS avg
     FROM (
       SELECT ba.id,
         IFNULL(ROUND(SUM(ci.status='selesai')/COUNT(ci.id)*100,0),0) AS pct
       FROM buddy_assignments ba
       JOIN users kb ON kb.id = ba.karyawan_baru_id
       LEFT JOIN checklist_items ci ON ci.assignment_id = ba.id
       WHERE 1=1 $whereDiv $whereYear
       GROUP BY ba.id
     ) t"
);
$avgProgres = $avgRow['avg'] ?? 0;

// Rata-rata skor feedback
$avgFeedback = Database::row(
    "SELECT
       ROUND(AVG(f.skor_keseluruhan),2)  AS avg_keseluruhan,
       ROUND(AVG(f.skor_komunikasi),2)   AS avg_komunikasi,
       ROUND(AVG(f.skor_keterlibatan),2) AS avg_keterlibatan,
       COUNT(*)                          AS total_feedback
     FROM feedback f
     JOIN buddy_assignments ba ON ba.id = f.assignment_id
     JOIN users kb ON kb.id = ba.karyawan_baru_id
     WHERE 1=1 $whereDiv $whereYear"
);

// Checklist completion rate per minggu
$mingguData = Database::rows(
    "SELECT ct.minggu_ke,
            COUNT(ci.id) AS total,
            SUM(ci.status='selesai') AS selesai
     FROM checklist_items ci
     JOIN checklist_templates ct ON ct.id = ci.template_id
     JOIN buddy_assignments ba ON ba.id = ci.assignment_id
     JOIN users kb ON kb.id = ba.karyawan_baru_id
     WHERE 1=1 $whereDiv $whereYear
     GROUP BY ct.minggu_ke
     ORDER BY ct.minggu_ke"
);

// ─── Laporan detail per assignment ──────────────────────────
$sql = "SELECT ba.id, ba.tanggal_mulai, ba.tanggal_selesai, ba.status,
               kb.nama AS karyawan_nama, kb.email AS karyawan_email,
               b.nama  AS buddy_nama,
               d.nama  AS divisi_nama,
               (SELECT IFNULL(ROUND(SUM(ci2.status='selesai')/COUNT(ci2.id)*100,0),0)
                FROM checklist_items ci2 WHERE ci2.assignment_id = ba.id) AS progres,
               (SELECT ROUND(AVG(f2.skor_keseluruhan),1)
                FROM feedback f2 WHERE f2.assignment_id = ba.id) AS avg_skor
        FROM buddy_assignments ba
        JOIN users kb ON kb.id = ba.karyawan_baru_id
        JOIN users b  ON b.id  = ba.buddy_id
        LEFT JOIN divisi d ON d.id = kb.divisi_id
        WHERE 1=1 $whereDiv $whereYear";
if ($filterStatus !== 'semua') {
    $sql .= " AND ba.status = " . Database::connect()->quote($filterStatus);
}
$sql .= ' ORDER BY ba.tanggal_mulai DESC';
$laporan = Database::rows($sql);

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_onboarding_' . $filterTahun . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Karyawan Baru','Email','Buddy','Divisi','Tgl Mulai','Tgl Selesai','Status','Progres (%)','Skor Feedback']);
    foreach ($laporan as $row) {
        fputcsv($out, [
            $row['karyawan_nama'], $row['karyawan_email'], $row['buddy_nama'],
            $row['divisi_nama'] ?? 'N/A', $row['tanggal_mulai'], $row['tanggal_selesai'] ?? '-',
            $row['status'], $row['progres'], $row['avg_skor'] ?? 'N/A',
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Laporan & Evaluasi Onboarding</h4>
  <div class="d-flex gap-2">
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
       class="btn btn-sm btn-outline-success">
      <i class="bi bi-download me-1"></i>Ekspor CSV
    </a>
  </div>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-3">
        <label class="form-label">Divisi</label>
        <select name="divisi" class="form-select form-select-sm">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisiList as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $filterDivisi == $d['id'] ? 'selected' : '' ?>><?= clean($d['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-select form-select-sm">
          <?php for ($y = date('Y'); $y >= date('Y') - 4; $y--): ?>
          <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua</option>
          <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
          <option value="dibatalkan" <?= $filterStatus === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">Terapkan</button>
      </div>
    </form>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-4 mb-4">
  <?php
  $statCards = [
    ['Total Assignment',   $totalAssign,                      'clipboard-data',    'primary'],
    ['Aktif',              $totalAktif,                       'person-check',      'success'],
    ['Selesai',            $totalSelesai,                     'patch-check',       'info'],
    ['Rata-rata Progres',  $avgProgres . '%',                 'graph-up-arrow',    'warning'],
    ['Skor Feedback',      $avgFeedback['avg_keseluruhan'] ? number_format($avgFeedback['avg_keseluruhan'], 1) . '/5' : 'N/A', 'star-half', 'secondary'],
    ['Total Feedback',     $avgFeedback['total_feedback'],    'chat-square-text',  'dark'],
  ];
  foreach ($statCards as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-2">
    <div class="card shadow-sm text-center py-3">
      <i class="bi bi-<?= $icon ?> fs-3 text-<?= $color ?>"></i>
      <h4 class="fw-bold mt-1 mb-0"><?= $val ?></h4>
      <p class="small text-muted mb-0"><?= $label ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Chart placeholder & Checklist per minggu -->
<?php if ($mingguData): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold">Penyelesaian Checklist per Minggu</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Minggu</th><th>Total Item</th><th>Selesai</th><th>Completion Rate</th></tr></thead>
        <tbody>
        <?php foreach ($mingguData as $mw): ?>
        <?php $rate = $mw['total'] > 0 ? round($mw['selesai'] / $mw['total'] * 100) : 0; ?>
        <tr>
          <td>Minggu <?= $mw['minggu_ke'] ?></td>
          <td><?= $mw['total'] ?></td>
          <td><?= $mw['selesai'] ?></td>
          <td style="min-width:160px;">
            <div class="progress" style="height:8px;">
              <div class="progress-bar bg-<?= $rate >= 80 ? 'success' : ($rate >= 40 ? 'warning' : 'danger') ?>"
                   style="width:<?= $rate ?>%"></div>
            </div>
            <small><?= $rate ?>%</small>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Skor feedback ringkasan -->
<?php if ($avgFeedback['total_feedback']): ?>
<div class="row g-4 mb-4">
  <?php
  $feedbackCards = [
    ['Skor Keseluruhan',  $avgFeedback['avg_keseluruhan']],
    ['Komunikasi',        $avgFeedback['avg_komunikasi']],
    ['Keterlibatan',      $avgFeedback['avg_keterlibatan']],
  ];
  foreach ($feedbackCards as [$label, $skor]): ?>
  <div class="col-md-4">
    <div class="card shadow-sm text-center py-3">
      <h5 class="fw-bold mb-1"><?= $skor ? number_format($skor, 1) : 'N/A' ?><span class="text-muted">/5</span></h5>
      <p class="text-muted small mb-0"><?= $label ?></p>
      <div class="mt-2 px-4">
        <div class="progress" style="height:6px;">
          <div class="progress-bar bg-warning" style="width:<?= $skor ? ($skor/5*100) : 0 ?>%"></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabel detail -->
<div class="card shadow-sm">
  <div class="card-header fw-semibold">Detail Laporan (<?= count($laporan) ?> assignment)</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Karyawan Baru</th><th>Buddy</th><th>Divisi</th><th>Tgl Mulai</th>
        <th>Tgl Selesai</th><th>Progres</th><th>Skor</th><th>Status</th>
      </tr></thead>
      <tbody>
      <?php foreach ($laporan as $row): ?>
      <tr>
        <td>
          <p class="mb-0 fw-semibold"><?= clean($row['karyawan_nama']) ?></p>
          <small class="text-muted"><?= clean($row['karyawan_email']) ?></small>
        </td>
        <td><?= clean($row['buddy_nama']) ?></td>
        <td><?= $row['divisi_nama'] ?? '-' ?></td>
        <td><?= tglIndo($row['tanggal_mulai']) ?></td>
        <td><?= $row['tanggal_selesai'] ? tglIndo($row['tanggal_selesai']) : '-' ?></td>
        <td style="min-width:110px;">
          <div class="progress" style="height:7px;">
            <div class="progress-bar bg-<?= $row['progres'] >= 80 ? 'success' : ($row['progres'] >= 40 ? 'warning' : 'danger') ?>"
                 style="width:<?= $row['progres'] ?>%"></div>
          </div>
          <small><?= $row['progres'] ?>%</small>
        </td>
        <td><?= $row['avg_skor'] ? number_format($row['avg_skor'], 1) . '/5' : '-' ?></td>
        <td><?= badgeStatus($row['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$laporan): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data dengan filter ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
