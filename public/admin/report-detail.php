<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

// Check if user is logged in and is admin
requireLogin();
if (!canViewAllReports() || !canProcessReport()) {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['update_status'])) {
            $newStatus = $_POST['status'] ?? '';
            $oldStatus = $_POST['old_status'] ?? '';
            
            if (in_array($newStatus, ['pending', 'in_progress', 'resolved', 'rejected'])) {
                // Update report status
                $stmt = $conn->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $report_id]);
                
                // Add to status history
                $stmt = $conn->prepare("
                    INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, notes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $notes = $_POST['status_notes'] ?? '';
                $stmt->execute([$report_id, $oldStatus, $newStatus, $_SESSION['user_id'], $notes]);
                
                // If resolved or rejected, set resolved_at and resolved_by
                if ($newStatus == 'resolved' || $newStatus == 'rejected') {
                    $stmt = $conn->prepare("UPDATE reports SET resolved_at = NOW(), resolved_by = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $report_id]);
                }
                
                // If rejected, save rejection reason
                if ($newStatus == 'rejected') {
                    $rejectionReason = $_POST['rejection_reason'] ?? '';
                    if ($rejectionReason) {
                        $stmt = $conn->prepare("UPDATE reports SET rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejectionReason, $report_id]);
                    }
                }
                
                $success = 'Status berhasil diupdate!';
            }
        } elseif (isset($_POST['assign_report'])) {
            $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $assignedRole = $_POST['assigned_role'] ?? null;
            
            if ($assignedTo && $assignedRole) {
                $stmt = $conn->prepare("UPDATE reports SET assigned_to = ?, assigned_role = ? WHERE id = ?");
                $stmt->execute([$assignedTo, $assignedRole, $report_id]);
                $success = 'Laporan berhasil di-assign!';
            } elseif ($assignedTo === null) {
                // Unassign
                $stmt = $conn->prepare("UPDATE reports SET assigned_to = NULL, assigned_role = NULL WHERE id = ?");
                $stmt->execute([$report_id]);
                $success = 'Assignment berhasil dihapus!';
            }
        } elseif (isset($_POST['update_admin_notes'])) {
            $adminNotes = $_POST['admin_notes'] ?? '';
            $stmt = $conn->prepare("UPDATE reports SET admin_notes = ? WHERE id = ?");
            $stmt->execute([$adminNotes, $report_id]);
            $success = 'Catatan admin berhasil diupdate!';
        } elseif (isset($_POST['update_resolution_notes'])) {
            $resolutionNotes = $_POST['resolution_notes'] ?? '';
            $stmt = $conn->prepare("UPDATE reports SET resolution_notes = ? WHERE id = ?");
            $stmt->execute([$resolutionNotes, $report_id]);
            $success = 'Catatan tindak lanjut berhasil diupdate!';
        } elseif (isset($_POST['upload_document'])) {
            if (!empty($_FILES['document']['name'])) {
                $uploadDir = '../../../uploads/reports/' . $report_id . '/admin/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                $fileType = $_FILES['document']['type'];
                $fileSize = $_FILES['document']['size'];
                $filename = $_FILES['document']['name'];
                $tmpName = $_FILES['document']['tmp_name'];
                
                if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
                    $newFilename = uniqid() . '_' . time() . '.' . $fileExt;
                    $filePath = $uploadDir . $newFilename;
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $stmt = $conn->prepare("
                            INSERT INTO report_attachments (report_id, file_name, file_path, file_size, file_type, uploaded_by, attachment_type)
                            VALUES (?, ?, ?, ?, ?, ?, 'admin_upload')
                        ");
                        $relativePath = 'uploads/reports/' . $report_id . '/admin/' . $newFilename;
                        $stmt->execute([$report_id, $filename, $relativePath, $fileSize, $fileType, $_SESSION['user_id']]);
                        $success = 'Dokumen berhasil diupload!';
                    }
                } else {
                    $error = 'File tidak valid atau terlalu besar!';
                }
            }
        }
    } catch(PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get report details
try {
    $conn = getDBConnection();
    
    // Get report
    $stmt = $conn->prepare("
        SELECT r.*, 
        u.username as reporter_username,
        COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) as reporter_name,
        resolved_user.username as resolved_username
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
        LEFT JOIN police_details pd ON u.id = pd.user_id AND u.role = 'police'
        LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
        LEFT JOIN general_user_details gud ON u.id = gud.user_id AND u.role = 'general_user'
        LEFT JOIN users resolved_user ON r.resolved_by = resolved_user.id
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        redirectTo(BASE_URL . '/admin/reports.php');
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
    
    // Get police users for assignment
    $stmt = $conn->prepare("
        SELECT u.id, pd.full_name
        FROM users u
        JOIN police_details pd ON u.id = pd.user_id
        WHERE u.role = 'police' AND u.status = 'active'
        ORDER BY pd.full_name
    ");
    $stmt->execute();
    $policeUsers = $stmt->fetchAll();
    
    // Get psychologist users for assignment
    $stmt = $conn->prepare("
        SELECT u.id, psd.full_name
        FROM users u
        JOIN psychologist_details psd ON u.id = psd.user_id
        WHERE u.role = 'psychologist' AND u.status = 'active'
        ORDER BY psd.full_name
    ");
    $stmt->execute();
    $psychologistUsers = $stmt->fetchAll();
    
    // Get assigned user info
    $assignedUser = null;
    if ($report['assigned_to']) {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.role,
            COALESCE(ad.full_name, pd.full_name, psd.full_name, gud.full_name) as full_name
            FROM users u
            LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
            LEFT JOIN police_details pd ON u.id = pd.user_id AND u.role = 'police'
            LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
            LEFT JOIN general_user_details gud ON u.id = gud.user_id AND u.role = 'general_user'
            WHERE u.id = ?
        ");
        $stmt->execute([$report['assigned_to']]);
        $assignedUser = $stmt->fetch();
    }
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $report = null;
    $attachments = [];
    $statusHistory = [];
    $policeUsers = [];
    $psychologistUsers = [];
    $assignedUser = null;
}

if (!$report) {
    redirectTo(BASE_URL . '/admin/reports.php');
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .action-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .action-section h3 {
            font-size: 18px;
            color: var(--color-text);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text);
            font-size: 14px;
        }
        
        .form-group select,
        .form-group textarea,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid rgba(211, 78, 78, 0.3);
            border-radius: 10px;
            background: #ffffff;
            color: var(--color-text);
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group select:focus,
        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: #D34E4E;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(211, 78, 78, 0.15);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #D34E4E !important;
            color: white !important;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3) !important;
        }
        
        .btn-primary:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4) !important;
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-danger {
            background: #EF5350;
            color: white;
        }
        
        .btn-danger:hover {
            background: #D32F2F;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            border: 2px solid rgba(253, 121, 121, 0.2);
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
        
        .description-box {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            line-height: 1.8;
            color: var(--color-text);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #d32f2f;
        }
        
        .alert-success {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1>Detail Laporan #<?php echo str_pad($report_id, 6, '0', STR_PAD_LEFT); ?></h1>
                <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-secondary">← Kembali</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Update Status -->
            <div class="action-section">
                <h3>Update Status</h3>
                <form method="POST">
                    <input type="hidden" name="old_status" value="<?php echo htmlspecialchars($report['status']); ?>">
                    <div class="form-group">
                        <label>Status Baru</label>
                        <select name="status" required>
                            <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $report['status'] == 'in_progress' ? 'selected' : ''; ?>>Sedang Diproses</option>
                            <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="rejected" <?php echo $report['status'] == 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="status_notes" placeholder="Tambahkan catatan untuk perubahan status ini..."></textarea>
                    </div>
                    <div id="rejectionReasonGroup" style="display: none;">
                        <div class="form-group">
                            <label>Alasan Penolakan *</label>
                            <textarea name="rejection_reason" placeholder="Jelaskan alasan penolakan laporan ini..." required></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
            
            <!-- Assign Report -->
            <div class="action-section">
                <h3>Assign Laporan</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Assign ke</label>
                        <select name="assigned_role" id="assignedRole">
                            <option value="">-- Pilih Role --</option>
                            <option value="police" <?php echo $report['assigned_role'] == 'police' ? 'selected' : ''; ?>>Police</option>
                            <option value="psychologist" <?php echo $report['assigned_role'] == 'psychologist' ? 'selected' : ''; ?>>Psychologist</option>
                        </select>
                    </div>
                    <div class="form-group" id="userSelectGroup" style="display: none;">
                        <label>Pilih User</label>
                        <select name="assigned_to" id="assignedTo">
                            <option value="">-- Pilih User --</option>
                        </select>
                    </div>
                    <?php if ($assignedUser): ?>
                        <div style="background: rgba(253, 121, 121, 0.1); padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                            <strong>Saat ini di-assign ke:</strong> <?php echo htmlspecialchars($assignedUser['full_name']); ?> 
                            (<?php echo htmlspecialchars($assignedUser['role']); ?>)
                        </div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" name="assign_report" class="btn btn-primary">Assign</button>
                        <?php if ($assignedUser): ?>
                            <button type="submit" name="assign_report" class="btn btn-danger" onclick="document.getElementById('assignedTo').value = ''; document.getElementById('assignedRole').value = '';">Unassign</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Admin Notes -->
            <div class="action-section">
                <h3>Catatan Admin</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Catatan Internal (Hanya terlihat oleh admin)</label>
                        <textarea name="admin_notes" placeholder="Tambahkan catatan internal untuk laporan ini..."><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_admin_notes" class="btn btn-primary">Simpan Catatan</button>
                    </div>
                </form>
            </div>
            
            <!-- Resolution Notes -->
            <div class="action-section">
                <h3>Catatan Tindak Lanjut</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Catatan Tindak Lanjut</label>
                        <textarea name="resolution_notes" placeholder="Tambahkan catatan tindak lanjut..."><?php echo htmlspecialchars($report['resolution_notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_resolution_notes" class="btn btn-primary">Simpan Catatan</button>
                    </div>
                </form>
            </div>
            
            <!-- Upload Document -->
            <div class="action-section">
                <h3>Upload Dokumen Tindak Lanjut</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Dokumen (PDF, DOC, DOCX - Max 5MB)</label>
                        <input type="file" name="document" accept=".pdf,.doc,.docx" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="upload_document" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
            
            <!-- Report Info (Similar to user detail page) -->
            <div class="info-section">
                <h3>Informasi Laporan</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="badge badge-status" style="background: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                            <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Prioritas</span>
                        <span class="badge" style="background: <?php echo $priorityColors[$report['priority']] ?? '#666'; ?>; color: white;">
                            <?php echo $priorityLabels[$report['priority']] ?? $report['priority']; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tipe Laporan</span>
                        <span class="info-value"><?php echo $reportTypeLabels[$report['report_type']] ?? $report['report_type']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Identitas</span>
                        <span class="info-value"><?php echo $report['is_anonymous'] ? 'Anonim' : 'Beridentitas'; ?></span>
                    </div>
                    <?php if (!$report['is_anonymous'] && $report['reporter_name']): ?>
                        <div class="info-item">
                            <span class="info-label">Pelapor</span>
                            <span class="info-value"><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Tanggal Dibuat</span>
                        <span class="info-value"><?php echo formatDateTime($report['created_at']); ?></span>
                    </div>
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
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($attachments as $attachment): ?>
                            <div style="background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(253, 121, 121, 0.2); border-radius: 12px; padding: 15px; text-align: center;">
                                <a href="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" style="color: var(--color-text); text-decoration: none;">
                                    <div style="margin-bottom: 10px; display: flex; justify-content: center;"><?php echo icon('reports', '', 32, 32); ?></div>
                                    <div style="font-size: 12px; word-break: break-all;"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
                                    <div style="font-size: 10px; color: var(--color-text-light); margin-top: 5px;">
                                        <?php echo number_format($attachment['file_size'] / 1024, 2); ?> KB
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Handle assigned role change
        document.getElementById('assignedRole')?.addEventListener('change', function() {
            const role = this.value;
            const userSelect = document.getElementById('assignedTo');
            const userSelectGroup = document.getElementById('userSelectGroup');
            
            if (role) {
                userSelectGroup.style.display = 'block';
                userSelect.innerHTML = '<option value="">-- Pilih User --</option>';
                
                <?php if (!empty($policeUsers)): ?>
                    if (role === 'police') {
                        <?php foreach ($policeUsers as $user): ?>
                            userSelect.innerHTML += '<option value="<?php echo $user['id']; ?>" <?php echo ($assignedUser && $assignedUser['id'] == $user['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>';
                        <?php endforeach; ?>
                    }
                <?php endif; ?>
                
                <?php if (!empty($psychologistUsers)): ?>
                    if (role === 'psychologist') {
                        <?php foreach ($psychologistUsers as $user): ?>
                            userSelect.innerHTML += '<option value="<?php echo $user['id']; ?>" <?php echo ($assignedUser && $assignedUser['id'] == $user['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>';
                        <?php endforeach; ?>
                    }
                <?php endif; ?>
            } else {
                userSelectGroup.style.display = 'none';
            }
        });
        
        // Show rejection reason when rejected is selected
        document.querySelector('select[name="status"]')?.addEventListener('change', function() {
            const rejectionGroup = document.getElementById('rejectionReasonGroup');
            if (this.value === 'rejected') {
                rejectionGroup.style.display = 'block';
                rejectionGroup.querySelector('textarea').required = true;
            } else {
                rejectionGroup.style.display = 'none';
                rejectionGroup.querySelector('textarea').required = false;
            }
        });
        
        // Initialize
        if (document.getElementById('assignedRole')?.value) {
            document.getElementById('assignedRole').dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>

