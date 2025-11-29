<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$id_konsumen = $_SESSION['user_id'];
$success = isset($_GET['msg']) && $_GET['msg'] == 'success' ? 'Pesanan berhasil dibuat!' : '';

// Check for session messages
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

$error = '';
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Get pesanan dengan info payment
$query = "SELECT p.*, t.nama as nama_tukang, t.no_telepon, t.kategori as kategori_tukang, t.rating_avg,
          va.nomor_rekening, va.bank, va.nama_penerima, va.jumlah as jumlah_va, va.status_pembayaran,
          va.tanggal_expired, va.tanggal_bayar, va.konfirmasi_konsumen, va.tanggal_konfirmasi_konsumen
          FROM pesanan p
          JOIN tukang t ON p.id_tukang = t.id_tukang
          LEFT JOIN virtual_account va ON p.id_pesanan = va.id_pesanan
          WHERE p.id_konsumen = ?
          ORDER BY p.tanggal_pesanan DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_konsumen);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
        
        .pesanan-id {
            font-size: 12px;
            color: #999;
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
        
        .pesanan-info {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .tukang-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: bold;
        }
        
        .tukang-detail h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .tukang-meta {
            color: #666;
            font-size: 14px;
        }
        
        .pesanan-detail {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .detail-label {
            color: #666;
            font-weight: 600;
        }
        
        .detail-value {
            color: #333;
            text-align: right;
        }
        
        .pesanan-deskripsi {
            color: #666;
            line-height: 1.6;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-welcome">
                <h1>📋 Pesanan Saya</h1>
                <p>Kelola dan pantau pesanan Anda</p>
            </div>
            <a href="dashboard.php" class="logout-btn" style="background: #667eea;">← Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($pesanan = $result->fetch_assoc()): ?>
                <?php
                // Fetch latest payment for this pesanan (if any)
                $stmt_pay = $conn->prepare("SELECT * FROM pembayaran WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt_pay->bind_param("i", $pesanan['id_pesanan']);
                $stmt_pay->execute();
                $payment = $stmt_pay->get_result()->fetch_assoc();
                $stmt_pay->close();

                // Check if review exists for this pesanan
                $stmt_rev = $conn->prepare("SELECT COUNT(*) AS cnt FROM reviews WHERE booking_id = ?");
                $stmt_rev->bind_param("i", $pesanan['id_pesanan']);
                $stmt_rev->execute();
                $rev_row = $stmt_rev->get_result()->fetch_assoc();
                $has_review = $rev_row['cnt'] > 0;
                $stmt_rev->close();

                // Check if VA expired
                $is_va_expired = false;
                if ($pesanan['tanggal_expired']) {
                    $is_va_expired = strtotime($pesanan['tanggal_expired']) < time();
                }
                ?>
                <div class="pesanan-card">
                    <div class="pesanan-header">
                        <div>
                            <div class="pesanan-id">
                                #<?php echo str_pad($pesanan['id_pesanan'], 5, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div style="color: #999; font-size: 12px; margin-top: 5px;">
                                <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal_pesanan'])); ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $pesanan['status']; ?>">
                            <?php
                            // Tampilkan status yang lebih detail
                            if ($pesanan['status'] == 'pending') {
                                echo '⏳ Menunggu Konfirmasi Tukang';
                            } elseif ($pesanan['status'] == 'diterima' && !$pesanan['nomor_rekening']) {
                                echo '✅ Sedang Dikerjakan';
                            } elseif ($pesanan['status'] == 'proses' && !$pesanan['nomor_rekening']) {
                                echo '🔧 Sedang Dikerjakan';
                            } else {
                                $status_text = [
                                    'pending' => '⏳ Menunggu',
                                    'diterima' => '✅ Diterima',
                                    'proses' => '🔧 Dikerjakan',
                                    'selesai' => '✔️ Selesai',
                                    'dibatalkan' => '❌ Dibatalkan'
                                ];
                                echo $status_text[$pesanan['status']];
                            }
                            ?>
                        </span>
                    </div>

                    <div class="pesanan-info">
                        <div class="tukang-avatar">
                            <?php echo strtoupper(substr($pesanan['nama_tukang'], 0, 1)); ?>
                        </div>
                        <div class="tukang-detail">
                            <h4><?php echo htmlspecialchars($pesanan['nama_tukang']); ?></h4>
                            <div class="tukang-meta">
                                ⭐ <?php echo number_format($pesanan['rating_avg'], 1); ?> • 
                                📞 <?php echo htmlspecialchars($pesanan['no_telepon']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="pesanan-detail">
                        <div class="detail-row">
                            <span class="detail-label">Kategori:</span>
                            <span class="detail-value" style="text-transform: capitalize;">
                                <?php echo htmlspecialchars($pesanan['kategori']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Jadwal:</span>
                            <span class="detail-value">
                                <?php echo date('d M Y', strtotime($pesanan['tanggal_pengerjaan'])); ?> • 
                                <?php echo date('H:i', strtotime($pesanan['waktu_pengerjaan'])); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Lokasi:</span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars(substr($pesanan['alamat_pengerjaan'], 0, 50)); ?>...
                            </span>
                        </div>
                        <?php if ($pesanan['total_biaya'] > 0): ?>
                        <div class="detail-row" style="border-top: 1px solid #e0e0e0; padding-top: 10px; margin-top: 10px;">
                            <span class="detail-label">Total Biaya:</span>
                            <span class="detail-value" style="font-size: 18px; font-weight: bold; color: #667eea;">
                                Rp <?php echo number_format($pesanan['total_biaya'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pesanan-deskripsi">
                        <strong style="color: #333;">Deskripsi Masalah:</strong><br>
                        <?php echo nl2br(htmlspecialchars($pesanan['deskripsi_masalah'])); ?>
                    </div>

                    <?php if ($pesanan['catatan']): ?>
                    <div class="pesanan-deskripsi" style="background: #fff3cd;">
                        <strong style="color: #856404;">📝 Catatan:</strong><br>
                        <?php echo nl2br(htmlspecialchars($pesanan['catatan'])); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($pesanan['status'] == 'pending'): ?>
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <div style="font-weight: 600; color: #856404; margin-bottom: 5px;">⏳ Menunggu Konfirmasi Tukang</div>
                        <div style="color: #856404; font-size: 14px;">
                            Pesanan Anda sedang menunggu konfirmasi dari tukang. Anda akan mendapat notifikasi setelah tukang menerima pesanan.
                        </div>
                    </div>
                    <?php elseif (($pesanan['status'] == 'diterima' || $pesanan['status'] == 'proses') && !$pesanan['nomor_rekening']): ?>
                    <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <div style="font-weight: 600; color: #1976d2; margin-bottom: 5px;">🔧 Sedang Dikerjakan</div>
                        <div style="color: #1976d2; font-size: 14px;">
                            Tukang sedang mengerjakan pesanan Anda. Harga akan ditentukan setelah pekerjaan selesai. Anda akan mendapat notifikasi untuk melakukan pembayaran.
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($pesanan['nomor_rekening']): ?>
                    <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; border-radius: 10px; margin: 15px 0; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <strong style="font-size: 16px;">💳 Informasi Pembayaran</strong>
                            <?php
                            $is_expired = strtotime($pesanan['tanggal_expired']) < time();
                            $status_class = 'background: rgba(255,255,255,0.2);';
                            $status_text = '⏳ Pending';

                            if ($pesanan['status_pembayaran'] == 'paid') {
                                $status_class = 'background: #28a745;';
                                $status_text = '✓ Lunas';
                            } elseif ($pesanan['konfirmasi_konsumen'] == 'sudah' && $pesanan['status_pembayaran'] == 'pending') {
                                $status_class = 'background: #ffc107;';
                                $status_text = '⏱️ Menunggu Verifikasi Admin';
                            } elseif ($is_expired) {
                                $status_class = 'background: #dc3545;';
                                $status_text = '✗ Expired';
                            }
                            ?>
                            <span style="padding: 5px 12px; border-radius: 20px; font-size: 12px; <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Bank <?php echo $pesanan['bank']; ?> - Transfer Manual</div>
                            <div style="font-size: 20px; font-weight: bold; letter-spacing: 2px; font-family: 'Courier New', monospace; margin-bottom: 8px;">
                                <?php echo $pesanan['nomor_rekening']; ?>
                            </div>
                            <div style="font-size: 14px; margin-bottom: 8px;">a.n. <?php echo htmlspecialchars($pesanan['nama_penerima']); ?></div>
                            <button onclick="copyVA('<?php echo $pesanan['nomor_rekening']; ?>', this)" style="background: white; color: #667eea; border: none; padding: 6px 12px; border-radius: 5px; font-size: 12px; cursor: pointer; font-weight: 600;">
                                📋 Salin Nomor
                            </button>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 12px; opacity: 0.9;">Total Pembayaran</div>
                                <div style="font-size: 22px; font-weight: bold;">Rp <?php echo number_format($pesanan['jumlah_va'], 0, ',', '.'); ?></div>
                            </div>
                            <?php if ($pesanan['status_pembayaran'] == 'pending' && !$is_va_expired): ?>
                            <div style="text-align: right;">
                                <div style="font-size: 11px; opacity: 0.9;">Bayar sebelum</div>
                                <div style="font-size: 13px; font-weight: 600;">
                                    <?php echo date('d M Y H:i', strtotime($pesanan['tanggal_expired'])); ?>
                                </div>
                            </div>
                            <?php elseif ($pesanan['status_pembayaran'] == 'paid'): ?>
                            <div style="text-align: right;">
                                <div style="font-size: 11px; opacity: 0.9;">Dibayar pada</div>
                                <div style="font-size: 13px; font-weight: 600;">
                                    <?php echo date('d M Y H:i', strtotime($pesanan['tanggal_bayar'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tombol Konfirmasi Pembayaran -->
                    <?php if ($pesanan['nomor_rekening'] && $pesanan['status_pembayaran'] == 'pending' && !$is_va_expired && $pesanan['konfirmasi_konsumen'] != 'sudah'): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <div style="font-weight: 600; color: #856404; margin-bottom: 8px;">⚠️ Sudah transfer?</div>
                            <div style="color: #856404; font-size: 14px; margin-bottom: 12px;">
                                Klik tombol "Sudah Bayar" setelah Anda melakukan transfer. Admin akan memverifikasi pembayaran Anda.
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <form method="POST" action="konfirmasi-bayar.php" style="display: inline;" onsubmit="return confirm('Apakah Anda sudah melakukan pembayaran?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" class="btn" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                        ✓ Sudah Bayar
                                    </button>
                                </form>
                                <form method="POST" action="konfirmasi-bayar.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                        ✗ Batalkan
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($pesanan['konfirmasi_konsumen'] == 'sudah' && $pesanan['status_pembayaran'] == 'pending'): ?>
                        <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <div style="font-weight: 600; color: #0c5460; margin-bottom: 5px;">⏱️ Menunggu Verifikasi</div>
                            <div style="color: #0c5460; font-size: 14px;">
                                Konfirmasi pembayaran Anda sudah dikirim pada <?php echo date('d M Y H:i', strtotime($pesanan['tanggal_konfirmasi_konsumen'])); ?>. Menunggu admin memverifikasi pembayaran.
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <?php if ($pesanan['status'] == 'pending' && (!$pesanan['nomor_rekening'] || $pesanan['konfirmasi_konsumen'] == 'sudah')): ?>
                            <form method="POST" action="batalkan_pesanan.php" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                <input type="hidden" name="id_pesanan" value="<?php echo $pesanan['id_pesanan']; ?>">
                                <button type="submit" class="btn" style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer;">
                                    Batalkan
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($pesanan['total_biaya'] > 0): ?>
                            <?php if (!$payment || $payment['status'] != 'paid'): ?>
                                <a href="pay.php?id=<?php echo $pesanan['id_pesanan']; ?>" class="btn" style="background: #28a745; color: white; padding: 8px 15px; border: none; border-radius: 5px; text-decoration: none; display: inline-flex; align-items: center;">🔖 Bayar via Virtual Account</a>
                            <?php else: ?>
                                <span class="btn" style="background: #6c757d; color: white; padding: 8px 15px; border: none; border-radius: 5px;">✓ Sudah Dibayar</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="detail-pesanan.php?id=<?php echo $pesanan['id_pesanan']; ?>" class="btn" style="background: #667eea; color: white; padding: 8px 15px; border: none; border-radius: 5px; text-decoration: none;">Detail</a>
                    </div>

                    <?php if ($pesanan['status'] == 'selesai' && !$has_review): ?>
                        <div style="margin-top: 15px; background: #fff; padding: 15px; border-radius: 10px;">
                            <form method="POST" action="submit_review.php">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="booking_id" value="<?php echo $pesanan['id_pesanan']; ?>">
                                <input type="hidden" name="tukang_id" value="<?php echo $pesanan['id_tukang']; ?>">
                                <label style="font-weight:600;">Beri rating dan ulasan untuk tukang:</label>
                                <div style="margin:10px 0; display:flex; gap:10px; align-items:center;">
                                    <select name="rating" required style="padding:8px;">
                                        <option value="">Pilih rating</option>
                                        <option value="5">5 — Sangat Baik</option>
                                        <option value="4">4 — Baik</option>
                                        <option value="3">3 — Cukup</option>
                                        <option value="2">2 — Kurang</option>
                                        <option value="1">1 — Buruk</option>
                                    </select>
                                    <button type="submit" class="btn" style="background:#007bff;color:white;padding:8px 12px;border-radius:6px;border:none;">Kirim Ulasan</button>
                                </div>
                                <textarea name="review" rows="3" placeholder="Tulis ulasan (opsional)" style="width:100%;padding:10px;border-radius:6px;border:1px solid #e0e0e0"></textarea>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📋</div>
                <h2>Belum Ada Pesanan</h2>
                <p style="color: #666; margin: 10px 0;">Anda belum pernah memesan tukang</p>
                <a href="cari-tukang.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">
                    🔍 Cari Tukang Sekarang
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyVA(vaNumber, btn) {
            // Coba copy menggunakan clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(vaNumber).then(() => {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✓ Tersalin!';
                    btn.style.background = '#4caf50';
                    btn.style.color = 'white';

                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = 'white';
                        btn.style.color = '#667eea';
                    }, 2000);
                }).catch(err => {
                    // Fallback jika gagal
                    fallbackCopyVA(vaNumber, btn);
                });
            } else {
                // Fallback untuk browser lama
                fallbackCopyVA(vaNumber, btn);
            }
        }

        function fallbackCopyVA(text, btn) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                const originalText = btn.innerHTML;
                btn.innerHTML = '✓ Tersalin!';
                btn.style.background = '#4caf50';
                btn.style.color = 'white';

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = 'white';
                    btn.style.color = '#667eea';
                }, 2000);
            } catch (err) {
                alert('Gagal menyalin. Silakan salin manual: ' + text);
            }

            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>