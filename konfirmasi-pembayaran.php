<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pesanan == 0) {
    header('Location: pesanan-saya.php');
    exit();
}

// Get pesanan dan payment data
$stmt = $conn->prepare("
    SELECT p.*, va.*, t.nama as nama_tukang, t.keahlian, k.nama as nama_konsumen
    FROM pesanan p
    LEFT JOIN virtual_account va ON p.id_pesanan = va.id_pesanan
    LEFT JOIN tukang t ON p.id_tukang = t.id_tukang
    LEFT JOIN konsumen k ON p.id_konsumen = k.id_konsumen
    WHERE p.id_pesanan = ? AND p.id_konsumen = ?
");
$stmt->bind_param("ii", $id_pesanan, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: pesanan-saya.php');
    exit();
}

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .success-icon {
            text-align: center;
            font-size: 80px;
            margin-bottom: 20px;
        }

        .success-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .success-subtitle {
            text-align: center;
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }

        .bank-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
        }

        .bank-logo {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .account-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .account-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .account-value {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
        }

        .account-name {
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
        }

        .copy-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .amount-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .amount-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .amount-value {
            font-size: 28px;
            font-weight: bold;
        }

        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-size: 14px;
        }

        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .countdown {
            text-align: center;
            padding: 20px;
            background: #fff3cd;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .countdown-label {
            font-size: 14px;
            color: #856404;
            margin-bottom: 5px;
        }

        .countdown-timer {
            font-size: 24px;
            font-weight: bold;
            color: #856404;
            font-family: 'Courier New', monospace;
        }

        .instruction-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .instruction-title {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .instruction-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .instruction-list li {
            padding: 10px 0;
            color: #333;
            font-size: 14px;
            line-height: 1.6;
        }

        .instruction-list li::before {
            content: "✓ ";
            color: #667eea;
            font-weight: bold;
            margin-right: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-secondary {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-primary {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4caf50;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            display: none;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.show {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="payment-container">
            <div class="payment-card">
                <div class="success-icon">✅</div>
                <h1 class="success-title">Pesanan Berhasil Dibuat!</h1>
                <p class="success-subtitle">Silakan transfer ke rekening di bawah ini</p>

                <!-- Countdown Timer -->
                <div class="countdown">
                    <div class="countdown-label">⏰ Bayar Sebelum</div>
                    <div class="countdown-timer" id="countdown">
                        <?php echo date('d M Y H:i', strtotime($data['tanggal_expired'])); ?> WIB
                    </div>
                </div>

                <!-- Bank Account Section -->
                <div class="bank-section">
                    <div class="bank-logo">
                        <span>🏦</span>
                        <span>BANK <?php echo htmlspecialchars($data['bank']); ?></span>
                    </div>

                    <div class="account-box">
                        <div class="account-label">Nomor Rekening</div>
                        <div class="account-value" id="accountNumber"><?php echo $data['nomor_rekening']; ?></div>
                        <button class="copy-btn" onclick="copyAccount(event)">📋 Salin Nomor</button>

                        <div class="account-label" style="margin-top: 15px;">Atas Nama</div>
                        <div class="account-name"><?php echo htmlspecialchars($data['nama_penerima']); ?></div>
                    </div>

                    <div class="amount-box">
                        <div class="amount-label">Total yang Harus Dibayar</div>
                        <div class="amount-value">Rp <?php echo number_format($data['jumlah'], 0, ',', '.'); ?></div>
                    </div>
                </div>

                <!-- Detail Pesanan -->
                <div class="info-section">
                    <h3 style="margin-bottom: 15px; color: #333;">📋 Detail Pesanan</h3>
                    <div class="info-row">
                        <span class="info-label">No. Pesanan</span>
                        <span class="info-value">#<?php echo str_pad($data['id_pesanan'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tukang</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['nama_tukang']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kategori</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['kategori']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Jadwal</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($data['tanggal_pengerjaan'])); ?> • <?php echo date('H:i', strtotime($data['waktu_pengerjaan'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value" style="color: #ffc107;">⏳ Menunggu Pembayaran</span>
                    </div>
                </div>

                <!-- Cara Pembayaran -->
                <div class="instruction-box">
                    <div class="instruction-title">💳 Cara Transfer (Semua Bank/E-Wallet)</div>
                    <ul class="instruction-list">
                        <li><strong>Mobile Banking:</strong> Buka aplikasi m-banking → Transfer → Antar Bank → Masukkan nomor rekening → Input nominal → Konfirmasi</li>
                        <li><strong>ATM:</strong> Pilih Transfer → Antar Bank → Masukkan kode bank + nomor rekening → Input nominal → Konfirmasi</li>
                        <li><strong>E-Wallet (GoPay/OVO/Dana):</strong> Transfer → Bank Transfer → Pilih bank tujuan → Masukkan nomor rekening → Bayar</li>
                        <li><strong>Internet Banking:</strong> Login → Transfer → Pilih rekening tujuan → Input nominal → Submit</li>
                    </ul>
                </div>

                <div class="instruction-box" style="background: #fff3cd; border-left-color: #ffc107;">
                    <strong style="color: #856404;">⚠️ Penting:</strong><br>
                    <span style="color: #856404;">
                    • Transfer HARUS sesuai dengan nominal yang tertera<br>
                    • Setelah transfer, konfirmasi akan diproses oleh admin<br>
                    • Simpan bukti transfer untuk keperluan konfirmasi<br>
                    • Jika sudah melewati batas waktu, hubungi admin
                    </span>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="pesanan-saya.php" class="btn-secondary">
                        📋 Lihat Pesanan Saya
                    </a>
                    <a href="cari-tukang.php" class="btn-primary">
                        🔍 Cari Tukang Lain
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <span style="font-size: 24px;">✓</span>
        <span>Nomor rekening berhasil disalin!</span>
    </div>

    <script>
        function copyAccount(event) {
            const accountNumber = document.getElementById('accountNumber').textContent;

            // Coba copy menggunakan clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(accountNumber).then(() => {
                    showToast();
                    updateButton(event);
                }).catch(err => {
                    // Fallback jika gagal
                    fallbackCopy(accountNumber, event);
                });
            } else {
                // Fallback untuk browser lama
                fallbackCopy(accountNumber, event);
            }
        }

        function fallbackCopy(text, event) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                showToast();
                updateButton(event);
            } catch (err) {
                alert('Gagal menyalin. Silakan salin manual: ' + text);
            }

            document.body.removeChild(textArea);
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function updateButton(event) {
            if (!event || !event.target) return;

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '✓ Tersalin!';
            btn.style.background = '#4caf50';
            btn.style.color = 'white';

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = 'white';
                btn.style.color = '#667eea';
            }, 2000);
        }

        // Countdown timer
        const expiredDate = new Date('<?php echo $data['tanggal_expired']; ?>').getTime();

        const countdown = setInterval(() => {
            const now = new Date().getTime();
            const distance = expiredDate - now;

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('countdown').innerHTML =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            if (distance < 0) {
                clearInterval(countdown);
                document.getElementById('countdown').innerHTML = 'EXPIRED';
                document.getElementById('countdown').style.color = '#dc3545';
            }
        }, 1000);
    </script>
</body>
</html>
