<?php
// ============================================================
// Session Management
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME ?? 'obs_session');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME ?? 7200);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ── Cek apakah user sudah login ─────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// ── Wajib login; redirect jika tidak ────────────────────────
function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        redirect(APP_URL . $redirect);
    }
}

// ── Wajib role tertentu ──────────────────────────────────────
function requireRole(array|string $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        redirect(APP_URL . '/dashboard.php');
    }
}

// ── Ambil data user dari sesi ────────────────────────────────
function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']   ?? 0,
        'nama'  => $_SESSION['user_nama'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
        'divisi_id' => $_SESSION['user_divisi_id'] ?? null,
    ];
}

// ── Set sesi setelah login ───────────────────────────────────
function setSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_nama']     = $user['nama'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_divisi_id'] = $user['divisi_id'];
}

// ── Hapus sesi (logout) ──────────────────────────────────────
function destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
