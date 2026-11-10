<?php
function require_login(): void {
    if (!current_user()) { set_flash('warning', 'Silakan login terlebih dahulu.'); redirect('login'); }
}
function require_roles(array $roles): void {
    require_login();
    if (!in_array(role(), $roles, true)) { http_response_code(403); set_flash('danger', 'Kamu tidak memiliki hak akses untuk membuka halaman tersebut.'); redirect('dashboard'); }
}
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id'=>(int)$user['id'],'nama'=>$user['nama'],'username'=>$user['username'] ?? null,'email'=>$user['email'],'role'=>$user['role']];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    audit_log('login', 'auth', (int)$user['id'], 'Pengguna berhasil login.');
}
function logout_user(): void {
    if (current_user()) audit_log('logout', 'auth', user_id(), 'Pengguna keluar dari sistem.');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
