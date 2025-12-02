<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

// Get tukang ID
$id_tukang = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_tukang == 0) {
    header('Location: cari-tukang.php');
    exit();
}

// Get tukang data
$stmt = $conn->prepare("SELECT * FROM tukang WHERE id_tukang = ? AND status_aktif = 'aktif'");
$stmt->bind_param("i", $id_tukang);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: cari-tukang.php');
    exit();
}

$tukang = $result->fetch_assoc();

// Get reviews
$ulasan_query = "SELECT r.*, k.nama as nama_konsumen
                 FROM reviews r
                 JOIN konsumen k ON r.konsumen_id = k.id_konsumen
                 WHERE r.tukang_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT 5";
$stmt_ulasan = $conn->prepare($ulasan_query);
$stmt_ulasan->bind_param("i", $id_tukang);
$stmt_ulasan->execute();
$ulasan_result = $stmt_ulasan->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil <?php echo htmlspecialchars($tukang['nama']); ?> - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profil-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .profil-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .profil-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .profil-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .profil-info {
            flex: 1;
        }
        
        .profil-info h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 32px;
        }
        
        .kategori-badge {
            display: inline-block;
            padding: 6px 15px;
            background: #e3f2fd;
            color: #667eea;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: capitalize;
        }
        
        .rating-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 15px 0;
        }
        
        .rating-big {
            font-size: 48px;
            font-weight: bold;
            color: #ffd700;
        }
        
        .rating-details {
            flex: 1;
        }
        
        .rating-stars {
            font-size: 24px;
            color: #ffd700;
            margin-bottom: 5px;
        }
        
        .rating-count {
            color: #666;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .info-section {
            margin: 30px 0;
        }
        
        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .info-icon {
            font-size: 24px;
            width: 40px;
            text-align: center;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #666;
        }
        
        .ulasan-section {
            margin: 30px 0;
        }
        
        .ulasan-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .ulasan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
        }
        
        .ulasan-rating {
            color: #ffd700;
        }
        
        .ulasan-text {
            color: #666;
            line-height: 1.6;
        }
        
        .ulasan-date {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-pesan {
            flex: 1;
            padding: 15px 30px;
            background: #667eea;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .btn-pesan:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-back {
            padding: 15px 30px;
            background: #f0f0f0;
            color: #333;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #e0e0e0;
        }
        
        .no-ulasan {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="profil-container">
            <div class="profil-card">
                <div class="profil-header">
                    <div class="profil-avatar">
                        <?php echo strtoupper(substr($tukang['nama'], 0, 1)); ?>
                    </div>
                    <div class="profil-info">
                        <h1><?php echo htmlspecialchars($tukang['nama']); ?></h1>
                        <span class="kategori-badge">
                            <?php 
                            $icons = [
                                'listrik' => '⚡',
                                'ac' => '❄️',
                                'elektronik' => '📺',
                                'pipa' => '🚰',
                                'furniture' => '🪑',
                                'lainnya' => '🔧'
                            ];
                            echo $icons[$tukang['kategori']] . ' ' . ucfirst($tukang['kategori']); 
                            ?>
                        </span>
                        <div class="rating-section">
                            <div class="rating-big">
                                <?php echo number_format($tukang['rating_avg'], 1); ?>
                            </div>
                            <div class="rating-details">
                                <div class="rating-stars">
                                    <?php
                                    $rating = $tukang['rating_avg'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '⭐';
                                        } elseif ($i - 0.5 <= $rating) {
                                            echo '⭐';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="rating-count">
                                    <?php echo $ulasan_result->num_rows; ?> ulasan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $tukang['jumlah_pesanan']; ?></div>
                        <div class="stat-label">Pesanan Selesai</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">Rp <?php echo number_format($tukang['harga_per_jam'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Per Jam</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?php echo number_format($tukang['rating_avg'], 1); ?>⭐</div>
                        <div class="stat-label">Rating</div>
                    </div>
                </div>

                <div class="info-section">
                    <h3>📋 Informasi Tukang</h3>
                    
                    <div class="info-item">
                        <div class="info-icon">🔧</div>
                        <div class="info-content">
                            <div class="info-label">Keahlian</div>
                            <div class="info-value"><?php echo htmlspecialchars($tukang['keahlian']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">📍</div>
                        <div class="info-content">
                            <div class="info-label">Lokasi</div>
                            <div class="info-value"><?php echo htmlspecialchars($tukang['lokasi']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">📞</div>
                        <div class="info-content">
                            <div class="info-label">No. Telepon</div>
                            <div class="info-value"><?php echo htmlspecialchars($tukang['no_telepon']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">📝</div>
                        <div class="info-content">
                            <div class="info-label">Deskripsi</div>
                            <div class="info-value"><?php echo htmlspecialchars($tukang['deskripsi']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="ulasan-section">
                    <h3>⭐ Ulasan Pelanggan</h3>
                    
                    <?php if ($ulasan_result->num_rows > 0): ?>
                        <?php while ($ulasan = $ulasan_result->fetch_assoc()): ?>
                            <div class="ulasan-item">
                                <div class="ulasan-header">
                                    <span class="reviewer-name">
                                        <?php echo htmlspecialchars($ulasan['nama_konsumen']); ?>
                                    </span>
                                    <span class="ulasan-rating">
                                        <?php
                                        for ($i = 0; $i < $ulasan['rating']; $i++) {
                                            echo '⭐';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="ulasan-text">
                                    <?php echo htmlspecialchars($ulasan['review']); ?>
                                </div>
                                <div class="ulasan-date">
                                    <?php echo date('d M Y, H:i', strtotime($ulasan['created_at'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-ulasan">
                            <div style="font-size: 48px; margin-bottom: 10px;">💬</div>
                            Belum ada ulasan
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <a href="cari-tukang.php" class="btn-back">← Kembali</a>
                    <a href="pesan-tukang.php?id=<?php echo $tukang['id_tukang']; ?>" class="btn-pesan">
                        📋 Pesan Sekarang
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>