<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() != 'admin') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
}

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get panic alerts
try {
    $conn = getDBConnection();
    
    $query = "SELECT pa.*, 
              gud.full_name as user_name,
              gud.phone as user_phone,
              pd.full_name as responded_by_name,
              COUNT(DISTINCT pn.id) as notification_count
              FROM panic_alerts pa
              LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
              LEFT JOIN police_details pd ON pa.responded_by = pd.user_id
              LEFT JOIN panic_notifications pn ON pa.id = pn.panic_alert_id
              WHERE 1=1";
    
    $params = [];
    
    if ($statusFilter != 'all') {
        $query .= " AND pa.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($dateFilter == 'today') {
        $query .= " AND DATE(pa.created_at) = CURDATE()";
    } elseif ($dateFilter == 'week') {
        $query .= " AND pa.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateFilter == 'month') {
        $query .= " AND pa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    if ($search) {
        $query .= " AND (gud.full_name LIKE ? OR pa.location LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " GROUP BY pa.id ORDER BY pa.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $panicAlerts = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM panic_alerts GROUP BY status");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $totalAlerts = array_sum($stats);
    $activeAlerts = $stats['active'] ?? 0;
    $respondedAlerts = $stats['responded'] ?? 0;
    $resolvedAlerts = $stats['resolved'] ?? 0;
    
    // Today's alerts
    $stmt = $conn->query("SELECT COUNT(*) as count FROM panic_alerts WHERE DATE(created_at) = CURDATE()");
    $todayAlerts = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $panicAlerts = [];
    $stats = [];
    $totalAlerts = 0;
    $activeAlerts = 0;
    $respondedAlerts = 0;
    $resolvedAlerts = 0;
    $todayAlerts = 0;
}

$statusLabels = [
    'active' => 'Aktif',
    'responded' => 'Ditanggapi',
    'resolved' => 'Selesai',
    'false_alarm' => 'Alarm Palsu'
];

$statusColors = [
    'active' => '#EF5350',
    'responded' => '#42A5F5',
    'resolved' => '#66BB6A',
    'false_alarm' => '#9E9E9E'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panic Button Monitoring - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--color-text-light);
            margin: 0 0 10px 0;
            font-weight: 600;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }
        
        .filters-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 12px;
            color: var(--color-text-light);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            font-size: 14px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .search-box input {
            flex: 1;
        }
        
        .search-box button {
            padding: 10px 20px;
            background: #D34E4E !important;
            color: white !important;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
        }
        
        .search-box button:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .data-table {
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: rgba(255, 255, 255, 0.3);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            font-size: 14px;
            border-bottom: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(253, 121, 121, 0.1);
            color: var(--color-text);
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: rgba(253, 121, 121, 0.05);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(66, 165, 245, 0.1);
            color: #42A5F5;
        }
        
        .btn-view:hover {
            background: #42A5F5;
            color: white;
        }
        
        .btn-map {
            background: rgba(255, 193, 7, 0.1);
            color: #FFC107;
        }
        
        .btn-map:hover {
            background: #FFC107;
            color: #2b2b2b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-light);
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Panic Button Monitoring</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Panic Alerts</h3>
                    <div class="number"><?php echo number_format($totalAlerts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Aktif</h3>
                    <div class="number" style="color: #EF5350;"><?php echo number_format($activeAlerts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ditanggapi</h3>
                    <div class="number" style="color: #42A5F5;"><?php echo number_format($respondedAlerts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Selesai</h3>
                    <div class="number" style="color: #66BB6A;"><?php echo number_format($resolvedAlerts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Hari Ini</h3>
                    <div class="number"><?php echo number_format($todayAlerts); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="responded" <?php echo $statusFilter == 'responded' ? 'selected' : ''; ?>>Ditanggapi</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="false_alarm" <?php echo $statusFilter == 'false_alarm' ? 'selected' : ''; ?>>Alarm Palsu</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Periode</label>
                            <select name="date">
                                <option value="all" <?php echo $dateFilter == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                                <option value="today" <?php echo $dateFilter == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                                <option value="week" <?php echo $dateFilter == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                                <option value="month" <?php echo $dateFilter == 'month' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Cari berdasarkan nama user atau lokasi..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">Cari</button>
                    </div>
                </form>
            </div>
            
            <!-- Data Table -->
            <div class="data-table">
                <?php if (empty($panicAlerts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><?php echo icon('panic', '', 64, 64); ?></div>
                        <h3>Tidak Ada Data</h3>
                        <p>Belum ada panic alert yang tercatat.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Waktu</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                                <th>Ditanggapi Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($panicAlerts as $alert): ?>
                                <tr>
                                    <td>#<?php echo str_pad($alert['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($alert['user_name'] ?? 'User'); ?></td>
                                    <td><?php echo formatDateTime($alert['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($alert['location'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $statusColors[$alert['status']] ?? '#666'; ?>; color: white;">
                                            <?php echo $statusLabels[$alert['status']] ?? $alert['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['responded_by_name'] ?? '-'); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/admin/panic-detail.php?id=<?php echo $alert['id']; ?>" class="btn-action btn-view">Detail</a>
                                        <?php if ($alert['latitude'] && $alert['longitude']): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($alert['latitude']); ?>,<?php echo htmlspecialchars($alert['longitude']); ?>" target="_blank" class="btn-action btn-map">Maps</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
