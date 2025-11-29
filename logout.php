<?php
require_once 'config.php';

// Hapus remember token dari database jika ada
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    
    // Hapus cookie
    setcookie('remember_token', '', time() - 3600, '/');
}

// Hapus session
session_destroy();

// Redirect ke login
header('Location: login.php?msg=logout');
exit();
?>