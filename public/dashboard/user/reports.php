<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

// Check if user is logged in and is general user
requireLogin();
if (getUserRole() != 'general_user') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';

// Get user reports
try {
    $conn = getDBConnection();
    
    $query = "SELECT r.*, 
              COUNT(ra.id) as attachment_count
              FROM reports r
              LEFT JOIN report_attachments ra ON r.id = ra.report_id
              WHERE r.user_id = ?
              ";
    
    $params = [$_SESSION['user_id']];
    
    if ($statusFilter != 'all') {
        $query .= " AND r.status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " GROUP BY r.id ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM reports WHERE user_id = ? GROUP BY status");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $reports = [];
    $stats = [];
}

$reportTypeLabels = [
    'bullying' => 'Perundungan',
    'violence' => 'Kekerasan',
    'harassment' => 'Pelecehan',
    'abuse' => 'Abuse',
    'other' => 'Lainnya'
];

$statusLabels = [
    'pending' => 'Pending',
    'in_progress' => 'Sedang Diproses',
    'resolved' => 'Selesai',
    'rejected' => 'Ditolak'
];

$statusColors = [
    'pending' => '#FFA726',
    'in_progress' => '#42A5F5',
    'resolved' => '#66BB6A',
    'rejected' => '#EF5350'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Laporan - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .reports-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: var(--color-text);
        }
        
        .btn-create {
            background: #D34E4E !important;
            color: white !important;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
            transition: all 0.3s ease;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            border: none;
            cursor: pointer;
        }
        
        .btn-create:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .btn-create:active {
            transform: translateY(0);
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
        
        .filters {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid rgba(253, 121, 121, 0.2);
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--color-text);
            font-size: 14px;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            border-color: var(--color-primary);
            background: rgba(253, 121, 121, 0.1);
            color: var(--color-primary);
        }
        
        .reports-list {
            display: grid;
            gap: 20px;
        }
        
        .report-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 5px;
        }
        
        .report-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--color-text-light);
        }
        
        .report-badges {
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
        
        .badge-type {
            background: rgba(253, 121, 121, 0.1);
            color: var(--color-primary);
        }
        
        .badge-status {
            color: white;
        }
        
        .report-description {
            color: var(--color-text-light);
            line-height: 1.6;
            margin-top: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="reports-container">
        <div class="page-header">
            <h1>Daftar Laporan Saya</h1>
            <a href="<?php echo BASE_URL; ?>/dashboard/user/create-report.php" class="btn-create">+ Buat Laporan Baru</a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Laporan</h3>
                <div class="number"><?php echo array_sum($stats); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Sedang Diproses</h3>
                <div class="number"><?php echo $stats['in_progress'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Selesai</h3>
                <div class="number"><?php echo $stats['resolved'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-buttons">
                <a href="?status=all" class="filter-btn <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">Semua</a>
                <a href="?status=pending" class="filter-btn <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?status=in_progress" class="filter-btn <?php echo $statusFilter == 'in_progress' ? 'active' : ''; ?>">Sedang Diproses</a>
                <a href="?status=resolved" class="filter-btn <?php echo $statusFilter == 'resolved' ? 'active' : ''; ?>">Selesai</a>
                <a href="?status=rejected" class="filter-btn <?php echo $statusFilter == 'rejected' ? 'active' : ''; ?>">Ditolak</a>
            </div>
        </div>
        
        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?php echo icon('reports', '', 64, 64); ?></div>
                <h3>Tidak Ada Laporan</h3>
                <p>Anda belum membuat laporan apapun.</p>
                <a href="<?php echo BASE_URL; ?>/dashboard/user/create-report.php" class="btn-create">Buat Laporan Pertama</a>
            </div>
        <?php else: ?>
            <div class="reports-list">
                <?php foreach ($reports as $report): ?>
                    <a href="<?php echo BASE_URL; ?>/dashboard/user/report-detail.php?id=<?php echo $report['id']; ?>" class="report-card">
                        <div class="report-header">
                            <div style="flex: 1;">
                                <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                <div class="report-meta">
                                    <span>#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    <span>•</span>
                                    <span><?php echo formatDate($report['created_at']); ?></span>
                                    <?php if ($report['attachment_count'] > 0): ?>
                                        <span>•</span>
                                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> <?php echo $report['attachment_count']; ?> file</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="report-badges">
                                <?php if ($report['is_anonymous']): ?>
                                    <span class="badge" style="background: #9E9E9E; color: white;">Anonim</span>
                                <?php endif; ?>
                                <span class="badge badge-type"><?php echo $reportTypeLabels[$report['report_type']] ?? $report['report_type']; ?></span>
                                <span class="badge badge-status" style="background: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                                    <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="report-description">
                            <?php echo htmlspecialchars($report['description']); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo BASE_URL; ?>/dashboard/user/index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
        </div>
    </div>
</body>
</html>

