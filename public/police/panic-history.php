<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();
if (getUserRole() != 'police') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

try {
    $conn = getDBConnection();
    
    // Build query for panic alerts history - SEMUA panic alerts
    $query = "
        SELECT pa.*, 
               gud.full_name as user_name,
               gud.phone as user_phone,
               pd.full_name as responded_by_name
        FROM panic_alerts pa
        LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
        LEFT JOIN police_details pd ON pa.responded_by = pd.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply status filter
    if ($statusFilter !== 'all') {
        $query .= " AND pa.status = ?";
        $params[] = $statusFilter;
    }
    
    // Apply date filter
    if ($dateFilter === 'today') {
        $query .= " AND DATE(pa.created_at) = CURDATE()";
    } elseif ($dateFilter === 'week') {
        $query .= " AND pa.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateFilter === 'month') {
        $query .= " AND pa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    // Apply search filter
    if (!empty($searchQuery)) {
        $query .= " AND (gud.full_name LIKE ? OR pa.location LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " ORDER BY pa.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $panicAlerts = $stmt->fetchAll();
    
    // Get statistics - SEMUA panic alerts
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count
        FROM panic_alerts
        GROUP BY status
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $totalAlerts = array_sum($stats);
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $panicAlerts = [];
    $stats = [];
    $totalAlerts = 0;
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
    <title>Riwayat Panic Button - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: var(--color-text);
            margin: 0;
        }
        
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
            padding: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--color-text-light);
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: var(--color-text);
        }
        
        .filters-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr;
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
            padding: 10px;
            border: 1px solid rgba(253, 121, 121, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            font-size: 14px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .btn-filter {
            padding: 10px 20px;
            background: #D34E4E !important;
            color: white !important;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
        }
        
        .btn-filter:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .panic-alerts-list {
            display: grid;
            gap: 20px;
        }
        
        .panic-alert-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        
        .panic-alert-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .panic-alert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .panic-alert-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 5px;
        }
        
        .panic-alert-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--color-text-light);
        }
        
        .panic-alert-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-status {
            color: white;
        }
        
        .panic-alert-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(253, 121, 121, 0.1);
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-size: 11px;
            color: var(--color-text-light);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-item span {
            font-size: 14px;
            color: var(--color-text);
        }
        
        .panic-alert-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-view {
            padding: 10px 20px;
            background: var(--color-primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-action {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-respond {
            background: #42A5F5;
            color: white;
        }
        
        .btn-respond:hover:not(:disabled) {
            background: #1E88E5;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-resolve {
            background: #66BB6A;
            color: white;
        }
        
        .btn-resolve:hover:not(:disabled) {
            background: #43A047;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 24px;
            color: var(--color-text);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--color-text-light);
        }
        
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>Riwayat Panic Button</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px; padding: 15px; background: rgba(239, 83, 80, 0.1); border: 1px solid rgba(239, 83, 80, 0.3); border-radius: 10px; color: #EF5350;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Panic Alerts</h3>
                    <div class="number"><?php echo $totalAlerts; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Aktif</h3>
                    <div class="number" style="color: #EF5350;"><?php echo $stats['active'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ditanggapi</h3>
                    <div class="number" style="color: #42A5F5;"><?php echo $stats['responded'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Selesai</h3>
                    <div class="number" style="color: #66BB6A;"><?php echo $stats['resolved'] ?? 0; ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="responded" <?php echo $statusFilter === 'responded' ? 'selected' : ''; ?>>Ditanggapi</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="false_alarm" <?php echo $statusFilter === 'false_alarm' ? 'selected' : ''; ?>>Alarm Palsu</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Periode</label>
                            <select name="date">
                                <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                                <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                                <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Cari (Nama / Lokasi)</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Cari nama atau lokasi...">
                        </div>
                    </div>
                    <button type="submit" class="btn-filter">Filter</button>
                    <?php if ($statusFilter !== 'all' || $dateFilter !== 'all' || !empty($searchQuery)): ?>
                        <a href="panic-history.php" style="margin-left: 10px; padding: 10px 20px; background: rgba(255, 255, 255, 0.6); color: var(--color-text); text-decoration: none; border-radius: 8px; display: inline-block; border: 1px solid rgba(253, 121, 121, 0.2);">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Panic Alerts List -->
            <?php if (empty($panicAlerts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?php echo icon('panic', '', 64, 64); ?></div>
                    <h3>Tidak Ada Riwayat Panic</h3>
                    <p>Tidak ada panic alert yang ditemukan berdasarkan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="panic-alerts-list">
                    <?php foreach ($panicAlerts as $alert): ?>
                        <div class="panic-alert-card">
                            <div class="panic-alert-header">
                                <div style="flex: 1;">
                                    <div class="panic-alert-title">
                                        Panic Alert #<?php echo str_pad($alert['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div class="panic-alert-meta" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('users', '', 16, 16); ?> <?php echo htmlspecialchars($alert['user_name'] ?? 'User'); ?></span>
                                        <span>•</span>
                                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> <?php echo formatDateTime($alert['created_at']); ?></span>
                                    </div>
                                </div>
                                <div class="panic-alert-badges">
                                    <span class="badge badge-status" style="background: <?php echo $statusColors[$alert['status']] ?? '#666'; ?>;">
                                        <?php echo $statusLabels[$alert['status']] ?? $alert['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="panic-alert-info">
                                <?php if ($alert['location']): ?>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> Lokasi</label>
                                        <span><?php echo htmlspecialchars($alert['location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($alert['latitude'] && $alert['longitude']): ?>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> Koordinat</label>
                                        <span><?php echo htmlspecialchars($alert['latitude']); ?>, <?php echo htmlspecialchars($alert['longitude']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($alert['responded_by_name']): ?>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 4px;"><?php echo icon('users', '', 16, 16); ?> Ditanggapi Oleh</label>
                                        <span><?php echo htmlspecialchars($alert['responded_by_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($alert['responded_at']): ?>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> Waktu Ditanggapi</label>
                                        <span><?php echo formatDateTime($alert['responded_at']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($alert['resolved_at']): ?>
                                    <div class="info-item">
                                        <label style="display: flex; align-items: center; gap: 4px;"><?php echo icon('completed', '', 16, 16); ?> Waktu Diselesaikan</label>
                                        <span><?php echo formatDateTime($alert['resolved_at']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($alert['notes']): ?>
                                <div style="margin-top: 15px; padding: 15px; background: rgba(255, 255, 255, 0.6); border-radius: 10px;">
                                    <label style="font-size: 11px; color: var(--color-text-light); font-weight: 600; display: block; margin-bottom: 5px;">Catatan:</label>
                                    <p style="font-size: 14px; color: var(--color-text); margin: 0;"><?php echo nl2br(htmlspecialchars($alert['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="panic-alert-actions">
                                <?php if ($alert['status'] === 'active'): ?>
                                    <button type="button" 
                                            class="btn-action btn-respond" 
                                            onclick="respondToPanic(<?php echo $alert['id']; ?>, this)"
                                            data-alert-id="<?php echo $alert['id']; ?>">
                                        Tanggapi
                                    </button>
                                    <button type="button" 
                                            class="btn-action btn-resolve" 
                                            onclick="resolvePanic(<?php echo $alert['id']; ?>, this)"
                                            data-alert-id="<?php echo $alert['id']; ?>">
                                        Selesaikan
                                    </button>
                                <?php elseif ($alert['status'] === 'responded'): ?>
                                    <button type="button" 
                                            class="btn-action btn-resolve" 
                                            onclick="resolvePanic(<?php echo $alert['id']; ?>, this)"
                                            data-alert-id="<?php echo $alert['id']; ?>">
                                        Selesaikan
                                    </button>
                                <?php endif; ?>
                                
                                <a href="panic-detail.php?id=<?php echo $alert['id']; ?>" class="btn-view">Lihat Detail</a>
                                
                                <?php if ($alert['latitude'] && $alert['longitude']): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo urlencode($alert['latitude'] . ',' . $alert['longitude']); ?>" 
                                       target="_blank" 
                                       class="btn-view" 
                                       style="background: #42A5F5;">
                                        Buka di Maps
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Modal for Notes -->
    <div id="notesModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; border-radius: 20px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-top: 0; color: var(--color-text);">Tambahkan Catatan</h3>
            <form id="notesForm">
                <input type="hidden" id="modalAlertId" name="alert_id">
                <input type="hidden" id="modalAction" name="action">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; color: var(--color-text); font-weight: 600;">Catatan:</label>
                    <textarea id="modalNotes" name="notes" rows="5" style="width: 100%; padding: 10px; border: 1px solid rgba(253, 121, 121, 0.2); border-radius: 8px; font-family: inherit; resize: vertical;" placeholder="Tambahkan catatan tindakan yang dilakukan..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeNotesModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.6); color: var(--color-text); border: 1px solid rgba(253, 121, 121, 0.2); border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Batal
                    </button>
                    <button type="submit" style="padding: 10px 20px; background: var(--color-primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function respondToPanic(alertId, button) {
            document.getElementById('modalAlertId').value = alertId;
            document.getElementById('modalAction').value = 'respond';
            document.getElementById('modalNotes').value = '';
            document.getElementById('notesModal').style.display = 'flex';
        }
        
        function resolvePanic(alertId, button) {
            document.getElementById('modalAlertId').value = alertId;
            document.getElementById('modalAction').value = 'resolve';
            document.getElementById('modalNotes').value = '';
            document.getElementById('notesModal').style.display = 'flex';
        }
        
        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
        }
        
        document.getElementById('notesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const alertId = document.getElementById('modalAlertId').value;
            const action = document.getElementById('modalAction').value;
            const notes = document.getElementById('modalNotes').value;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('alert_id', alertId);
            formData.append('notes', notes);
            
            // Disable button
            const buttons = document.querySelectorAll(`[data-alert-id="${alertId}"]`);
            buttons.forEach(btn => btn.disabled = true);
            
            fetch('<?php echo BASE_URL; ?>/police/panic-action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal memproses aksi'));
                    buttons.forEach(btn => btn.disabled = false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memproses aksi');
                buttons.forEach(btn => btn.disabled = false);
            });
        });
        
        // Close modal when clicking outside
        document.getElementById('notesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotesModal();
            }
        });
    </script>
</body>
</html>

