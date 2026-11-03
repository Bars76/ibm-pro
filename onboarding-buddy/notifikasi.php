<?php
// ============================================================
// Notifikasi — In-app Notification Center
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();
$user = currentUser();

// POST: tandai dibaca / hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect(APP_URL . '/notifikasi.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'baca') {
        $id = (int)$_POST['id'];
        Database::execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [$id, $user['id']]
        );
        redirect($_POST['link'] ?? APP_URL . '/notifikasi.php');
    }

    if ($action === 'baca_semua') {
        Database::execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?',
            [$user['id']]
        );
        flash('notif', 'Semua notifikasi ditandai dibaca.', 'success');
        redirect(APP_URL . '/notifikasi.php');
    }

    if ($action === 'hapus') {
        $id = (int)$_POST['id'];
        Database::execute('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        redirect(APP_URL . '/notifikasi.php');
    }
}

// GET: ambil notifikasi
$filter = $_GET['filter'] ?? 'semua';
$sql = 'SELECT * FROM notifications WHERE user_id = ?';
$params = [$user['id']];
if ($filter === 'belum') {
    $sql .= ' AND is_read = 0';
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$notifs = Database::rows($sql, $params);
$unreadCount = countNotif($user['id']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-bell-fill me-2"></i>Notifikasi
    <?php if ($unreadCount > 0): ?>
    <span class="badge bg-danger ms-1"><?= $unreadCount ?></span>
    <?php endif; ?>
  </h4>
  <?php if ($unreadCount > 0): ?>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="baca_semua">
    <button type="submit" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-check-all me-1"></i>Tandai Semua Dibaca
    </button>
  </form>
  <?php endif; ?>
</div>

<?= showFlash('notif') ?>

<!-- Filter tabs -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?= $filter === 'semua' ? 'active' : '' ?>" href="?filter=semua">Semua</a></li>
  <li class="nav-item"><a class="nav-link <?= $filter === 'belum' ? 'active' : '' ?>" href="?filter=belum">Belum Dibaca (<?= $unreadCount ?>)</a></li>
</ul>

<?php if ($notifs): ?>
<div class="card shadow-sm">
  <div class="list-group list-group-flush">
    <?php foreach ($notifs as $n): ?>
    <div class="list-group-item <?= !$n['is_read'] ? 'obs-notif-unread' : '' ?>">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="flex-grow-1">
          <?php
          $iconMap = [
            'reminder' => ['bi-alarm','text-warning'],
            'info'     => ['bi-info-circle','text-info'],
            'tugas'    => ['bi-check-square','text-primary'],
            'feedback' => ['bi-chat-heart','text-success'],
          ];
          [$icon, $color] = $iconMap[$n['tipe']] ?? ['bi-bell','text-secondary'];
          ?>
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi <?= $icon ?> <?= $color ?>"></i>
            <span class="fw-semibold"><?= clean($n['judul']) ?></span>
            <?php if (!$n['is_read']): ?>
            <span class="badge bg-danger" style="font-size:.65rem;">Baru</span>
            <?php endif; ?>
          </div>
          <p class="mb-0 text-muted small"><?= clean($n['pesan']) ?></p>
          <small class="text-muted"><?= dtIndo($n['created_at']) ?></small>
        </div>
        <div class="d-flex gap-1">
          <?php if (!$n['is_read']): ?>
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="baca">
            <input type="hidden" name="id" value="<?= $n['id'] ?>">
            <input type="hidden" name="link" value="<?= clean($n['link'] ?? APP_URL . '/notifikasi.php') ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary">Baca</button>
          </form>
          <?php endif; ?>
          <form method="POST" onsubmit="return confirm('Hapus notifikasi ini?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= $n['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-bell-slash display-4"></i>
  <p class="mt-3">Tidak ada notifikasi.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
