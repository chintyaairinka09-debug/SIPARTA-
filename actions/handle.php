<?php
/** Menangani semua aksi POST. Semua query menggunakan PDO prepared statement. */
function handle_post_action(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) return;
    verify_csrf();
    $action = (string)$_POST['action'];
    $pdo = db();

    try {
        switch ($action) {
            case 'login': {
                if (current_user()) redirect('dashboard');
                $identity = trim((string)post('identity'));
                $password = (string)post('password');
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
                $stmt->execute([$identity, $identity]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password'])) throw new RuntimeException('Email/username atau kata sandi tidak sesuai.');
                if ($user['status'] !== 'aktif') throw new RuntimeException('Akun ini sedang nonaktif. Hubungi Admin RT.');
                login_user($user);
                redirect('dashboard');
            }
            case 'register_warga': {
                if (current_user()) redirect('dashboard');
                $name = trim((string)post('nama'));
                $nik = preg_replace('/\D/', '', (string)post('nik'));
                $email = strtolower(trim((string)post('email')));
                $phone = trim((string)post('no_hp'));
                $username = trim((string)post('username')) ?: null;
                $password = (string)post('password');
                $confirm = (string)post('password_confirmation');
                $address = trim((string)post('alamat'));
                $gender = (string)post('jenis_kelamin');
                if ($name === '' || strlen($nik) !== 16 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $address === '') throw new RuntimeException('Lengkapi data. NIK harus 16 digit dan email harus valid.');
                if ($phone !== '' && !preg_match('/^[0-9+().\- ]{8,25}$/', $phone)) throw new RuntimeException('Format nomor HP tidak valid.');
                if (strlen($password) < 8) throw new RuntimeException('Kata sandi minimal 8 karakter.');
                if ($password !== $confirm) throw new RuntimeException('Konfirmasi kata sandi tidak sama.');
                if (!in_array($gender, ['laki-laki','perempuan'], true)) $gender = null;
                $pdo->beginTransaction();
                $check = $pdo->prepare('SELECT id FROM users WHERE email = ? OR (? IS NOT NULL AND username = ?)');
                $check->execute([$email, $username, $username]);
                if ($check->fetch()) throw new RuntimeException('Email atau username sudah digunakan.');
                $check = $pdo->prepare('SELECT id FROM data_warga WHERE nik = ?');
                $check->execute([$nik]);
                if ($check->fetch()) throw new RuntimeException('NIK sudah terdaftar dalam data warga. Silakan hubungi pengurus apabila data tersebut milikmu.');
                $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, no_hp, password, role, status) VALUES (?, ?, ?, ?, ?, 'warga', 'aktif')");
                $stmt->execute([$name, $username, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO data_warga (user_id, nik, nama_lengkap, jenis_kelamin, alamat, no_hp, tanggal_masuk, status) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'aktif')");
                $stmt->execute([$uid, $nik, $name, $gender, $address, $phone ?: null]);
                $pdo->commit();
                $user = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $user->execute([$uid]);
                login_user($user->fetch());
                notify_roles(['admin','pengurus'], 'Akun warga baru', $name . ' telah membuat akun SIPARTA.', 'akun', url('data-warga'));
                audit_log('register', 'users', $uid, 'Registrasi warga baru.');
                set_flash('success', 'Pendaftaran berhasil. Selamat datang di SIPARTA!');
                redirect('dashboard');
            }
            case 'register_pengurus': {
                if (current_user()) redirect('dashboard');
                $code = strtoupper(trim((string)post('kode_akses')));
                $name = trim((string)post('nama'));
                $nik = preg_replace('/\D/', '', (string)post('nik'));
                $email = strtolower(trim((string)post('email')));
                $phone = trim((string)post('no_hp'));
                $username = trim((string)post('username')) ?: null;
                $password = (string)post('password');
                $confirm = (string)post('password_confirmation');
                $job = trim((string)post('jabatan'));
                $rt = trim((string)post('rt'));
                $rw = trim((string)post('rw'));
                $address = trim((string)post('alamat'));
                if ($code === '' || $name === '' || strlen($nik) !== 16 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $job === '' || $address === '') throw new RuntimeException('Lengkapi seluruh data. Pastikan NIK 16 digit dan email benar.');
                if (strlen($password) < 8 || $password !== $confirm) throw new RuntimeException('Kata sandi minimal 8 karakter dan konfirmasi harus sama.');
                $pdo->beginTransaction();
                $codeHash = hash('sha256', $code);
                $stmt = $pdo->prepare("SELECT * FROM access_codes WHERE kode_hash = ? AND role = 'pengurus' FOR UPDATE");
                $stmt->execute([$codeHash]);
                $access = $stmt->fetch();
                if (!$access || $access['status'] !== 'aktif' || ($access['berlaku_sampai'] && strtotime($access['berlaku_sampai']) < time()) || (int)$access['jumlah_digunakan'] >= (int)$access['max_penggunaan']) throw new RuntimeException('Kode akses tidak valid, sudah digunakan, atau kedaluwarsa. Hubungi Admin RT.');
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR (? IS NOT NULL AND username = ?)');
                $stmt->execute([$email, $username, $username]);
                if ($stmt->fetch()) throw new RuntimeException('Email atau username sudah digunakan.');
                $stmt = $pdo->prepare('SELECT id, user_id FROM data_warga WHERE nik = ? FOR UPDATE');
                $stmt->execute([$nik]);
                $resident = $stmt->fetch();
                if ($resident && $resident['user_id']) throw new RuntimeException('NIK sudah terhubung ke akun lain. Hubungi Admin untuk penyesuaian data.');
                $stmt = $pdo->prepare("INSERT INTO users (nama, username, email, no_hp, password, role, status) VALUES (?, ?, ?, ?, ?, 'pengurus', 'aktif')");
                $stmt->execute([$name, $username, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                if ($resident) {
                    $residentId = (int)$resident['id'];
                    $stmt = $pdo->prepare('UPDATE data_warga SET user_id = ?, nama_lengkap = ?, alamat = ?, no_hp = ? WHERE id = ?');
                    $stmt->execute([$uid, $name, $address, $phone ?: null, $residentId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO data_warga (user_id, nik, nama_lengkap, alamat, no_hp, rt, rw, tanggal_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'aktif')");
                    $stmt->execute([$uid, $nik, $name, $address, $phone ?: null, $rt ?: null, $rw ?: null]);
                    $residentId = (int)$pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO data_pengurus (user_id, data_warga_id, jabatan, rt, rw, periode_mulai, status, created_by) VALUES (?, ?, ?, ?, ?, CURDATE(), 'aktif', ?)");
                $stmt->execute([$uid, $residentId, $job, $rt ?: null, $rw ?: null, $access['dibuat_oleh']]);
                $newCount = (int)$access['jumlah_digunakan'] + 1;
                $newStatus = $newCount >= (int)$access['max_penggunaan'] ? 'digunakan' : 'aktif';
                $stmt = $pdo->prepare('UPDATE access_codes SET jumlah_digunakan = ?, status = ?, digunakan_oleh = ?, digunakan_pada = NOW() WHERE id = ?');
                $stmt->execute([$newCount, $newStatus, $uid, $access['id']]);
                $pdo->commit();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $stmt->execute([$uid]);
                login_user($stmt->fetch());
                notify_roles(['admin'], 'Pengurus baru terdaftar', $name . ' telah menyelesaikan pendaftaran Pengurus.', 'akun', url('pengurus'));
                audit_log('register', 'users', $uid, 'Registrasi pengurus menggunakan kode akses.');
                set_flash('success', 'Pendaftaran Pengurus berhasil.');
                redirect('dashboard');
            }
            case 'create_access_code': {
                require_roles(['admin']);
                $expires = trim((string)post('berlaku_sampai'));
                $plain = 'RT-' . strtoupper(bin2hex(random_bytes(4)));
                $expiry = null;
                if ($expires !== '') { $ts = strtotime($expires); if (!$ts || $ts < time()) throw new RuntimeException('Tanggal kedaluwarsa harus berada di masa depan.'); $expiry = date('Y-m-d H:i:s', $ts); }
                $stmt = $pdo->prepare("INSERT INTO access_codes (kode_hash, role, status, max_penggunaan, dibuat_oleh, berlaku_sampai) VALUES (?, 'pengurus', 'aktif', 1, ?, ?)");
                $stmt->execute([hash('sha256', $plain), user_id(), $expiry]);
                $_SESSION['one_time_code'] = $plain;
                audit_log('create_access_code', 'access_codes', (int)$pdo->lastInsertId(), 'Admin membuat kode pendaftaran pengurus.');
                set_flash('success', 'Kode akses berhasil dibuat. Salin kode yang muncul; kode asli tidak disimpan dalam database.');
                redirect('pengurus');
            }
            case 'revoke_access_code': {
                require_roles(['admin']);
                $id = (int)post('id');
                $stmt = $pdo->prepare("UPDATE access_codes SET status='dicabut' WHERE id=? AND status='aktif'"); $stmt->execute([$id]);
                audit_log('revoke_access_code', 'access_codes', $id, 'Admin mencabut kode akses.');
                set_flash('success', 'Kode akses berhasil dicabut.'); redirect('pengurus');
            }
            case 'save_warga': {
                require_roles(['admin','pengurus']);
                $id = (int)post('id'); $nik = preg_replace('/\D/', '', (string)post('nik')); $name = trim((string)post('nama_lengkap')); $address = trim((string)post('alamat'));
                if (strlen($nik) !== 16 || $name === '' || $address === '') throw new RuntimeException('Nama, alamat, dan NIK 16 digit wajib diisi.');
                $gender = in_array(post('jenis_kelamin'), ['laki-laki','perempuan'], true) ? post('jenis_kelamin') : null;
                $data = [$nik, trim((string)post('no_kk')) ?: null, $name, $gender, trim((string)post('tempat_lahir')) ?: null, post('tanggal_lahir') ?: null, $address, trim((string)post('rt')) ?: null, trim((string)post('rw')) ?: null, trim((string)post('status_keluarga')) ?: null, trim((string)post('pekerjaan')) ?: null, trim((string)post('no_hp')) ?: null, post('status') ?: 'aktif'];
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE data_warga SET nik=?, no_kk=?, nama_lengkap=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, alamat=?, rt=?, rw=?, status_keluarga=?, pekerjaan=?, no_hp=?, status=? WHERE id=?');
                    $stmt->execute([...$data, $id]);
                    $pdo->prepare('UPDATE users SET nama=?, no_hp=? WHERE id=(SELECT user_id FROM data_warga WHERE id=?)')->execute([$name, $data[11], $id]);
                    audit_log('update', 'data_warga', $id, 'Data warga diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO data_warga (nik,no_kk,nama_lengkap,jenis_kelamin,tempat_lahir,tanggal_lahir,alamat,rt,rw,status_keluarga,pekerjaan,no_hp,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([...$data, user_id()]); audit_log('create', 'data_warga', (int)$pdo->lastInsertId(), 'Data warga ditambahkan.');
                }
                set_flash('success', 'Data warga berhasil disimpan.'); redirect('data-warga');
            }
            case 'set_warga_status': {
                require_roles(['admin','pengurus']); $id=(int)post('id'); $status=(string)post('status');
                if (!in_array($status,['aktif','nonaktif','pindah','meninggal'],true)) throw new RuntimeException('Status warga tidak valid.');
                $pdo->prepare('UPDATE data_warga SET status=? WHERE id=?')->execute([$status,$id]);
                $pdo->prepare("UPDATE users u JOIN data_warga w ON w.user_id=u.id SET u.status=IF(?='aktif','aktif','nonaktif') WHERE w.id=?")->execute([$status,$id]);
                audit_log('change_status','data_warga',$id,'Status warga diubah menjadi '.$status.'.'); set_flash('success','Status warga diperbarui.'); redirect('data-warga');
            }
            case 'save_pengumuman': {
                require_roles(['admin','pengurus']); $id=(int)post('id'); $title=trim((string)post('judul')); $summary=trim((string)post('ringkasan')); $body=trim((string)post('isi')); $status=in_array(post('status'),['draft','published'],true)?post('status'):'draft'; $priority=in_array(post('prioritas'),['normal','penting'],true)?post('prioritas'):'normal';
                if ($title==='' || $body==='') throw new RuntimeException('Judul dan isi pengumuman wajib diisi.');
                if ($id) { $stmt=$pdo->prepare('UPDATE pengumuman SET judul=?,ringkasan=?,isi=?,status=?,prioritas=?,tanggal_publish=IF(?=\'published\',COALESCE(tanggal_publish,NOW()),NULL),diubah_oleh=? WHERE id=?'); $stmt->execute([$title,$summary,$body,$status,$priority,$status,user_id(),$id]); }
                else { $stmt=$pdo->prepare('INSERT INTO pengumuman (judul,ringkasan,isi,status,prioritas,tanggal_publish,dibuat_oleh,diubah_oleh) VALUES (?,?,?,?,?,IF(?=\'published\',NOW(),NULL),?,?)'); $stmt->execute([$title,$summary,$body,$status,$priority,$status,user_id(),user_id()]); $id=(int)$pdo->lastInsertId(); }
                if ($status==='published') notify_roles(['warga'],'Pengumuman baru: '.$title,$summary ?: 'Ada pengumuman baru dari pengurus RT.','pengumuman',url('pengumuman'), 'pengumuman',$id);
                audit_log('save','pengumuman',$id,'Pengumuman disimpan.'); set_flash('success','Pengumuman berhasil disimpan.'); redirect('pengumuman');
            }
            case 'delete_pengumuman': { require_roles(['admin','pengurus']); $id=(int)post('id'); $pdo->prepare("UPDATE pengumuman SET status='archived',diubah_oleh=? WHERE id=?")->execute([user_id(),$id]); audit_log('archive','pengumuman',$id,'Pengumuman diarsipkan.'); set_flash('success','Pengumuman diarsipkan.'); redirect('pengumuman'); }
            case 'save_kegiatan': {
                require_roles(['admin','pengurus']); $id=(int)post('id'); $name=trim((string)post('nama_kegiatan')); $start=(string)post('mulai_pada'); $end=trim((string)post('selesai_pada')); $location=trim((string)post('lokasi')); $description=trim((string)post('deskripsi')); $pic=trim((string)post('penanggung_jawab'));
                if ($name==='' || !$start || $location==='') throw new RuntimeException('Nama kegiatan, waktu mulai, dan lokasi wajib diisi.');
                if ($id) { $stmt=$pdo->prepare('UPDATE kegiatan SET nama_kegiatan=?,deskripsi=?,mulai_pada=?,selesai_pada=?,lokasi=?,penanggung_jawab=?,diubah_oleh=? WHERE id=?'); $stmt->execute([$name,$description,$start,$end ?: null,$location,$pic,user_id(),$id]); }
                else { $stmt=$pdo->prepare('INSERT INTO kegiatan (nama_kegiatan,deskripsi,mulai_pada,selesai_pada,lokasi,penanggung_jawab,dibuat_oleh,diubah_oleh) VALUES (?,?,?,?,?,?,?,?)'); $stmt->execute([$name,$description,$start,$end ?: null,$location,$pic,user_id(),user_id()]); $id=(int)$pdo->lastInsertId(); notify_roles(['warga'],'Kegiatan RT baru: '.$name,format_datetime($start).' · '.$location,'kegiatan',url('kegiatan'),'kegiatan',$id); }
                audit_log('save','kegiatan',$id,'Kegiatan disimpan.'); set_flash('success','Kegiatan berhasil disimpan.'); redirect('kegiatan');
            }
            case 'delete_kegiatan': { require_roles(['admin','pengurus']); $id=(int)post('id'); $pdo->prepare("UPDATE kegiatan SET status='dibatalkan',diubah_oleh=? WHERE id=?")->execute([user_id(),$id]); audit_log('cancel','kegiatan',$id,'Kegiatan dibatalkan.'); set_flash('success','Kegiatan dibatalkan.'); redirect('kegiatan'); }
            case 'store_pengajuan': {
                require_roles(['warga']); $resident=warga_for_user(user_id()); if (!$resident) throw new RuntimeException('Data warga belum terhubung ke akunmu. Hubungi Admin RT.');
                $service=(int)post('jenis_layanan_id'); $purpose=trim((string)post('keperluan')); $note=trim((string)post('keterangan'));
                $stmt=$pdo->prepare('SELECT * FROM jenis_layanan WHERE id=? AND aktif=1'); $stmt->execute([$service]); $kind=$stmt->fetch();
                if (!$kind || $purpose==='') throw new RuntimeException('Pilih layanan dan isi keperluan pengajuan.');
                $file=upload_file('berkas','pengajuan');
                $pdo->beginTransaction();
                $number='PJN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt=$pdo->prepare("INSERT INTO pengajuan (nomor_pengajuan,data_warga_id,jenis_layanan_id,nama_layanan,keperluan,keterangan,status) VALUES (?,?,?,?,?,?,'menunggu')");
                $stmt->execute([$number,$resident['id'],$kind['id'],$kind['nama_layanan'],$purpose,$note]); $id=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO pengajuan_status_history (pengajuan_id,status,catatan,diubah_oleh) VALUES (?,'menunggu','Pengajuan berhasil dikirim.',?)")->execute([$id,user_id()]);
                if ($file) $pdo->prepare('INSERT INTO pengajuan_berkas (pengajuan_id,nama_file_asli,nama_file,path_file,mime_type,ukuran_byte,diunggah_oleh) VALUES (?,?,?,?,?,?,?)')->execute([$id,$file['nama_file_asli'],$file['nama_file'],$file['path_file'],$file['mime_type'],$file['ukuran_byte'],user_id()]);
                $pdo->commit(); notify_roles(['admin','pengurus'],'Pengajuan administrasi baru',$resident['nama_lengkap'].' mengajukan '.$kind['nama_layanan'].'.','pengajuan',url('pengajuan-detail',['id'=>$id]),'pengajuan',$id);
                audit_log('create','pengajuan',$id,'Pengajuan baru '.$number); set_flash('success','Pengajuan berhasil dikirim. Nomor pengajuan: '.$number); redirect('riwayat');
            }
            case 'update_pengajuan_status': {
                require_roles(['admin','pengurus']); $id=(int)post('id'); $status=(string)post('status'); $note=trim((string)post('catatan')); $allowed=['diproses','disetujui','ditolak','selesai'];
                if (!in_array($status,$allowed,true)) throw new RuntimeException('Status pengajuan tidak valid.');
                if ($status==='ditolak' && $note==='') throw new RuntimeException('Alasan penolakan wajib ditulis.');
                $stmt=$pdo->prepare('SELECT p.*, w.user_id FROM pengajuan p JOIN data_warga w ON w.id=p.data_warga_id WHERE p.id=?'); $stmt->execute([$id]); $item=$stmt->fetch(); if (!$item) throw new RuntimeException('Pengajuan tidak ditemukan.');
                $pdo->beginTransaction();
                $stmt=$pdo->prepare('UPDATE pengajuan SET status=?,diproses_oleh=?,diproses_pada=IF(diproses_pada IS NULL,NOW(),diproses_pada),selesai_pada=IF(?=\'selesai\',NOW(),selesai_pada),alasan_penolakan=IF(?=\'ditolak\',?,NULL) WHERE id=?'); $stmt->execute([$status,user_id(),$status,$status,$note,$id]);
                $pdo->prepare('INSERT INTO pengajuan_status_history (pengajuan_id,status,catatan,diubah_oleh) VALUES (?,?,?,?)')->execute([$id,$status,$note ?: 'Status diperbarui menjadi '.$status.'.',user_id()]);
                $pdo->commit(); if ($item['user_id']) notify_user((int)$item['user_id'],'Status pengajuan diperbarui',$item['nomor_pengajuan'].' sekarang berstatus '.$status.'.','pengajuan',url('riwayat'),'pengajuan',$id);
                audit_log('update_status','pengajuan',$id,'Status menjadi '.$status); set_flash('success','Status pengajuan berhasil diperbarui.'); redirect('detail-pengajuan',['id'=>$id]);
            }
            case 'store_keluhan': {
                require_roles(['warga']); $resident=warga_for_user(user_id()); if (!$resident) throw new RuntimeException('Data warga belum terhubung ke akunmu.');
                $type=in_array(post('jenis'),['keluhan','saran'],true)?post('jenis'):'keluhan'; $category=trim((string)post('kategori')); $title=trim((string)post('judul')); $body=trim((string)post('isi'));
                if ($category==='' || $title==='' || $body==='') throw new RuntimeException('Jenis, kategori, judul, dan isi wajib dilengkapi.');
                $file=upload_file('lampiran','keluhan'); $number='ASP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo->beginTransaction(); $stmt=$pdo->prepare("INSERT INTO keluhan_saran (nomor_laporan,user_id,data_warga_id,jenis,kategori,judul,isi,status) VALUES (?,?,?,?,?,?,?,'baru')"); $stmt->execute([$number,user_id(),$resident['id'],$type,$category,$title,$body]); $id=(int)$pdo->lastInsertId();
                if ($file) $pdo->prepare('INSERT INTO keluhan_lampiran (keluhan_id,nama_file_asli,nama_file,path_file,mime_type,ukuran_byte) VALUES (?,?,?,?,?,?)')->execute([$id,$file['nama_file_asli'],$file['nama_file'],$file['path_file'],$file['mime_type'],$file['ukuran_byte']]);
                $pdo->commit(); notify_roles(['admin','pengurus'],'Keluhan/saran baru',$resident['nama_lengkap'].' mengirim '.$type.': '.$title,'keluhan',url('keluhan'),'keluhan_saran',$id); audit_log('create','keluhan_saran',$id,'Aspirasi baru '.$number); set_flash('success','Keluhan/saran berhasil dikirim. Nomor laporan: '.$number); redirect('keluhan');
            }
            case 'update_keluhan': {
                require_roles(['admin','pengurus']); $id=(int)post('id'); $status=in_array(post('status'),['diproses','selesai','ditolak'],true)?post('status'):'diproses'; $response=trim((string)post('tanggapan'));
                $stmt=$pdo->prepare('SELECT * FROM keluhan_saran WHERE id=?'); $stmt->execute([$id]); $item=$stmt->fetch(); if(!$item) throw new RuntimeException('Laporan tidak ditemukan.');
                $pdo->prepare('UPDATE keluhan_saran SET status=?,tanggapan=?,ditangani_oleh=?,ditanggapi_pada=NOW() WHERE id=?')->execute([$status,$response ?: null,user_id(),$id]);
                notify_user((int)$item['user_id'],'Tanggapan keluhan/saran','Laporan '.$item['nomor_laporan'].' telah diperbarui dengan status '.$status.'.','keluhan',url('keluhan'),'keluhan_saran',$id); audit_log('respond','keluhan_saran',$id,'Status aspirasi menjadi '.$status); set_flash('success','Tanggapan berhasil disimpan.'); redirect('keluhan');
            }
            case 'upload_arsip': {
                require_roles(['admin','pengurus']); $title=trim((string)post('judul')); $category=(string)post('kategori'); $description=trim((string)post('deskripsi')); $date=post('tanggal_dokumen') ?: null; $access=post('akses')==='semua_pengguna'?'semua_pengguna':'admin_pengurus';
                if ($title==='') throw new RuntimeException('Judul dokumen wajib diisi.'); $file=upload_file('file','arsip'); if(!$file) throw new RuntimeException('Pilih file yang ingin diunggah.');
                $allowed=['surat_pengantar','surat_keterangan','dokumen_warga','laporan','dokumen_kegiatan','lainnya']; if(!in_array($category,$allowed,true)) $category='lainnya';
                $stmt=$pdo->prepare('INSERT INTO arsip_dokumen (judul,kategori,deskripsi,nama_file_asli,nama_file,path_file,mime_type,ekstensi,ukuran_byte,tanggal_dokumen,akses,diunggah_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$title,$category,$description,$file['nama_file_asli'],$file['nama_file'],$file['path_file'],$file['mime_type'],$file['ekstensi'],$file['ukuran_byte'],$date,$access,user_id()]); $id=(int)$pdo->lastInsertId(); audit_log('upload','arsip_dokumen',$id,'Dokumen diunggah: '.$title); set_flash('success','Dokumen berhasil diunggah.'); redirect('arsip');
            }
            case 'delete_arsip': { require_roles(['admin','pengurus']); $id=(int)post('id'); $stmt=$pdo->prepare('SELECT path_file FROM arsip_dokumen WHERE id=?'); $stmt->execute([$id]); $file=$stmt->fetch(); if($file){$pdo->prepare('DELETE FROM arsip_dokumen WHERE id=?')->execute([$id]); delete_uploaded_file($file['path_file']); audit_log('delete','arsip_dokumen',$id,'Dokumen dihapus.');} set_flash('success','Dokumen berhasil dihapus.'); redirect('arsip'); }
            case 'toggle_account': { require_roles(['admin']); $id=(int)post('id'); if($id===user_id()) throw new RuntimeException('Kamu tidak dapat menonaktifkan akun sendiri.'); $stmt=$pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=? AND role <> 'admin'"); $stmt->execute([$id]); audit_log('toggle_account','users',$id,'Status akun diubah.'); set_flash('success','Status akun diperbarui.'); redirect('manajemen-akun'); }
            case 'save_settings': {
                require_roles(['admin']); $fields=['nama_rt'=>'nama_rt','nama_rw'=>'nama_rw','kelurahan'=>'kelurahan','kecamatan'=>'kecamatan','kabupaten_kota'=>'kabupaten_kota','provinsi'=>'provinsi','kode_pos'=>'kode_pos','alamat_sekretariat'=>'alamat_sekretariat','nomor_telepon'=>'nomor_telepon','email'=>'email'];
                $data=[]; foreach($fields as $key=>$col)$data[$col]=trim((string)post($key));
                $stmt=$pdo->prepare('UPDATE profil_rt SET nama_rt=?,nama_rw=?,kelurahan=?,kecamatan=?,kabupaten_kota=?,provinsi=?,kode_pos=?,alamat_sekretariat=?,nomor_telepon=?,email=?,updated_by=? WHERE id=1');
                $stmt->execute([$data['nama_rt']?:'RT',$data['nama_rw']?:'RW',$data['kelurahan']?:null,$data['kecamatan']?:null,$data['kabupaten_kota']?:null,$data['provinsi']?:null,$data['kode_pos']?:null,$data['alamat_sekretariat']?:null,$data['nomor_telepon']?:null,$data['email']?:null,user_id()]);
                foreach(['notifikasi_email','notifikasi_sms','mode_maintenance'] as $key){$value=post($key)==='1'?'1':'0';$pdo->prepare('INSERT INTO pengaturan_sistem (kunci,nilai,grup,diperbarui_oleh) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai),diperbarui_oleh=VALUES(diperbarui_oleh)')->execute([$key,$value,'sistem',user_id()]);}
                audit_log('update','pengaturan_sistem',1,'Pengaturan SIPARTA diperbarui.'); set_flash('success','Pengaturan berhasil disimpan.'); redirect('pengaturan');
            }
            case 'update_profile': {
                require_login(); $name=trim((string)post('nama')); $email=strtolower(trim((string)post('email'))); $phone=trim((string)post('no_hp')); $newPass=(string)post('password_baru'); $current=(string)post('password_lama');
                if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Nama dan email harus diisi dengan benar.');
                $stmt=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?');$stmt->execute([$email,user_id()]);if($stmt->fetch())throw new RuntimeException('Email sudah digunakan akun lain.');
                $pdo->prepare('UPDATE users SET nama=?,email=?,no_hp=? WHERE id=?')->execute([$name,$email,$phone,user_id()]);
                $pdo->prepare('UPDATE data_warga SET nama_lengkap=?,no_hp=? WHERE user_id=?')->execute([$name,$phone,user_id()]);
                if($newPass!==''){ $stmt=$pdo->prepare('SELECT password FROM users WHERE id=?');$stmt->execute([user_id()]);$old=$stmt->fetchColumn(); if(!password_verify($current,$old))throw new RuntimeException('Kata sandi lama tidak benar.'); if(strlen($newPass)<8)throw new RuntimeException('Kata sandi baru minimal 8 karakter.'); $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($newPass,PASSWORD_DEFAULT),user_id()]); }
                $_SESSION['user']['nama']=$name;$_SESSION['user']['email']=$email;audit_log('update_profile','users',user_id(),'Profil akun diperbarui.');set_flash('success','Profil berhasil diperbarui.');redirect('profil');
            }
            case 'mark_notifications': { require_login(); $pdo->prepare('UPDATE notifikasi SET dibaca_pada=NOW() WHERE user_id=? AND dibaca_pada IS NULL')->execute([user_id()]); set_flash('success','Semua notifikasi ditandai sudah dibaca.'); redirect('notifikasi'); }
            case 'logout': { require_login(); logout_user(); header('Location: index.php?page=login'); exit; }
            default: throw new RuntimeException('Aksi tidak dikenal.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && (string)$exception->getCode() === '23000') $message = 'Data tidak dapat disimpan karena NIK, email, atau data unik lainnya sudah terdaftar.';
        else $message = $exception->getMessage();
        set_flash('danger', $message ?: 'Terjadi kesalahan. Silakan coba lagi.');
        $return = (string)post('return_page', current_user() ? 'dashboard' : 'login');
        $safe = safe_return_page($return, ['login','register-warga','register-pengurus','dashboard','data-warga','pengurus','pengajuan','detail-pengajuan','pengumuman','kegiatan','keluhan','arsip','laporan','manajemen-akun','pengaturan','profil','riwayat','ajukan','notifikasi']);
        redirect($safe, isset($_POST['return_id']) ? ['id'=>(int)$_POST['return_id']] : []);
    }
}

function handle_get_action(): void {
    if (!isset($_GET['action'])) return;
    $action = (string)$_GET['action'];
    if ($action === 'export_report') {
        require_roles(['admin','pengurus']);
        $type = (string)get('type','pengajuan');
        $start = (string)get('start', date('Y-m-01'));
        $end = (string)get('end', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)) { set_flash('danger','Rentang tanggal tidak valid.'); redirect('laporan'); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="siparta-' . preg_replace('/[^a-z0-9_-]/i','',$type) . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        if ($type==='warga') {
            fputcsv($out,['NIK','Nama','Jenis Kelamin','Alamat','Status Keluarga','Telepon','Status']);
            $stmt=db()->query('SELECT nik,nama_lengkap,jenis_kelamin,alamat,status_keluarga,no_hp,status FROM data_warga ORDER BY nama_lengkap');
        } elseif ($type==='keluhan') {
            fputcsv($out,['Nomor Laporan','Nama','Jenis','Kategori','Judul','Status','Tanggal']);
            $stmt=db()->prepare('SELECT k.nomor_laporan,w.nama_lengkap,k.jenis,k.kategori,k.judul,k.status,k.created_at FROM keluhan_saran k JOIN data_warga w ON w.id=k.data_warga_id WHERE DATE(k.created_at) BETWEEN ? AND ? ORDER BY k.created_at DESC'); $stmt->execute([$start,$end]);
        } else {
            fputcsv($out,['Nomor Pengajuan','Nama Pemohon','Jenis Layanan','Keperluan','Tanggal','Status']);
            $stmt=db()->prepare('SELECT p.nomor_pengajuan,w.nama_lengkap,p.nama_layanan,p.keperluan,p.diajukan_pada,p.status FROM pengajuan p JOIN data_warga w ON w.id=p.data_warga_id WHERE DATE(p.diajukan_pada) BETWEEN ? AND ? ORDER BY p.diajukan_pada DESC'); $stmt->execute([$start,$end]);
        }
        while($row=$stmt->fetch(PDO::FETCH_NUM)) fputcsv($out,$row);
        fclose($out); exit;
    }
    if ($action === 'download_arsip') {
        require_login(); $id=(int)get('id'); $stmt=db()->prepare('SELECT * FROM arsip_dokumen WHERE id=?');$stmt->execute([$id]);$file=$stmt->fetch();
        if(!$file || ($file['akses']==='admin_pengurus' && !in_array(role(),['admin','pengurus'],true))){http_response_code(404);exit('Dokumen tidak ditemukan.');}
        $base=realpath(dirname(__DIR__).'/uploads'); $path=realpath(dirname(__DIR__).'/'.$file['path_file']); if(!$base || !$path || !str_starts_with($path,$base.DIRECTORY_SEPARATOR) || !is_file($path)){http_response_code(404);exit('File tidak ditemukan.');}
        header('Content-Type: '.($file['mime_type'] ?: 'application/octet-stream'));header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.rawurlencode($file['nama_file_asli']).'"');readfile($path);exit;
    }
    if ($action === 'download_attachment') {
        require_login();
        $type=(string)get('type'); $id=(int)get('id'); $file=null;
        if ($type==='pengajuan') {
            $stmt=db()->prepare('SELECT b.*,w.user_id FROM pengajuan_berkas b JOIN pengajuan p ON p.id=b.pengajuan_id JOIN data_warga w ON w.id=p.data_warga_id WHERE b.id=?');
            $stmt->execute([$id]); $file=$stmt->fetch();
        } elseif ($type==='keluhan') {
            $stmt=db()->prepare('SELECT l.*,k.user_id FROM keluhan_lampiran l JOIN keluhan_saran k ON k.id=l.keluhan_id WHERE l.id=?');
            $stmt->execute([$id]); $file=$stmt->fetch();
        }
        if (!$file || (role()==='warga' && (int)$file['user_id'] !== user_id())) { http_response_code(404); exit('Lampiran tidak ditemukan.'); }
        $base=realpath(dirname(__DIR__).'/uploads'); $path=realpath(dirname(__DIR__).'/'.$file['path_file']);
        if (!$base || !$path || !str_starts_with($path,$base.DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit('File tidak ditemukan.'); }
        header('Content-Type: '.($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.rawurlencode($file['nama_file_asli']).'"');
        readfile($path); exit;
    }
}
