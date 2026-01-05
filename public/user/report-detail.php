<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

// Check if user is logged in and is general user
requireLogin();
if (getUserRole() != 'general_user') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get report details
try {
    $conn = getDBConnection();
    
    // Get report
    $stmt = $conn->prepare("
        SELECT r.*, u.username
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$report_id, $_SESSION['user_id']]);
    $report = $stmt->fetch();
    
    if (!$report) {
        header('Location: ' . BASE_URL . '/user/reports.php');
        exit();
    }
    
    // Get attachments
    $stmt = $conn->prepare("SELECT * FROM report_attachments WHERE report_id = ? ORDER BY created_at ASC");
    $stmt->execute([$report_id]);
    $attachments = $stmt->fetchAll();
    
    // Get status history
    $stmt = $conn->prepare("
        SELECT rsh.*, u.username, u.role
        FROM report_status_history rsh
        LEFT JOIN users u ON rsh.changed_by = u.id
        WHERE rsh.report_id = ?
        ORDER BY rsh.created_at DESC
    ");
    $stmt->execute([$report_id]);
    $statusHistory = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $report = null;
    $attachments = [];
    $statusHistory = [];
}

if (!$report) {
    header('Location: ' . BASE_URL . '/user/reports.php');
    exit();
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

$priorityLabels = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent'
];

$priorityColors = [
    'low' => '#66BB6A',
    'medium' => '#FFA726',
    'high' => '#EF5350',
    'urgent' => '#D32F2F'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .detail-header h1 {
            font-size: 32px;
            color: var(--color-text);
        }
        
        .info-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .info-section h3 {
            font-size: 20px;
            color: var(--color-text);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--color-text-light);
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--color-text);
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .badge-status {
            color: white;
        }
        
        .badge-priority {
            color: white;
        }
        
        .description-box {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            line-height: 1.8;
            color: var(--color-text);
        }
        
        .attachments-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .attachment-item {
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .attachment-item:hover {
            border-color: var(--color-primary);
            transform: translateY(-3px);
        }
        
        .attachment-item a {
            color: var(--color-text);
            text-decoration: none;
            display: block;
        }
        
        .attachment-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .attachment-name {
            font-size: 12px;
            word-break: break-all;
        }
        
        .notes-section {
            background: rgba(253, 121, 121, 0.05);
            border-left: 4px solid var(--color-primary);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .notes-section h4 {
            font-size: 16px;
            color: var(--color-text);
            margin-bottom: 10px;
        }
        
        .notes-section p {
            color: var(--color-text-light);
            line-height: 1.8;
        }
        
        .timeline {
            margin-top: 20px;
        }
        
        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-left: 2px solid rgba(253, 121, 121, 0.2);
            padding-left: 20px;
            margin-left: 10px;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--color-primary);
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-date {
            font-size: 12px;
            color: var(--color-text-light);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            border: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .btn-secondary:hover {
            border-color: var(--color-primary);
            background: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <div class="detail-container">
        <div class="detail-header">
            <h1>Detail Laporan</h1>
            <a href="<?php echo BASE_URL; ?>/user/reports.php" class="btn btn-secondary">← Kembali</a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Report Info -->
        <div class="info-section">
            <h3>Informasi Laporan</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nomor Laporan</span>
                    <span class="info-value">#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="badge badge-status" style="background: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                        <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Prioritas</span>
                    <span class="badge badge-priority" style="background: <?php echo $priorityColors[$report['priority']] ?? '#666'; ?>;">
                        <?php echo $priorityLabels[$report['priority']] ?? $report['priority']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipe Laporan</span>
                    <span class="info-value"><?php echo $reportTypeLabels[$report['report_type']] ?? $report['report_type']; ?></span>
                    <?php if ($report['report_type'] == 'other' && $report['report_type_other']): ?>
                        <span class="info-value" style="font-size: 14px; color: var(--color-text-light);">
                            (<?php echo htmlspecialchars($report['report_type_other']); ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Dibuat</span>
                    <span class="info-value"><?php echo formatDateTime($report['created_at']); ?></span>
                </div>
                <?php if ($report['location']): ?>
                    <div class="info-item">
                        <span class="info-label">Lokasi</span>
                        <span class="info-value"><?php echo htmlspecialchars($report['location']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($report['incident_date']): ?>
                    <div class="info-item">
                        <span class="info-label">Tanggal Kejadian</span>
                        <span class="info-value"><?php echo formatDate($report['incident_date']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($report['incident_time']): ?>
                    <div class="info-item">
                        <span class="info-label">Waktu Kejadian</span>
                        <span class="info-value"><?php echo formatTime($report['incident_time']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Description -->
        <div class="info-section">
            <h3>Judul & Deskripsi</h3>
            <div style="margin-bottom: 15px;">
                <span class="info-label">Judul</span>
                <div style="font-size: 20px; font-weight: 600; color: var(--color-text); margin-top: 5px;">
                    <?php echo htmlspecialchars($report['title']); ?>
                </div>
            </div>
            <div>
                <span class="info-label">Deskripsi Kejadian</span>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
            <div class="info-section">
                <h3>Bukti & Dokumen</h3>
                <div class="attachments-list">
                    <?php foreach ($attachments as $attachment): ?>
                        <div class="attachment-item">
                            <a href="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank">
                                <div class="attachment-icon">
                                    <?php
                                    $ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        echo icon('reports', '', 32, 32);
                                    } elseif ($ext == 'pdf') {
                                        echo icon('reports', '', 32, 32);
                                    } else {
                                        echo icon('reports', '', 32, 32);
                                    }
                                    ?>
                                </div>
                                <div class="attachment-name"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
                                <div style="font-size: 10px; color: var(--color-text-light); margin-top: 5px;">
                                    <?php echo number_format($attachment['file_size'] / 1024, 2); ?> KB
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Additional Info -->
        <?php if ($report['additional_info']): ?>
            <div class="info-section">
                <h3>Informasi Tambahan</h3>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($report['additional_info'])); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Admin Notes -->
        <?php if ($report['admin_notes']): ?>
            <div class="info-section">
                <h3>Catatan dari Admin</h3>
                <div class="notes-section">
                    <p><?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Resolution Notes -->
        <?php if ($report['resolution_notes']): ?>
            <div class="info-section">
                <h3>Catatan Tindak Lanjut</h3>
                <div class="notes-section">
                    <p><?php echo nl2br(htmlspecialchars($report['resolution_notes'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Rejection Reason -->
        <?php if ($report['status'] == 'rejected' && $report['rejection_reason']): ?>
            <div class="info-section">
                <h3>Alasan Penolakan</h3>
                <div class="notes-section" style="background: rgba(239, 83, 80, 0.1); border-left-color: #EF5350;">
                    <p><?php echo nl2br(htmlspecialchars($report['rejection_reason'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Status History -->
        <?php if (!empty($statusHistory)): ?>
            <div class="info-section">
                <h3>Riwayat Status</h3>
                <div class="timeline">
                    <?php foreach ($statusHistory as $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <div style="font-weight: 600; color: var(--color-text); margin-bottom: 5px;">
                                    Status berubah: <?php echo htmlspecialchars($history['old_status'] ?? 'N/A'); ?> → <?php echo htmlspecialchars($history['new_status']); ?>
                                </div>
                                <?php if ($history['notes']): ?>
                                    <div style="color: var(--color-text-light); margin-top: 5px;">
                                        <?php echo nl2br(htmlspecialchars($history['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="timeline-date">
                                    Oleh: <?php echo htmlspecialchars($history['username'] ?? 'System'); ?> (<?php echo htmlspecialchars($history['role'] ?? ''); ?>)
                                    • <?php echo formatDateTime($history['created_at']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo BASE_URL; ?>/user/reports.php" class="btn btn-secondary">Kembali ke Daftar Laporan</a>
        </div>
    </div>
</body>
</html>

