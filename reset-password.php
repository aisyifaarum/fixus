<?php
require_once 'config.php';

$error = '';
$success = '';

// Cek apakah ada session reset
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_type'])) {
    $error = 'Session reset tidak ditemukan. Silakan mulai dari halaman lupa password.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $token = trim($_POST['token']); // Hapus spasi
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    if (empty($token) || empty($password) || empty($konfirmasi_password)) {
        $error = 'Semua field harus diisi!';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Ambil info dari session
        $email = $_SESSION['reset_email'];
        $user_type = $_SESSION['reset_user_type'];
        $table = ($user_type == 'konsumen') ? 'konsumen' : 'tukang';
        $id_field = ($user_type == 'konsumen') ? 'id_konsumen' : 'id_tukang';

        // Cek data di database
        $stmt = $conn->prepare("SELECT $id_field, email, reset_token, reset_token_expiry FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Validasi token dan expiry
            if ($token == $user['reset_token'] && strtotime($user['reset_token_expiry']) > time()) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password dan hapus token
                $stmt = $conn->prepare("UPDATE $table SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE $id_field = ?");
                $stmt->bind_param("si", $hashed_password, $user[$id_field]);
                
                if ($stmt->execute()) {
                    // Hapus session reset
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_user_type']);
                    unset($_SESSION['reset_token']);
                    
                    header('Location: login.php?msg=reset_success');
                    exit();
                } else {
                    $error = 'Gagal mengubah password. Silakan coba lagi.';
                }
            } else {
                if ($token != $user['reset_token']) {
                    $error = 'Kode reset tidak sesuai!';
                } elseif (strtotime($user['reset_token_expiry']) < time()) {
                    $error = 'Kode reset sudah kadaluarsa! Silakan minta kode baru.';
                } else {
                    $error = 'Kode reset tidak valid!';
                }
            }
        } else {
            $error = 'Data user tidak ditemukan!';
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
    <title>Reset Password - Fix Us</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <a href="forgot-password.php" class="back-link">← Minta Kode Baru</a>
            
            <div class="auth-header">
                <h2>Reset Password</h2>
                <p>Masukkan kode reset dan password baru Anda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['reset_email'])): ?>
                <div class="alert alert-info">
                    <strong>📧 Email:</strong> <?php echo $_SESSION['reset_email']; ?><br>
                    <strong>👤 Tipe:</strong> <?php echo ucfirst($_SESSION['reset_user_type']); ?><br>
                    <?php if (isset($_SESSION['reset_token'])): ?>
                        <strong>🔑 Kode Reset Anda:</strong> 
                        <div style="font-size: 32px; font-weight: bold; color: #0c5460; margin: 10px 0; letter-spacing: 5px;">
                            <?php echo $_SESSION['reset_token']; ?>
                        </div>
                        <small>Copy kode di atas ke form di bawah</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Kode Reset (6 Digit)</label>
                    <div class="input-group">
                        <input type="text" name="token" id="tokenInput" placeholder="Masukkan 6 digit kode" 
                               value="" 
                               maxlength="6" 
                               pattern="[0-9]{6}"
                               style="font-size: 24px; text-align: center; letter-spacing: 5px;"
                               required>
                    </div>
                    <small style="color: #666; font-size: 12px;">
                        💡 Salin kode 6 digit di atas
                    </small>
                    <?php if (isset($_SESSION['reset_token'])): ?>
                    <button type="button" onclick="copyToken()" style="margin-top: 10px; padding: 8px 15px; background: #17a2b8; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12px;">
                        📋 Copy Kode Otomatis
                    </button>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Password Baru</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder="Minimal 6 karakter" required>
                        <span class="toggle-password" onclick="togglePassword('password')">👁️</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <div class="input-group">
                        <input type="password" name="konfirmasi_password" id="konfirmasi_password" placeholder="Ulangi password baru" required>
                        <span class="toggle-password" onclick="togglePassword('konfirmasi_password')">👁️</span>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Reset Password</button>
            </form>

            <div class="auth-footer">
                Ingat password Anda? <a href="login.php">Login sekarang</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
            } else {
                passwordField.type = 'password';
            }
        }

        function copyToken() {
            <?php if (isset($_SESSION['reset_token'])): ?>
            const token = '<?php echo $_SESSION['reset_token']; ?>';
            document.getElementById('tokenInput').value = token;
            alert('✅ Kode berhasil di-copy ke form!\n\nKode: ' + token);
            <?php endif; ?>
        }
    </script>
</body>
</html>