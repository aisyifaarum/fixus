<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if ($id_pesanan == 0) {
    header('Location: ' . ($user_type == 'konsumen' ? 'pesanan-saya.php' : 'pesanan-masuk.php'));
    exit();
}

// Get pesanan data with verification
if ($user_type == 'konsumen') {
    $stmt = $conn->prepare("SELECT p.*, t.nama as nama_tukang, t.no_telepon as telepon_tukang, t.email as email_tukang, t.keahlian, t.rating_avg
                           FROM pesanan p
                           JOIN tukang t ON p.id_tukang = t.id_tukang
                           WHERE p.id_pesanan = ? AND p.id_konsumen = ?");
    $stmt->bind_param("ii", $id_pesanan, $user_id);
} else {
    $stmt = $conn->prepare("SELECT p.*, k.nama as nama_konsumen, k.no_telepon as telepon_konsumen, k.email as email_konsumen, k.alamat as alamat_konsumen
                           FROM pesanan p
                           JOIN konsumen k ON p.id_konsumen = k.id_konsumen
                           WHERE p.id_pesanan = ? AND p.id_tukang = ?");
    $stmt->bind_param("ii", $id_pesanan, $user_id);
}

$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    header('Location: ' . ($user_type == 'konsumen' ? 'pesanan-saya.php' : 'pesanan-masuk.php'));
    exit();
}

// Get payment info from virtual_account if exists
$stmt_payment = $conn->prepare("SELECT * FROM virtual_account WHERE id_pesanan = ? ORDER BY tanggal_dibuat DESC LIMIT 1");
$stmt_payment->bind_param("i", $id_pesanan);
$stmt_payment->execute();
$payment = $stmt_payment->get_result()->fetch_assoc();
$stmt_payment->close();

// Get review if exists
$stmt_review = $conn->prepare("SELECT r.*, k.nama as nama_konsumen FROM reviews r
                               JOIN konsumen k ON r.konsumen_id = k.id_konsumen
                               WHERE r.booking_id = ?");
$stmt_review->bind_param("i", $id_pesanan);
$stmt_review->execute();
$review = $stmt_review->get_result()->fetch_assoc();
$stmt_review->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo str_pad($id_pesanan, 5, '0', STR_PAD_LEFT); ?> - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .detail-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .detail-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-diterima { background: #d1ecf1; color: #0c5460; }
        .status-proses { background: #cce5ff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .person-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-top: 15px;
        }

        .person-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            font-weight: bold;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -17px;
            top: 17px;
            width: 2px;
            height: calc(100% - 10px);
            background: #e0e0e0;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-time {
            font-size: 12px;
            color: #999;
        }

        .timeline-content {
            margin-top: 5px;
            font-size: 14px;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .stars {
            color: #ffd700;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="detail-container">
        <a href="<?php echo $user_type == 'konsumen' ? 'pesanan-saya.php' : 'pesanan-masuk.php'; ?>" class="back-link">
            ← Kembali
        </a>

        <div class="detail-card">
            <div class="detail-header">
                <div>
                    <h1 style="margin: 0;">Pesanan #<?php echo str_pad($id_pesanan, 5, '0', STR_PAD_LEFT); ?></h1>
                    <p style="color: #666; margin: 5px 0 0 0;">
                        <?php echo date('d F Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?>
                    </p>
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

            <!-- Info Pihak Lain -->
            <div class="info-section">
                <h3><?php echo $user_type == 'konsumen' ? '👨‍🔧 Informasi Tukang' : '👤 Informasi Konsumen'; ?></h3>
                <div class="person-card">
                    <div class="person-avatar">
                        <?php
                        $nama = $user_type == 'konsumen' ? $pesanan['nama_tukang'] : $pesanan['nama_konsumen'];
                        echo strtoupper(substr($nama, 0, 1));
                        ?>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($nama); ?></h3>
                        <p style="margin: 0; color: #666;">
                            📞 <?php echo htmlspecialchars($user_type == 'konsumen' ? $pesanan['telepon_tukang'] : $pesanan['telepon_konsumen']); ?>
                        </p>
                        <?php if ($user_type == 'konsumen'): ?>
                            <div class="rating-display">
                                <span class="stars">⭐</span>
                                <span><?php echo number_format($pesanan['rating_avg'], 1); ?></span>
                                <span style="color: #999;">• <?php echo htmlspecialchars($pesanan['keahlian']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="tel:<?php echo $user_type == 'konsumen' ? $pesanan['telepon_tukang'] : $pesanan['telepon_konsumen']; ?>"
                       class="btn btn-primary">
                        📞 Hubungi
                    </a>
                </div>
            </div>

            <!-- Detail Pesanan -->
            <div class="info-section">
                <h3>📋 Detail Pesanan</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Kategori</div>
                        <div class="info-value" style="text-transform: capitalize;">
                            <?php echo htmlspecialchars($pesanan['kategori']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tanggal Pengerjaan</div>
                        <div class="info-value">
                            <?php echo date('d F Y', strtotime($pesanan['tanggal_pengerjaan'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Waktu Pengerjaan</div>
                        <div class="info-value">
                            <?php echo date('H:i', strtotime($pesanan['waktu_pengerjaan'])); ?> WIB
                        </div>
                    </div>
                    <?php if ($pesanan['total_biaya'] > 0): ?>
                    <div class="info-item" style="background: #e8f5e9;">
                        <div class="info-label">Total Biaya</div>
                        <div class="info-value" style="color: #28a745; font-size: 20px;">
                            Rp <?php echo number_format($pesanan['total_biaya'], 0, ',', '.'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <div class="info-label">Alamat Pengerjaan</div>
                    <div class="info-value" style="margin-top: 10px;">
                        <?php echo nl2br(htmlspecialchars($pesanan['alamat_pengerjaan'])); ?>
                    </div>
                </div>

                <div style="margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                    <div class="info-label" style="color: #856404;">Deskripsi Masalah</div>
                    <div class="info-value" style="margin-top: 10px; color: #856404;">
                        <?php echo nl2br(htmlspecialchars($pesanan['deskripsi_masalah'])); ?>
                    </div>
                </div>

                <?php if ($pesanan['catatan']): ?>
                <div style="margin-top: 20px; padding: 20px; background: #e3f2fd; border-radius: 10px;">
                    <div class="info-label" style="color: #0c5460;">Catatan Tambahan</div>
                    <div class="info-value" style="margin-top: 10px; color: #0c5460;">
                        <?php echo nl2br(htmlspecialchars($pesanan['catatan'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Info -->
            <?php if ($payment): ?>
            <div class="info-section">
                <h3>💳 Informasi Pembayaran</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Metode Pembayaran</div>
                        <div class="info-value">
                            Transfer Bank (Virtual Account)
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status Pembayaran</div>
                        <div class="info-value">
                            <?php
                            $payment_status = [
                                'pending' => '⏳ Menunggu',
                                'paid' => '✅ Lunas',
                                'expired' => '⏰ Kedaluwarsa',
                                'failed' => '❌ Gagal'
                            ];
                            $status = $payment['status_pembayaran'] ?? 'pending';
                            echo isset($payment_status[$status]) ? $payment_status[$status] : '⏳ Menunggu';
                            ?>
                        </div>
                    </div>
                    <?php if (isset($payment['tanggal_bayar']) && $payment['tanggal_bayar']): ?>
                    <div class="info-item">
                        <div class="info-label">Dibayar Pada</div>
                        <div class="info-value">
                            <?php echo date('d M Y, H:i', strtotime($payment['tanggal_bayar'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($payment['bank']) && $payment['bank']): ?>
                    <div class="info-item">
                        <div class="info-label">Bank</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($payment['bank']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Nomor Rekening</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($payment['nomor_rekening'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Atas Nama</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($payment['nama_penerima'] ?? ''); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Review -->
            <?php if ($review): ?>
            <div class="info-section">
                <h3>⭐ Rating & Ulasan</h3>
                <div style="padding: 20px; background: #fff3e0; border-radius: 10px;">
                    <div class="rating-display">
                        <span class="stars"><?php echo str_repeat('⭐', $review['rating']); ?></span>
                        <span style="font-weight: 600;"><?php echo $review['rating']; ?> / 5</span>
                    </div>
                    <?php if ($review['review']): ?>
                    <p style="margin-top: 15px; color: #333; line-height: 1.6;">
                        "<?php echo nl2br(htmlspecialchars($review['review'])); ?>"
                    </p>
                    <?php endif; ?>
                    <p style="margin-top: 10px; font-size: 12px; color: #999;">
                        - <?php echo htmlspecialchars($review['nama_konsumen']); ?> •
                        <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($user_type == 'konsumen'): ?>
                    <?php if ($pesanan['status'] == 'pending'): ?>
                        <form method="POST" action="batalkan_pesanan.php" style="display: inline;">
                            <input type="hidden" name="id_pesanan" value="<?php echo $id_pesanan; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                ❌ Batalkan Pesanan
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="<?php echo $user_type == 'konsumen' ? 'pesanan-saya.php' : 'pesanan-masuk.php'; ?>" class="btn btn-secondary">
                    ← Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
</body>
</html>
