<?php
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

requireLogin();
if (getUserRole() != 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$alertId = $_GET['id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // Get panic alert details
    $stmt = $conn->prepare("
        SELECT pa.*, 
               gud.full_name as user_name,
               gud.phone as user_phone,
               pd.full_name as responded_by_name
        FROM panic_alerts pa
        LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
        LEFT JOIN police_details pd ON pa.responded_by = pd.user_id
        WHERE pa.id = ?
    ");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch();
    
    if (!$alert) {
        header('Location: panic-button.php?error=not_found');
        exit();
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
    
} catch(PDOException $e) {
    header('Location: panic-button.php?error=system_error');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Panic Alert - AntiAbuse</title>
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
        
        .btn-back {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            color: var(--color-text);
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            display: inline-block;
            border: 1px solid var(--glass-border);
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .detail-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            margin-bottom: 20px;
        }
        
        .detail-card h3 {
            color: var(--color-text);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-item strong {
            color: var(--color-text-light);
            font-size: 12px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .detail-item p,
        .detail-item span {
            color: var(--color-text);
            margin: 0;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .btn-map {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #FFC107;
            color: var(--color-text);
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-map:hover {
            background: #FFB300;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Detail Panic Alert #<?php echo str_pad($alert['id'], 4, '0', STR_PAD_LEFT); ?></h1>
                <a href="panic-button.php" class="btn-back">← Kembali</a>
            </div>
            
            <div class="detail-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Informasi Alert</h3>
                    <span class="badge" style="background: <?php echo $statusColors[$alert['status']] ?? '#666'; ?>; color: white;">
                        <?php echo $statusLabels[$alert['status']] ?? $alert['status']; ?>
                    </span>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>User</strong>
                        <p><?php echo htmlspecialchars($alert['user_name'] ?? 'User'); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Telepon</strong>
                        <p><?php echo htmlspecialchars($alert['user_phone'] ?? '-'); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Waktu</strong>
                        <p><?php echo formatDateTime($alert['created_at'], 'd M Y H:i:s'); ?></p>
                    </div>
                    <?php if ($alert['responded_by_name']): ?>
                    <div class="detail-item">
                        <strong>Ditanggapi Oleh</strong>
                        <p><?php echo htmlspecialchars($alert['responded_by_name']); ?></p>
                        <?php if ($alert['responded_at']): ?>
                            <span style="font-size: 11px; color: var(--color-text-light);">(<?php echo formatDateTime($alert['responded_at']); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($alert['resolved_at']): ?>
                    <div class="detail-item">
                        <strong>Diselesaikan</strong>
                        <p><?php echo formatDateTime($alert['resolved_at']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($alert['location'] || ($alert['latitude'] && $alert['longitude'])): ?>
            <div class="detail-card">
                <h3>Informasi Lokasi</h3>
                <?php if ($alert['location']): ?>
                <div class="detail-item" style="margin-bottom: 15px;">
                    <strong>Lokasi</strong>
                    <p><?php echo htmlspecialchars($alert['location']); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($alert['latitude'] && $alert['longitude']): ?>
                <div class="detail-item">
                    <strong>Koordinat</strong>
                    <p><?php echo htmlspecialchars($alert['latitude']); ?>, <?php echo htmlspecialchars($alert['longitude']); ?></p>
                    <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars($alert['latitude']); ?>,<?php echo htmlspecialchars($alert['longitude']); ?>" target="_blank" class="btn-map">Buka di Maps</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($alert['notes']): ?>
            <div class="detail-card">
                <h3>Catatan</h3>
                <p style="color: var(--color-text); margin: 0; padding: 15px; background: rgba(255, 255, 255, 0.5); border-radius: 10px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($alert['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>

