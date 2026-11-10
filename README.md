# SIPARTA — Sistem Informasi Pelayanan dan Administrasi RT

Aplikasi web sekolah berbasis **PHP Native, MySQL/MariaDB, Bootstrap 5, dan JavaScript**. Struktur ini disesuaikan dengan rancangan fitur SIPARTA dan database `db_siparta.sql`.

## Identitas visual

- Hijau army utama: `#556B2F`
- Hijau army gelap: `#3F4F2B`
- Hijau muda: `#E8EDDF`
- Cream: `#F6F1E7`
- Putih: `#FFFFFF`
- Font: **Lexend**
- Ukuran teks utama: sekitar **18 px**, teks pendukung 14–16 px.

Bootstrap dan font Lexend dimuat melalui CDN, sehingga komputer perlu terhubung ke internet saat membuka aplikasi agar tampil optimal.

## Instalasi di XAMPP

1. Ekstrak folder `SIPARTA_PHP` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih menu **Import**, pilih file `db_siparta.sql` yang ada di folder project ini, lalu klik **Go/Import**.
5. Buka `config/database.php`. Pengaturan bawaan memakai host `127.0.0.1`, database `db_siparta`, user `root`, password kosong. Sesuaikan bila konfigurasi MySQL milikmu berbeda.
6. Buka `http://localhost/SIPARTA_PHP/`.

## Login Admin awal

- Username: `admin`
- Email: `admin@siparta.local`
- Kata sandi awal: `SIPARTA!Admin2026`

Segera ganti kata sandi melalui menu **Profil Saya** setelah login pertama. Password disimpan memakai `password_hash()`.

## Alur akun

- **Warga:** dapat mendaftar sendiri melalui tombol Daftar sebagai Warga. Saat registrasi berhasil, aplikasi membuat akun di `users` serta biodata terkait di `data_warga` dalam satu transaksi.
- **Pengurus/RT:** harus mendapat kode sekali pakai dari Admin melalui menu **Pengurus / RT**. Kode asli hanya ditampilkan satu kali; database menyimpan SHA-256-nya. Saat registrasi, akun dan data kepengurusan dibuat sekaligus.
- **Admin:** akun awal dibuat oleh skrip SQL. Tidak ada pendaftaran Admin publik.

## Fitur yang tersedia

- Login, logout, registrasi Warga, registrasi Pengurus dengan kode akses.
- Pembatasan akses halaman berdasarkan role Admin, Pengurus, dan Warga.
- Dashboard berbasis data database.
- Manajemen data Warga dan data Pengurus.
- Pengajuan administrasi, lampiran, riwayat status, serta pemberitahuan.
- Pengumuman dan kegiatan lingkungan.
- Keluhan/saran Warga dan tanggapan Pengurus/Admin.
- Arsip dokumen dengan pembatasan akses unduhan.
- Rekap laporan dan ekspor CSV.
- Notifikasi, manajemen akun, pengaturan identitas RT/RW, dan profil akun.
- Audit log untuk aktivitas penting.

## Catatan teknis

- Memerlukan PHP 8.1+ dengan ekstensi PDO MySQL dan Fileinfo aktif.
- Semua form POST memakai token CSRF.
- Query database menggunakan PDO prepared statements.
- Password memakai `password_hash()` dan `password_verify()`.
- File unggahan dibatasi ke PDF/JPG/PNG maksimum 5 MB dan folder `uploads` ditutup dari akses URL langsung; file diunduh melalui endpoint PHP yang memeriksa hak akses.
- Ekspor laporan tersedia dalam format CSV.
- Pengiriman email/SMS dan reset password otomatis belum diintegrasikan; halaman lupa kata sandi mengarahkan pengguna untuk menghubungi Admin RT.
- Aplikasi ini ditujukan untuk pengembangan dan demonstrasi lokal. Sebelum dipasang di server publik, lakukan konfigurasi HTTPS, backup, kebijakan retensi data, hardening server, dan uji keamanan tambahan.

## Struktur folder

```text
SIPARTA_PHP/
├── actions/       # handler aksi/form
├── assets/        # CSS dan JavaScript
├── config/        # konfigurasi database
├── includes/      # helper, session, autentikasi
├── uploads/       # unggahan privat
├── views/
│   ├── auth/      # login dan registrasi
│   ├── layout/    # sidebar dan layout utama
│   └── pages/     # halaman aplikasi per fitur
├── db_siparta.sql
└── index.php
```
