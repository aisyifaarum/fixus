<?php
require_once 'config.php';

// Admin check
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
        $delete_all = isset($_POST['delete_all']) && $_POST['delete_all'] === '1';

        if ($delete_all) {
            // Danger: delete all orders and payments
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM pembayaran");
                $conn->query("DELETE FROM virtual_account");
                $conn->query("DELETE FROM reviews");
                $conn->query("DELETE FROM pesanan");
                $conn->query("DELETE FROM notification_log WHERE related_id IS NOT NULL");
                $conn->commit();
                $success = 'Semua pesanan dan data pembayaran berhasil dihapus.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Gagal menghapus semua data: ' . $e->getMessage();
            }
        } elseif ($id_pesanan > 0) {
            // Delete by specific order id
            $conn->begin_transaction();
            try {
                // Remove reviews
                $stmt = $conn->prepare("DELETE FROM reviews WHERE booking_id = ?");
                $stmt->bind_param("i", $id_pesanan);
                $stmt->execute();
                $stmt->close();

                // Remove pembayaran (gateway payments)
                $stmt = $conn->prepare("DELETE FROM pembayaran WHERE booking_id = ?");
                $stmt->bind_param("i", $id_pesanan);
                $stmt->execute();
                $stmt->close();

                // Remove virtual_account (manual transfers)
                $stmt = $conn->prepare("DELETE FROM virtual_account WHERE id_pesanan = ?");
                $stmt->bind_param("i", $id_pesanan);
                $stmt->execute();
                $stmt->close();

                // Remove notifications related to this order
                $stmt = $conn->prepare("DELETE FROM notification_log WHERE related_id = ?");
                $stmt->bind_param("i", $id_pesanan);
                $stmt->execute();
                $stmt->close();

                // Finally remove the order itself
                $stmt = $conn->prepare("DELETE FROM pesanan WHERE id_pesanan = ?");
                $stmt->bind_param("i", $id_pesanan);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $success = 'Pesanan #' . str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) . ' dan data terkait berhasil dihapus.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Gagal menghapus pesanan: ' . $e->getMessage();
            }
        } else {
            $error = 'ID pesanan tidak valid.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Pesanan - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            color: white;
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 24px;
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            margin-left: 270px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .danger {
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .success {
            background: #e6ffed;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            color: #856404;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        input[type="number"] {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            width: 100%;
            max-width: 200px;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
        }
        hr {
            border: none;
            border-top: 2px solid #f0f0f0;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="card">
            <h1 style="color: #333; margin-bottom: 10px;">🗑️ Hapus Pesanan & Pembayaran</h1>
            <p style="color: #666; margin-bottom: 30px;">Gunakan halaman ini untuk menghapus pesanan dan data pembayaran terkait. <strong style="color: #dc3545;">Hati-hati — operasi ini tidak dapat dikembalikan.</strong></p>

            <?php if ($error): ?>
                <div class="danger">
                    <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success">
                    <strong>✅ Sukses:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="warning">
                <strong>⚠️ Peringatan:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Menghapus pesanan akan menghapus semua data terkait (review, pembayaran, notifikasi)</li>
                    <li>Data yang sudah dihapus TIDAK DAPAT dikembalikan</li>
                    <li>Pastikan Anda yakin sebelum menghapus</li>
                </ul>
            </div>

            <h3 style="color: #333; margin-top: 30px;">📝 Hapus Pesanan Tertentu</h3>
            <p style="color: #666; font-size: 14px;">Masukkan ID pesanan untuk menghapus satu pesanan beserta semua data terkait:</p>

            <form method="POST" onsubmit="return confirm('⚠️ KONFIRMASI\n\nHapus pesanan ini dan semua data terkait?\n• Review\n• Pembayaran\n• Virtual Account\n• Notifikasi\n\nTindakan ini TIDAK DAPAT dibatalkan!');">
                <?php echo csrfField(); ?>
                <div style="margin: 20px 0;">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: #333;">
                        ID Pesanan:
                    </label>
                    <input type="number" name="id_pesanan" min="1" placeholder="Contoh: 2" required>
                    <p style="color: #999; font-size: 13px; margin: 5px 0;">
                        💡 Tip: Lihat ID pesanan di menu "Daftar Pesanan" atau "Konfirmasi VA"
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">🗑️ Hapus Pesanan Ini</button>
            </form>

            <hr>

            <h3 style="color: #dc3545; margin-top: 30px;">⚠️ DANGER ZONE - Hapus Semua Data</h3>
            <p style="color: #666; font-size: 14px;">Menghapus SEMUA pesanan dan pembayaran dari database. <strong style="color: #dc3545;">Gunakan dengan sangat hati-hati!</strong></p>

            <div class="danger">
                <strong>❌ PERINGATAN KERAS:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Ini akan menghapus SEMUA pesanan dari semua konsumen</li>
                    <li>Semua data pembayaran akan hilang</li>
                    <li>Semua review akan terhapus</li>
                    <li>Semua notifikasi terkait akan terhapus</li>
                    <li><strong>TIDAK ADA CARA untuk mengembalikan data!</strong></li>
                </ul>
            </div>

            <form method="POST" onsubmit="return confirm('⛔ KONFIRMASI FINAL\n\nAnda akan menghapus SEMUA pesanan dan pembayaran!\n\n• Semua pesanan (dari semua konsumen)\n• Semua pembayaran\n• Semua virtual account\n• Semua review\n• Semua notifikasi terkait\n\nData akan HILANG PERMANEN!\n\nKlik OK jika Anda YAKIN 100%');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="delete_all" value="1">
                <button type="submit" class="btn btn-danger">💣 Hapus Semua Pesanan & Pembayaran</button>
            </form>

            <p style="margin-top: 20px; text-align: center;">
                <a href="dashboard.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                    ← Kembali ke Dashboard
                </a>
            </p>
        </div>
    </div>
</body>
</html>
