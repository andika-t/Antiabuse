<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() != 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get statistics
try {
    $conn = getDBConnection();
    
    // Total users by role
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total users
    $totalUsers = array_sum($usersByRole);
    
    // Recent users (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentUsers = $stmt->fetch()['count'];
    
    // Total reports (if table exists, otherwise 0)
    $totalReports = 0;
    $pendingReports = 0;
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM reports");
        $totalReports = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
        $pendingReports = $stmt->fetch()['count'] ?? 0;
    } catch(PDOException $e) {
        // Table doesn't exist yet
    }
    
    // Get admin name
    $stmt = $conn->prepare("SELECT full_name FROM admin_details WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $adminName = $stmt->fetch()['full_name'] ?? 'Administrator';
    
    // Get user registrations per day for last 7 days (for chart)
    $chartBars = [0, 0, 0, 0, 0, 0, 0];
    $maxCount = 1;
    try {
        $stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as count
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DAYOFWEEK returns: 1=Sunday, 2=Monday, 3=Tuesday, 4=Wednesday, 5=Thursday, 6=Friday, 7=Saturday
        // We need to map to: 0=Monday(Sen), 1=Tuesday(Sel), 2=Wednesday(Rab), 3=Thursday(Kam), 4=Friday(Jum), 5=Saturday(Sab), 6=Sunday(Min)
        $dayOfWeekMap = [
            1 => 6, // Sunday -> index 6 (Min)
            2 => 0, // Monday -> index 0 (Sen)
            3 => 1, // Tuesday -> index 1 (Sel)
            4 => 2, // Wednesday -> index 2 (Rab)
            5 => 3, // Thursday -> index 3 (Kam)
            6 => 4, // Friday -> index 4 (Jum)
            7 => 5  // Saturday -> index 5 (Sab)
        ];
        
        // Fill chart data
        foreach ($chartData as $row) {
            $dayOfWeek = (int)$row['day_of_week'];
            if (isset($dayOfWeekMap[$dayOfWeek])) {
                $dayIndex = $dayOfWeekMap[$dayOfWeek];
                $count = (int)$row['count'];
                $chartBars[$dayIndex] = $count;
            }
        }
        
        // Find max value for percentage calculation
        $maxCount = max($chartBars) > 0 ? max($chartBars) : 1;
    } catch(PDOException $e) {
        // Keep default values
        $chartBars = [0, 0, 0, 0, 0, 0, 0];
        $maxCount = 1;
    }
    
} catch(PDOException $e) {
    $error = 'Error loading statistics: ' . $e->getMessage();
    $chartBars = [0, 0, 0, 0, 0, 0, 0];
    $maxCount = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboard-unified.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Dashboard Grid - 3 Columns -->
            <div class="dashboard-grid">
                <!-- Left Column -->
                <div class="card-col">
                    <!-- Activity Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 style="opacity: 1 !important; visibility: visible !important; color: #2b2b2b !important; display: block !important;">Aktivitas</h3>
                            <select class="filter-select">
                                <option>7 hari terakhir</option>
                                <option>30 hari terakhir</option>
                                <option>Bulan ini</option>
                            </select>
                        </div>
                        <div class="activity-stats">
                            <div class="big-number"><?php echo number_format($totalUsers); ?></div>
                            <p>Total Pengguna</p>
                        </div>
                        <div class="chart-placeholder">
                            <div class="chart-bars">
                                <?php
                                $days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                                foreach ($days as $index => $day) {
                                    $count = $chartBars[$index] ?? 0;
                                    $height = ($count / $maxCount) * 100;
                                    // Minimum height 5% if there's data, otherwise 0%
                                    if ($count > 0 && $height < 5) {
                                        $height = 5;
                                    }
                                    echo '<div class="bar-item">';
                                    echo '<div class="bar" style="height: ' . $height . '%;" title="' . $count . ' pengguna"></div>';
                                    echo '<span class="bar-label">' . $day . '</span>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Platform Card -->
                    <div class="dashboard-card">
                        <h3 style="opacity: 1 !important; visibility: visible !important; color: #2b2b2b !important; display: block !important;">Per Platform</h3>
                        <div class="platform-list">
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('reports', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Laporan</strong>
                                    <small><?php echo $totalReports; ?> laporan</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('panic', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Panic Button</strong>
                                    <small>0 aktivitas</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('forum', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Forum</strong>
                                    <small>0 post</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('education', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Edukasi</strong>
                                    <small>0 konten</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Middle Column -->
                <div class="card-col">
                    <!-- Progress Statistics Card -->
                    <div class="dashboard-card">
                        <h3 style="opacity: 1 !important; visibility: visible !important; color: #2b2b2b !important; display: block !important;">Statistik Progress</h3>
                        <div class="progress-stat">
                            <div class="big-number"><?php echo $totalReports > 0 ? round((($totalReports - $pendingReports) / $totalReports) * 100) : 0; ?>%</div>
                            <p>Total Aktivitas</p>
                        </div>
                        <div class="progress-bar-segmented">
                            <?php
                            $completed = $totalReports > 0 ? ($totalReports - $pendingReports) / $totalReports * 100 : 0;
                            $pending = $totalReports > 0 ? $pendingReports / $totalReports * 100 : 0;
                            $upcoming = 0;
                            ?>
                            <div class="segment" style="width: <?php echo $completed; ?>%; background: #FD7979;"></div>
                            <div class="segment" style="width: <?php echo $pending; ?>%; background: #F9DFDF;"></div>
                            <div class="segment" style="width: <?php echo $upcoming; ?>%; background: #ffffff;"></div>
                        </div>
                        <div class="stat-icons">
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('pending', '', 20, 20); ?></span>
                                <span><?php echo $pendingReports; ?> Pending</span>
                            </div>
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('completed', '', 20, 20); ?></span>
                                <span><?php echo $totalReports - $pendingReports; ?> Selesai</span>
                            </div>
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('calendar', '', 20, 20); ?></span>
                                <span>0 Upcoming</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="card-col">
                    <!-- System Management Card -->
                    <div class="dashboard-card">
                        <div class="card-badges">
                            <span class="badge badge-group">Aktif</span>
                            <span class="badge badge-level">Admin</span>
                        </div>
                        <h3 style="opacity: 1 !important; visibility: visible !important; color: #2b2b2b !important; display: block !important;">Manajemen Sistem</h3>
                        <p>Kelola semua aspek sistem AntiAbuse dari satu tempat. Buat akun untuk psikolog dan polisi, kelola laporan, dan pantau aktivitas pengguna.</p>
                        <div class="card-footer">
                            <a href="users.php" class="btn-continue">Kelola Sekarang</a>
                        </div>
                    </div>
                    
                    <!-- Recent Reports Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 style="opacity: 1 !important; visibility: visible !important; color: #2b2b2b !important; display: block !important;">Laporan Terbaru</h3>
                            <button class="nav-arrows">
                                <span>‹</span>
                                <span>Today</span>
                                <span>›</span>
                            </button>
                        </div>
                        <div class="schedule-list">
                            <?php if ($totalReports > 0): ?>
                                <div class="schedule-item">
                                    <div class="schedule-time">Baru</div>
                                    <div class="schedule-content">
                                        <strong>Laporan Baru</strong>
                                        <small><?php echo $pendingReports; ?> laporan pending</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--color-text); opacity: 0.6; padding: 20px;">Tidak ada laporan baru</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

