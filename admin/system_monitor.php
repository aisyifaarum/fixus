<?php
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get MySQL status
$mysql_status = [];
$result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
if ($row = $result->fetch_assoc()) {
    $mysql_status['threads_connected'] = $row['Value'];
}

$result = $conn->query("SHOW STATUS LIKE 'Max_used_connections'");
if ($row = $result->fetch_assoc()) {
    $mysql_status['max_used_connections'] = $row['Value'];
}

$result = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
if ($row = $result->fetch_assoc()) {
    $mysql_status['max_connections'] = $row['Value'];
}

$result = $conn->query("SHOW STATUS LIKE 'Uptime'");
if ($row = $result->fetch_assoc()) {
    $mysql_status['uptime'] = $row['Value'];
}

$result = $conn->query("SHOW STATUS LIKE 'Questions'");
if ($row = $result->fetch_assoc()) {
    $mysql_status['queries'] = $row['Value'];
}

// Get database size
$result = $conn->query("
    SELECT
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES
    WHERE table_schema = 'fixus_db'
");
if ($row = $result->fetch_assoc()) {
    $mysql_status['db_size'] = $row['size_mb'];
}

// Get table stats
$table_stats = [];
$result = $conn->query("
    SELECT
        table_name,
        table_rows,
        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES
    WHERE table_schema = 'fixus_db'
    ORDER BY (data_length + index_length) DESC
");
while ($row = $result->fetch_assoc()) {
    $table_stats[] = $row;
}

// Calculate queries per second
$qps = 0;
if ($mysql_status['uptime'] > 0) {
    $qps = round($mysql_status['queries'] / $mysql_status['uptime'], 2);
}

// Get connection percentage
$connection_usage = 0;
if ($mysql_status['max_connections'] > 0) {
    $connection_usage = round(($mysql_status['threads_connected'] / $mysql_status['max_connections']) * 100, 2);
}

// PHP Info
$php_info = [
    'version' => phpversion(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
];

// Server info
$server_info = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'os' => PHP_OS,
    'hostname' => gethostname(),
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - Admin Fix Us</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            margin-left: 250px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            color: #999;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #ffc107 0%, #ff9800 100%);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #dc3545 0%, #c82333 100%);
        }

        .info-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .info-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .auto-refresh {
            text-align: right;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>📊 System Monitor</h1>
            <div class="auto-refresh">
                Auto-refresh setiap 10 detik | Last update: <?php echo date('d M Y H:i:s'); ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>MySQL Connections</h3>
                <div class="stat-value"><?php echo $mysql_status['threads_connected']; ?></div>
                <div class="stat-label">of <?php echo $mysql_status['max_connections']; ?> max</div>
                <div class="progress-bar">
                    <div class="progress-fill <?php
                        echo $connection_usage > 80 ? 'danger' : ($connection_usage > 60 ? 'warning' : '');
                    ?>" style="width: <?php echo $connection_usage; ?>%"></div>
                </div>
                <div style="margin-top: 5px; font-size: 12px; color: <?php
                    echo $connection_usage > 80 ? '#dc3545' : ($connection_usage > 60 ? '#ffc107' : '#28a745');
                ?>;">
                    <?php echo $connection_usage; ?>% used
                </div>
            </div>

            <div class="stat-card">
                <h3>Queries Per Second</h3>
                <div class="stat-value"><?php echo $qps; ?></div>
                <div class="stat-label">Average throughput</div>
            </div>

            <div class="stat-card">
                <h3>Database Size</h3>
                <div class="stat-value"><?php echo $mysql_status['db_size']; ?> MB</div>
                <div class="stat-label">Total storage used</div>
            </div>

            <div class="stat-card">
                <h3>MySQL Uptime</h3>
                <div class="stat-value"><?php echo round($mysql_status['uptime'] / 3600, 1); ?>h</div>
                <div class="stat-label"><?php echo number_format($mysql_status['queries']); ?> total queries</div>
            </div>
        </div>

        <div class="info-section">
            <h2>⚙️ PHP Configuration</h2>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo $php_info['version']; ?></td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
                <tr>
                    <td>Memory Limit</td>
                    <td><?php echo $php_info['memory_limit']; ?></td>
                    <td>
                        <?php
                        $mem_limit = (int)$php_info['memory_limit'];
                        echo $mem_limit >= 256 ? '<span class="badge badge-success">Good</span>' : '<span class="badge badge-danger">Low</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Max Execution Time</td>
                    <td><?php echo $php_info['max_execution_time']; ?>s</td>
                    <td><span class="badge badge-success">OK</span></td>
                </tr>
                <tr>
                    <td>OPcache Status</td>
                    <td><?php echo $php_info['opcache_enabled'] ? 'Enabled' : 'Disabled'; ?></td>
                    <td>
                        <?php echo $php_info['opcache_enabled'] ?
                            '<span class="badge badge-success">Enabled</span>' :
                            '<span class="badge badge-danger">Disabled</span>'; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section">
            <h2>💾 Database Tables</h2>
            <table>
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Rows</th>
                        <th>Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_stats as $table): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($table['table_name']); ?></strong></td>
                            <td><?php echo number_format($table['table_rows']); ?></td>
                            <td><?php echo $table['size_mb']; ?> MB</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="info-section">
            <h2>🖥️ Server Information</h2>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Server Software</td>
                    <td><?php echo htmlspecialchars($server_info['software']); ?></td>
                </tr>
                <tr>
                    <td>Operating System</td>
                    <td><?php echo htmlspecialchars($server_info['os']); ?></td>
                </tr>
                <tr>
                    <td>Hostname</td>
                    <td><?php echo htmlspecialchars($server_info['hostname']); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Auto-refresh every 10 seconds
        setTimeout(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>
