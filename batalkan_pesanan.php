<?php
require_once 'config.php';

// Cek login sebagai konsumen
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan-saya.php');
    exit();
}

$id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
$id_konsumen = $_SESSION['user_id'];

if ($id_pesanan == 0) {
    $_SESSION['error'] = 'Data tidak valid';
    header('Location: pesanan-saya.php');
    exit();
}

// Verify this pesanan belongs to this konsumen and status is pending
$stmt = $conn->prepare("SELECT * FROM pesanan WHERE id_pesanan = ? AND id_konsumen = ? AND status = 'pending'");
$stmt->bind_param("ii", $id_pesanan, $id_konsumen);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    $_SESSION['error'] = 'Pesanan tidak dapat dibatalkan';
    header('Location: pesanan-saya.php');
    exit();
}

// Update status to dibatalkan
$stmt_update = $conn->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id_pesanan = ?");
$stmt_update->bind_param("i", $id_pesanan);
$stmt_update->execute();
$stmt_update->close();

// Send notification to tukang
$title = 'Pesanan Dibatalkan';
$message = 'Pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' telah dibatalkan oleh konsumen.';
$link = 'pesanan-masuk.php';
$user_type = 'tukang';

$stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
$stmt_notif->bind_param("issss", $pesanan['id_tukang'], $user_type, $title, $message, $link);
$stmt_notif->execute();
$stmt_notif->close();

$_SESSION['success'] = 'Pesanan berhasil dibatalkan';
header('Location: pesanan-saya.php?msg=cancelled');
exit();
?>
