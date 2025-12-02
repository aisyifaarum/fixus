<?php
// ============================================
// CONFIG UNTUK HOSTING
// Copy file ini ke config.php setelah upload
// Ubah nilai sesuai hosting Anda
// ============================================

session_start();

// ===== DATABASE HOSTING =====
// Dapatkan info ini dari cPanel/hosting dashboard
define('DB_HOST', 'localhost');              // atau IP hosting
define('DB_USER', 'username_hosting');       // username database hosting
define('DB_PASS', 'password_hosting');       // password database hosting
define('DB_NAME', 'nama_database_hosting');  // nama database di hosting

// ===== KONEKSI DATABASE =====
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// ===== SECURITY SETTINGS =====
// Ubah ini untuk production
ini_set('display_errors', '0');              // MATIKAN error display
ini_set('log_errors', '1');                  // Aktifkan error logging
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Session security untuk hosting
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);         // Jika pakai HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// ===== TIMEZONE =====
date_default_timezone_set('Asia/Jakarta');

// ===== BASE URL HOSTING =====
// Ubah sesuai domain hosting
define('BASE_URL', 'https://yourdomain.com/fixus/');  // ganti dengan domain Anda

?>
