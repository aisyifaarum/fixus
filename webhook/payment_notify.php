<?php
/**
 * Payment Webhook Handler
 * Handles payment notifications from payment gateway (Xendit, Midtrans, etc.)
 * In production: validate gateway signature and protect endpoint
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Also accept POST for demo/testing
if (!$data && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
}

if (!$data) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Payload tidak valid']);
    exit();
}

// For demo: simple secret validation
// In production: validate signature from payment gateway
$secret_ok = isset($data['secret']) && $data['secret'] === 'fixus_webhook_secret_2024';

$external_id = isset($data['external_id']) ? $data['external_id'] : '';
$status = isset($data['status']) ? $data['status'] : ''; // 'paid', 'expired', 'failed'

if (!$external_id || !$status) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'external_id dan status harus diisi']);
    exit();
}

// Fetch payment by external_id
$stmt = $conn->prepare("SELECT * FROM pembayaran WHERE external_id = ? LIMIT 1");
$stmt->bind_param("s", $external_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo json_encode(['status'=>'error','message'=>'Pembayaran tidak ditemukan']);
    exit();
}

// Update payment status
$paid_at = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

if ($status === 'paid') {
    $stmt_update = $conn->prepare("UPDATE pembayaran SET status = 'paid', paid_at = ? WHERE id_payment = ?");
    $stmt_update->bind_param("si", $paid_at, $payment['id_payment']);
    $stmt_update->execute();
    $stmt_update->close();

    // Create notification for konsumen
    $title_konsumen = 'Pembayaran Berhasil';
    $message_konsumen = 'Pembayaran untuk pesanan #' . str_pad($payment['booking_id'], 5, '0', STR_PAD_LEFT) . ' telah berhasil dikonfirmasi. Terima kasih!';
    $link_konsumen = 'pesanan-saya.php';
    $user_type_konsumen = 'konsumen';

    $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt_notif->bind_param("issss", $payment['konsumen_id'], $user_type_konsumen, $title_konsumen, $message_konsumen, $link_konsumen);
    $stmt_notif->execute();
    $stmt_notif->close();

    // Get tukang_id from pesanan
    $stmt_pesanan = $conn->prepare("SELECT id_tukang FROM pesanan WHERE id_pesanan = ?");
    $stmt_pesanan->bind_param("i", $payment['booking_id']);
    $stmt_pesanan->execute();
    $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
    $stmt_pesanan->close();

    if ($pesanan) {
        // Create notification for tukang
        $title_tukang = 'Pembayaran Diterima';
        $message_tukang = 'Pembayaran untuk pesanan #' . str_pad($payment['booking_id'], 5, '0', STR_PAD_LEFT) . ' telah diterima dari konsumen.';
        $link_tukang = 'dashboard.php';
        $user_type_tukang = 'tukang';

        $stmt_notif_tukang = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt_notif_tukang->bind_param("issss", $pesanan['id_tukang'], $user_type_tukang, $title_tukang, $message_tukang, $link_tukang);
        $stmt_notif_tukang->execute();
        $stmt_notif_tukang->close();
    }

    echo json_encode(['status'=>'ok','message'=>'Pembayaran berhasil dikonfirmasi']);
} elseif ($status === 'expired') {
    $stmt_update = $conn->prepare("UPDATE pembayaran SET status = 'expired' WHERE id_payment = ?");
    $stmt_update->bind_param("i", $payment['id_payment']);
    $stmt_update->execute();
    $stmt_update->close();

    // Notify konsumen
    $title = 'Pembayaran Kedaluwarsa';
    $message = 'QR Code pembayaran untuk pesanan #' . str_pad($payment['booking_id'], 5, '0', STR_PAD_LEFT) . ' telah kedaluwarsa. Silakan buat pembayaran baru.';
    $link = 'pesanan-saya.php';
    $user_type = 'konsumen';

    $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt_notif->bind_param("issss", $payment['konsumen_id'], $user_type, $title, $message, $link);
    $stmt_notif->execute();
    $stmt_notif->close();

    echo json_encode(['status'=>'ok','message'=>'Pembayaran ditandai kedaluwarsa']);
} elseif ($status === 'failed') {
    $stmt_update = $conn->prepare("UPDATE pembayaran SET status = 'failed' WHERE id_payment = ?");
    $stmt_update->bind_param("i", $payment['id_payment']);
    $stmt_update->execute();
    $stmt_update->close();

    echo json_encode(['status'=>'ok','message'=>'Pembayaran gagal']);
} else {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Status tidak valid']);
}

exit();
?>