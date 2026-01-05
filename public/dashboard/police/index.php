<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

// Check if user is logged in and is police
requireLogin();
if (getUserRole() != 'police') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get statistics
try {
    $conn = getDBConnection();
    
    // Get police name
    $stmt = $conn->prepare("SELECT full_name FROM police_details WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $policeName = $stmt->fetch()['full_name'] ?? 'Petugas Polisi';
    
    // Total reports - SEMUA laporan
    $stmt = $conn->query("SELECT COUNT(*) as count FROM reports");
    $assignedReports = $stmt->fetch()['count'] ?? 0;
    
    // Reports by status - SEMUA laporan
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
    $reportsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $pendingReports = $reportsByStatus['pending'] ?? 0;
    $inProgressReports = $reportsByStatus['in_progress'] ?? 0;
    $resolvedReports = $reportsByStatus['resolved'] ?? 0;
    $rejectedReports = $reportsByStatus['rejected'] ?? 0;
    
    // Total reports (all, for context)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM reports");
    $totalReports = $stmt->fetch()['count'] ?? 0;
    
    // Recent reports - SEMUA laporan (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentAssigned = $stmt->fetch()['count'] ?? 0;
    
    // Get recent reports list untuk ditampilkan
    $recentReportsList = [];
    try {
        $stmt = $conn->query("
            SELECT r.*,
                   COALESCE(gud.full_name, 'Anonim') as reporter_name
            FROM reports r
            LEFT JOIN general_user_details gud ON r.user_id = gud.user_id
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $recentReportsList = $stmt->fetchAll();
    } catch(PDOException $e) {
        $recentReportsList = [];
    }
    
    // Status labels
    $statusLabels = [
        'pending' => 'Pending',
        'in_progress' => 'Sedang Diproses',
        'resolved' => 'Selesai',
        'rejected' => 'Ditolak'
    ];
    
    // Get reports per day for last 7 days (for chart) - SEMUA laporan
    $chartBars = [0, 0, 0, 0, 0, 0, 0];
    $maxCount = 1;
    try {
        $stmt = $conn->query("
            SELECT 
                DATE(created_at) as date,
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as count
            FROM reports
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
    
    // Get active and responded panic alerts
    $activePanicAlerts = [];
    try {
        $stmt = $conn->query("
            SELECT pa.*, 
                   gud.full_name as user_name,
                   gud.phone as user_phone
            FROM panic_alerts pa
            LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
            WHERE pa.status IN ('active', 'responded')
            ORDER BY pa.created_at DESC
            LIMIT 5
        ");
        $activePanicAlerts = $stmt->fetchAll();
    } catch(PDOException $e) {
        $activePanicAlerts = [];
    }
    
} catch(PDOException $e) {
    $error = 'Error loading statistics: ' . $e->getMessage();
    $chartBars = [0, 0, 0, 0, 0, 0, 0];
    $maxCount = 1;
    $recentReportsList = [];
    $statusLabels = [
        'pending' => 'Pending',
        'in_progress' => 'Sedang Diproses',
        'resolved' => 'Selesai',
        'rejected' => 'Ditolak'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Polisi - AntiAbuse</title>
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
                            <h3>Aktivitas</h3>
                            <select class="filter-select">
                                <option>7 hari terakhir</option>
                                <option>30 hari terakhir</option>
                                <option>Bulan ini</option>
                            </select>
                        </div>
                        <div class="activity-stats">
                            <div class="big-number"><?php echo number_format($assignedReports); ?></div>
                            <p>Laporan yang Ditugaskan</p>
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
                                    echo '<div class="bar" style="height: ' . $height . '%;" title="' . $count . ' laporan"></div>';
                                    echo '<span class="bar-label">' . $day . '</span>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Platform Card -->
                    <div class="dashboard-card">
                        <h3>Status Laporan</h3>
                        <div class="platform-list">
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('pending', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Pending</strong>
                                    <small><?php echo $pendingReports; ?> laporan</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('pending', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Sedang Diproses</strong>
                                    <small><?php echo $inProgressReports; ?> laporan</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('completed', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Selesai</strong>
                                    <small><?php echo $resolvedReports; ?> laporan</small>
                                </div>
                            </div>
                            <div class="platform-item">
                                <span class="platform-icon"><?php echo icon('completed', '', 24, 24); ?></span>
                                <div class="platform-info">
                                    <strong>Ditolak</strong>
                                    <small><?php echo $rejectedReports; ?> laporan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Middle Column -->
                <div class="card-col">
                    <!-- Progress Statistics Card -->
                    <div class="dashboard-card">
                        <h3>Statistik Progress</h3>
                        <div class="progress-stat">
                            <div class="big-number"><?php echo $assignedReports > 0 ? round(($resolvedReports / $assignedReports) * 100) : 0; ?>%</div>
                            <p>Laporan Selesai</p>
                        </div>
                        <div class="progress-bar-segmented">
                            <?php
                            $resolved = $assignedReports > 0 ? ($resolvedReports / $assignedReports) * 100 : 0;
                            $inProgress = $assignedReports > 0 ? ($inProgressReports / $assignedReports) * 100 : 0;
                            $pending = $assignedReports > 0 ? ($pendingReports / $assignedReports) * 100 : 0;
                            ?>
                            <div class="segment" style="width: <?php echo $resolved; ?>%; background: #66BB6A;"></div>
                            <div class="segment" style="width: <?php echo $inProgress; ?>%; background: #42A5F5;"></div>
                            <div class="segment" style="width: <?php echo $pending; ?>%; background: #FFA726;"></div>
                        </div>
                        <div class="stat-icons">
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('pending', '', 20, 20); ?></span>
                                <span><?php echo $pendingReports; ?> Pending</span>
                            </div>
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('pending', '', 20, 20); ?></span>
                                <span><?php echo $inProgressReports; ?> Diproses</span>
                            </div>
                            <div class="stat-icon-item">
                                <span class="icon"><?php echo icon('completed', '', 20, 20); ?></span>
                                <span><?php echo $resolvedReports; ?> Selesai</span>
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
                            <span class="badge badge-level">Polisi</span>
                        </div>
                        <h3>Manajemen Laporan</h3>
                        <p>Kelola laporan yang ditugaskan kepada Anda. Proses laporan, update status, dan tambahkan catatan tindak lanjut investigasi.</p>
                        <div class="card-footer">
                            <a href="reports.php" class="btn-continue">Kelola Sekarang</a>
                        </div>
                    </div>
                    
                    <!-- Recent Reports Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Laporan Terbaru</h3>
                            <button class="nav-arrows">
                                <span>‹</span>
                                <span>Today</span>
                                <span>›</span>
                            </button>
                        </div>
                        <div class="schedule-list">
                            <?php if (!empty($recentReportsList)): ?>
                                <?php foreach ($recentReportsList as $report): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time"><?php echo formatTime($report['created_at']); ?></div>
                                        <div class="schedule-content">
                                            <strong><?php echo htmlspecialchars($report['title']); ?></strong>
                                            <small>
                                                <?php echo htmlspecialchars($report['reporter_name']); ?> • 
                                                <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--color-text); opacity: 0.6; padding: 20px;">Tidak ada laporan baru</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Panic Alerts Card -->
                    <div class="dashboard-card" style="background: rgba(239, 83, 80, 0.1); border: 2px solid rgba(239, 83, 80, 0.3); margin-top: 20px;">
                        <h3 style="color: #EF5350; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;"><?php echo icon('panic', '', 24, 24); ?> Panic Alerts</h3>
                        <?php if (empty($activePanicAlerts)): ?>
                            <p style="text-align: center; color: var(--color-text); opacity: 0.6; padding: 20px;">Tidak ada panic alert</p>
                        <?php else: ?>
                            <div class="panic-alerts-list">
                                <?php foreach ($activePanicAlerts as $alert): 
                                    $statusBadges = [
                                        'active' => ['text' => 'URGENT', 'color' => '#EF5350'],
                                        'responded' => ['text' => 'DITANGGAPI', 'color' => '#42A5F5'],
                                        'resolved' => ['text' => 'SELESAI', 'color' => '#66BB6A']
                                    ];
                                    $badge = $statusBadges[$alert['status']] ?? ['text' => 'UNKNOWN', 'color' => '#666'];
                                ?>
                                    <div class="panic-alert-item" style="background: rgba(255, 255, 255, 0.6); border-radius: 12px; padding: 15px; margin-bottom: 10px; border: 1px solid rgba(239, 83, 80, 0.2);">
                                        <div class="panic-alert-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <strong style="color: var(--color-text);"><?php echo htmlspecialchars($alert['user_name'] ?? 'User'); ?></strong>
                                            <span class="badge badge-urgent" style="background: <?php echo $badge['color']; ?>; color: white; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                                                <?php echo $badge['text']; ?>
                                            </span>
                                        </div>
                                        <p style="font-size: 12px; color: var(--color-text-light); margin-bottom: 5px;">
                                            <?php echo formatDateTime($alert['created_at']); ?>
                                        </p>
                                        <?php if ($alert['location']): ?>
                                            <p style="font-size: 12px; color: var(--color-text-light); margin-bottom: 10px; display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 14, 14); ?> <?php echo htmlspecialchars($alert['location']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($alert['latitude'] && $alert['longitude']): ?>
                                            <p style="font-size: 11px; color: var(--color-text-light); margin-bottom: 10px;">
                                                Koordinat: <?php echo htmlspecialchars($alert['latitude']); ?>, <?php echo htmlspecialchars($alert['longitude']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                            <?php if ($alert['status'] === 'active'): ?>
                                                <button type="button" 
                                                        class="btn-tanggapi" 
                                                        onclick="respondToPanic(<?php echo $alert['id']; ?>, this)"
                                                        data-alert-id="<?php echo $alert['id']; ?>"
                                                        style="padding: 8px 16px; background: #42A5F5; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                                    Tanggapi
                                                </button>
                                            <?php elseif ($alert['status'] === 'responded'): ?>
                                                <button type="button" 
                                                        class="btn-selesai" 
                                                        onclick="resolvePanic(<?php echo $alert['id']; ?>, this)"
                                                        data-alert-id="<?php echo $alert['id']; ?>"
                                                        style="padding: 8px 16px; background: #66BB6A; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                                    Selesai
                                                </button>
                                            <?php endif; ?>
                                            <a href="panic-detail.php?id=<?php echo $alert['id']; ?>" 
                                               style="padding: 8px 16px; background: rgba(255, 255, 255, 0.6); color: var(--color-text); text-decoration: none; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid rgba(253, 121, 121, 0.2);">
                                                Detail
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function respondToPanic(alertId, button) {
            if (!confirm('Apakah Anda yakin ingin menandai panic alert ini sebagai ditanggapi?')) {
                return;
            }
            
            button.disabled = true;
            button.innerHTML = 'Memproses...';
            
            const formData = new FormData();
            formData.append('action', 'respond');
            formData.append('alert_id', alertId);
            formData.append('notes', '');
            
            fetch('<?php echo BASE_URL; ?>/dashboard/police/panic-action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Panic alert berhasil ditandai sebagai ditanggapi!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal memproses'));
                    button.disabled = false;
                    button.innerHTML = 'Tanggapi';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memproses');
                button.disabled = false;
                button.innerHTML = 'Tanggapi';
            });
        }
        
        function resolvePanic(alertId, button) {
            if (!confirm('Apakah Anda yakin ingin menyelesaikan panic alert ini?')) {
                return;
            }
            
            button.disabled = true;
            button.innerHTML = 'Memproses...';
            
            const formData = new FormData();
            formData.append('action', 'resolve');
            formData.append('alert_id', alertId);
            formData.append('notes', '');
            
            fetch('<?php echo BASE_URL; ?>/dashboard/police/panic-action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Panic alert berhasil diselesaikan!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal memproses'));
                    button.disabled = false;
                    button.innerHTML = 'Selesai';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memproses');
                button.disabled = false;
                button.innerHTML = 'Selesai';
            });
        }
    </script>
</body>
</html>

