<?php
require_once 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Get unread notifications count
$stmt_notif = $conn->prepare("SELECT COUNT(*) as count FROM notification_log WHERE user_id = ? AND user_type = ? AND is_read = FALSE");
$stmt_notif->bind_param("is", $user_id, $user_type);
$stmt_notif->execute();
$notif_count = $stmt_notif->get_result()->fetch_assoc()['count'];
$stmt_notif->close();

// Get stats for konsumen
if ($user_type == 'konsumen') {
    // Count pending orders
    $stmt_pending = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_konsumen = ? AND status IN ('pending', 'diterima', 'proses')");
    $stmt_pending->bind_param("i", $user_id);
    $stmt_pending->execute();
    $pending_orders = $stmt_pending->get_result()->fetch_assoc()['count'];
    $stmt_pending->close();

    // Count unpaid orders (using virtual_account table)
    $stmt_unpaid = $conn->prepare("SELECT COUNT(DISTINCT p.id_pesanan) as count
                                    FROM pesanan p
                                    LEFT JOIN virtual_account va ON p.id_pesanan = va.id_pesanan
                                    WHERE p.id_konsumen = ?
                                    AND p.status = 'selesai'
                                    AND p.total_biaya > 0
                                    AND (va.status_pembayaran IS NULL OR va.status_pembayaran != 'paid')");
    $stmt_unpaid->bind_param("i", $user_id);
    $stmt_unpaid->execute();
    $unpaid_orders = $stmt_unpaid->get_result()->fetch_assoc()['count'];
    $stmt_unpaid->close();
} else {
    // Get stats for tukang
    // Count new orders
    $stmt_new = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_tukang = ? AND status = 'pending'");
    $stmt_new->bind_param("i", $user_id);
    $stmt_new->execute();
    $new_orders = $stmt_new->get_result()->fetch_assoc()['count'];
    $stmt_new->close();

    // Count active orders
    $stmt_active = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_tukang = ? AND status IN ('diterima', 'proses')");
    $stmt_active->bind_param("i", $user_id);
    $stmt_active->execute();
    $active_orders = $stmt_active->get_result()->fetch_assoc()['count'];
    $stmt_active->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .notif-badge {
            position: relative;
            display: inline-block;
        }
        .notif-badge::after {
            content: attr(data-count);
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
        .menu-card {
            background: #e3f2fd;
            padding: 25px;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            position: relative;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .menu-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .menu-card.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        .menu-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ff4444;
            color: white;
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: bold;
        }
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .btn-notif {
            position: relative;
            padding: 10px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-notif:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-welcome">
                <h1>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                <p>
                    <?php echo htmlspecialchars($user_email); ?>
                    |
                    <span style="text-transform: capitalize;"><?php echo $user_type; ?></span>
                </p>
            </div>
            <div class="header-actions">
                <a href="notifications.php" class="btn-notif <?php echo $notif_count > 0 ? 'notif-badge' : ''; ?>" <?php echo $notif_count > 0 ? 'data-count="' . $notif_count . '"' : ''; ?>>
                    🔔 Notifikasi
                </a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="dashboard-content">
            <?php if ($user_type == 'konsumen'): ?>
                <h2 style="color: #667eea; margin-bottom: 20px;">Dashboard Konsumen</h2>
                <p style="color: #666; margin-bottom: 30px;">
                    Selamat datang di dashboard konsumen Fix Us. Anda dapat mencari tukang terpercaya untuk memperbaiki masalah listrik, kipas angin, magic com, dan lainnya.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; text-align: left;">
                    <a href="cari-tukang.php" class="menu-card" style="background: #e3f2fd;">
                        <h3 style="color: #1976d2; margin-bottom: 10px;">🔍 Cari Tukang</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Temukan tukang terdekat sesuai kebutuhan Anda</p>
                    </a>

                    <a href="pesanan-saya.php" class="menu-card" style="background: #f3e5f5;">
                        <?php if ($pending_orders > 0): ?>
                            <span class="menu-badge"><?php echo $pending_orders; ?></span>
                        <?php endif; ?>
                        <h3 style="color: #7b1fa2; margin-bottom: 10px;">📋 Pesanan Saya</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Lihat riwayat dan status pesanan Anda</p>
                    </a>

                    <a href="pesanan-saya.php" class="menu-card" style="background: #e8f5e9;">
                        <?php if ($unpaid_orders > 0): ?>
                            <span class="menu-badge"><?php echo $unpaid_orders; ?></span>
                        <?php endif; ?>
                        <h3 style="color: #388e3c; margin-bottom: 10px;">💳 Pembayaran</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php if ($unpaid_orders > 0): ?>
                                <?php echo $unpaid_orders; ?> pesanan menunggu pembayaran
                                <?php else: ?>
                                Bayar dengan mudah via Virtual Account
                            <?php endif; ?>
                        </p>
                    </a>

                    <a href="pesanan-saya.php" class="menu-card" style="background: #fff3e0;">
                        <h3 style="color: #f57c00; margin-bottom: 10px;">⭐ Ulasan</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Berikan ulasan untuk pesanan yang sudah selesai</p>
                    </a>

                    <a href="profil-saya-konsumen.php" class="menu-card" style="background: #fce4ec;">
                        <h3 style="color: #c2185b; margin-bottom: 10px;">👤 Profil Saya</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Kelola informasi profil dan data pribadi Anda</p>
                    </a>
                </div>
            <?php else: ?>
                <h2 style="color: #764ba2; margin-bottom: 20px;">Dashboard Tukang</h2>
                <p style="color: #666; margin-bottom: 30px;">
                    Selamat datang di dashboard tukang Fix Us. Kelola pesanan, perbarui profil, dan tingkatkan layanan Anda.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; text-align: left;">
                    <a href="pesanan-masuk.php?filter=baru" class="menu-card" style="background: #f3e5f5;">
                        <?php if ($new_orders > 0): ?>
                            <span class="menu-badge"><?php echo $new_orders; ?></span>
                        <?php endif; ?>
                        <h3 style="color: #7b1fa2; margin-bottom: 10px;">📦 Pesanan Masuk</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php if ($new_orders > 0): ?>
                                <?php echo $new_orders; ?> pesanan baru menunggu konfirmasi
                            <?php else: ?>
                                Terima dan kelola pesanan dari konsumen
                            <?php endif; ?>
                        </p>
                    </a>

                    <a href="pesanan-masuk.php?filter=aktif" class="menu-card" style="background: #e3f2fd;">
                        <?php if ($active_orders > 0): ?>
                            <span class="menu-badge"><?php echo $active_orders; ?></span>
                        <?php endif; ?>
                        <h3 style="color: #1976d2; margin-bottom: 10px;">✅ Update Status</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">
                            <?php if ($active_orders > 0): ?>
                                <?php echo $active_orders; ?> pekerjaan sedang berjalan
                            <?php else: ?>
                                Perbarui status pekerjaan Anda
                            <?php endif; ?>
                        </p>
                    </a>

                    <a href="profil-saya-tukang.php" class="menu-card" style="background: #fff3e0;">
                        <h3 style="color: #f57c00; margin-bottom: 10px;">👤 Profil Saya</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Kelola profil, keahlian, dan jadwal kerja Anda</p>
                    </a>

                    <a href="pesanan-masuk.php?filter=selesai" class="menu-card" style="background: #e8f5e9;">
                        <h3 style="color: #388e3c; margin-bottom: 10px;">💰 Riwayat</h3>
                        <p style="color: #666; font-size: 14px; margin: 0;">Lihat riwayat pesanan yang sudah selesai</p>
                    </a>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 40px; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                <h3 style="color: #333; margin-bottom: 10px;">ℹ️ Informasi Akun</h3>
                <p style="color: #666; font-size: 14px; margin: 5px 0;">
                    <strong>Nama:</strong> <?php echo htmlspecialchars($user_name); ?>
                </p>
                <p style="color: #666; font-size: 14px; margin: 5px 0;">
                    <strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?>
                </p>
                <p style="color: #666; font-size: 14px; margin: 5px 0;">
                    <strong>Tipe Akun:</strong> <span style="text-transform: capitalize;"><?php echo $user_type; ?></span>
                </p>
            </div>
        </div>
    </div>
</body>
</html>