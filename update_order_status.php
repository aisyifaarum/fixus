<?php
/**
 * Update Order Status & Send Notifications
 * This file handles order status updates and sends notifications to users
 */

require_once 'config.php';

// Check if user is logged in (can be konsumen or tukang)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
$new_status = isset($_POST['status']) ? $_POST['status'] : '';
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Validate status
$valid_statuses = ['pending', 'diterima', 'proses', 'selesai', 'dibatalkan'];
if (!in_array($new_status, $valid_statuses)) {
    $_SESSION['error'] = 'Status tidak valid';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Get pesanan data
$stmt = $conn->prepare("SELECT * FROM pesanan WHERE id_pesanan = ?");
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    $_SESSION['error'] = 'Pesanan tidak ditemukan';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Verify user has permission to update this order
if ($user_type === 'tukang' && $pesanan['id_tukang'] != $user_id) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah pesanan ini';
    header('Location: dashboard.php');
    exit();
} elseif ($user_type === 'konsumen' && $pesanan['id_konsumen'] != $user_id) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah pesanan ini';
    header('Location: pesanan-saya.php');
    exit();
}

// Update order status
$stmt_update = $conn->prepare("UPDATE pesanan SET status = ? WHERE id_pesanan = ?");
$stmt_update->bind_param("si", $new_status, $id_pesanan);
$stmt_update->execute();
$stmt_update->close();

// Prepare notification messages based on status and user type
$notification_data = getNotificationData($new_status, $id_pesanan, $user_type);

// Send notification to the other party
if ($user_type === 'tukang') {
    // Tukang updated status, notify konsumen
    $notify_user_id = $pesanan['id_konsumen'];
    $notify_user_type = 'konsumen';
    $link = 'pesanan-saya.php';
} else {
    // Konsumen updated status (e.g., cancelled), notify tukang
    $notify_user_id = $pesanan['id_tukang'];
    $notify_user_type = 'tukang';
    $link = 'dashboard.php';
}

$stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
$stmt_notif->bind_param("issss", $notify_user_id, $notify_user_type, $notification_data['title'], $notification_data['message'], $link);
$stmt_notif->execute();
$stmt_notif->close();

$_SESSION['success'] = 'Status pesanan berhasil diubah';
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();

/**
 * Get notification title and message based on status
 */
function getNotificationData($status, $order_id, $updated_by) {
    $order_id_formatted = str_pad($order_id, 5, '0', STR_PAD_LEFT);

    $notifications = [
        'pending' => [
            'title' => 'Pesanan Baru',
            'message' => 'Pesanan #' . $order_id_formatted . ' sedang menunggu konfirmasi.'
        ],
        'diterima' => [
            'title' => 'Pesanan Diterima',
            'message' => 'Pesanan #' . $order_id_formatted . ' telah diterima oleh tukang. Tukang akan segera datang sesuai jadwal.'
        ],
        'proses' => [
            'title' => 'Pesanan Sedang Dikerjakan',
            'message' => 'Pekerjaan untuk pesanan #' . $order_id_formatted . ' sedang dalam proses pengerjaan.'
        ],
        'selesai' => [
            'title' => 'Pesanan Selesai',
            'message' => 'Pekerjaan untuk pesanan #' . $order_id_formatted . ' telah selesai. Silakan lakukan pembayaran dan berikan rating.'
        ],
        'dibatalkan' => [
            'title' => 'Pesanan Dibatalkan',
            'message' => 'Pesanan #' . $order_id_formatted . ' telah dibatalkan.'
        ]
    ];

    return $notifications[$status] ?? [
        'title' => 'Update Pesanan',
        'message' => 'Status pesanan #' . $order_id_formatted . ' telah diubah.'
    ];
}
?>
