<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

// Check if user is logged in and is general user
requireLogin();
if (!canCreateReport()) {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get report info
$report = null;
if ($report_id > 0) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT r.*, u.username 
            FROM reports r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = ? AND (r.user_id = ? OR r.user_id IS NULL)
        ");
        $stmt->execute([$report_id, $_SESSION['user_id']]);
        $report = $stmt->fetch();
    } catch(PDOException $e) {
        // Error
    }
}

if (!$report) {
    header('Location: ' . BASE_URL . '/user/reports.php');
    exit();
}

// Clear draft from session
unset($_SESSION['report_draft']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Berhasil Dikirim - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <script>
        // Clear draft from sessionStorage when success page loads
        if (typeof(Storage) !== "undefined") {
            sessionStorage.removeItem('report_draft');
        }
    </script>
    <style>
        .success-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 0 20px;
            text-align: center;
        }
        
        .success-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 60px 40px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--glass-border);
        }
        
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .success-card h1 {
            font-size: 32px;
            color: var(--color-text);
            margin-bottom: 15px;
        }
        
        .success-card p {
            font-size: 16px;
            color: var(--color-text-light);
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .report-number {
            background: rgba(253, 121, 121, 0.1);
            border: 2px solid rgba(253, 121, 121, 0.3);
            border-radius: 15px;
            padding: 15px 30px;
            margin: 20px 0;
            display: inline-block;
        }
        
        .report-number strong {
            color: var(--color-primary);
            font-size: 18px;
        }
        
        .success-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
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
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon"><?php echo icon('completed', '', 80, 80); ?></div>
            <h1>Laporan Berhasil Dikirim!</h1>
            <p>Terima kasih telah melaporkan kejadian ini. Kami akan meninjau laporan Anda segera.</p>
            
            <?php if (!$report['is_anonymous']): ?>
                <div class="report-number">
                    <strong>Nomor Laporan: #<?php echo str_pad($report_id, 6, '0', STR_PAD_LEFT); ?></strong>
                </div>
                <p style="font-size: 14px; color: var(--color-text-light);">
                    Simpan nomor laporan ini untuk melacak status laporan Anda.
                </p>
            <?php else: ?>
                <p style="font-size: 14px; color: var(--color-text-light);">
                    Laporan Anda telah dikirim secara anonim. Kami akan meninjau laporan ini segera.
                </p>
            <?php endif; ?>
            
            <div class="success-actions">
                <a href="<?php echo BASE_URL; ?>/user/reports.php" class="btn btn-primary">Lihat Daftar Laporan</a>
                <a href="<?php echo BASE_URL; ?>/user/create-report.php" class="btn btn-secondary">Buat Laporan Baru</a>
            </div>
        </div>
    </div>
</body>
</html>

