<?php
$roleMenus = [
 'admin' => [
  ['dashboard','house-door','Dashboard'],['data-warga','people','Data Warga'],['pengurus','person-badge','Pengurus / RT'],['pengajuan','file-earmark-text','Pengajuan Administrasi'],['pengumuman','megaphone','Pengumuman'],['kegiatan','calendar-event','Kegiatan'],['arsip','folder2-open','Arsip Dokumen'],['laporan','bar-chart','Laporan'],['keluhan','chat-square-text','Keluhan & Saran'],['notifikasi','bell','Notifikasi'],['manajemen-akun','person-gear','Manajemen Akun'],['pengaturan','gear','Pengaturan']
 ],
 'pengurus' => [
  ['dashboard','house-door','Dashboard'],['data-warga','people','Data Warga'],['pengajuan','file-earmark-text','Pengajuan Administrasi'],['pengumuman','megaphone','Pengumuman'],['kegiatan','calendar-event','Kegiatan'],['arsip','folder2-open','Arsip Dokumen'],['laporan','bar-chart','Laporan'],['keluhan','chat-square-text','Keluhan & Saran'],['notifikasi','bell','Notifikasi']
 ],
 'warga' => [
  ['dashboard','house-door','Beranda'],['ajukan','file-earmark-plus','Ajukan Administrasi'],['riwayat','clock-history','Riwayat Pengajuan'],['pengumuman','megaphone','Pengumuman'],['kegiatan','calendar-event','Kegiatan'],['keluhan','chat-square-text','Keluhan & Saran'],['notifikasi','bell','Notifikasi'],['profil','person-circle','Profil Saya']
 ]
];
$menus = $roleMenus[role()] ?? [];
?>
<aside class="sidebar" id="appSidebar">
  <a href="<?= e(url('dashboard')) ?>" class="brand-lockup"><span class="brand-icon"><i class="bi bi-house-heart-fill"></i></span><span><strong>SIPARTA</strong><small>Pelayanan RT Digital</small></span></a>
  <div class="sidebar-section-label">MENU UTAMA</div>
  <nav class="sidebar-nav">
    <?php foreach($menus as [$key,$icon,$label]): ?>
      <a href="<?= e(url($key)) ?>" class="sidebar-link <?= $page===$key || ($key==='pengajuan' && $page==='detail-pengajuan') ? 'active' : '' ?>"><i class="bi bi-<?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if($key==='notifikasi' && $unreadCount): ?><span class="nav-count"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="sidebar-help"><i class="bi bi-info-circle"></i><div><strong>Butuh bantuan?</strong><small>Hubungi Admin RT untuk bantuan akun dan layanan.</small></div></div>
    <div class="sidebar-user"><span class="avatar-circle"><?= e(initials($user['nama'])) ?></span><div class="min-w-0"><strong class="text-truncate d-block"><?= e($user['nama']) ?></strong><small><?= e(role_label($user['role'])) ?></small></div></div>
  </div>
</aside>
