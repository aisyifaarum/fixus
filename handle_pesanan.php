<?php
require_once 'config.php';

// Cek login sebagai tukang
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'tukang') {
    header('Location: login.php');
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan-masuk.php');
    exit();
}

$id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$id_tukang = $_SESSION['user_id'];

if ($id_pesanan == 0 || empty($action)) {
    $_SESSION['error'] = 'Data tidak valid';
    header('Location: pesanan-masuk.php');
    exit();
}

// Verify this pesanan belongs to this tukang
$stmt = $conn->prepare("SELECT * FROM pesanan WHERE id_pesanan = ? AND id_tukang = ?");
$stmt->bind_param("ii", $id_pesanan, $id_tukang);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    $_SESSION['error'] = 'Pesanan tidak ditemukan';
    header('Location: pesanan-masuk.php');
    exit();
}

// Process action
switch ($action) {
    case 'accept':
        // Update status to diterima (tanpa set harga dulu)
        $stmt_update = $conn->prepare("UPDATE pesanan SET status = 'diterima' WHERE id_pesanan = ?");
        $stmt_update->bind_param("i", $id_pesanan);
        $stmt_update->execute();
        $stmt_update->close();

        // Send notification to konsumen
        $title = 'Pesanan Diterima';
        $message = 'Pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' telah diterima oleh tukang. Tukang akan datang sesuai jadwal yang ditentukan.';
        $link = 'pesanan-saya.php';
        $user_type = 'konsumen';

        $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt_notif->bind_param("issss", $pesanan['id_konsumen'], $user_type, $title, $message, $link);
        $stmt_notif->execute();
        $stmt_notif->close();

        header('Location: pesanan-masuk.php?msg=accepted');
        break;

    case 'reject':
        // Update status to dibatalkan
        $stmt_update = $conn->prepare("UPDATE pesanan SET status = 'dibatalkan' WHERE id_pesanan = ?");
        $stmt_update->bind_param("i", $id_pesanan);
        $stmt_update->execute();
        $stmt_update->close();

        // Send notification to konsumen
        $title = 'Pesanan Ditolak';
        $message = 'Maaf, pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' ditolak oleh tukang. Silakan cari tukang lain.';
        $link = 'pesanan-saya.php';
        $user_type = 'konsumen';

        $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt_notif->bind_param("issss", $pesanan['id_konsumen'], $user_type, $title, $message, $link);
        $stmt_notif->execute();
        $stmt_notif->close();

        header('Location: pesanan-masuk.php?msg=rejected');
        break;

    case 'start':
        // Update status to proses
        $stmt_update = $conn->prepare("UPDATE pesanan SET status = 'proses' WHERE id_pesanan = ?");
        $stmt_update->bind_param("i", $id_pesanan);
        $stmt_update->execute();
        $stmt_update->close();

        // Send notification to konsumen
        $title = 'Pekerjaan Dimulai';
        $message = 'Tukang sudah mulai mengerjakan pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . '.';
        $link = 'pesanan-saya.php';
        $user_type = 'konsumen';

        $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt_notif->bind_param("issss", $pesanan['id_konsumen'], $user_type, $title, $message, $link);
        $stmt_notif->execute();
        $stmt_notif->close();

        header('Location: pesanan-masuk.php?msg=updated');
        break;

    case 'complete':
        $total_biaya = isset($_POST['total_biaya']) ? floatval($_POST['total_biaya']) : 0;

        if ($total_biaya <= 0) {
            $_SESSION['error'] = 'Total biaya harus diisi';
            header('Location: pesanan-masuk.php');
            exit();
        }

        // Update status to selesai and set total_biaya
        $stmt_update = $conn->prepare("UPDATE pesanan SET status = 'selesai', total_biaya = ? WHERE id_pesanan = ?");
        $stmt_update->bind_param("di", $total_biaya, $id_pesanan);
        $stmt_update->execute();
        $stmt_update->close();

        // Create payment info dengan rekening admin
        $bank_query = "SELECT * FROM rekening_admin WHERE is_active = 'yes' ORDER BY RAND() LIMIT 1";
        $bank_result = $conn->query($bank_query);
        $admin_bank = $bank_result->fetch_assoc();

        if ($admin_bank) {
            // Payment expired dalam 24 jam
            $tanggal_expired = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Insert payment info dengan jumlah yang ditentukan tukang
            $payment_stmt = $conn->prepare("INSERT INTO virtual_account (id_pesanan, nomor_rekening, bank, nama_penerima, jumlah, tanggal_expired) VALUES (?, ?, ?, ?, ?, ?)");
            $payment_stmt->bind_param("isssds", $id_pesanan, $admin_bank['nomor_rekening'], $admin_bank['bank'], $admin_bank['nama_pemilik'], $total_biaya, $tanggal_expired);
            $payment_stmt->execute();
            $payment_stmt->close();
        }

        // Send notification to konsumen
        $title = 'Pekerjaan Selesai';
        $message = 'Pekerjaan untuk pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' telah selesai. Total biaya: Rp ' . number_format($total_biaya, 0, ',', '.') . '. Silakan lakukan pembayaran dan berikan rating.';
        $link = 'pesanan-saya.php';
        $user_type = 'konsumen';

        $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt_notif->bind_param("issss", $pesanan['id_konsumen'], $user_type, $title, $message, $link);
        $stmt_notif->execute();
        $stmt_notif->close();

        header('Location: pesanan-masuk.php?msg=updated');
        break;

    default:
        $_SESSION['error'] = 'Aksi tidak valid';
        header('Location: pesanan-masuk.php');
        break;
}

exit();
?>
