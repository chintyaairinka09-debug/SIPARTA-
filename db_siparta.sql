-- ============================================================
-- SIPARTA - Sistem Informasi Pelayanan dan Administrasi RT
-- Database : db_siparta
-- Target   : MySQL / MariaDB (XAMPP + phpMyAdmin)
-- Charset  : utf8mb4
--
-- CATATAN:
-- 1. Import file ini melalui phpMyAdmin > tab Import.
-- 2. File ini tidak menghapus database lama (tidak memakai DROP DATABASE).
-- 3. Password akun admin awal sudah disimpan dalam bentuk hash.
-- 4. Kode akses pengurus nantinya juga disimpan dalam bentuk SHA-256 hash.
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_siparta
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_siparta;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. USERS: akun login untuk Admin, Pengurus, dan Warga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(120) NOT NULL,
  username VARCHAR(50) DEFAULT NULL,
  email VARCHAR(120) NOT NULL,
  no_hp VARCHAR(25) DEFAULT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Simpan hasil password_hash(), jangan plaintext',
  role ENUM('admin','pengurus','warga') NOT NULL DEFAULT 'warga',
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  KEY idx_users_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. DATA_WARGA: biodata kependudukan.
--    user_id boleh NULL untuk warga yang didata pengurus tetapi
--    belum mempunyai akun SIPARTA.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS data_warga (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  nik CHAR(16) NOT NULL,
  no_kk CHAR(16) DEFAULT NULL,
  nama_lengkap VARCHAR(120) NOT NULL,
  jenis_kelamin ENUM('laki-laki','perempuan') DEFAULT NULL,
  tempat_lahir VARCHAR(80) DEFAULT NULL,
  tanggal_lahir DATE DEFAULT NULL,
  alamat TEXT NOT NULL,
  rt VARCHAR(10) DEFAULT NULL,
  rw VARCHAR(10) DEFAULT NULL,
  status_keluarga VARCHAR(50) DEFAULT NULL COMMENT 'Contoh: Kepala Keluarga, Istri, Anak, Lainnya',
  pekerjaan VARCHAR(100) DEFAULT NULL,
  no_hp VARCHAR(25) DEFAULT NULL,
  tanggal_masuk DATE DEFAULT NULL,
  status ENUM('aktif','nonaktif','pindah','meninggal') NOT NULL DEFAULT 'aktif',
  foto_path VARCHAR(255) DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_warga_user (user_id),
  UNIQUE KEY uq_data_warga_nik (nik),
  KEY idx_data_warga_nama (nama_lengkap),
  KEY idx_data_warga_status (status),
  KEY idx_data_warga_no_kk (no_kk),
  CONSTRAINT fk_data_warga_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_data_warga_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. DATA_PENGURUS: data jabatan dan masa tugas Pengurus/RT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS data_pengurus (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  data_warga_id BIGINT UNSIGNED DEFAULT NULL,
  jabatan VARCHAR(100) NOT NULL,
  rt VARCHAR(10) DEFAULT NULL,
  rw VARCHAR(10) DEFAULT NULL,
  periode_mulai DATE DEFAULT NULL,
  periode_selesai DATE DEFAULT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_pengurus_user (user_id),
  UNIQUE KEY uq_data_pengurus_warga (data_warga_id),
  KEY idx_data_pengurus_jabatan (jabatan),
  KEY idx_data_pengurus_status (status),
  CONSTRAINT fk_data_pengurus_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_data_pengurus_warga FOREIGN KEY (data_warga_id) REFERENCES data_warga(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_data_pengurus_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. ACCESS_CODES: kode sekali pakai untuk pendaftaran Pengurus.
--    Simpan SHA-256 dari kode, bukan kode asli.
--    PHP: hash('sha256', strtoupper(trim($kode)))
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS access_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_hash CHAR(64) NOT NULL,
  role ENUM('pengurus') NOT NULL DEFAULT 'pengurus',
  status ENUM('aktif','digunakan','dicabut','kedaluwarsa') NOT NULL DEFAULT 'aktif',
  max_penggunaan SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  jumlah_digunakan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  berlaku_sampai DATETIME DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  digunakan_oleh BIGINT UNSIGNED DEFAULT NULL,
  digunakan_pada DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_access_codes_hash (kode_hash),
  KEY idx_access_codes_status (status, berlaku_sampai),
  CONSTRAINT fk_access_codes_creator FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_access_codes_user FOREIGN KEY (digunakan_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. JENIS_LAYANAN: daftar layanan administrasi yang tersedia
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jenis_layanan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(30) NOT NULL,
  nama_layanan VARCHAR(120) NOT NULL,
  deskripsi TEXT DEFAULT NULL,
  persyaratan TEXT DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  urutan INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jenis_layanan_kode (kode),
  KEY idx_jenis_layanan_aktif (aktif, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. PENGAJUAN: pengajuan surat/administrasi dari warga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengajuan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nomor_pengajuan VARCHAR(40) NOT NULL,
  data_warga_id BIGINT UNSIGNED NOT NULL,
  jenis_layanan_id BIGINT UNSIGNED DEFAULT NULL,
  nama_layanan VARCHAR(120) NOT NULL COMMENT 'Snapshot nama layanan saat pengajuan dibuat',
  keperluan VARCHAR(255) NOT NULL,
  keterangan TEXT DEFAULT NULL,
  status ENUM('menunggu','diproses','disetujui','ditolak','selesai') NOT NULL DEFAULT 'menunggu',
  ditugaskan_ke BIGINT UNSIGNED DEFAULT NULL,
  diproses_oleh BIGINT UNSIGNED DEFAULT NULL,
  alasan_penolakan TEXT DEFAULT NULL,
  diajukan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_pada DATETIME DEFAULT NULL,
  selesai_pada DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pengajuan_nomor (nomor_pengajuan),
  KEY idx_pengajuan_warga (data_warga_id, created_at),
  KEY idx_pengajuan_status (status, created_at),
  KEY idx_pengajuan_jenis (jenis_layanan_id),
  KEY idx_pengajuan_petugas (ditugaskan_ke),
  CONSTRAINT fk_pengajuan_warga FOREIGN KEY (data_warga_id) REFERENCES data_warga(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pengajuan_jenis FOREIGN KEY (jenis_layanan_id) REFERENCES jenis_layanan(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pengajuan_assignee FOREIGN KEY (ditugaskan_ke) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pengajuan_processor FOREIGN KEY (diproses_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. PENGAJUAN_STATUS_HISTORY: riwayat/timeline perubahan status
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengajuan_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajuan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('menunggu','diproses','disetujui','ditolak','selesai') NOT NULL,
  catatan TEXT DEFAULT NULL,
  diubah_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pengajuan_history (pengajuan_id, created_at),
  CONSTRAINT fk_pengajuan_history_pengajuan FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pengajuan_history_user FOREIGN KEY (diubah_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. PENGAJUAN_BERKAS: lampiran dokumen dari pengajuan warga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengajuan_berkas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengajuan_id BIGINT UNSIGNED NOT NULL,
  nama_file_asli VARCHAR(255) NOT NULL,
  nama_file VARCHAR(255) NOT NULL,
  path_file VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  ukuran_byte BIGINT UNSIGNED DEFAULT NULL,
  diunggah_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pengajuan_berkas (pengajuan_id),
  CONSTRAINT fk_pengajuan_berkas_pengajuan FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pengajuan_berkas_user FOREIGN KEY (diunggah_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. PENGUMUMAN: informasi yang dipublikasikan kepada warga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengumuman (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  judul VARCHAR(180) NOT NULL,
  ringkasan VARCHAR(500) DEFAULT NULL,
  isi LONGTEXT NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  prioritas ENUM('normal','penting') NOT NULL DEFAULT 'normal',
  tanggal_publish DATETIME DEFAULT NULL,
  ditampilkan_sampai DATETIME DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  diubah_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pengumuman_status_tanggal (status, tanggal_publish),
  KEY idx_pengumuman_prioritas (prioritas),
  CONSTRAINT fk_pengumuman_creator FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pengumuman_editor FOREIGN KEY (diubah_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. KEGIATAN: kalender dan jadwal kegiatan lingkungan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kegiatan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_kegiatan VARCHAR(180) NOT NULL,
  deskripsi TEXT DEFAULT NULL,
  mulai_pada DATETIME NOT NULL,
  selesai_pada DATETIME DEFAULT NULL,
  lokasi VARCHAR(255) NOT NULL,
  penanggung_jawab VARCHAR(120) DEFAULT NULL,
  status ENUM('terjadwal','berlangsung','selesai','dibatalkan') NOT NULL DEFAULT 'terjadwal',
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  diubah_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kegiatan_mulai (mulai_pada),
  KEY idx_kegiatan_status (status),
  CONSTRAINT fk_kegiatan_creator FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_kegiatan_editor FOREIGN KEY (diubah_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. KELUHAN_SARAN: aspirasi yang dikirim oleh warga
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS keluhan_saran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nomor_laporan VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  data_warga_id BIGINT UNSIGNED NOT NULL,
  jenis ENUM('keluhan','saran') NOT NULL,
  kategori VARCHAR(100) NOT NULL COMMENT 'Fasilitas Lingkungan, Kebersihan, Keamanan, Pelayanan, Lainnya',
  judul VARCHAR(180) NOT NULL,
  isi TEXT NOT NULL,
  status ENUM('baru','diproses','selesai','ditolak') NOT NULL DEFAULT 'baru',
  tanggapan TEXT DEFAULT NULL,
  ditangani_oleh BIGINT UNSIGNED DEFAULT NULL,
  ditanggapi_pada DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_keluhan_nomor (nomor_laporan),
  KEY idx_keluhan_user (user_id, created_at),
  KEY idx_keluhan_status (status, created_at),
  KEY idx_keluhan_kategori (kategori),
  CONSTRAINT fk_keluhan_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_keluhan_warga FOREIGN KEY (data_warga_id) REFERENCES data_warga(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_keluhan_handler FOREIGN KEY (ditangani_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. KELUHAN_LAMPIRAN: foto/dokumen pendukung keluhan atau saran
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS keluhan_lampiran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  keluhan_id BIGINT UNSIGNED NOT NULL,
  nama_file_asli VARCHAR(255) NOT NULL,
  nama_file VARCHAR(255) NOT NULL,
  path_file VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  ukuran_byte BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_keluhan_lampiran (keluhan_id),
  CONSTRAINT fk_keluhan_lampiran_parent FOREIGN KEY (keluhan_id) REFERENCES keluhan_saran(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. ARSIP_DOKUMEN: arsip digital yang dikelola Admin/Pengurus
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS arsip_dokumen (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  judul VARCHAR(180) NOT NULL,
  kategori ENUM('surat_pengantar','surat_keterangan','dokumen_warga','laporan','dokumen_kegiatan','lainnya') NOT NULL DEFAULT 'lainnya',
  deskripsi TEXT DEFAULT NULL,
  nama_file_asli VARCHAR(255) NOT NULL,
  nama_file VARCHAR(255) NOT NULL,
  path_file VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  ekstensi VARCHAR(15) DEFAULT NULL,
  ukuran_byte BIGINT UNSIGNED DEFAULT NULL,
  tanggal_dokumen DATE DEFAULT NULL,
  akses ENUM('admin_pengurus','semua_pengguna') NOT NULL DEFAULT 'admin_pengurus',
  pengajuan_id BIGINT UNSIGNED DEFAULT NULL,
  diunggah_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_arsip_kategori (kategori),
  KEY idx_arsip_tanggal (tanggal_dokumen),
  KEY idx_arsip_pengajuan (pengajuan_id),
  CONSTRAINT fk_arsip_pengajuan FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_arsip_uploader FOREIGN KEY (diunggah_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. NOTIFIKASI: pemberitahuan untuk masing-masing akun
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifikasi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  judul VARCHAR(180) NOT NULL,
  pesan TEXT NOT NULL,
  tipe ENUM('pengajuan','keluhan','pengumuman','kegiatan','akun','sistem','lainnya') NOT NULL DEFAULT 'sistem',
  tautan VARCHAR(500) DEFAULT NULL,
  referensi_tabel VARCHAR(60) DEFAULT NULL,
  referensi_id BIGINT UNSIGNED DEFAULT NULL,
  dibaca_pada DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifikasi_user_baca (user_id, dibaca_pada, created_at),
  CONSTRAINT fk_notifikasi_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. PROFIL_RT: identitas lingkungan pada menu Pengaturan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profil_rt (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_rt VARCHAR(80) NOT NULL DEFAULT 'RT',
  nama_rw VARCHAR(80) NOT NULL DEFAULT 'RW',
  kelurahan VARCHAR(120) DEFAULT NULL,
  kecamatan VARCHAR(120) DEFAULT NULL,
  kabupaten_kota VARCHAR(120) DEFAULT NULL,
  provinsi VARCHAR(120) DEFAULT NULL,
  kode_pos VARCHAR(10) DEFAULT NULL,
  alamat_sekretariat TEXT DEFAULT NULL,
  nomor_telepon VARCHAR(25) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  logo_path VARCHAR(255) DEFAULT NULL,
  updated_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_profil_rt_editor FOREIGN KEY (updated_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. PENGATURAN_SISTEM: preferensi aplikasi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengaturan_sistem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kunci VARCHAR(100) NOT NULL,
  nilai TEXT DEFAULT NULL,
  grup VARCHAR(80) NOT NULL DEFAULT 'umum',
  diperbarui_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pengaturan_kunci (kunci),
  CONSTRAINT fk_pengaturan_editor FOREIGN KEY (diperbarui_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 17. LAPORAN_EKSPOR: catatan file laporan yang pernah diekspor
--     Angka/grafik laporan dihitung dari tabel transaksi terkait.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan_ekspor (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  judul VARCHAR(180) NOT NULL,
  jenis VARCHAR(80) NOT NULL,
  periode_mulai DATE DEFAULT NULL,
  periode_selesai DATE DEFAULT NULL,
  format_file ENUM('pdf','csv','xlsx') NOT NULL DEFAULT 'pdf',
  path_file VARCHAR(500) DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_laporan_ekspor_user_tanggal (dibuat_oleh, created_at),
  CONSTRAINT fk_laporan_ekspor_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 18. PASSWORD_RESET_TOKENS: token reset password
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_reset_hash (token_hash),
  KEY idx_password_reset_user (user_id, expires_at),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 19. AUDIT_LOGS: catatan aktivitas penting untuk pemeriksaan Admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  aktivitas VARCHAR(100) NOT NULL,
  modul VARCHAR(80) DEFAULT NULL,
  record_id BIGINT UNSIGNED DEFAULT NULL,
  deskripsi TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user_tanggal (user_id, created_at),
  KEY idx_audit_modul_tanggal (modul, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATA AWAL
-- ============================================================

-- Akun Admin awal. Ganti password setelah fitur ubah password tersedia.
-- Email    : admin@siparta.local
-- Username : admin
-- Password sementara: SIPARTA!Admin2026
-- Password di bawah adalah HASH bcrypt, bukan teks biasa.
INSERT IGNORE INTO users
  (id, nama, username, email, no_hp, password, role, status)
VALUES
  (1, 'Administrator SIPARTA', 'admin', 'admin@siparta.local', NULL,
   '$2y$12$8VgSTwK1INh2q9CTw.Cnz.BbD3tk8a0usjbitfvlwaZcow2bM.xr2', 'admin', 'aktif');

-- Jenis layanan awal sesuai rancangan halaman Pengajuan Administrasi.
INSERT IGNORE INTO jenis_layanan (kode, nama_layanan, deskripsi, aktif, urutan) VALUES
  ('SURAT-DOMISILI', 'Surat Domisili', 'Pengajuan surat keterangan domisili warga.', 1, 1),
  ('SURAT-PENGANTAR', 'Surat Pengantar', 'Pengajuan surat pengantar dari RT.', 1, 2),
  ('SURAT-KETERANGAN', 'Surat Keterangan', 'Pengajuan surat keterangan sesuai keperluan warga.', 1, 3),
  ('LAINNYA', 'Layanan Administrasi Lainnya', 'Layanan administrasi lain yang disediakan pengurus RT.', 1, 4);

-- Profil awal; detail RT/RW dapat diubah dari menu Pengaturan.
INSERT IGNORE INTO profil_rt (id, nama_rt, nama_rw) VALUES (1, 'RT', 'RW');

-- Preferensi awal sistem.
INSERT IGNORE INTO pengaturan_sistem (kunci, nilai, grup) VALUES
  ('notifikasi_email', '0', 'notifikasi'),
  ('notifikasi_sms', '0', 'notifikasi'),
  ('mode_maintenance', '0', 'sistem');

-- ============================================================
-- SELESAI
-- Tabel utama dibuat tanpa akun demo Pengurus/Warga dan tanpa
-- data penduduk palsu. Akun Pengurus/Warga dibuat melalui aplikasi.
-- ============================================================
