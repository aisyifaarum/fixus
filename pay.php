<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_konsumen = $_SESSION['user_id'];

if ($id_pesanan == 0) {
    header('Location: pesanan-saya.php');
    exit();
}

// Get pesanan data dengan payment info
$stmt = $conn->prepare("
    SELECT p.*, t.nama as nama_tukang,
           va.nomor_rekening, va.bank, va.nama_penerima, va.jumlah as jumlah_va, va.status_pembayaran, va.tanggal_expired, va.tanggal_bayar
    FROM pesanan p
    JOIN tukang t ON p.id_tukang = t.id_tukang
    LEFT JOIN virtual_account va ON p.id_pesanan = va.id_pesanan
    WHERE p.id_pesanan = ? AND p.id_konsumen = ?
");
$stmt->bind_param("ii", $id_pesanan, $id_konsumen);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: pesanan-saya.php');
    exit();
}

$pesanan = $result->fetch_assoc();
$stmt->close();

// Redirect jika tidak ada nomor rekening atau belum ada total biaya
if (!$pesanan['nomor_rekening'] || $pesanan['total_biaya'] <= 0) {
    header('Location: pesanan-saya.php?msg=no_payment');
    exit();
}

// Redirect jika sudah dibayar
if ($pesanan['status_pembayaran'] == 'paid') {
    header('Location: pesanan-saya.php?msg=already_paid');
    exit();
}

// Check payment status via AJAX
if (isset($_GET['check_status']) && $_GET['check_status'] == '1') {
    header('Content-Type: application/json');

    $stmt_check = $conn->prepare("SELECT status_pembayaran FROM virtual_account WHERE id_pesanan = ?");
    $stmt_check->bind_param("i", $id_pesanan);
    $stmt_check->execute();
    $va_status = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    echo json_encode([
        'status' => $va_status['status_pembayaran'] == 'paid' ? 'paid' : 'pending'
    ]);
    exit();
}

// Check jika VA expired
$is_expired = strtotime($pesanan['tanggal_expired']) < time();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Virtual Account BRI - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }

        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .payment-header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .va-section {
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

        .va-number-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }

        .va-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .va-value {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
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

        .status-checking {
            text-align: center;
            padding: 20px;
            background: #d1ecf1;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd6;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed !important;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .expired-notice {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
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
    <div class="payment-container">
        <div class="payment-card">
            <div class="payment-header">
                <h1>💳 Pembayaran Virtual Account</h1>
                <p style="color: #666;">Pesanan #<?php echo str_pad($id_pesanan, 5, '0', STR_PAD_LEFT); ?></p>
                <p style="color: #999; font-size: 14px; margin-top: 5px;">
                    Tukang: <?php echo htmlspecialchars($pesanan['nama_tukang']); ?>
                </p>
            </div>

            <div id="payment-status" class="status-checking">
                <div class="spinner"></div>
                <p style="margin: 10px 0; color: #0c5460; font-weight: 600;">Memeriksa status pembayaran...</p>
            </div>

            <?php if ($is_expired): ?>
                <div class="expired-notice">
                    <h3 style="margin: 0 0 10px 0;">⏰ Virtual Account Expired</h3>
                    <p style="margin: 0;">Virtual Account ini sudah melewati batas waktu pembayaran.</p>
                    <p style="margin: 5px 0 0 0;">Silakan hubungi admin untuk bantuan lebih lanjut.</p>
                </div>
            <?php else: ?>
                <!-- Countdown Timer -->
                <div class="countdown">
                    <div class="countdown-label">⏰ Bayar Sebelum</div>
                    <div class="countdown-timer" id="countdown">
                        <?php echo date('d M Y H:i', strtotime($pesanan['tanggal_expired'])); ?> WIB
                    </div>
                </div>

                <!-- Bank Account Section -->
                <div class="va-section">
                    <div class="bank-logo">
                        <span>🏦</span>
                        <span>BANK <?php echo htmlspecialchars($pesanan['bank']); ?></span>
                    </div>

                    <div class="va-number-box">
                        <div class="va-label">Nomor Rekening</div>
                        <div class="va-value" id="vaNumber"><?php echo $pesanan['nomor_rekening']; ?></div>
                        <div class="va-label" style="margin-top: 15px;">Atas Nama</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 10px;"><?php echo htmlspecialchars($pesanan['nama_penerima']); ?></div>
                        <button class="copy-btn" onclick="copyVA(event)">📋 Salin Nomor</button>
                    </div>

                    <div class="amount-box">
                        <div class="amount-label">Total Pembayaran</div>
                        <div class="amount-value">Rp <?php echo number_format($pesanan['jumlah_va'], 0, ',', '.'); ?></div>
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
            <?php endif; ?>

            <div class="btn-container">
                <button type="button" id="checkBtn" class="btn btn-primary" onclick="manualCheckPayment()">✓ Cek Status Pembayaran</button>
                <a href="pesanan-saya.php" class="btn btn-secondary">← Kembali ke Pesanan</a>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <span style="font-size: 24px;">✓</span>
        <span>Nomor rekening berhasil disalin!</span>
    </div>

    <script>
        // Auto-check payment status every 5 seconds
        let statusCheckInterval = setInterval(checkPaymentStatus, 5000);

        function manualCheckPayment() {
            const btn = document.getElementById('checkBtn');
            const originalText = btn.innerHTML;

            // Disable button and show loading state
            btn.disabled = true;
            btn.innerHTML = '⏳ Memeriksa...';
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';

            // Call the check function
            checkPaymentStatus();

            // Re-enable button after 2 seconds
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }, 2000);
        }

        function checkPaymentStatus() {
            document.getElementById('payment-status').style.display = 'block';

            fetch('pay.php?id=<?php echo $id_pesanan; ?>&check_status=1')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'paid') {
                        clearInterval(statusCheckInterval);

                        // Show success message
                        document.getElementById('payment-status').innerHTML =
                            '<div style="color: #155724; font-size: 48px;">✓</div>' +
                            '<h3 style="color: #155724; margin: 10px 0;">Pembayaran Berhasil!</h3>' +
                            '<p style="color: #155724;">Terima kasih. Pembayaran Anda telah dikonfirmasi.</p>' +
                            '<p style="color: #666; margin-top: 10px;">Anda akan dialihkan ke halaman pesanan...</p>';

                        // Redirect after 3 seconds
                        setTimeout(function() {
                            window.location.href = 'pesanan-saya.php?msg=payment_success';
                        }, 3000);
                    } else {
                        document.getElementById('payment-status').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('payment-status').style.display = 'none';
                });
        }

        // Initial check
        checkPaymentStatus();

        function copyVA(event) {
            const vaNumber = document.getElementById('vaNumber').textContent;

            // Coba copy menggunakan clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(vaNumber).then(() => {
                    showToast();
                    updateButton(event);
                }).catch(err => {
                    // Fallback jika gagal
                    fallbackCopy(vaNumber, event);
                });
            } else {
                // Fallback untuk browser lama
                fallbackCopy(vaNumber, event);
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

        <?php if (!$is_expired): ?>
        // Countdown timer
        const expiredDate = new Date('<?php echo $pesanan['tanggal_expired']; ?>').getTime();

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
                clearInterval(statusCheckInterval);
                document.getElementById('countdown').innerHTML = 'EXPIRED';
                document.getElementById('countdown').style.color = '#dc3545';
                location.reload();
            }
        }, 1000);
        <?php endif; ?>
   </script>
</body>
</html>
