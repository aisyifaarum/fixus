<?php
require_once 'config.php';

// Cek login sebagai konsumen
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$id_konsumen = $_SESSION['user_id'];
$error = '';
$success = '';

// Get konsumen data
$stmt = $conn->prepare("SELECT * FROM konsumen WHERE id_konsumen = ?");
$stmt->bind_param("i", $id_konsumen);
$stmt->execute();
$konsumen = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
        $no_telepon = mysqli_real_escape_string($conn, trim($_POST['no_telepon']));
        $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));

        // Validation
        if (empty($nama) || empty($no_telepon) || empty($alamat)) {
            $error = 'Semua field wajib diisi!';
        } elseif (!preg_match('/^[0-9]{10,15}$/', $no_telepon)) {
            $error = 'Nomor telepon harus berupa angka 10-15 digit!';
        } else {
            $stmt = $conn->prepare("UPDATE konsumen SET nama = ?, no_telepon = ?, alamat = ? WHERE id_konsumen = ?");
            $stmt->bind_param("sssi", $nama, $no_telepon, $alamat, $id_konsumen);

            if ($stmt->execute()) {
                $success = 'Profil berhasil diperbarui!';
                $_SESSION['user_name'] = $nama; // Update session

                // Refresh data
                $stmt_refresh = $conn->prepare("SELECT * FROM konsumen WHERE id_konsumen = ?");
                $stmt_refresh->bind_param("i", $id_konsumen);
                $stmt_refresh->execute();
                $konsumen = $stmt_refresh->get_result()->fetch_assoc();
                $stmt_refresh->close();
            } else {
                $error = 'Terjadi kesalahan saat memperbarui profil!';
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
    <title>Profil Saya - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .profile-info h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 28px;
        }

        .profile-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
            color: #999;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: #764ba2;
            transform: translateX(-5px);
        }

        .info-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 15px;
            }

            .profile-card {
                padding: 25px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 36px;
            }

            .profile-info h1 {
                font-size: 24px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .info-badge {
                display: block;
                margin: 10px 0 0 0;
            }
        }

        @media (max-width: 480px) {
            .profile-card {
                padding: 20px;
            }

            .profile-info h1 {
                font-size: 20px;
            }

            .form-group input,
            .form-group textarea {
                font-size: 14px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($konsumen['nama'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($konsumen['nama']); ?></h1>
                    <p>👤 Konsumen • 📧 <?php echo htmlspecialchars($konsumen['email']); ?></p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="email">
                        Email
                        <span class="info-badge">Tidak dapat diubah</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        value="<?php echo htmlspecialchars($konsumen['email']); ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label for="nama">Nama Lengkap *</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?php echo htmlspecialchars($konsumen['nama']); ?>"
                        required
                        maxlength="100"
                        placeholder="Masukkan nama lengkap Anda"
                    >
                </div>

                <div class="form-group">
                    <label for="no_telepon">Nomor Telepon *</label>
                    <input
                        type="tel"
                        id="no_telepon"
                        name="no_telepon"
                        value="<?php echo htmlspecialchars($konsumen['no_telepon']); ?>"
                        required
                        pattern="[0-9]{10,15}"
                        placeholder="Contoh: 081234567890"
                    >
                    <small style="color: #666; font-size: 12px;">Format: 10-15 digit angka</small>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat Lengkap *</label>
                    <textarea
                        id="alamat"
                        name="alamat"
                        required
                        placeholder="Masukkan alamat lengkap Anda"
                    ><?php echo htmlspecialchars($konsumen['alamat']); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Simpan Perubahan
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        ❌ Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
