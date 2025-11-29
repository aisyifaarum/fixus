<?php
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'konsumen') {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$lokasi_filter = isset($_GET['lokasi']) ? $_GET['lokasi'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query
$query = "SELECT * FROM tukang WHERE status_aktif = 'aktif'";

if ($kategori_filter) {
    $query .= " AND kategori = '" . mysqli_real_escape_string($conn, $kategori_filter) . "'";
}

if ($lokasi_filter) {
    $query .= " AND lokasi LIKE '%" . mysqli_real_escape_string($conn, $lokasi_filter) . "%'";
}

if ($search) {
    $query .= " AND (nama LIKE '%$search%' OR keahlian LIKE '%$search%' OR deskripsi LIKE '%$search%')";
}

$query .= " ORDER BY rating_avg DESC, jumlah_pesanan DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Tukang - Fix Us</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-box {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            width: 100%;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-btn:hover {
            background: #5568d3;
            transform: scale(1.05);
        }
        
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .tukang-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .tukang-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .tukang-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }
        
        .tukang-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .tukang-avatar {
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
        
        .tukang-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 18px;
        }
        
        .tukang-kategori {
            display: inline-block;
            padding: 4px 12px;
            background: #e3f2fd;
            color: #667eea;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .tukang-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #666;
        }
        
        .rating {
            color: #ffd700;
            font-weight: bold;
        }
        
        .tukang-desc {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .tukang-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .harga {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .pesan-btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .pesan-btn:hover {
            background: #5568d3;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .no-results img {
            width: 200px;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .lokasi-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-welcome">
                <h1>🔍 Cari Tukang</h1>
                <p>Temukan tukang terbaik untuk kebutuhan Anda</p>
            </div>
            <a href="dashboard.php" class="logout-btn" style="background: #667eea;">← Dashboard</a>
        </div>

        <!-- Search & Filter -->
        <div class="search-container">
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Cari tukang berdasarkan nama atau keahlian..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">🔍 Cari</button>
                </div>
                
                <div class="filters">
                    <div class="filter-group">
                        <label>Kategori</label>
                        <select name="kategori" onchange="this.form.submit()">
                            <option value="">Semua Kategori</option>
                            <option value="listrik" <?php echo $kategori_filter == 'listrik' ? 'selected' : ''; ?>>⚡ Listrik</option>
                            <option value="ac" <?php echo $kategori_filter == 'ac' ? 'selected' : ''; ?>>❄️ AC & Kulkas</option>
                            <option value="elektronik" <?php echo $kategori_filter == 'elektronik' ? 'selected' : ''; ?>>📺 Elektronik</option>
                            <option value="pipa" <?php echo $kategori_filter == 'pipa' ? 'selected' : ''; ?>>🚰 Pipa</option>
                            <option value="furniture" <?php echo $kategori_filter == 'furniture' ? 'selected' : ''; ?>>🪑 Furniture</option>
                            <option value="lainnya" <?php echo $kategori_filter == 'lainnya' ? 'selected' : ''; ?>>🔧 Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Lokasi</label>
                        <select name="lokasi" onchange="this.form.submit()">
                            <option value="">Semua Lokasi</option>
                            <option value="Jambi Kota" <?php echo $lokasi_filter == 'Jambi Kota' ? 'selected' : ''; ?>>Jambi Kota</option>
                            <option value="Jelutung" <?php echo $lokasi_filter == 'Jelutung' ? 'selected' : ''; ?>>Jelutung</option>
                            <option value="Paal Merah" <?php echo $lokasi_filter == 'Paal Merah' ? 'selected' : ''; ?>>Paal Merah</option>
                            <option value="Telanaipura" <?php echo $lokasi_filter == 'Telanaipura' ? 'selected' : ''; ?>>Telanaipura</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results -->
        <?php if ($result->num_rows > 0): ?>
            <div style="margin-bottom: 20px; color: #666;">
                <strong><?php echo $result->num_rows; ?></strong> tukang ditemukan
            </div>
            
            <div class="tukang-grid">
                <?php while ($tukang = $result->fetch_assoc()): ?>
                    <div class="tukang-card" onclick="window.location='profil-tukang.php?id=<?php echo $tukang['id_tukang']; ?>'">
                        <div class="tukang-header">
                            <div class="tukang-avatar">
                                <?php echo strtoupper(substr($tukang['nama'], 0, 1)); ?>
                            </div>
                            <div class="tukang-info">
                                <h3><?php echo htmlspecialchars($tukang['nama']); ?></h3>
                                <span class="tukang-kategori"><?php echo $tukang['kategori']; ?></span>
                            </div>
                        </div>
                        
                        <div class="lokasi-badge">
                            📍 <?php echo htmlspecialchars($tukang['lokasi']); ?>
                        </div>
                        
                        <div class="tukang-stats">
                            <div class="stat-item">
                                <span class="rating">⭐ <?php echo number_format($tukang['rating_avg'], 1); ?></span>
                            </div>
                            <div class="stat-item">
                                📋 <?php echo $tukang['jumlah_pesanan']; ?> pesanan
                            </div>
                        </div>
                        
                        <div class="tukang-desc">
                            <?php echo htmlspecialchars(substr($tukang['deskripsi'], 0, 100)); ?>...
                        </div>
                        
                        <div class="tukang-footer">
                            <div class="harga">
                                Rp <?php echo number_format($tukang['harga_per_jam'], 0, ',', '.'); ?>/jam
                            </div>
                            <a href="pesan-tukang.php?id=<?php echo $tukang['id_tukang']; ?>" 
                               class="pesan-btn" onclick="event.stopPropagation()">
                                Pesan
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div style="font-size: 64px; margin-bottom: 20px;">🔍</div>
                <h2>Tidak ada tukang ditemukan</h2>
                <p style="color: #666; margin: 10px 0;">Coba ubah filter atau kata kunci pencarian Anda</p>
                <a href="cari-tukang.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">Reset Filter</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>