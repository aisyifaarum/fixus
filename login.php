<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'registered') {
        $success = 'Registrasi berhasil! Silakan login.';
    } elseif ($_GET['msg'] == 'reset_success') {
        $success = 'Password berhasil diubah! Silakan login.';
    } elseif ($_GET['msg'] == 'logout') {
        $success = 'Anda telah logout.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        $remember = isset($_POST['remember_me']) ? true : false;

        if (empty($email) || empty($password)) {
            $error = 'Email dan password harus diisi!';
    } else {
        $table = ($user_type == 'konsumen') ? 'konsumen' : 'tukang';
        $id_field = ($user_type == 'konsumen') ? 'id_konsumen' : 'id_tukang';
        
        $stmt = $conn->prepare("SELECT $id_field, nama, email, password FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set Session
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user[$id_field];
                $_SESSION['user_name'] = $user['nama'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user_type;
                
                // Remember Me functionality
                if ($remember) {
                    // Generate token
                    $token = bin2hex(random_bytes(32));
                    $user_id = $user[$id_field];
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Hapus token lama jika ada
                    $stmt_delete = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND user_type = ?");
                    $stmt_delete->bind_param("is", $user_id, $user_type);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                    
                    // Insert token baru ke database
                    $stmt_insert = $conn->prepare("INSERT INTO remember_tokens (user_id, user_type, token, expiry) VALUES (?, ?, ?, ?)");
                    $stmt_insert->bind_param("isss", $user_id, $user_type, $token, $expiry);
                    
                    if ($stmt_insert->execute()) {
                        // Set cookie dengan parameter lengkap
                        $cookie_set = setcookie(
                            'remember_token',      // name
                            $token,                // value
                            time() + (30 * 24 * 60 * 60), // expire: 30 hari
                            '/',                   // path
                            '',                    // domain (kosong = current domain)
                            false,                 // secure (false untuk localhost)
                            true                   // httponly (true untuk keamanan)
                        );
                    } else {
                        error_log("REMEMBER ME: Gagal insert token ke database");
                    }
                    $stmt_insert->close();
                }
                
                $stmt->close();
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Password salah!';
            }
        } else {
            $error = 'Email tidak ditemukan!';
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fix Us</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <a href="index.php" class="back-link">← Kembali</a>
            
            <!-- Logo Mini -->
            <div style="text-align: center; margin-bottom: 20px;">
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width: 80px; height: 80px;">
                    <circle cx="100" cy="100" r="95" fill="url(#gradientLogin)" />
                    <defs>
                        <linearGradient id="gradientLogin" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <g transform="translate(100, 100)">
                        <path d="M -45,10 L 0,-35 L 45,10 L 45,50 L -45,50 Z" fill="white" opacity="0.95"/>
                        <g transform="rotate(-30)">
                            <rect x="-3" y="-55" width="6" height="50" rx="2" fill="white" stroke="#e0e0e0" stroke-width="2"/>
                        </g>
                        <g transform="rotate(30)">
                            <rect x="-15" y="-55" width="30" height="12" rx="2" fill="white" stroke="#e0e0e0" stroke-width="2"/>
                        </g>
                        <circle cx="0" cy="0" r="18" fill="#ffd700" stroke="#daa520" stroke-width="3"/>
                        <text x="0" y="7" font-family="Arial" font-size="20" font-weight="bold" fill="#667eea" text-anchor="middle">FU</text>
                    </g>
                </svg>
            </div>
            
            <div class="auth-header">
                <h2>Masuk ke Fix Us</h2>
                <p>Selamat datang kembali!</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

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
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder="Masukkan password Anda" required>
                        <span class="toggle-password" onclick="togglePassword()">👁️</span>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" id="remember_me_checkbox">
                        <span>Ingat saya (30 hari)</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Lupa Password?</a>
                </div>

                <button type="submit" class="submit-btn">Masuk</button>
            </form>

            <div class="auth-footer">
                Belum punya akun? <a href="register.php">Daftar sekarang</a>
            </div>
            
            </div>
        </div>
    </div>

    <script>
        function setUserType(type, btn) {
            document.getElementById('user_type').value = type;
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function togglePassword() {
            const passwordField = document.getElementById('password');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
            } else {
                passwordField.type = 'password';
            }
        }
    </script>
</body>
</html>