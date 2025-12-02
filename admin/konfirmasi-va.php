<?php
require_once 'config.php';

// Cek login admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Handle konfirmasi pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $id_va = intval($_POST['id_va']);
        $action = $_POST['action'];

        if ($action == 'confirm') {
            // Konfirmasi pembayaran
            $stmt = $conn->prepare("UPDATE virtual_account SET status_pembayaran = 'paid', tanggal_bayar = NOW() WHERE id_va = ?");
            $stmt->bind_param("i", $id_va);

            if ($stmt->execute()) {
                $success = 'Pembayaran berhasil dikonfirmasi!';
            } else {
                $error = 'Gagal mengkonfirmasi pembayaran!';
            }
            $stmt->close();
        }
    }
}

// Get semua VA dengan konfirmasi konsumen yang perlu diverifikasi prioritas pertama
$va_query = "
    SELECT va.*, p.id_pesanan, p.kategori, p.tanggal_pengerjaan, p.waktu_pengerjaan, p.status,
           k.nama as nama_konsumen, k.email as email_konsumen, k.no_telepon as telepon_konsumen,
           t.nama as nama_tukang, t.keahlian
    FROM virtual_account va
    INNER JOIN pesanan p ON va.id_pesanan = p.id_pesanan
    INNER JOIN konsumen k ON p.id_konsumen = k.id_konsumen
    INNER JOIN tukang t ON p.id_tukang = t.id_tukang
    ORDER BY
        CASE WHEN va.konfirmasi_konsumen = 'sudah' AND va.status_pembayaran = 'pending' THEN 0 ELSE 1 END,
        va.tanggal_dibuat DESC
";
$va_result = $conn->query($va_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Virtual Account - Admin Fix Us</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            color: white;
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 24px;
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            margin-left: 250px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e9ecef;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .va-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 14px;
            color: #667eea;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-confirm:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .detail-info {
            font-size: 13px;
            line-height: 1.6;
        }

        .detail-info strong {
            color: #333;
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }

        /* Tablet */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 13px;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 999;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .container {
                margin-left: 0;
                padding: 70px 15px 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card h3 {
                font-size: 13px;
            }

            .stat-value {
                font-size: 24px;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px;
                padding: 0 15px;
            }

            table {
                min-width: 800px;
                font-size: 12px;
            }

            th, td {
                padding: 10px !important;
            }

            .va-number {
                font-size: 12px;
            }

            .detail-info {
                font-size: 11px;
            }

            .status-badge {
                font-size: 10px;
                padding: 4px 8px;
            }

            .btn-confirm {
                padding: 6px 12px;
                font-size: 11px;
            }

            .btn-back {
                padding: 6px 12px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 20px;
            }

            .sidebar-header h2 {
                font-size: 20px;
            }

            .sidebar-menu a {
                padding: 10px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>💳 Konfirmasi Virtual Account</h1>
            <a href="dashboard.php" class="btn-back">← Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php
            $total_pending = 0;
            $total_paid = 0;
            $total_expired = 0;
            $total_amount = 0;

            $va_result->data_seek(0);
            while ($va = $va_result->fetch_assoc()) {
                if ($va['status_pembayaran'] == 'pending') {
                    $total_pending++;
                    if (strtotime($va['tanggal_expired']) < time()) {
                        $total_expired++;
                    }
                } elseif ($va['status_pembayaran'] == 'paid') {
                    $total_paid++;
                    $total_amount += $va['jumlah'];
                }
            }
            $va_result->data_seek(0);
            ?>
            <div class="stat-card">
                <h3>⏳ Menunggu Pembayaran</h3>
                <div class="stat-value"><?php echo $total_pending; ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ Sudah Dibayar</h3>
                <div class="stat-value"><?php echo $total_paid; ?></div>
            </div>
            <div class="stat-card">
                <h3>❌ Expired</h3>
                <div class="stat-value"><?php echo $total_expired; ?></div>
            </div>
            <div class="stat-card">
                <h3>💰 Total Pembayaran</h3>
                <div class="stat-value" style="font-size: 24px;">Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">📋 Daftar Virtual Account</h2>
            <table>
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Nomor VA</th>
                        <th>Detail</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Expired</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($va_result->num_rows > 0): ?>
                        <?php while ($va = $va_result->fetch_assoc()): ?>
                            <?php
                            $is_expired = strtotime($va['tanggal_expired']) < time();
                            $status_class = 'status-pending';
                            $status_text = 'Pending';
                            $highlight_row = '';

                            if ($va['status_pembayaran'] == 'paid') {
                                $status_class = 'status-paid';
                                $status_text = 'Lunas';
                            } elseif ($va['konfirmasi_konsumen'] == 'sudah' && $va['status_pembayaran'] == 'pending') {
                                $status_class = 'status-badge' ;
                                $status_text = '⚠️ Menunggu Verifikasi';
                                $highlight_row = 'background: #fff3cd;';
                            } elseif ($is_expired) {
                                $status_class = 'status-expired';
                                $status_text = 'Expired';
                            }
                            ?>
                            <tr style="<?php echo $highlight_row; ?>">
                                <td><strong>#<?php echo str_pad($va['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div class="va-number"><?php echo $va['nomor_rekening']; ?></div>
                                    <div style="font-size: 11px; color: #666; margin-top: 3px;">🏦 <?php echo $va['bank']; ?> - <?php echo htmlspecialchars($va['nama_penerima']); ?></div>
                                </td>
                                <td>
                                    <div class="detail-info">
                                        <strong>Konsumen:</strong> <?php echo htmlspecialchars($va['nama_konsumen']); ?><br>
                                        <strong>Tukang:</strong> <?php echo htmlspecialchars($va['nama_tukang']); ?><br>
                                        <strong>Kategori:</strong> <?php echo htmlspecialchars($va['kategori']); ?><br>
                                        <strong>Jadwal:</strong> <?php echo date('d M Y, H:i', strtotime($va['tanggal_pengerjaan'] . ' ' . $va['waktu_pengerjaan'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="font-size: 16px; color: #667eea;">
                                        Rp <?php echo number_format($va['jumlah'], 0, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                    <?php if ($va['status_pembayaran'] == 'paid'): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                            Dibayar: <?php echo date('d M Y H:i', strtotime($va['tanggal_bayar'])); ?>
                                        </div>
                                    <?php elseif ($va['konfirmasi_konsumen'] == 'sudah'): ?>
                                        <div style="font-size: 11px; color: #856404; margin-top: 5px; font-weight: 600;">
                                            Konsumen konfirmasi: <?php echo date('d M Y H:i', strtotime($va['tanggal_konfirmasi_konsumen'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #666;">
                                        <?php echo date('d M Y', strtotime($va['tanggal_expired'])); ?><br>
                                        <?php echo date('H:i', strtotime($va['tanggal_expired'])); ?> WIB
                                    </div>
                                </td>
                                <td>
                                    <?php if ($va['status_pembayaran'] == 'pending' && !$is_expired): ?>
                                        <?php if ($va['konfirmasi_konsumen'] == 'sudah'): ?>
                                            <div style="margin-bottom: 8px;">
                                                <div style="background: #ffc107; color: #856404; padding: 6px 10px; border-radius: 5px; font-size: 11px; font-weight: 600; margin-bottom: 8px;">
                                                    ⚠️ Konsumen sudah konfirmasi pembayaran!
                                                </div>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Verifikasi pembayaran untuk pesanan #<?php echo $va['id_pesanan']; ?>?\n\nPastikan pembayaran sudah masuk ke rekening sebelum konfirmasi!');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="id_va" value="<?php echo $va['id_va']; ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn-confirm" style="width: 100%;">✓ Terima & Verifikasi</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Konfirmasi pembayaran untuk pesanan #<?php echo $va['id_pesanan']; ?>?');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="id_va" value="<?php echo $va['id_va']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn-confirm">✓ Konfirmasi</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php elseif ($va['status_pembayaran'] == 'paid'): ?>
                                        <span style="color: #28a745; font-size: 12px;">✓ Terkonfirmasi</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-size: 12px;">✗ Expired</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                Belum ada transaksi Virtual Account
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
