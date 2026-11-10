<?php
function db(): PDO { global $pdo; return $pdo; }
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'dashboard', array $params = []): string {
    $query = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}
function redirect(string $page = 'dashboard', array $params = []): never { header('Location: ' . url($page, $params)); exit; }
function post(string $key, mixed $default = ''): mixed { return $_POST[$key] ?? $default; }
function get(string $key, mixed $default = ''): mixed { return $_GET[$key] ?? $default; }
function set_flash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Silakan kembali dan muat ulang halaman.');
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function user_id(): ?int { return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }
function role(): ?string { return $_SESSION['user']['role'] ?? null; }
function role_label(?string $role): string { return match ($role) { 'admin' => 'Administrator', 'pengurus' => 'Pengurus / RT', 'warga' => 'Warga', default => 'Pengunjung' }; }
function format_date(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '-';
    try { return (new DateTime($date))->format($format); } catch (Throwable) { return '-'; }
}
function format_datetime(?string $date): string { return format_date($date, 'd M Y, H:i'); }
function rupiah(int|float $value): string { return 'Rp ' . number_format($value, 0, ',', '.'); }
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr($parts[0] ?? 'S', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
function status_badge(string $status): string {
    $map = [
        'menunggu' => ['Menunggu', 'warning'], 'diproses' => ['Diproses', 'info'],
        'disetujui' => ['Disetujui', 'success'], 'ditolak' => ['Ditolak', 'danger'],
        'selesai' => ['Selesai', 'success'], 'baru' => ['Baru', 'warning'],
        'aktif' => ['Aktif', 'success'], 'nonaktif' => ['Nonaktif', 'secondary'],
        'pindah' => ['Pindah', 'secondary'], 'meninggal' => ['Meninggal', 'secondary'],
        'published' => ['Terbit', 'success'], 'draft' => ['Draft', 'secondary'],
        'archived' => ['Diarsipkan', 'secondary'], 'terjadwal' => ['Terjadwal', 'info'],
        'berlangsung' => ['Berlangsung', 'success'], 'dibatalkan' => ['Dibatalkan', 'danger'],
    ];
    [$label, $class] = $map[strtolower($status)] ?? [ucfirst(str_replace('_', ' ', $status)), 'secondary'];
    return '<span class="badge rounded-pill text-bg-' . $class . '">' . e($label) . '</span>';
}
function audit_log(string $activity, ?string $module = null, ?int $recordId = null, ?string $description = null): void {
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, aktivitas, modul, record_id, deskripsi, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([user_id(), $activity, $module, $recordId, $description, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    } catch (Throwable) { /* Audit tidak boleh menghentikan proses utama. */ }
}
function notify_user(int $targetUserId, string $title, string $message, string $type = 'sistem', ?string $link = null, ?string $refTable = null, ?int $refId = null): void {
    $stmt = db()->prepare('INSERT INTO notifikasi (user_id, judul, pesan, tipe, tautan, referensi_tabel, referensi_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$targetUserId, $title, $message, $type, $link, $refTable, $refId]);
}
function notify_roles(array $roles, string $title, string $message, string $type = 'sistem', ?string $link = null, ?string $refTable = null, ?int $refId = null): void {
    if (!$roles) return;
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = db()->prepare("SELECT id FROM users WHERE status='aktif' AND role IN ($placeholders)");
    $stmt->execute($roles);
    foreach ($stmt->fetchAll() as $user) notify_user((int)$user['id'], $title, $message, $type, $link, $refTable, $refId);
}
function warga_for_user(int $uid): ?array {
    $stmt = db()->prepare('SELECT * FROM data_warga WHERE user_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: null;
}
function upload_file(string $field, string $folder, array $allowedExtensions = ['pdf','jpg','jpeg','png'], int $maxBytes = 5242880): ?array {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Unggahan file gagal. Silakan coba lagi.');
    if ($file['size'] > $maxBytes) throw new RuntimeException('Ukuran file maksimal 5 MB.');
    $original = basename((string)$file['name']);
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) throw new RuntimeException('Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
    $mimeByExtension = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
    if (($mimeByExtension[$extension] ?? null) !== $mime) throw new RuntimeException('Isi file tidak sesuai dengan ekstensi yang dipilih.');
    $relativeDir = 'uploads/' . trim($folder, '/');
    $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
    $stored = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $stored)) throw new RuntimeException('File tidak dapat disimpan.');
    return ['nama_file_asli' => $original, 'nama_file' => $stored, 'path_file' => $relativeDir . '/' . $stored, 'mime_type' => $mime, 'ukuran_byte' => (int)$file['size'], 'ekstensi' => $extension];
}
function delete_uploaded_file(?string $relativePath): void {
    if (!$relativePath) return;
    $base = realpath(dirname(__DIR__) . '/uploads');
    $target = realpath(dirname(__DIR__) . '/' . $relativePath);
    if ($base && $target && str_starts_with($target, $base . DIRECTORY_SEPARATOR) && is_file($target)) @unlink($target);
}
function safe_return_page(string $page, array $allowed): string { return in_array($page, $allowed, true) ? $page : 'dashboard'; }
function page_title(string $page): string {
    $titles = ['dashboard'=>'Dashboard','data-warga'=>'Data Warga','pengurus'=>'Pengurus / RT','pengajuan'=>'Pengajuan Administrasi','detail-pengajuan'=>'Detail Pengajuan','pengumuman'=>'Pengumuman','kegiatan'=>'Kegiatan','arsip'=>'Arsip Dokumen','laporan'=>'Laporan','keluhan'=>'Keluhan & Saran','notifikasi'=>'Notifikasi','manajemen-akun'=>'Manajemen Akun','pengaturan'=>'Pengaturan','ajukan'=>'Ajukan Administrasi','riwayat'=>'Riwayat Pengajuan','profil'=>'Profil Saya'];
    return $titles[$page] ?? 'SIPARTA';
}
