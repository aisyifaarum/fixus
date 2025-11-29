<?php
require_once 'config.php';

// Cek login sebagai tukang
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'tukang') {
    header('Location: login.php');
    exit();
}

$id_tukang = $_SESSION['user_id'];
$success = isset($_GET['msg']) ? $_GET['msg'] : '';

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';

// Build query based on filter
$where_clause = "p.id_tukang = ?";
$params = [$id_tukang];
$param_types = "i";

if ($filter == 'baru') {
    $where_clause .= " AND p.status = 'pending'";
} elseif ($filter == 'aktif') {
    $where_clause .= " AND p.status IN ('diterima', 'proses')";
} elseif ($filter == 'selesai') {
    $where_clause .= " AND p.status = 'selesai'";
}

// Get pesanan list
$query = "SELECT p.*, k.nama as nama_konsumen, k.no_telepon as telepon_konsumen, k.email as email_konsumen
          FROM pesanan p
          JOIN konsumen k ON p.id_konsumen = k.id_konsumen
          WHERE $where_clause
          ORDER BY
            CASE p.status
                WHEN 'pending' THEN 1
                WHEN 'diterima' THEN 2
                WHEN 'proses' THEN 3
                WHEN 'selesai' THEN 4
                WHEN 'dibatalkan' THEN 5
            END,
            p.tanggal_pesanan DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Count statistics
$stmt_stats = $conn->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('diterima', 'proses') THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai
    FROM pesanan WHERE id_tukang = ?");
$stmt_stats->bind_param("i", $id_tukang);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Masuk - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pesanan-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 10px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
            position: relative;
        }

        .filter-tab:hover {
            background: #f0f0f0;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .filter-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ff4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }

        .pesanan-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .pesanan-card:hover {
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .pesanan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-diterima { background: #d1ecf1; color: #0c5460; }
        .status-proses { background: #cce5ff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }

        .konsumen-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .konsumen-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: bold;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-accept {
            background: #28a745;
            color: white;
        }

        .btn-accept:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-process {
            background: #007bff;
            color: white;
        }

        .btn-process:hover {
            background: #0056b3;
        }

        .btn-complete {
            background: #28a745;
            color: white;
        }

        .btn-complete:hover {
            background: #218838;
        }

        .btn-detail {
            background: #6c757d;
            color: white;
        }

        .btn-detail:hover {
            background: #5a6268;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="pesanan-container">
        <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>

        <h1 style="margin-bottom: 20px;">📦 Pesanan Masuk</h1>

        <?php if ($success == 'accepted'): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">✅ Pesanan berhasil diterima!</div>
        <?php elseif ($success == 'rejected'): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">❌ Pesanan ditolak</div>
        <?php elseif ($success == 'updated'): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">✅ Status pesanan berhasil diperbarui!</div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="?filter=semua" class="filter-tab <?php echo $filter == 'semua' ? 'active' : ''; ?>">
                Semua (<?php echo $stats['total']; ?>)
            </a>
            <a href="?filter=baru" class="filter-tab <?php echo $filter == 'baru' ? 'active' : ''; ?>">
                Pesanan Baru (<?php echo $stats['pending']; ?>)
                <?php if ($stats['pending'] > 0): ?>
                    <span class="filter-badge"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=aktif" class="filter-tab <?php echo $filter == 'aktif' ? 'active' : ''; ?>">
                Sedang Dikerjakan (<?php echo $stats['aktif']; ?>)
            </a>
            <a href="?filter=selesai" class="filter-tab <?php echo $filter == 'selesai' ? 'active' : ''; ?>">
                Selesai (<?php echo $stats['selesai']; ?>)
            </a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($pesanan = $result->fetch_assoc()): ?>
                <div class="pesanan-card">
                    <div class="pesanan-header">
                        <div>
                            <div style="font-size: 12px; color: #999;">
                                Pesanan #<?php echo str_pad($pesanan['id_pesanan'], 5, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div style="font-size: 12px; color: #999; margin-top: 5px;">
                                <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $pesanan['status']; ?>">
                            <?php
                            $status_text = [
                                'pending' => '⏳ Menunggu Konfirmasi',
                                'diterima' => '✅ Diterima',
                                'proses' => '🔧 Sedang Dikerjakan',
                                'selesai' => '✔️ Selesai',
                                'dibatalkan' => '❌ Dibatalkan'
                            ];
                            echo $status_text[$pesanan['status']];
                            ?>
                        </span>
                    </div>

                    <div class="konsumen-info">
                        <div class="konsumen-avatar">
                            <?php echo strtoupper(substr($pesanan['nama_konsumen'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: #333;"><?php echo htmlspecialchars($pesanan['nama_konsumen']); ?></h3>
                            <div style="font-size: 14px; color: #666;">
                                📞 <?php echo htmlspecialchars($pesanan['telepon_konsumen']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Kategori</span>
                            <span class="detail-value" style="text-transform: capitalize;">
                                <?php echo htmlspecialchars($pesanan['kategori']); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Jadwal Pengerjaan</span>
                            <span class="detail-value">
                                <?php echo date('d M Y', strtotime($pesanan['tanggal_pengerjaan'])); ?> •
                                <?php echo date('H:i', strtotime($pesanan['waktu_pengerjaan'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Lokasi</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars(substr($pesanan['alamat_pengerjaan'], 0, 40)); ?>...
                            </span>
                        </div>
                        <?php if ($pesanan['total_biaya'] > 0): ?>
                        <div class="detail-item">
                            <span class="detail-label">Total Biaya</span>
                            <span class="detail-value" style="font-weight: bold; color: #667eea;">
                                Rp <?php echo number_format($pesanan['total_biaya'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="padding: 15px; background: #f8f9fa; border-radius: 10px; margin: 15px 0;">
                        <strong style="color: #333;">Deskripsi Masalah:</strong><br>
                        <p style="margin: 10px 0 0 0; color: #666; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($pesanan['deskripsi_masalah'])); ?>
                        </p>
                    </div>

                    <div class="action-buttons">
                        <?php if ($pesanan['status'] == 'pending'): ?>
                            <form method="POST" action="handle_pesanan.php" style="display: inline;">
                                <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn btn-accept">✅ Terima Pesanan</button>
                            </form>
                            <form method="POST" action="handle_pesanan.php" style="display: inline;">
                                <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject">❌ Tolak Pesanan</button>
                            </form>
                        <?php elseif ($pesanan['status'] == 'diterima'): ?>
                            <form method="POST" action="handle_pesanan.php" style="display: inline;">
                                <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                <input type="hidden" name="action" value="start">
                                <button type="submit" class="btn btn-process">🔧 Mulai Pengerjaan</button>
                            </form>
                        <?php elseif ($pesanan['status'] == 'proses'): ?>
                            <button type="button" class="btn btn-complete" onclick="showCompleteModal(<?php echo $pesanan['id_pesanan']; ?>)">
                                ✔️ Selesaikan Pekerjaan
                            </button>
                        <?php endif; ?>

                        <a href="tel:<?php echo $pesanan['telepon_konsumen']; ?>" class="btn btn-detail">📞 Hubungi Konsumen</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📦</div>
                <h2>Belum Ada Pesanan</h2>
                <p style="color: #666; margin: 10px 0;">
                    <?php if ($filter == 'baru'): ?>
                        Belum ada pesanan baru yang perlu dikonfirmasi
                    <?php elseif ($filter == 'aktif'): ?>
                        Tidak ada pesanan yang sedang dikerjakan
                    <?php elseif ($filter == 'selesai'): ?>
                        Belum ada pesanan yang selesai
                    <?php else: ?>
                        Anda belum menerima pesanan
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Complete -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">✅ Selesaikan Pekerjaan</h2>
            <form method="POST" action="handle_pesanan.php">
                <input type="hidden" name="id_pesanan" id="complete_id_pesanan">
                <input type="hidden" name="action" value="complete">

                <div class="form-group">
                    <label>Total Biaya Pekerjaan <span style="color: red;">*</span></label>
                    <div class="input-group">
                        <input type="number" name="total_biaya" placeholder="150000" min="0" step="1000" required>
                    </div>
                    <small style="color: #666;">Masukkan total biaya untuk pekerjaan ini</small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-detail" onclick="closeCompleteModal()" style="flex: 1;">Batal</button>
                    <button type="submit" class="btn btn-complete" style="flex: 2;">Selesaikan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCompleteModal(id_pesanan) {
            document.getElementById('complete_id_pesanan').value = id_pesanan;
            document.getElementById('completeModal').classList.add('active');
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('completeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCompleteModal();
            }
        });
    </script>
</body>
</html>
