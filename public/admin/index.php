<?php
// Admin Dashboard

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/classes/Admin.php';

// Check login and role
requireLogin();
requireRole('admin');

// Get statistics
$admin = new Admin();
$stats = $admin->getStatistics();

// Extract variables for view
$usersByRole = $stats['usersByRole'] ?? [];
$totalUsers = $stats['totalUsers'] ?? 0;
$recentUsers = $stats['recentUsers'] ?? 0;
$totalReports = $stats['totalReports'] ?? 0;
$pendingReports = $stats['pendingReports'] ?? 0;
$adminName = $stats['adminName'] ?? 'Administrator';
$chartBars = $stats['chartBars'] ?? [0, 0, 0, 0, 0, 0, 0];
$maxCount = $stats['maxCount'] ?? 1;
$error = $stats['error'] ?? null;

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
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
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
                        <div class="big-number"><?= number_format($totalUsers) ?></div>
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
                            <span class="platform-icon"><?= icon('reports', '', 24, 24) ?></span>
                            <div class="platform-info">
                                <strong>Laporan</strong>
                                <small><?= $totalReports ?> laporan</small>
                            </div>
                        </div>
                        <div class="platform-item">
                            <span class="platform-icon"><?= icon('panic', '', 24, 24) ?></span>
                            <div class="platform-info">
                                <strong>Panic Button</strong>
                                <small>0 aktivitas</small>
                            </div>
                        </div>
                        <div class="platform-item">
                            <span class="platform-icon"><?= icon('forum', '', 24, 24) ?></span>
                            <div class="platform-info">
                                <strong>Forum</strong>
                                <small>0 post</small>
                            </div>
                        </div>
                        <div class="platform-item">
                            <span class="platform-icon"><?= icon('education', '', 24, 24) ?></span>
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
                        <div class="big-number"><?= $totalReports > 0 ? round((($totalReports - $pendingReports) / $totalReports) * 100) : 0 ?>%</div>
                        <p>Total Aktivitas</p>
                    </div>
                    <div class="progress-bar-segmented">
                        <?php
                        $completed = $totalReports > 0 ? ($totalReports - $pendingReports) / $totalReports * 100 : 0;
                        $pending = $totalReports > 0 ? $pendingReports / $totalReports * 100 : 0;
                        $upcoming = 0;
                        ?>
                        <div class="segment" style="width: <?= $completed ?>%; background: #FD7979;"></div>
                        <div class="segment" style="width: <?= $pending ?>%; background: #F9DFDF;"></div>
                        <div class="segment" style="width: <?= $upcoming ?>%; background: #ffffff;"></div>
                    </div>
                    <div class="stat-icons">
                        <div class="stat-icon-item">
                            <span class="icon"><?= icon('pending', '', 20, 20) ?></span>
                            <span><?= $pendingReports ?> Pending</span>
                        </div>
                        <div class="stat-icon-item">
                            <span class="icon"><?= icon('completed', '', 20, 20) ?></span>
                            <span><?= $totalReports - $pendingReports ?> Selesai</span>
                        </div>
                        <div class="stat-icon-item">
                            <span class="icon"><?= icon('calendar', '', 20, 20) ?></span>
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
                                    <small><?= $pendingReports ?> laporan pending</small>
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
