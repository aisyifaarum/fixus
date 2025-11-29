<?php
/**
 * API: Get Unread Notifications Count
 * Returns the count of unread notifications for the logged-in user
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated', 'count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notification_log WHERE user_id = ? AND user_type = ? AND is_read = FALSE");
$stmt->bind_param("is", $user_id, $user_type);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success' => true,
    'count' => (int)$result['count']
]);
?>
