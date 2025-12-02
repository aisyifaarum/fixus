<?php
require_once 'config.php';

// Cek login (bisa konsumen atau tukang)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Mark notification as read if requested
if (isset($_GET['read']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $stmt_read = $conn->prepare("UPDATE notification_log SET is_read = TRUE WHERE id_notification = ? AND user_id = ? AND user_type = ?");
    $stmt_read->bind_param("iis", $notif_id, $user_id, $user_type);
    $stmt_read->execute();
    $stmt_read->close();

    // Redirect to link if provided
    if (!empty($_GET['link'])) {
        header('Location: ' . $_GET['link']);
        exit();
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt_mark_all = $conn->prepare("UPDATE notification_log SET is_read = TRUE WHERE user_id = ? AND user_type = ?");
    $stmt_mark_all->bind_param("is", $user_id, $user_type);
    $stmt_mark_all->execute();
    $stmt_mark_all->close();

    header('Location: notifications.php?msg=all_read');
    exit();
}

// Get all notifications for this user
$stmt = $conn->prepare("SELECT * FROM notification_log WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC");
$stmt->bind_param("is", $user_id, $user_type);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Count unread notifications
$stmt_unread = $conn->prepare("SELECT COUNT(*) as unread_count FROM notification_log WHERE user_id = ? AND user_type = ? AND is_read = FALSE");
$stmt_unread->bind_param("is", $user_id, $user_type);
$stmt_unread->execute();
$unread_data = $stmt_unread->get_result()->fetch_assoc();
$unread_count = $unread_data['unread_count'];
$stmt_unread->close();

$success = isset($_GET['msg']) && $_GET['msg'] == 'all_read' ? 'Semua notifikasi telah ditandai sebagai dibaca' : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .notif-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .notif-header h1 {
            color: #333;
            margin: 0;
        }

        .notif-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-mark-all {
            padding: 8px 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-mark-all:hover {
            background: #218838;
        }

        .notif-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid transparent;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .notif-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }

        .notif-card.unread {
            background: #f0f7ff;
            border-left-color: #667eea;
        }

        .notif-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .notif-icon.success {
            background: #d4edda;
        }

        .notif-icon.info {
            background: #d1ecf1;
        }

        .notif-icon.warning {
            background: #fff3cd;
        }

        .notif-icon.error {
            background: #f8d7da;
        }

        .notif-content {
            flex: 1;
        }

        .notif-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .notif-message {
            color: #666;
            line-height: 1.5;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .notif-time {
            color: #999;
            font-size: 12px;
        }

        .notif-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-notif {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.3s;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .btn-dismiss {
            background: #e0e0e0;
            color: #666;
        }

        .btn-dismiss:hover {
            background: #d0d0d0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: #764ba2;
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="notif-container">
        <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>

        <div class="notif-header">
            <h1>🔔 Notifikasi</h1>
            <div class="notif-stats">
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?> belum dibaca</span>
                    <a href="notifications.php?mark_all_read=1" class="btn-mark-all">✓ Tandai Semua Dibaca</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($notifications->num_rows > 0): ?>
            <?php while ($notif = $notifications->fetch_assoc()): ?>
                <?php
                // Determine icon based on title
                $icon = '🔔';
                $icon_class = 'info';
                if (strpos($notif['title'], 'Berhasil') !== false || strpos($notif['title'], 'Selesai') !== false) {
                    $icon = '✅';
                    $icon_class = 'success';
                } elseif (strpos($notif['title'], 'Batal') !== false || strpos($notif['title'], 'Gagal') !== false) {
                    $icon = '❌';
                    $icon_class = 'error';
                } elseif (strpos($notif['title'], 'Pembayaran') !== false) {
                    $icon = '💳';
                    $icon_class = 'success';
                } elseif (strpos($notif['title'], 'Ulasan') !== false) {
                    $icon = '⭐';
                    $icon_class = 'warning';
                }

                $time_ago = getTimeAgo($notif['created_at']);
                ?>
                <div class="notif-card <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                    <div class="notif-icon <?php echo $icon_class; ?>">
                        <?php echo $icon; ?>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                        <div class="notif-message"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></div>
                        <div class="notif-time"><?php echo $time_ago; ?></div>
                        <div class="notif-actions">
                            <?php if ($notif['link']): ?>
                                <a href="notifications.php?read=1&id=<?php echo $notif['id_notification']; ?>&link=<?php echo urlencode($notif['link']); ?>" class="btn-notif btn-view">
                                    Lihat Detail
                                </a>
                            <?php endif; ?>
                            <?php if (!$notif['is_read']): ?>
                                <a href="notifications.php?read=1&id=<?php echo $notif['id_notification']; ?>" class="btn-notif btn-dismiss">
                                    Tandai Dibaca
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔔</div>
                <h2>Belum Ada Notifikasi</h2>
                <p style="color: #666; margin: 10px 0;">Anda belum memiliki notifikasi</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * Convert timestamp to human-readable format
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari yang lalu';
    } else {
        return date('d M Y, H:i', $time);
    }
}
?>
