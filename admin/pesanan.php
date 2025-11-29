<?php
require_once 'config.php';
checkAdminLogin();

$message = '';

// Handle status update
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = $_GET['status'];
    $valid_status = ['pending', 'diterima', 'proses', 'selesai', 'dibatalkan'];

    if (in_array($status, $valid_status)) {
        $stmt = $conn->prepare("UPDATE pesanan SET status = ? WHERE id_pesanan = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $message = 'Status pesanan berhasil diupdate!';
        }
        $stmt->close();
    }
}

// Filter
$filter_status = $_GET['filter_status'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if ($filter_status) {
    $where[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_date) {
    $where[] = "DATE(p.tanggal_pesanan) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "SELECT p.*, k.nama as nama_konsumen, k.no_telepon as telepon_konsumen,
          t.nama as nama_tukang, t.no_telepon as telepon_tukang,
          pb.status as payment_status
          FROM pesanan p
          JOIN konsumen k ON p.id_konsumen = k.id_konsumen
          JOIN tukang t ON p.id_tukang = t.id_tukang
          LEFT JOIN pembayaran pb ON p.id_pesanan = pb.booking_id
          $where_clause
          ORDER BY p.tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$pesanan_list = [];
while ($row = $result->fetch_assoc()) {
    $pesanan_list[] = $row;
}
$stmt->close();

// Get stats
$stats = [
    'pending' => 0, 'diterima' => 0, 'proses' => 0, 'selesai' => 0, 'dibatalkan' => 0
];
$result_stats = $conn->query("SELECT status, COUNT(*) as total FROM pesanan GROUP BY status");
while ($row = $result_stats->fetch_assoc()) {
    $stats[$row['status']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - Fix Us Admin</title>
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
        .filter-form select, .filter-form input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .stats-row { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-item { background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; min-width: 120px; }
        .stat-item .label { font-size: 12px; color: #666; text-transform: uppercase; }
        .stat-item .value { font-size: 24px; font-weight: bold; color: #333; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-body { padding: 20px; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        .table th { font-weight: 600; color: #666; font-size: 11px; text-transform: uppercase; background: #f8f9fa; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-diterima { background: #cce5ff; color: #004085; }
        .badge-proses { background: #d1ecf1; color: #0c5460; }
        .badge-selesai { background: #d4edda; color: #155724; }
        .badge-dibatalkan { background: #f8d7da; color: #721c24; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-unpaid { background: #f8d7da; color: #721c24; }
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content { display: none; position: absolute; background: white; min-width: 120px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); border-radius: 8px; z-index: 1; }
        .dropdown:hover .dropdown-content { display: block; }
        .dropdown-content a { display: block; padding: 8px 12px; text-decoration: none; color: #333; font-size: 12px; }
        .dropdown-content a:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Fix Us Admin</h2></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="konsumen.php">Kelola Konsumen</a></li>
            <li><a href="tukang.php">Kelola Tukang</a></li>
            <li><a href="pesanan.php" class="active">Daftar Pesanan</a></li>
            <li><a href="konfirmasi-va.php">Konfirmasi VA</a></li>
            <li><a href="kategori.php">Kategori Layanan</a></li>
            <li><a href="laporan.php">Laporan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Daftar Pesanan</h1>
            <form method="GET" class="filter-form">
                <select name="filter_status">
                    <option value="">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="diterima" <?php echo $filter_status == 'diterima' ? 'selected' : ''; ?>>Diterima</option>
                    <option value="proses" <?php echo $filter_status == 'proses' ? 'selected' : ''; ?>>Proses</option>
                    <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="dibatalkan" <?php echo $filter_status == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
                <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="pesanan.php" class="btn btn-sm" style="background: #6c757d; color: white;">Reset</a>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-item">
                <div class="label">Pending</div>
                <div class="value" style="color: #856404;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">Diterima</div>
                <div class="value" style="color: #004085;"><?php echo $stats['diterima']; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">Proses</div>
                <div class="value" style="color: #0c5460;"><?php echo $stats['proses']; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">Selesai</div>
                <div class="value" style="color: #155724;"><?php echo $stats['selesai']; ?></div>
            </div>
            <div class="stat-item">
                <div class="label">Dibatalkan</div>
                <div class="value" style="color: #721c24;"><?php echo $stats['dibatalkan']; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Konsumen</th>
                            <th>Tukang</th>
                            <th>Kategori</th>
                            <th>Jadwal</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Bayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pesanan_list)): ?>
                            <tr><td colspan="10" style="text-align: center; color: #666;">Tidak ada pesanan</td></tr>
                        <?php else: ?>
                            <?php foreach ($pesanan_list as $pesanan): ?>
                                <tr>
                                    <td>#<?php echo str_pad($pesanan['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo formatTanggal($pesanan['tanggal_pesanan']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($pesanan['nama_konsumen']); ?><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($pesanan['telepon_konsumen'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($pesanan['nama_tukang']); ?><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($pesanan['telepon_tukang'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($pesanan['kategori'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo formatTanggal($pesanan['tanggal_pengerjaan']); ?><br>
                                        <small style="color: #666;"><?php echo $pesanan['waktu_pengerjaan']; ?></small>
                                    </td>
                                    <td><?php echo formatRupiah($pesanan['total_biaya']); ?></td>
                                    <td><span class="badge badge-<?php echo $pesanan['status']; ?>"><?php echo ucfirst($pesanan['status']); ?></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $pesanan['payment_status'] == 'paid' ? 'paid' : 'unpaid'; ?>">
                                            <?php echo $pesanan['payment_status'] == 'paid' ? 'Lunas' : 'Belum'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm">Ubah Status ▼</button>
                                            <div class="dropdown-content">
                                                <a href="?id=<?php echo $pesanan['id_pesanan']; ?>&status=pending">Pending</a>
                                                <a href="?id=<?php echo $pesanan['id_pesanan']; ?>&status=diterima">Diterima</a>
                                                <a href="?id=<?php echo $pesanan['id_pesanan']; ?>&status=proses">Proses</a>
                                                <a href="?id=<?php echo $pesanan['id_pesanan']; ?>&status=selesai">Selesai</a>
                                                <a href="?id=<?php echo $pesanan['id_pesanan']; ?>&status=dibatalkan">Batalkan</a>
                                            </div>
                                        </div>
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
