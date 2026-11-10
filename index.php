<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/actions/handle.php';
handle_get_action();
handle_post_action();

$page = (string)get('page', current_user() ? 'dashboard' : 'login');
$publicPages = ['login','register-warga','register-pengurus','lupa-password'];
$allPages = ['dashboard','data-warga','pengurus','pengajuan','detail-pengajuan','pengumuman','kegiatan','arsip','laporan','keluhan','notifikasi','manajemen-akun','pengaturan','ajukan','riwayat','profil'];
if (!in_array($page, array_merge($publicPages,$allPages), true)) $page = current_user() ? 'dashboard' : 'login';

if (current_user() && in_array($page, ['login','register-warga','register-pengurus'], true)) redirect('dashboard');
if (!current_user() && !in_array($page, $publicPages, true)) redirect('login');

$permissions = [
    'dashboard'=>['admin','pengurus','warga'],
    'data-warga'=>['admin','pengurus'], 'pengurus'=>['admin'],
    'pengajuan'=>['admin','pengurus'], 'detail-pengajuan'=>['admin','pengurus'],
    'pengumuman'=>['admin','pengurus','warga'], 'kegiatan'=>['admin','pengurus','warga'],
    'arsip'=>['admin','pengurus'], 'laporan'=>['admin','pengurus'],
    'keluhan'=>['admin','pengurus','warga'], 'notifikasi'=>['admin','pengurus','warga'],
    'manajemen-akun'=>['admin'], 'pengaturan'=>['admin'],
    'ajukan'=>['warga'], 'riwayat'=>['warga'], 'profil'=>['admin','pengurus','warga'],
];
if (isset($permissions[$page])) require_roles($permissions[$page]);

$oneTimeCode = $_SESSION['one_time_code'] ?? null;
unset($_SESSION['one_time_code']);

if (in_array($page, $publicPages, true)) {
    require __DIR__ . '/views/auth/' . $page . '.php';
    exit;
}
$flash = take_flash();
require __DIR__ . '/views/layout/app.php';
