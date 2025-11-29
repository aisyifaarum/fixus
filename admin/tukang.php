<?php
require_once 'config.php';
checkAdminLogin();

$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM tukang WHERE id_tukang = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Tukang berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus tukang!';
    }
    $stmt->close();
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE tukang SET status_aktif = IF(status_aktif = 'aktif', 'nonaktif', 'aktif') WHERE id_tukang = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Status tukang berhasil diubah!';
    }
    $stmt->close();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_telepon = trim($_POST['no_telepon']);
    $alamat = trim($_POST['alamat']);
    $keahlian = trim($_POST['keahlian']);
    $harga_per_jam = floatval($_POST['harga_per_jam']);
    $kategori = $_POST['kategori'];
    $lokasi = trim($_POST['lokasi']);
    $deskripsi = trim($_POST['deskripsi']);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        // Update
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE tukang SET nama = ?, email = ?, password = ?, no_telepon = ?, alamat = ?, keahlian = ?, harga_per_jam = ?, kategori = ?, lokasi = ?, deskripsi = ? WHERE id_tukang = ?");
            $stmt->bind_param("ssssssdsssi", $nama, $email, $password, $no_telepon, $alamat, $keahlian, $harga_per_jam, $kategori, $lokasi, $deskripsi, $id);
        } else {
            $stmt = $conn->prepare("UPDATE tukang SET nama = ?, email = ?, no_telepon = ?, alamat = ?, keahlian = ?, harga_per_jam = ?, kategori = ?, lokasi = ?, deskripsi = ? WHERE id_tukang = ?");
            $stmt->bind_param("sssssdsssi", $nama, $email, $no_telepon, $alamat, $keahlian, $harga_per_jam, $kategori, $lokasi, $deskripsi, $id);
        }
        if ($stmt->execute()) {
            $message = 'Data tukang berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate data!';
        }
    } else {
        // Insert
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO tukang (nama, email, password, no_telepon, alamat, keahlian, harga_per_jam, kategori, lokasi, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdsss", $nama, $email, $password, $no_telepon, $alamat, $keahlian, $harga_per_jam, $kategori, $lokasi, $deskripsi);
        if ($stmt->execute()) {
            $message = 'Tukang baru berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambahkan tukang! Email mungkin sudah terdaftar.';
        }
    }
    $stmt->close();
    }
}

// Get all tukang
$result = $conn->query("SELECT * FROM tukang ORDER BY tanggal_daftar DESC");
$tukang_list = [];
while ($row = $result->fetch_assoc()) {
    $tukang_list[] = $row;
}

// Get edit data
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM tukang WHERE id_tukang = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tukang - Fix Us Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; overflow-y: auto; }
        .sidebar-header { color: white; text-align: center; padding: 20px 0; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 24px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: block; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 8px; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header h1 { color: #333; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #eee; font-weight: 600; color: #333; }
        .card-body { padding: 20px; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; background: #f8f9fa; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #333; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-aktif { background: #d4edda; color: #155724; }
        .badge-nonaktif { background: #f8d7da; color: #721c24; }
        .actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .rating { color: #ffc107; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Fix Us Admin</h2></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="konsumen.php">Kelola Konsumen</a></li>
            <li><a href="tukang.php" class="active">Kelola Tukang</a></li>
            <li><a href="pesanan.php">Daftar Pesanan</a></li>
            <li><a href="konfirmasi-va.php">Konfirmasi VA</a></li>
            <li><a href="kategori.php">Kategori Layanan</a></li>
            <li><a href="laporan.php">Laporan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Kelola Tukang</h1>
            <button class="btn btn-primary" onclick="openModal()">+ Tambah Tukang</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Daftar Tukang (<?php echo count($tukang_list); ?>)</div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Kategori</th>
                            <th>Keahlian</th>
                            <th>Harga/Jam</th>
                            <th>Rating</th>
                            <th>Pesanan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tukang_list)): ?>
                            <tr><td colspan="10" style="text-align: center; color: #666;">Belum ada data tukang</td></tr>
                        <?php else: ?>
                            <?php foreach ($tukang_list as $tukang): ?>
                                <tr>
                                    <td><?php echo $tukang['id_tukang']; ?></td>
                                    <td><?php echo htmlspecialchars($tukang['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($tukang['email']); ?></td>
                                    <td><?php echo ucfirst($tukang['kategori'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($tukang['keahlian'] ?? '-', 0, 20)); ?></td>
                                    <td><?php echo formatRupiah($tukang['harga_per_jam']); ?></td>
                                    <td class="rating"><?php echo number_format($tukang['rating_avg'], 1); ?> ⭐</td>
                                    <td><?php echo $tukang['jumlah_pesanan']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $tukang['status_aktif']; ?>">
                                            <?php echo ucfirst($tukang['status_aktif']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $tukang['id_tukang']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="?toggle=<?php echo $tukang['id_tukang']; ?>" class="btn btn-<?php echo $tukang['status_aktif'] == 'aktif' ? 'secondary' : 'success'; ?> btn-sm">
                                            <?php echo $tukang['status_aktif'] == 'aktif' ? 'Nonaktif' : 'Aktif'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $tukang['id_tukang']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus tukang ini?')">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal <?php echo $edit_data ? 'show' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo $edit_data ? 'Edit Tukang' : 'Tambah Tukang Baru'; ?></h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id_tukang']; ?>">
                <?php endif; ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" name="nama" required value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password <?php echo $edit_data ? '(kosongkan jika tidak diubah)' : ''; ?></label>
                    <input type="password" name="password" <?php echo !$edit_data ? 'required' : ''; ?>>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="no_telepon" value="<?php echo htmlspecialchars($edit_data['no_telepon'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori">
                            <option value="listrik" <?php echo ($edit_data['kategori'] ?? '') == 'listrik' ? 'selected' : ''; ?>>Listrik</option>
                            <option value="ac" <?php echo ($edit_data['kategori'] ?? '') == 'ac' ? 'selected' : ''; ?>>AC</option>
                            <option value="elektronik" <?php echo ($edit_data['kategori'] ?? '') == 'elektronik' ? 'selected' : ''; ?>>Elektronik</option>
                            <option value="pipa" <?php echo ($edit_data['kategori'] ?? '') == 'pipa' ? 'selected' : ''; ?>>Pipa/Ledeng</option>
                            <option value="furniture" <?php echo ($edit_data['kategori'] ?? '') == 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                            <option value="lainnya" <?php echo ($edit_data['kategori'] ?? '') == 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Keahlian</label>
                        <input type="text" name="keahlian" value="<?php echo htmlspecialchars($edit_data['keahlian'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Harga per Jam (Rp)</label>
                        <input type="number" name="harga_per_jam" value="<?php echo $edit_data['harga_per_jam'] ?? '50000'; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="lokasi" value="<?php echo htmlspecialchars($edit_data['lokasi'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" rows="2"><?php echo htmlspecialchars($edit_data['alamat'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" rows="3"><?php echo htmlspecialchars($edit_data['deskripsi'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?php echo $edit_data ? 'Update' : 'Simpan'; ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('modal').classList.add('show'); }
        function closeModal() {
            document.getElementById('modal').classList.remove('show');
            window.location.href = 'tukang.php';
        }
    </script>
</body>
</html>
