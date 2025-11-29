<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $user_type = $_POST['user_type'];
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password) || empty($no_telepon)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        $table = ($user_type == 'konsumen') ? 'konsumen' : 'tukang';
        
        // Cek email sudah terdaftar
        $stmt = $conn->prepare("SELECT email FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email sudah terdaftar!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($user_type == 'konsumen') {
                $stmt = $conn->prepare("INSERT INTO konsumen (nama, email, password, no_telepon, alamat) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nama, $email, $hashed_password, $no_telepon, $alamat);
            } else {
                $keahlian = mysqli_real_escape_string($conn, $_POST['keahlian']);
                $harga_per_jam = $_POST['harga_per_jam'];
                
                $stmt = $conn->prepare("INSERT INTO tukang (nama, email, password, no_telepon, alamat, keahlian, harga_per_jam) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssd", $nama, $email, $hashed_password, $no_telepon, $alamat, $keahlian, $harga_per_jam);
            }
            
            if ($stmt->execute()) {
                header('Location: login.php?msg=registered');
                exit();
            } else {
                $error = 'Terjadi kesalahan saat registrasi!';
            }
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
    <title>Registrasi - Fix Us</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <a href="index.php" class="back-link">← Kembali</a>
            
            <div class="auth-header">
                <h2>Daftar di Fix Us</h2>
                <p>Buat akun baru Anda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
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
                    <label>Nama Lengkap</label>
                    <div class="input-group">
                        <input type="text" name="nama" placeholder="Masukkan nama lengkap" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Masukkan email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder="Minimal 6 karakter" required>
                        <span class="toggle-password" onclick="togglePassword('password')">👁️</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <div class="input-group">
                        <input type="password" name="konfirmasi_password" id="konfirmasi_password" placeholder="Ulangi password" required>
                        <span class="toggle-password" onclick="togglePassword('konfirmasi_password')">👁️</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>No. Telepon</label>
                    <div class="input-group">
                        <input type="tel" name="no_telepon" placeholder="Contoh: 081234567890" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <div class="input-group">
                        <textarea name="alamat" placeholder="Masukkan alamat lengkap" required></textarea>
                    </div>
                </div>

                <!-- Extra fields untuk tukang -->
                <div id="tukangFields" class="tukang-fields">
                    <div class="form-group">
                        <label>Keahlian</label>
                        <div class="input-group">
                            <input type="text" name="keahlian" placeholder="Contoh: Listrik, Elektronik, AC">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Harga per Jam (Rp)</label>
                        <div class="input-group">
                            <input type="number" name="harga_per_jam" placeholder="Contoh: 50000" min="0">
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Daftar</button>
            </form>

            <div class="auth-footer">
                Sudah punya akun? <a href="login.php">Masuk sekarang</a>
            </div>
        </div>
    </div>

    <script>
        function setUserType(type, btn) {
            document.getElementById('user_type').value = type;
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Toggle tukang fields
            const tukangFields = document.getElementById('tukangFields');
            if (type === 'tukang') {
                tukangFields.classList.add('show');
                tukangFields.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            } else {
                tukangFields.classList.remove('show');
                tukangFields.querySelectorAll('input').forEach(input => {
                    input.required = false;
                });
            }
        }

        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
            } else {
                passwordField.type = 'password';
            }
        }
    </script>
</body>
</html>