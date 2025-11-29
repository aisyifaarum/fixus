<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_type = $_POST['user_type'];

    if (empty($email)) {
        $error = 'Email harus diisi!';
    } else {
        $table = ($user_type == 'konsumen') ? 'konsumen' : 'tukang';
        $id_field = ($user_type == 'konsumen') ? 'id_konsumen' : 'id_tukang';
        
        $stmt = $conn->prepare("SELECT $id_field, email FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token (6 digit angka)
            $token = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Update reset token di database
            $stmt = $conn->prepare("UPDATE $table SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expiry, $email);
            
            if ($stmt->execute()) {
                // Simpan info di session
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_type'] = $user_type;
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_token_time'] = time();
                
                // Verifikasi data tersimpan
                $stmt_check = $conn->prepare("SELECT reset_token, reset_token_expiry FROM $table WHERE email = ?");
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $check_result = $stmt_check->get_result();
                $check_data = $check_result->fetch_assoc();
                
                $success = 'Kode reset password berhasil dibuat!';
            } else {
                $error = 'Terjadi kesalahan saat menyimpan token. Silakan coba lagi.';
            }
        } else {
            $error = 'Email tidak ditemukan!';
        }
        $stmt->close();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .token-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .token-code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 10px;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .reset-btn {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .reset-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <a href="login.php" class="back-link">← Kembali ke Login</a>
            
            <div class="auth-header">
                <h2>Lupa Password</h2>
                <p>Masukkan email Anda untuk mendapatkan kode reset</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="token-display">
                    <h3 style="margin-bottom: 10px;">✅ Kode Reset Berhasil Dibuat!</h3>
                    <p style="font-size: 14px; margin-bottom: 10px;">Kode Reset Password Anda:</p>
                    <div class="token-code" id="tokenCode">
                        <?php echo $_SESSION['reset_token']; ?>
                    </div>
                    <button onclick="copyToClipboard()" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold;">
                        📋 Copy Kode
                    </button>
                    <br>
                    <a href="reset-password.php" class="reset-btn">
                        Reset Password Sekarang →
                    </a>
                    <div style="margin-top: 20px; font-size: 13px; opacity: 0.9;">
                        <p>📧 Email: <?php echo $_SESSION['reset_email']; ?></p>
                        <p>👤 Tipe: <?php echo ucfirst($_SESSION['reset_user_type']); ?></p>
                        <p>⏱️ Berlaku: 1 jam</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="user-type-toggle">
                    <button type="button" class="toggle-btn active" onclick="setUserType('konsumen', this)">
                        Konsumen
                    </button>
                    <button type="button" class="toggle-btn" onclick="setUserType('tukang', this)">
                        Tukang
                    </button>
                </div>
                
                <input type="hidden" name="user_type" id="user_type" value="konsumen">

                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Masukkan email Anda" required>
                    </div>
                    <small style="color: #666; font-size: 12px;">
                        💡 Pastikan email yang Anda masukkan sudah terdaftar
                    </small>
                </div>

                <button type="submit" class="submit-btn">Dapatkan Kode Reset</button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                Ingat password Anda? <a href="login.php">Login sekarang</a>
            </div>
        </div>
    </div>

    <script>
        function setUserType(type, btn) {
            document.getElementById('user_type').value = type;
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function copyToClipboard() {
            const token = document.getElementById('tokenCode').textContent.trim();
            
            // Cara 1: Menggunakan Clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(token).then(() => {
                    alert('✅ Kode berhasil di-copy!\n\nKode: ' + token + '\n\nSekarang buka halaman Reset Password dan paste kode ini.');
                });
            } else {
                // Cara 2: Fallback untuk browser lama
                const textarea = document.createElement('textarea');
                textarea.value = token;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ Kode berhasil di-copy!\n\nKode: ' + token);
            }
        }
    </script>
</body>
</html>