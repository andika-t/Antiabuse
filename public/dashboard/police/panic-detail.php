<?php
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

requireLogin();
if (getUserRole() != 'police') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$alertId = $_GET['id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // Get panic alert details - semua panic alerts bisa diakses
    $stmt = $conn->prepare("
        SELECT pa.*, 
               gud.full_name as user_name,
               gud.phone as user_phone
        FROM panic_alerts pa
        LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
        WHERE pa.id = ?
    ");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch();
    
    if (!$alert) {
        header('Location: ' . BASE_URL . '/dashboard/police/index.php?error=alert_not_found');
        exit();
    }
    
    // Handle response
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'respond') {
            // Update status to responded
            $stmt = $conn->prepare("
                UPDATE panic_alerts 
                SET status = 'responded', 
                    responded_by = ?,
                    responded_at = NOW(),
                    notes = ?
                WHERE id = ?
            ");
            $notes = $_POST['notes'] ?? '';
            $stmt->execute([$_SESSION['user_id'], $notes, $alertId]);
            
            header('Location: ' . BASE_URL . '/dashboard/police/panic-detail.php?id=' . $alertId . '&success=responded');
            exit();
            
        } elseif ($action === 'resolve') {
            // Update status to resolved
            $stmt = $conn->prepare("
                UPDATE panic_alerts 
                SET status = 'resolved', 
                    resolved_at = NOW(),
                    notes = ?
                WHERE id = ?
            ");
            $notes = $_POST['notes'] ?? '';
            $stmt->execute([$notes, $alertId]);
            
            header('Location: ' . BASE_URL . '/dashboard/police/panic-detail.php?id=' . $alertId . '&success=resolved');
            exit();
        }
    }
    
    // Reload alert data after update
    $stmt = $conn->prepare("
        SELECT pa.*, 
               gud.full_name as user_name,
               gud.phone as user_phone
        FROM panic_alerts pa
        LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
        WHERE pa.id = ?
    ");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
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
    <title>Detail Panic Alert - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div style="max-width: 900px; margin: 0 auto;">
                <div style="margin-bottom: 30px;">
                    <a href="<?php echo BASE_URL; ?>/dashboard/police/index.php" style="color: var(--color-primary); text-decoration: none; font-size: 14px;">
                        ← Kembali ke Dashboard
                    </a>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background: rgba(102, 187, 106, 0.1); border: 1px solid rgba(102, 187, 106, 0.3); border-radius: 10px; color: #66BB6A;">
                        <?php 
                        if ($_GET['success'] === 'responded') {
                            echo 'Panic alert berhasil ditandai sebagai ditanggapi!';
                        } elseif ($_GET['success'] === 'resolved') {
                            echo 'Panic alert berhasil diselesaikan!';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px; padding: 15px; background: rgba(239, 83, 80, 0.1); border: 1px solid rgba(239, 83, 80, 0.3); border-radius: 10px; color: #EF5350;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="dashboard-card" style="background: rgba(239, 83, 80, 0.1); border: 2px solid rgba(239, 83, 80, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="color: #EF5350; margin: 0; display: flex; align-items: center; gap: 8px;"><?php echo icon('panic', '', 28, 28); ?> Panic Alert Detail</h2>
                        <span class="badge" style="background: <?php echo $statusColors[$alert['status']] ?? '#666'; ?>; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 600;">
                            <?php echo $statusLabels[$alert['status']] ?? $alert['status']; ?>
                        </span>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: var(--color-text);">Informasi User</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <strong style="color: var(--color-text-light); font-size: 12px;">Nama:</strong>
                                <p style="margin: 5px 0; color: var(--color-text);"><?php echo htmlspecialchars($alert['user_name'] ?? 'Tidak diketahui'); ?></p>
                            </div>
                            <?php if ($alert['user_phone']): ?>
                                <div>
                                    <strong style="color: var(--color-text-light); font-size: 12px;">Telepon:</strong>
                                    <p style="margin: 5px 0; color: var(--color-text);"><?php echo htmlspecialchars($alert['user_phone']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: var(--color-text);">Informasi Lokasi</h3>
                        <?php if ($alert['location']): ?>
                            <p style="margin: 5px 0; color: var(--color-text); display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> <?php echo htmlspecialchars($alert['location']); ?></p>
                        <?php endif; ?>
                        <?php if ($alert['latitude'] && $alert['longitude']): ?>
                            <p style="margin: 5px 0; color: var(--color-text);">
                                Koordinat: <?php echo htmlspecialchars($alert['latitude']); ?>, <?php echo htmlspecialchars($alert['longitude']); ?>
                            </p>
                            <a href="https://www.google.com/maps?q=<?php echo urlencode($alert['latitude'] . ',' . $alert['longitude']); ?>" 
                               target="_blank" 
                               style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: var(--color-primary); color: white; text-decoration: none; border-radius: 8px; font-size: 12px;">
                                Buka di Google Maps
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0; color: var(--color-text);">Waktu Aktivasi</h3>
                        <p style="margin: 5px 0; color: var(--color-text);">
                            <?php echo formatDateTime($alert['created_at'], 'd M Y H:i:s'); ?>
                        </p>
                        <?php if ($alert['responded_at']): ?>
                            <p style="margin: 5px 0; color: var(--color-text-light); font-size: 12px;">
                                Ditanggapi: <?php echo formatDateTime($alert['responded_at'], 'd M Y H:i:s'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($alert['resolved_at']): ?>
                            <p style="margin: 5px 0; color: var(--color-text-light); font-size: 12px;">
                                Diselesaikan: <?php echo formatDateTime($alert['resolved_at'], 'd M Y H:i:s'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($alert['notes']): ?>
                        <div style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                            <h3 style="margin-top: 0; color: var(--color-text);">Catatan</h3>
                            <p style="margin: 5px 0; color: var(--color-text);"><?php echo nl2br(htmlspecialchars($alert['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($alert['status'] === 'active'): ?>
                        <form method="POST" style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px;">
                            <h3 style="margin-top: 0; color: var(--color-text);">Tanggapi Panic Alert</h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; color: var(--color-text); font-weight: 600;">Catatan:</label>
                                <textarea name="notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid rgba(253, 121, 121, 0.2); border-radius: 8px; font-family: inherit; resize: vertical;" placeholder="Tambahkan catatan tindakan yang dilakukan..."></textarea>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" name="action" value="respond" style="padding: 12px 24px; background: #42A5F5; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                    Tandai sebagai Ditanggapi
                                </button>
                                <button type="submit" name="action" value="resolve" style="padding: 12px 24px; background: #66BB6A; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                    Selesaikan
                                </button>
                            </div>
                        </form>
                    <?php elseif ($alert['status'] === 'responded'): ?>
                        <form method="POST" style="background: rgba(255, 255, 255, 0.6); border-radius: 15px; padding: 20px;">
                            <h3 style="margin-top: 0; color: var(--color-text);">Selesaikan Panic Alert</h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; color: var(--color-text); font-weight: 600;">Catatan Penyelesaian:</label>
                                <textarea name="notes" rows="4" style="width: 100%; padding: 10px; border: 1px solid rgba(253, 121, 121, 0.2); border-radius: 8px; font-family: inherit; resize: vertical;" placeholder="Tambahkan catatan penyelesaian..."></textarea>
                            </div>
                            <button type="submit" name="action" value="resolve" style="padding: 12px 24px; background: #66BB6A; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                Selesaikan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

