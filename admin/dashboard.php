<?php
require_once 'config.php';
checkAdminLogin();

// Get statistics
// Total konsumen
$result_konsumen = $conn->query("SELECT COUNT(*) as total FROM konsumen");
$total_konsumen = $result_konsumen->fetch_assoc()['total'];

// Total tukang
$result_tukang = $conn->query("SELECT COUNT(*) as total FROM tukang");
$total_tukang = $result_tukang->fetch_assoc()['total'];

// Tukang aktif
$result_tukang_aktif = $conn->query("SELECT COUNT(*) as total FROM tukang WHERE status_aktif = 'aktif'");
$tukang_aktif = $result_tukang_aktif->fetch_assoc()['total'];

// Total pesanan
$result_pesanan = $conn->query("SELECT COUNT(*) as total FROM pesanan");
$total_pesanan = $result_pesanan->fetch_assoc()['total'];

// Pesanan selesai
$result_selesai = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status = 'selesai'");
$pesanan_selesai = $result_selesai->fetch_assoc()['total'];

// Total pendapatan
$result_pendapatan = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM pembayaran WHERE status = 'paid'");
$total_pendapatan = $result_pendapatan->fetch_assoc()['total'];

// Rating rata-rata
$result_rating = $conn->query("SELECT COALESCE(AVG(rating), 0) as avg_rating FROM reviews");
$avg_rating = round($result_rating->fetch_assoc()['avg_rating'], 2);

// Pesanan pending
$result_pending = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status = 'pending'");
$pesanan_pending = $result_pending->fetch_assoc()['total'];

// Pesanan bulan ini
$result_bulan = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE MONTH(tanggal_pesanan) = MONTH(NOW()) AND YEAR(tanggal_pesanan) = YEAR(NOW())");
$pesanan_bulan_ini = $result_bulan->fetch_assoc()['total'];

// Pendapatan bulan ini
$result_pendapatan_bulan = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM pembayaran WHERE status = 'paid' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())");
$pendapatan_bulan_ini = $result_pendapatan_bulan->fetch_assoc()['total'];

// Recent orders
$result_recent = $conn->query("SELECT p.*, k.nama as nama_konsumen, t.nama as nama_tukang
                               FROM pesanan p
                               JOIN konsumen k ON p.id_konsumen = k.id_konsumen
                               JOIN tukang t ON p.id_tukang = t.id_tukang
                               ORDER BY p.tanggal_pesanan DESC LIMIT 5");
$recent_orders = [];
while ($row = $result_recent->fetch_assoc()) {
    $recent_orders[] = $row;
}

// Top rated tukang
$result_top = $conn->query("SELECT * FROM tukang WHERE status_aktif = 'aktif' ORDER BY rating_avg DESC LIMIT 5");
$top_tukang = [];
while ($row = $result_top->fetch_assoc()) {
    $top_tukang[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Fix Us</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
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
        }

        .sidebar-menu {
            list-style: none;
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

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h1 {
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .stat-card.primary .value { color: #667eea; }
        .stat-card.success .value { color: #28a745; }
        .stat-card.warning .value { color: #ffc107; }
        .stat-card.info .value { color: #17a2b8; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #333;
        }

        .card-body {
            padding: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-diterima { background: #cce5ff; color: #004085; }
        .badge-proses { background: #d1ecf1; color: #0c5460; }
        .badge-selesai { background: #d4edda; color: #155724; }
        .badge-dibatalkan { background: #f8d7da; color: #721c24; }

        .tukang-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .tukang-item:last-child {
            border-bottom: none;
        }

        .tukang-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
        }

        .tukang-info h4 {
            margin: 0;
            font-size: 14px;
            color: #333;
        }

        .tukang-info p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #666;
        }

        .rating {
            margin-left: auto;
            color: #ffc107;
            font-weight: 600;
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

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

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

            .main-content {
                margin-left: 0;
                padding: 70px 15px 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header h1 {
                font-size: 24px;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 12px;
            }

            .stat-card .value {
                font-size: 20px;
            }

            /* Table responsive */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table {
                min-width: 600px;
            }

            .table th,
            .table td {
                padding: 8px;
                font-size: 12px;
            }

            .badge {
                font-size: 10px;
                padding: 3px 6px;
            }

            .tukang-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .rating {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card .value {
                font-size: 24px;
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
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Fix Us Admin</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="konsumen.php">Kelola Konsumen</a></li>
            <li><a href="tukang.php">Kelola Tukang</a></li>
            <li><a href="pesanan.php">Daftar Pesanan</a></li>
            <li><a href="konfirmasi-va.php">Konfirmasi VA</a></li>
            <li><a href="delete_order.php">Hapus Pesanan</a></li>
            <li><a href="kategori.php">Kategori Layanan</a></li>
            <li><a href="laporan.php">Laporan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['admin_nama']); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card primary">
                <h3>Total Konsumen</h3>
                <div class="value"><?php echo number_format($total_konsumen); ?></div>
            </div>
            <div class="stat-card info">
                <h3>Total Tukang</h3>
                <div class="value"><?php echo number_format($total_tukang); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Tukang Aktif</h3>
                <div class="value"><?php echo number_format($tukang_aktif); ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Rating Rata-rata</h3>
                <div class="value"><?php echo $avg_rating; ?> ⭐</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Pesanan</h3>
                <div class="value"><?php echo number_format($total_pesanan); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pesanan Selesai</h3>
                <div class="value"><?php echo number_format($pesanan_selesai); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pesanan Pending</h3>
                <div class="value"><?php echo number_format($pesanan_pending); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Total Pendapatan</h3>
                <div class="value"><?php echo formatRupiah($total_pendapatan); ?></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card info">
                <h3>Pesanan Bulan Ini</h3>
                <div class="value"><?php echo number_format($pesanan_bulan_ini); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Pendapatan Bulan Ini</h3>
                <div class="value"><?php echo formatRupiah($pendapatan_bulan_ini); ?></div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">Pesanan Terbaru</div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Konsumen</th>
                                <th>Tukang</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #666;">Belum ada pesanan</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($order['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($order['nama_konsumen']); ?></td>
                                        <td><?php echo htmlspecialchars($order['nama_tukang']); ?></td>
                                        <td><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                        <td><?php echo formatRupiah($order['total_biaya']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Tukang Rating Tertinggi</div>
                <div class="card-body">
                    <?php if (empty($top_tukang)): ?>
                        <p style="text-align: center; color: #666;">Belum ada data tukang</p>
                    <?php else: ?>
                        <?php foreach ($top_tukang as $tukang): ?>
                            <div class="tukang-item">
                                <div class="tukang-avatar">
                                    <?php echo strtoupper(substr($tukang['nama'], 0, 1)); ?>
                                </div>
                                <div class="tukang-info">
                                    <h4><?php echo htmlspecialchars($tukang['nama']); ?></h4>
                                    <p><?php echo htmlspecialchars($tukang['keahlian'] ?? '-'); ?></p>
                                </div>
                                <div class="rating">
                                    <?php echo number_format($tukang['rating_avg'], 1); ?> ⭐
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        mobileMenuBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when menu item clicked on mobile
        const menuLinks = document.querySelectorAll('.sidebar-menu a');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
    </script>
</body>
</html>
