<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan-saya.php');
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$tukang_id = isset($_POST['tukang_id']) ? (int)$_POST['tukang_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review = isset($_POST['review']) ? trim($_POST['review']) : '';
$konsumen_id = $_SESSION['user_id'];

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    header('Location: pesanan-saya.php');
    exit();
}

if (!$booking_id || !$tukang_id || $rating < 1 || $rating > 5) {
    header('Location: pesanan-saya.php');
    exit();
}

// Ensure the booking belongs to the user and is finished
$stmt = $conn->prepare("SELECT id_pesanan, status FROM pesanan WHERE id_pesanan = ? AND id_konsumen = ? LIMIT 1");
$stmt->bind_param("ii", $booking_id, $konsumen_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    $stmt->close();
    header('Location: pesanan-saya.php');
    exit();
}
$row = $res->fetch_assoc();
$stmt->close();

if ($row['status'] != 'selesai') {
    // hanya boleh review jika pesanan selesai
    header('Location: pesanan-saya.php');
    exit();
}

// Check existing review
$stmtChk = $conn->prepare("SELECT COUNT(*) AS cnt FROM reviews WHERE booking_id = ?");
$stmtChk->bind_param("i", $booking_id);
$stmtChk->execute();
$cntRow = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();
if ($cntRow['cnt'] > 0) {
    header('Location: pesanan-saya.php');
    exit();
}

// Insert review
$stmtIns = $conn->prepare("INSERT INTO reviews (booking_id,konsumen_id,tukang_id,rating,review) VALUES (?,?,?,?,?)");
$stmtIns->bind_param("iiiis", $booking_id, $konsumen_id, $tukang_id, $rating, $review);
if ($stmtIns->execute()) {
    $stmtIns->close();
    // Recompute average rating and jumlah_pesanan
    $stmtAgg = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE tukang_id = ?");
    $stmtAgg->bind_param("i", $tukang_id);
    $stmtAgg->execute();
    $agg = $stmtAgg->get_result()->fetch_assoc();
    $stmtAgg->close();

    $avg = number_format($agg['avg_rating'],2,'.','');
    $cnt = (int)$agg['cnt'];

    $stmtUpd = $conn->prepare("UPDATE tukang SET rating_avg = ?, jumlah_pesanan = ? WHERE id_tukang = ?");
    $stmtUpd->bind_param("dii", $avg, $cnt, $tukang_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Insert notification to tukang
    $title = 'Ulasan Baru';
    $msg = 'Anda menerima ulasan baru untuk pesanan #' . $booking_id;
    $link = 'profil-tukang.php?id=' . $tukang_id;
    $user_type = 'tukang';
    $stmtNote = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?,?,?,?,?)");
    $stmtNote->bind_param("issss", $tukang_id, $user_type, $title, $msg, $link);
    $stmtNote->execute();
    $stmtNote->close();
}

header('Location: pesanan-saya.php');
exit();
?>