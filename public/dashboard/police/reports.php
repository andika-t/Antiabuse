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

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$assignedFilter = $_GET['assigned'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get reports
try {
    $conn = getDBConnection();
    
    $query = "SELECT r.*, 
              u.username as reporter_username,
              COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) as reporter_name,
              COUNT(DISTINCT ra.id) as attachment_count
              FROM reports r
              LEFT JOIN users u ON r.user_id = u.id
              LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
              LEFT JOIN police_details pd ON u.id = pd.user_id AND u.role = 'police'
              LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
              LEFT JOIN general_user_details gud ON u.id = gud.user_id AND u.role = 'general_user'
              LEFT JOIN report_attachments ra ON r.id = ra.report_id
              WHERE (r.assigned_to = ? AND r.assigned_role = 'police') 
                 OR (r.assigned_role = 'police' AND r.assigned_to IS NULL)
                 OR r.assigned_role IS NULL";
    
    $params = [$_SESSION['user_id']];
    
    if ($statusFilter != 'all') {
        $query .= " AND r.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($typeFilter != 'all') {
        $query .= " AND r.report_type = ?";
        $params[] = $typeFilter;
    }
    
    if ($priorityFilter != 'all') {
        $query .= " AND r.priority = ?";
        $params[] = $priorityFilter;
    }
    
    if ($assignedFilter == 'me') {
        $query .= " AND r.assigned_to = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($assignedFilter == 'unassigned') {
        $query .= " AND r.assigned_to IS NULL";
    }
    
    if ($search) {
        $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " GROUP BY r.id ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM reports WHERE assigned_to = ? AND assigned_role = 'police' GROUP BY status");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE assigned_to = ? AND assigned_role = 'police'");
    $stmt->execute([$_SESSION['user_id']]);
    $assignedCount = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $reports = [];
    $stats = [];
    $assignedCount = 0;
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
    <title>Manajemen Laporan - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
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
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
            transition: all 0.3s ease;
        }
        
        .search-box button:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .reports-table {
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .reports-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th {
            background: rgba(253, 121, 121, 0.1);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .reports-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(253, 121, 121, 0.1);
        }
        
        .reports-table tr:hover {
            background: rgba(253, 121, 121, 0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-status {
            color: white;
        }
        
        .badge-assigned {
            background: rgba(66, 165, 245, 0.2);
            color: #42A5F5;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: rgba(66, 165, 245, 0.2);
            color: #42A5F5;
        }
        
        .btn-view:hover {
            background: rgba(66, 165, 245, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
        <main class="main-content">
            <div class="page-header">
                <h1>Manajemen Laporan</h1>
                <div>
                    <span style="color: var(--color-text-light);">Total: <?php echo count($reports); ?> laporan</span>
                    <?php if ($assignedCount > 0): ?>
                        <span style="margin-left: 15px; padding: 5px 12px; background: rgba(66, 165, 245, 0.2); color: #42A5F5; border-radius: 8px; font-size: 12px; font-weight: 600;">
                            <?php echo $assignedCount; ?> Assigned to Me
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Semua</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $statusFilter == 'in_progress' ? 'selected' : ''; ?>>Sedang Diproses</option>
                                <option value="resolved" <?php echo $statusFilter == 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Tipe Laporan</label>
                            <select name="type">
                                <option value="all" <?php echo $typeFilter == 'all' ? 'selected' : ''; ?>>Semua</option>
                                <option value="bullying" <?php echo $typeFilter == 'bullying' ? 'selected' : ''; ?>>Perundungan</option>
                                <option value="violence" <?php echo $typeFilter == 'violence' ? 'selected' : ''; ?>>Kekerasan</option>
                                <option value="harassment" <?php echo $typeFilter == 'harassment' ? 'selected' : ''; ?>>Pelecehan</option>
                                <option value="abuse" <?php echo $typeFilter == 'abuse' ? 'selected' : ''; ?>>Abuse</option>
                                <option value="other" <?php echo $typeFilter == 'other' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Prioritas</label>
                            <select name="priority">
                                <option value="all" <?php echo $priorityFilter == 'all' ? 'selected' : ''; ?>>Semua</option>
                                <option value="low" <?php echo $priorityFilter == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $priorityFilter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $priorityFilter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo $priorityFilter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Assignment</label>
                            <select name="assigned">
                                <option value="all" <?php echo $assignedFilter == 'all' ? 'selected' : ''; ?>>Semua</option>
                                <option value="me" <?php echo $assignedFilter == 'me' ? 'selected' : ''; ?>>Assigned to Me</option>
                                <option value="unassigned" <?php echo $assignedFilter == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                            </select>
                        </div>
                    </div>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Cari judul atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">Cari</button>
                        <a href="reports.php" class="btn-small" style="background: rgba(255, 255, 255, 0.6); color: var(--color-text); padding: 10px 20px; border: 2px solid rgba(253, 121, 121, 0.2); border-radius: 10px; text-decoration: none;">Reset</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($reports)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?php echo icon('reports', '', 64, 64); ?></div>
                    <h3>Tidak Ada Laporan</h3>
                    <p>Tidak ada laporan yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="reports-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Prioritas</th>
                                <th>Pelapor</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['title']); ?></strong>
                                        <?php if ($report['attachment_count'] > 0): ?>
                                            <span style="color: var(--color-text-light); font-size: 12px; display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 14, 14); ?> <?php echo $report['attachment_count']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($report['assigned_to'] == $_SESSION['user_id']): ?>
                                            <span class="badge badge-assigned" style="margin-left: 10px;">Assigned to Me</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $reportTypeLabels[$report['report_type']] ?? $report['report_type']; ?></td>
                                    <td>
                                        <span class="badge badge-status" style="background: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                                            <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: rgba(253, 121, 121, 0.2); color: var(--color-primary);">
                                            <?php echo ucfirst($report['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($report['is_anonymous']): ?>
                                            <span style="color: var(--color-text-light);">Anonim</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($report['reporter_name'] ?? $report['reporter_username'] ?? 'N/A'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($report['created_at']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="report-detail.php?id=<?php echo $report['id']; ?>" class="btn-small btn-view">Lihat</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

