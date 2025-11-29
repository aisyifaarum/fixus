<?php
/**
 * Configuration Template for Fix Us Application
 *
 * INSTRUCTIONS:
 * 1. Copy this file and rename to: config.php
 * 2. Update database credentials below
 * 3. NEVER commit config.php to GitHub (already in .gitignore)
 */

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS in production
ini_set('session.use_strict_mode', 1);

session_start();

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Update these with your database credentials
define('DB_HOST', 'localhost');           // Database host (usually 'localhost')
define('DB_USER', 'your_db_username');    // Your database username
define('DB_PASS', 'your_db_password');    // Your database password
define('DB_NAME', 'fixus_db');            // Database name

// ============================================
// APPLICATION SETTINGS
// ============================================
// Default Virtual Account settings (change as needed)
define('DEFAULT_VA_BANK', 'BCA');

// ============================================
// DATABASE CONNECTION
// ============================================
// Use persistent connection for better performance with high traffic
$conn = mysqli_connect('p:' . DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Log error instead of showing to user
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

mysqli_set_charset($conn, "utf8mb4");

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
// REMEMBER ME FUNCTIONALITY
// ============================================
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT user_id, user_type FROM remember_tokens WHERE token = ? AND expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['user_type'] = $row['user_type'];

        $table = ($row['user_type'] == 'konsumen') ? 'konsumen' : 'tukang';
        $id_field = ($row['user_type'] == 'konsumen') ? 'id_konsumen' : 'id_tukang';

        $stmt2 = $conn->prepare("SELECT nama, email FROM $table WHERE $id_field = ?");
        $stmt2->bind_param("i", $row['user_id']);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows > 0) {
            $user = $result2->fetch_assoc();
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_email'] = $user['email'];
        }
        $stmt2->close();
    }
    $stmt->close();
}
?>
