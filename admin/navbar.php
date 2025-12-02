<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">☰</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Fix Us Admin</h2>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" <?php echo $current_page == 'dashboard.php' ? 'class="active"' : ''; ?>>Dashboard</a></li>
        <li><a href="konsumen.php" <?php echo $current_page == 'konsumen.php' ? 'class="active"' : ''; ?>>Kelola Konsumen</a></li>
        <li><a href="tukang.php" <?php echo $current_page == 'tukang.php' ? 'class="active"' : ''; ?>>Kelola Tukang</a></li>
        <li><a href="pesanan.php" <?php echo $current_page == 'pesanan.php' ? 'class="active"' : ''; ?>>Daftar Pesanan</a></li>
        <li><a href="konfirmasi-va.php" <?php echo $current_page == 'konfirmasi-va.php' ? 'class="active"' : ''; ?>>Konfirmasi VA</a></li>
        <li><a href="delete_order.php" <?php echo $current_page == 'delete_order.php' ? 'class="active"' : ''; ?>>Hapus Pesanan</a></li>
        <li><a href="kategori.php" <?php echo $current_page == 'kategori.php' ? 'class="active"' : ''; ?>>Kategori Layanan</a></li>
        <li><a href="laporan.php" <?php echo $current_page == 'laporan.php' ? 'class="active"' : ''; ?>>Laporan</a></li>
        <li><a href="system_monitor.php" <?php echo $current_page == 'system_monitor.php' ? 'class="active"' : ''; ?>>System Monitor</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Close sidebar when menu item clicked on mobile
document.addEventListener('DOMContentLoaded', function() {
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    menuLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });
});
</script>
