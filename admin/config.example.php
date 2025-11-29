<?php
/**
 * Admin Configuration Template for Fix Us Application
 *
 * INSTRUCTIONS:
 * 1. Copy this file and rename to: config.php
 * 2. Update database credentials below
 * 3. NEVER commit config.php to GitHub (already in .gitignore)
 */

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);

session_start();

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Update these with your database credentials
$host = 'localhost';           // Database host (usually 'localhost')
$username = 'your_db_username'; // Your database username
$password = 'your_db_password'; // Your database password
$database = 'fixus_db';         // Database name

// ============================================
// DATABASE CONNECTION
// ============================================
// Use persistent connection for better performance with high traffic
$conn = new mysqli('p:' . $host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

// Set charset to utf8mb4 for emoji support
$conn->set_charset("utf8mb4");

// ============================================
// CSRF PROTECTION FUNCTIONS
// ============================================
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ============================================
// ADMIN AUTHENTICATION HELPER
// ============================================
// Check admin login
function checkAdminLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}
?>
