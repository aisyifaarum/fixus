<?php
require_once 'config.php';
checkAdminLogin();

$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM kategori_layanan WHERE id_kategori = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Kategori berhasil dihapus!';
    } else {
        $error = 'Gagal menghapus kategori!';
    }
    $stmt->close();
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE kategori_layanan SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_kategori = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Status kategori berhasil diubah!';
    }
    $stmt->close();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
    $nama_kategori = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    $icon = trim($_POST['icon']);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE kategori_layanan SET nama_kategori = ?, deskripsi = ?, icon = ? WHERE id_kategori = ?");
        $stmt->bind_param("sssi", $nama_kategori, $deskripsi, $icon, $id);
        if ($stmt->execute()) {
            $message = 'Kategori berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate kategori!';
        }
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO kategori_layanan (nama_kategori, deskripsi, icon) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_kategori, $deskripsi, $icon);
        if ($stmt->execute()) {
            $message = 'Kategori baru berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambahkan kategori!';
        }
    }
    $stmt->close();
    }
}

// Get all kategori
$result = $conn->query("SELECT * FROM kategori_layanan ORDER BY id_kategori ASC");
$kategori_list = [];
while ($row = $result->fetch_assoc()) {
    $kategori_list[] = $row;
}

// Get edit data
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM kategori_layanan WHERE id_kategori = ?");
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
    <title>Kategori Layanan - Fix Us Admin</title>
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
        .card-body { padding: 20px; }
        .kategori-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .kategori-card { background: #f8f9fa; border-radius: 10px; padding: 20px; border: 2px solid transparent; transition: all 0.3s; }
        .kategori-card:hover { border-color: #667eea; }
        .kategori-card .icon { font-size: 40px; margin-bottom: 10px; }
        .kategori-card h3 { color: #333; margin-bottom: 5px; }
        .kategori-card p { color: #666; font-size: 14px; margin-bottom: 15px; }
        .kategori-card .actions { display: flex; gap: 8px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #333; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-aktif { background: #d4edda; color: #155724; }
        .badge-nonaktif { background: #f8d7da; color: #721c24; }
        .emoji-hint { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Fix Us Admin</h2></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="konsumen.php">Kelola Konsumen</a></li>
            <li><a href="tukang.php">Kelola Tukang</a></li>
            <li><a href="pesanan.php">Daftar Pesanan</a></li>
            <li><a href="konfirmasi-va.php">Konfirmasi VA</a></li>
            <li><a href="kategori.php" class="active">Kategori Layanan</a></li>
            <li><a href="laporan.php">Laporan</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Kategori Layanan</h1>
            <button class="btn btn-primary" onclick="openModal()">+ Tambah Kategori</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Daftar Kategori (<?php echo count($kategori_list); ?>)</div>
            <div class="card-body">
                <?php if (empty($kategori_list)): ?>
                    <p style="text-align: center; color: #666;">Belum ada kategori</p>
                <?php else: ?>
                    <div class="kategori-grid">
                        <?php foreach ($kategori_list as $kategori): ?>
                            <div class="kategori-card">
                                <div class="icon"><?php echo $kategori['icon']; ?></div>
                                <h3><?php echo htmlspecialchars($kategori['nama_kategori']); ?></h3>
                                <p><?php echo htmlspecialchars($kategori['deskripsi'] ?? 'Tidak ada deskripsi'); ?></p>
                                <span class="badge badge-<?php echo $kategori['status']; ?>" style="margin-bottom: 10px; display: inline-block;">
                                    <?php echo ucfirst($kategori['status']); ?>
                                </span>
                                <div class="actions">
                                    <a href="?edit=<?php echo $kategori['id_kategori']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="?toggle=<?php echo $kategori['id_kategori']; ?>" class="btn btn-<?php echo $kategori['status'] == 'aktif' ? 'secondary' : 'success'; ?> btn-sm">
                                        <?php echo $kategori['status'] == 'aktif' ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                    </a>
                                    <a href="?delete=<?php echo $kategori['id_kategori']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus kategori ini?')">Hapus</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal <?php echo $edit_data ? 'show' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?php echo $edit_data ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id_kategori']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input type="text" name="nama_kategori" required value="<?php echo htmlspecialchars($edit_data['nama_kategori'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Icon (Emoji)</label>
                    <input type="text" name="icon" value="<?php echo htmlspecialchars($edit_data['icon'] ?? '🔧'); ?>" maxlength="10">
                    <p class="emoji-hint">Contoh: ⚡ ❄️ 📺 🚿 🪑 🔧 🔨 💡 🔌</p>
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
            window.location.href = 'kategori.php';
        }
    </script>
</body>
</html>
