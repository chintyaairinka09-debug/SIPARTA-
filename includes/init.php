<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['name'], $config['charset']);
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
} catch (PDOException $exception) {
    http_response_code(500);
    $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit('<main style="font-family:Arial,sans-serif;max-width:760px;margin:60px auto;padding:24px"><h1>Koneksi database SIPARTA gagal</h1><p>Pastikan MySQL XAMPP aktif, database <b>db_siparta</b> sudah diimpor, dan konfigurasi di <code>config/database.php</code> sudah benar.</p><pre>' . $message . '</pre></main>');
}

date_default_timezone_set('Asia/Jakarta');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
