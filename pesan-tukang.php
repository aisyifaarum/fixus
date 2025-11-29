<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

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

// Get konsumen data
$id_konsumen = $_SESSION['user_id'];
$konsumen_stmt = $conn->prepare("SELECT * FROM konsumen WHERE id_konsumen = ?");
$konsumen_stmt->bind_param("i", $id_konsumen);
$konsumen_stmt->execute();
$konsumen = $konsumen_stmt->get_result()->fetch_assoc();

// Process form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $waktu = mysqli_real_escape_string($conn, $_POST['waktu']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan']);
    
    if (empty($kategori) || empty($deskripsi) || empty($alamat) || empty($tanggal) || empty($waktu)) {
        $error = 'Semua field wajib diisi!';
    } else {
        $stmt = $conn->prepare("INSERT INTO pesanan (id_konsumen, id_tukang, kategori, deskripsi_masalah, alamat_pengerjaan, tanggal_pengerjaan, waktu_pengerjaan, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssss", $id_konsumen, $id_tukang, $kategori, $deskripsi, $alamat, $tanggal, $waktu, $catatan);
        
        if ($stmt->execute()) {
            $id_pesanan = $stmt->insert_id;

            // Send notification to tukang
            $title = 'Pesanan Baru';
            $message = 'Anda mendapat pesanan baru #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' untuk ' . htmlspecialchars($kategori) . '.';
            $link = 'pesanan-masuk.php';
            $user_type = 'tukang';

            $stmt_notif = $conn->prepare("INSERT INTO notification_log (user_id, user_type, title, message, link) VALUES (?, ?, ?, ?, ?)");
            $stmt_notif->bind_param("issss", $id_tukang, $user_type, $title, $message, $link);
            $stmt_notif->execute();
            $stmt_notif->close();

            // Redirect ke halaman pesanan-saya (bukan payment!)
            header('Location: pesanan-saya.php?msg=booked');
            exit();
        } else {
            $error = 'Terjadi kesalahan saat membuat pesanan!';
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
    <title>Pesan <?php echo htmlspecialchars($tukang['nama']); ?> - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pesan-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .pesan-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .tukang-preview {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .preview-avatar {
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
        
        .preview-info {
            flex: 1;
        }
        
        .preview-info h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .preview-rating {
            color: #ffd700;
            font-size: 14px;
        }
        
        .preview-price {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        
        .info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="pesan-container">
            <div class="pesan-card">
                <h1 style="margin-bottom: 30px;">📋 Form Pemesanan</h1>
                
                <!-- Tukang Preview -->
                <div class="tukang-preview">
                    <div class="preview-avatar">
                        <?php echo strtoupper(substr($tukang['nama'], 0, 1)); ?>
                    </div>
                    <div class="preview-info">
                        <h3><?php echo htmlspecialchars($tukang['nama']); ?></h3>
                        <div class="preview-rating">
                            ⭐ <?php echo number_format($tukang['rating_avg'], 1); ?> • 
                            <?php echo $tukang['jumlah_pesanan']; ?> pesanan
                        </div>
                        <div style="color: #666; font-size: 14px; margin-top: 5px;">
                            <?php echo htmlspecialchars($tukang['keahlian']); ?>
                        </div>
                    </div>
                    <div class="preview-price">
                        Rp <?php echo number_format($tukang['harga_per_jam'], 0, ',', '.'); ?>/jam
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <!-- Informasi Masalah -->
                    <div class="form-section">
                        <h3 class="section-title">🔧 Informasi Masalah</h3>
                        
                        <div class="form-group">
                            <label>Kategori Masalah</label>
                            <div class="input-group">
                                <select name="kategori" required>
                                    <option value="">Pilih kategori</option>
                                    <option value="listrik">⚡ Listrik (Instalasi, Konsleting, MCB)</option>
                                    <option value="ac">❄️ AC & Kulkas (Service, Isi Freon)</option>
                                    <option value="elektronik">📺 Elektronik (TV, Kipas, Magic Com)</option>
                                    <option value="pipa">🚰 Pipa (Bocor, Kran Rusak, Toilet)</option>
                                    <option value="furniture">🪑 Furniture (Lemari, Meja, Kursi)</option>
                                    <option value="lainnya">🔧 Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi Masalah</label>
                            <div class="input-group">
                                <textarea name="deskripsi" rows="5" placeholder="Jelaskan masalah secara detail. Contoh: Kipas angin tidak mau berputar, lampu indikator mati, sudah dicoba ganti stop kontak tetap tidak berfungsi." required></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Jadwal & Lokasi -->
                    <div class="form-section">
                        <h3 class="section-title">📅 Jadwal & Lokasi</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tanggal Pengerjaan</label>
                                <div class="input-group">
                                    <input type="date" name="tanggal" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Waktu Pengerjaan</label>
                                <div class="input-group">
                                    <input type="time" name="waktu" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Alamat Pengerjaan</label>
                            <div class="input-group">
                                <textarea name="alamat" rows="3" placeholder="Masukkan alamat lengkap tempat pengerjaan" required><?php echo htmlspecialchars($konsumen['alamat']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="form-section">
                        <h3 class="section-title">📝 Catatan Tambahan (Opsional)</h3>
                        
                        <div class="form-group">
                            <div class="input-group">
                                <textarea name="catatan" rows="3" placeholder="Catatan tambahan untuk tukang (opsional). Contoh: Mohon bawa peralatan sendiri, akses lewat pintu belakang, dll."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="info-box">
                        <strong>💡 Informasi:</strong><br>
                        • Pesanan akan menunggu konfirmasi dari tukang<br>
                        • Biaya final akan dihitung setelah pekerjaan selesai<br>
                        • Anda bisa membatalkan pesanan sebelum dikonfirmasi<br>
                        • Pembayaran dilakukan setelah pekerjaan selesai
                    </div>

                    <div class="action-buttons" style="display: flex; gap: 15px; margin-top: 30px;">
                        <a href="profil-tukang.php?id=<?php echo $tukang['id_tukang']; ?>" class="btn-back" style="flex: 1; padding: 15px; text-align: center; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 10px; font-weight: 600;">
                            ← Kembali
                        </a>
                        <button type="submit" class="submit-btn" style="flex: 2;">
                            ✅ Buat Pesanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>