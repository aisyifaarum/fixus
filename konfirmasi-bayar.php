<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan-saya.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error_msg'] = 'Invalid request. Please try again.';
    header('Location: pesanan-saya.php');
    exit();
}

$id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($id_pesanan == 0 || ($action != 'confirm' && $action != 'cancel')) {
    header('Location: pesanan-saya.php');
    exit();
}

$id_konsumen = $_SESSION['user_id'];

// Verify pesanan belongs to konsumen
$stmt_check = $conn->prepare("SELECT id_pesanan FROM pesanan WHERE id_pesanan = ? AND id_konsumen = ?");
$stmt_check->bind_param("ii", $id_pesanan, $id_konsumen);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows == 0) {
    $stmt_check->close();
    header('Location: pesanan-saya.php');
    exit();
}
$stmt_check->close();

if ($action == 'confirm') {
    // Konsumen konfirmasi sudah bayar
    $stmt = $conn->prepare("UPDATE virtual_account SET konfirmasi_konsumen = 'sudah', tanggal_konfirmasi_konsumen = NOW() WHERE id_pesanan = ?");
    $stmt->bind_param("i", $id_pesanan);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = 'Konfirmasi pembayaran berhasil dikirim! Menunggu verifikasi admin.';
    } else {
        $_SESSION['error_msg'] = 'Gagal mengirim konfirmasi pembayaran!';
    }
    $stmt->close();

} elseif ($action == 'cancel') {
    // Konsumen batalkan pesanan
    $stmt = $conn->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id_pesanan = ?");
    $stmt->bind_param("i", $id_pesanan);

    if ($stmt->execute()) {
        // Update VA status jadi expired
        $stmt_va = $conn->prepare("UPDATE virtual_account SET status_pembayaran = 'expired' WHERE id_pesanan = ?");
        $stmt_va->bind_param("i", $id_pesanan);
        $stmt_va->execute();
        $stmt_va->close();

        $_SESSION['success_msg'] = 'Pesanan berhasil dibatalkan!';
    } else {
        $_SESSION['error_msg'] = 'Gagal membatalkan pesanan!';
    }
    $stmt->close();
}

header('Location: pesanan-saya.php');
exit();
?>
