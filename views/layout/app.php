<?php
$user = current_user();
$unreadStmt = db()->prepare('SELECT COUNT(*) FROM notifikasi WHERE user_id=? AND dibaca_pada IS NULL');
$unreadStmt->execute([user_id()]);
$unreadCount = (int)$unreadStmt->fetchColumn();
$rtProfile = db()->query('SELECT * FROM profil_rt WHERE id=1')->fetch() ?: [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#556B2F">
  <title><?= e(page_title($page)) ?> · SIPARTA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="main-area">
    <header class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-icon d-lg-none" type="button" id="sidebarToggle" aria-label="Buka navigasi"><i class="bi bi-list fs-4"></i></button>
        <div>
          <div class="topbar-title"><?= e(page_title($page)) ?></div>
          <div class="topbar-subtitle"><?= e(($rtProfile['nama_rt'] ?? 'RT') . ' / ' . ($rtProfile['nama_rw'] ?? 'RW')) ?> · Sistem Pelayanan Warga</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <a class="notification-link" href="<?= e(url('notifikasi')) ?>" title="Notifikasi"><i class="bi bi-bell"></i><?php if($unreadCount): ?><span class="notification-dot"></span><?php endif; ?></a>
        <div class="user-chip"><span class="avatar-circle"><?= e(initials($user['nama'])) ?></span><span class="d-none d-sm-block"><strong><?= e($user['nama']) ?></strong><small><?= e(role_label($user['role'])) ?></small></span></div>
        <form method="post" action="index.php" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn btn-outline-army btn-sm" type="submit"><i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-md-inline">Keluar</span></button></form>
      </div>
    </header>
    <main class="content-area">
      <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert"><?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif; ?>
      <?php if ($oneTimeCode): ?><div class="alert alert-success d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"><div><strong>Kode akses pengurus berhasil dibuat.</strong><div class="small">Kode hanya ditampilkan sekali. Salin dan berikan kepada calon pengurus.</div><code class="fs-5 fw-bold text-dark"><?= e($oneTimeCode) ?></code></div><button class="btn btn-sm btn-success" type="button" data-copy="<?= e($oneTimeCode) ?>"><i class="bi bi-clipboard me-1"></i>Salin kode</button></div><?php endif; ?>
      <?php
      $viewFile = __DIR__ . '/../pages/' . $page . '.php';
      if (is_file($viewFile)) require $viewFile;
      else require __DIR__ . '/../pages/dashboard.php';
      ?>
    </main>
    <footer class="app-footer"><span>© <?= date('Y') ?> SIPARTA</span><span>Sistem Informasi Pelayanan dan Administrasi RT</span></footer>
  </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
