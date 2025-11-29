<?php
require_once 'config.php';

// Cek login sebagai tukang
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'tukang') {
    header('Location: login.php');
    exit();
}

$id_tukang = $_SESSION['user_id'];
$error = '';
$success = '';

// Get tukang data
$stmt = $conn->prepare("SELECT * FROM tukang WHERE id_tukang = ?");
$stmt->bind_param("i", $id_tukang);
$stmt->execute();
$tukang = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $keahlian = mysqli_real_escape_string($conn, $_POST['keahlian']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $lokasi = mysqli_real_escape_string($conn, $_POST['lokasi']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $harga_per_jam = floatval($_POST['harga_per_jam']);

    // Jadwal kerja dalam format JSON
    $jadwal = [
        'senin' => isset($_POST['senin']) ? $_POST['senin'] : [],
        'selasa' => isset($_POST['selasa']) ? $_POST['selasa'] : [],
        'rabu' => isset($_POST['rabu']) ? $_POST['rabu'] : [],
        'kamis' => isset($_POST['kamis']) ? $_POST['kamis'] : [],
        'jumat' => isset($_POST['jumat']) ? $_POST['jumat'] : [],
        'sabtu' => isset($_POST['sabtu']) ? $_POST['sabtu'] : [],
        'minggu' => isset($_POST['minggu']) ? $_POST['minggu'] : []
    ];
    $jadwal_kerja = json_encode($jadwal);

    if (empty($nama) || empty($no_telepon) || empty($keahlian)) {
        $error = 'Nama, no telepon, dan keahlian wajib diisi!';
    } else {
        $stmt = $conn->prepare("UPDATE tukang SET nama = ?, no_telepon = ?, alamat = ?, keahlian = ?, kategori = ?, lokasi = ?, deskripsi = ?, harga_per_jam = ?, jadwal_kerja = ? WHERE id_tukang = ?");
        $stmt->bind_param("sssssssdsi", $nama, $no_telepon, $alamat, $keahlian, $kategori, $lokasi, $deskripsi, $harga_per_jam, $jadwal_kerja, $id_tukang);

        if ($stmt->execute()) {
            $success = 'Profil berhasil diperbarui!';
            $_SESSION['user_name'] = $nama; // Update session

            // Refresh data
            $stmt_refresh = $conn->prepare("SELECT * FROM tukang WHERE id_tukang = ?");
            $stmt_refresh->bind_param("i", $id_tukang);
            $stmt_refresh->execute();
            $tukang = $stmt_refresh->get_result()->fetch_assoc();
            $stmt_refresh->close();
        } else {
            $error = 'Terjadi kesalahan saat memperbarui profil!';
        }
        $stmt->close();
    }
}

// Parse jadwal kerja
$jadwal_data = !empty($tukang['jadwal_kerja']) ? json_decode($tukang['jadwal_kerja'], true) : [];
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
            max-width: 900px;
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
        }

        .profile-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        .jadwal-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .jadwal-day {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }

        .jadwal-day label {
            min-width: 80px;
            font-weight: 600;
            color: #333;
        }

        .jadwal-time {
            display: flex;
            gap: 10px;
            flex: 1;
        }

        .time-input {
            flex: 1;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            color: #5568d3;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <a href="dashboard.php" class="back-link">← Kembali ke Dashboard</a>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($tukang['nama'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($tukang['nama']); ?></h1>
                    <div class="profile-stats">
                        <span>⭐ <?php echo number_format($tukang['rating_avg'], 1); ?></span>
                        <span>📋 <?php echo $tukang['jumlah_pesanan']; ?> pesanan</span>
                        <span style="text-transform: capitalize;">🏷️ <?php echo htmlspecialchars($tukang['kategori']); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <h2 style="color: #333; margin-bottom: 20px;">📝 Informasi Dasar</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Lengkap <span style="color: red;">*</span></label>
                        <div class="input-group">
                            <input type="text" name="nama" value="<?php echo htmlspecialchars($tukang['nama']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>No Telepon <span style="color: red;">*</span></label>
                        <div class="input-group">
                            <input type="tel" name="no_telepon" value="<?php echo htmlspecialchars($tukang['no_telepon']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-group">
                            <input type="email" value="<?php echo htmlspecialchars($tukang['email']); ?>" disabled style="background: #f0f0f0;">
                        </div>
                        <small style="color: #666;">Email tidak dapat diubah</small>
                    </div>

                    <div class="form-group">
                        <label>Kategori Utama</label>
                        <div class="input-group">
                            <select name="kategori">
                                <option value="listrik" <?php echo $tukang['kategori'] == 'listrik' ? 'selected' : ''; ?>>⚡ Listrik</option>
                                <option value="ac" <?php echo $tukang['kategori'] == 'ac' ? 'selected' : ''; ?>>❄️ AC & Kulkas</option>
                                <option value="elektronik" <?php echo $tukang['kategori'] == 'elektronik' ? 'selected' : ''; ?>>📺 Elektronik</option>
                                <option value="pipa" <?php echo $tukang['kategori'] == 'pipa' ? 'selected' : ''; ?>>🚰 Pipa & Sanitasi</option>
                                <option value="furniture" <?php echo $tukang['kategori'] == 'furniture' ? 'selected' : ''; ?>>🪑 Furniture</option>
                                <option value="lainnya" <?php echo $tukang['kategori'] == 'lainnya' ? 'selected' : ''; ?>>🔧 Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Keahlian <span style="color: red;">*</span></label>
                        <div class="input-group">
                            <input type="text" name="keahlian" value="<?php echo htmlspecialchars($tukang['keahlian']); ?>" placeholder="Contoh: Listrik, AC, Elektronik" required>
                        </div>
                        <small style="color: #666;">Pisahkan dengan koma</small>
                    </div>

                    <div class="form-group">
                        <label>Tarif per Jam</label>
                        <div class="input-group">
                            <input type="number" name="harga_per_jam" value="<?php echo $tukang['harga_per_jam']; ?>" min="0" step="1000" placeholder="50000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Lokasi/Area Layanan</label>
                        <div class="input-group">
                            <input type="text" name="lokasi" value="<?php echo htmlspecialchars($tukang['lokasi']); ?>" placeholder="Contoh: Jakarta Selatan">
                        </div>
                    </div>

                    <div class="form-group form-grid-full">
                        <label>Alamat</label>
                        <div class="input-group">
                            <textarea name="alamat" rows="3" placeholder="Alamat lengkap"><?php echo htmlspecialchars($tukang['alamat']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group form-grid-full">
                        <label>Deskripsi Diri</label>
                        <div class="input-group">
                            <textarea name="deskripsi" rows="4" placeholder="Ceritakan pengalaman dan keahlian Anda..."><?php echo htmlspecialchars($tukang['deskripsi']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="jadwal-section">
                    <h2 style="color: #333; margin-bottom: 20px;">📅 Jadwal Kerja</h2>
                    <p style="color: #666; margin-bottom: 20px;">Atur jadwal ketersediaan Anda untuk setiap hari</p>

                    <?php
                    $hari = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu'];
                    foreach ($hari as $key => $label):
                        $is_available = isset($jadwal_data[$key]) && !empty($jadwal_data[$key]);
                        $jam_mulai = $is_available && isset($jadwal_data[$key]['mulai']) ? $jadwal_data[$key]['mulai'] : '08:00';
                        $jam_selesai = $is_available && isset($jadwal_data[$key]['selesai']) ? $jadwal_data[$key]['selesai'] : '17:00';
                    ?>
                    <div class="jadwal-day">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="<?php echo $key; ?>_aktif" class="day-toggle" data-day="<?php echo $key; ?>" <?php echo $is_available ? 'checked' : ''; ?>>
                            <label for="<?php echo $key; ?>_aktif" style="min-width: 80px;"><?php echo $label; ?></label>
                        </div>
                        <div class="jadwal-time" id="<?php echo $key; ?>_time" style="<?php echo !$is_available ? 'display: none;' : ''; ?>">
                            <div class="time-input">
                                <input type="time" name="<?php echo $key; ?>[mulai]" value="<?php echo $jam_mulai; ?>">
                            </div>
                            <span style="color: #666;">s/d</span>
                            <div class="time-input">
                                <input type="time" name="<?php echo $key; ?>[selesai]" value="<?php echo $jam_selesai; ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: center;">
                    <button type="submit" class="btn-save">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle jadwal kerja visibility
        document.querySelectorAll('.day-toggle').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const day = this.dataset.day;
                const timeSection = document.getElementById(day + '_time');
                if (this.checked) {
                    timeSection.style.display = 'flex';
                } else {
                    timeSection.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
