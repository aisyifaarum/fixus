<?php
require_once 'config.php';

// Only konsumen may create manual VA
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan-saya.php');
    exit();
}

// Verify CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    die('Invalid request');
}

$id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
if ($id_pesanan === 0) {
    header('Location: pesanan-saya.php');
    exit();
}

// Ensure order belongs to user and valid
$stmt = $conn->prepare("SELECT p.id_pesanan, p.total_biaya, p.id_konsumen, p.status FROM pesanan p WHERE p.id_pesanan = ? AND p.id_konsumen = ?");
$stmt->bind_param("ii", $id_pesanan, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || $order['total_biaya'] <= 0) {
    header('Location: pesanan-saya.php');
    exit();
}

// Prevent duplicates: check if virtual_account pending exists
$stmt_va = $conn->prepare("SELECT * FROM virtual_account WHERE id_pesanan = ? AND status_pembayaran = 'pending' ORDER BY tanggal_expired DESC LIMIT 1");
$stmt_va->bind_param("i", $id_pesanan);
$stmt_va->execute();
$existing_va = $stmt_va->get_result()->fetch_assoc();
$stmt_va->close();

if ($existing_va) {
    // Redirect to existing konfirmasi page
    header('Location: konfirmasi-pembayaran.php?id=' . $id_pesanan);
    exit();
}

// Get random active admin bank account
$bank_query = "SELECT * FROM rekening_admin WHERE is_active = 'yes' ORDER BY RAND() LIMIT 1";
$bank_result = $conn->query($bank_query);
$admin_bank = $bank_result ? $bank_result->fetch_assoc() : null;

if (!$admin_bank) {
    // No bank configured
    $_SESSION['error'] = 'Tidak ada rekening admin yang tersedia. Hubungi admin.';
    header('Location: pesanan-saya.php');
    exit();
}

$tanggal_expired = date('Y-m-d H:i:s', strtotime('+24 hours'));

$payment_stmt = $conn->prepare("INSERT INTO virtual_account (id_pesanan, nomor_rekening, bank, nama_penerima, jumlah, tanggal_expired, status_pembayaran) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
$payment_stmt->bind_param("isssds", $id_pesanan, $admin_bank['nomor_rekening'], $admin_bank['bank'], $admin_bank['nama_pemilik'], $order['total_biaya'], $tanggal_expired);
$payment_stmt->execute();
$payment_stmt->close();

header('Location: konfirmasi-pembayaran.php?id=' . $id_pesanan);
exit();
?>
