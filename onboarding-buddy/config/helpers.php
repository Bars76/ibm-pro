<?php
// ============================================================
// Helper Functions Global
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Sanitasi input ──────────────────────────────────────────
function clean(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// ── Flash message ────────────────────────────────────────────
function flash(string $key, string $msg = '', string $type = 'success'): ?array {
    if ($msg !== '') {
        $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $data = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $data;
    }
    return null;
}

// ── Tampilkan flash HTML ─────────────────────────────────────
function showFlash(string $key): string {
    $f = flash($key);
    if (!$f) return '';
    $type = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $type . ' alert-dismissible fade show" role="alert">'
         . clean($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ── Format tanggal Indonesia ─────────────────────────────────
function tglIndo(string $date): string {
    if (!$date) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// ── Format tanggal-waktu ─────────────────────────────────────
function dtIndo(string $datetime): string {
    if (!$datetime) return '-';
    return tglIndo($datetime) . ' ' . date('H:i', strtotime($datetime));
}

// ── Persentase progres checklist ────────────────────────────
function hitungProgres(int $assignmentId): int {
    $total = Database::count(
        'SELECT COUNT(*) FROM checklist_items WHERE assignment_id = ?',
        [$assignmentId]
    );
    if ($total === 0) return 0;
    $selesai = Database::count(
        "SELECT COUNT(*) FROM checklist_items WHERE assignment_id = ? AND status = 'selesai'",
        [$assignmentId]
    );
    return (int) round(($selesai / $total) * 100);
}

// ── Hitung notifikasi belum dibaca ──────────────────────────
function countNotif(int $userId): int {
    return Database::count(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
}

// ── Buat notifikasi ─────────────────────────────────────────
function buatNotif(int $userId, string $judul, string $pesan, string $tipe = 'info', string $link = ''): void {
    Database::execute(
        'INSERT INTO notifications (user_id, judul, pesan, tipe, link) VALUES (?, ?, ?, ?, ?)',
        [$userId, $judul, $pesan, $tipe, $link]
    );
}

// ── Cek CSRF token ──────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// ── Label role ───────────────────────────────────────────────
function labelRole(string $role): string {
    return match($role) {
        'karyawan_baru' => 'Karyawan Baru',
        'buddy'         => 'Buddy',
        'hr'            => 'HR',
        'manajer'       => 'Manajer',
        'admin'         => 'Admin',
        default         => ucfirst($role),
    };
}

// ── Warna badge role ─────────────────────────────────────────
function badgeRole(string $role): string {
    $color = match($role) {
        'karyawan_baru' => 'primary',
        'buddy'         => 'success',
        'hr'            => 'info',
        'manajer'       => 'warning',
        'admin'         => 'danger',
        default         => 'secondary',
    };
    return '<span class="badge bg-' . $color . '">' . labelRole($role) . '</span>';
}

// ── Warna badge status ───────────────────────────────────────
function badgeStatus(string $status): string {
    $map = [
        'belum'      => ['secondary', 'Belum'],
        'sedang'     => ['warning',   'Sedang'],
        'selesai'    => ['success',   'Selesai'],
        'aktif'      => ['primary',   'Aktif'],
        'dibatalkan' => ['danger',    'Dibatalkan'],
        'terjadwal'  => ['info',      'Terjadwal'],
        'dilewati'   => ['secondary', 'Dilewati'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge bg-' . $color . '">' . $label . '</span>';
}

// ── Pagination ───────────────────────────────────────────────
function paginate(int $total, int $perPage, int $page, string $url): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}
