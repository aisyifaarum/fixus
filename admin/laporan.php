<?php
require_once 'config.php';
checkAdminLogin();

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'transaksi';

// Get transaction data
$query = "SELECT p.*, k.nama as nama_konsumen, t.nama as nama_tukang,
          pb.status as payment_status, pb.paid_at
          FROM pesanan p
          JOIN konsumen k ON p.id_konsumen = k.id_konsumen
          JOIN tukang t ON p.id_tukang = t.id_tukang
          LEFT JOIN pembayaran pb ON p.id_pesanan = pb.booking_id
          WHERE DATE(p.tanggal_pesanan) BETWEEN ? AND ?
          ORDER BY p.tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
$total_revenue = 0;
$total_paid = 0;
$status_count = ['pending' => 0, 'diterima' => 0, 'proses' => 0, 'selesai' => 0, 'dibatalkan' => 0];

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
    $total_revenue += $row['total_biaya'];
    if ($row['payment_status'] == 'paid') {
        $total_paid += $row['total_biaya'];
    }
    $status_count[$row['status']]++;
}
$stmt->close();

// Export functions
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="laporan_transaksi_' . date('Y-m-d') . '.xls"');

        echo "<table border='1'>";
        echo "<tr><th colspan='9'>Laporan Transaksi Fix Us</th></tr>";
        echo "<tr><th colspan='9'>Periode: $start_date s/d $end_date</th></tr>";
        echo "<tr><th>ID</th><th>Tanggal</th><th>Konsumen</th><th>Tukang</th><th>Kategori</th><th>Status</th><th>Total</th><th>Pembayaran</th><th>Tgl Bayar</th></tr>";

        foreach ($transactions as $t) {
            echo "<tr>";
            echo "<td>" . str_pad($t['id_pesanan'], 5, '0', STR_PAD_LEFT) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($t['tanggal_pesanan'])) . "</td>";
            echo "<td>" . htmlspecialchars($t['nama_konsumen']) . "</td>";
            echo "<td>" . htmlspecialchars($t['nama_tukang']) . "</td>";
            echo "<td>" . htmlspecialchars($t['kategori'] ?? '-') . "</td>";
            echo "<td>" . ucfirst($t['status']) . "</td>";
            echo "<td>" . number_format($t['total_biaya'], 0, ',', '.') . "</td>";
            echo "<td>" . ($t['payment_status'] == 'paid' ? 'Lunas' : 'Belum') . "</td>";
            echo "<td>" . ($t['paid_at'] ? date('d/m/Y H:i', strtotime($t['paid_at'])) : '-') . "</td>";
            echo "</tr>";
        }

        echo "<tr><td colspan='6'><strong>Total Pendapatan</strong></td><td colspan='3'><strong>Rp " . number_format($total_paid, 0, ',', '.') . "</strong></td></tr>";
        echo "</table>";
        exit();
    }

    if ($export_type == 'pdf') {
        // Simple PDF using HTML to PDF approach
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laporan Transaksi - Fix Us</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                h1 { text-align: center; color: #667eea; }
                .info { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #667eea; color: white; }
                .total { font-weight: bold; background: #f0f0f0; }
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body onload="window.print()">
            <h1>Laporan Transaksi Fix Us</h1>
            <p class="info">Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Konsumen</th>
                        <th>Tukang</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>#<?php echo str_pad($t['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($t['tanggal_pesanan'])); ?></td>
                        <td><?php echo htmlspecialchars($t['nama_konsumen']); ?></td>
                        <td><?php echo htmlspecialchars($t['nama_tukang']); ?></td>
                        <td><?php echo htmlspecialchars($t['kategori'] ?? '-'); ?></td>
                        <td><?php echo ucfirst($t['status']); ?></td>
                        <td>Rp <?php echo number_format($t['total_biaya'], 0, ',', '.'); ?></td>
                        <td><?php echo $t['payment_status'] == 'paid' ? 'Lunas' : 'Belum'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td colspan="6">Total Pendapatan</td>
                        <td colspan="2">Rp <?php echo number_format($total_paid, 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>

            <p><strong>Ringkasan:</strong></p>
            <ul>
                <li>Total Transaksi: <?php echo count($transactions); ?></li>
                <li>Selesai: <?php echo $status_count['selesai']; ?></li>
                <li>Dalam Proses: <?php echo $status_count['proses']; ?></li>
                <li>Pending: <?php echo $status_count['pending']; ?></li>
                <li>Dibatalkan: <?php echo $status_count['dibatalkan']; ?></li>
            </ul>
        </body>
        </html>
        <?php
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Fix Us Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; overflow-y: auto; }
        .sidebar-header { color: white; text-align: center; padding: 20px 0; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: block; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 8px; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header h1 { color: #333; margin-bottom: 15px; }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .filter-form input, .filter-form select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h3 { color: #666; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: bold; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #eee; font-weight: 600; color: #333; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        .table th { font-weight: 600; color: #666; font-size: 11px; text-transform: uppercase; background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-selesai { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-proses { background: #d1ecf1; color: #0c5460; }
        .badge-dibatalkan { background: #f8d7da; color: #721c24; }
        .badge-diterima { background: #cce5ff; color: #004085; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-unpaid { background: #f8d7da; color: #721c24; }
        .export-buttons { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Fix Us Admin</h2></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="konsumen.php">Kelola Konsumen</a></li>
            <li><a href="tukang.php">Kelola Tukang</a></li>
            <li><a href="pesanan.php">Daftar Pesanan</a></li>
            <li><a href="konfirmasi-va.php">Konfirmasi VA</a></li>
            <li><a href="kategori.php">Kategori Layanan</a></li>
            <li><a href="laporan.php" class="active">Laporan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Laporan Transaksi</h1>
            <form method="GET" class="filter-form">
                <label>Dari:</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <label>Sampai:</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Transaksi</h3>
                <div class="value" style="color: #667eea;"><?php echo count($transactions); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Nilai</h3>
                <div class="value" style="color: #333;"><?php echo formatRupiah($total_revenue); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pendapatan (Lunas)</h3>
                <div class="value" style="color: #28a745;"><?php echo formatRupiah($total_paid); ?></div>
            </div>
            <div class="stat-card">
                <h3>Selesai</h3>
                <div class="value" style="color: #155724;"><?php echo $status_count['selesai']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Dalam Proses</h3>
                <div class="value" style="color: #0c5460;"><?php echo $status_count['proses'] + $status_count['diterima']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Dibatalkan</h3>
                <div class="value" style="color: #721c24;"><?php echo $status_count['dibatalkan']; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>Data Transaksi</span>
                <div class="export-buttons">
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=excel" class="btn btn-success btn-sm">Export Excel</a>
                    <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=pdf" class="btn btn-danger btn-sm" target="_blank">Export PDF</a>
                </div>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Konsumen</th>
                            <th>Tukang</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Bayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="8" style="text-align: center; color: #666;">Tidak ada data transaksi</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td>#<?php echo str_pad($t['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo formatTanggal($t['tanggal_pesanan']); ?></td>
                                    <td><?php echo htmlspecialchars($t['nama_konsumen']); ?></td>
                                    <td><?php echo htmlspecialchars($t['nama_tukang']); ?></td>
                                    <td><?php echo htmlspecialchars($t['kategori'] ?? '-'); ?></td>
                                    <td><span class="badge badge-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                    <td><?php echo formatRupiah($t['total_biaya']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $t['payment_status'] == 'paid' ? 'paid' : 'unpaid'; ?>">
                                            <?php echo $t['payment_status'] == 'paid' ? 'Lunas' : 'Belum'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
