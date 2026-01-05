<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

// Check if user is logged in and is psychologist
requireLogin();
if (getUserRole() != 'psychologist') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

// Get statistics
try {
    $conn = getDBConnection();
    
    // Get psychologist name
    $stmt = $conn->prepare("SELECT full_name FROM psychologist_details WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $psychologistName = $stmt->fetch()['full_name'] ?? 'Psikolog';
    
    // Get education content statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM education_content WHERE author_id = ? AND author_role = 'psychologist'");
    $stmt->execute([$_SESSION['user_id']]);
    $totalContent = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $conn->prepare("SELECT SUM(views) as total FROM education_content WHERE author_id = ? AND author_role = 'psychologist'");
    $stmt->execute([$_SESSION['user_id']]);
    $totalViews = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM education_content WHERE author_id = ? AND author_role = 'psychologist' AND is_published = TRUE");
    $stmt->execute([$_SESSION['user_id']]);
    $publishedContent = $stmt->fetch()['count'] ?? 0;
    
    // Get recent content (last 5)
    $stmt = $conn->prepare("
        SELECT * FROM education_content 
        WHERE author_id = ? AND author_role = 'psychologist' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentContent = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading statistics: ' . $e->getMessage();
    $totalContent = 0;
    $totalViews = 0;
    $publishedContent = 0;
    $recentContent = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Psikolog - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboard-unified.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid psychologist-dashboard">
                <!-- Left Column -->
                <div class="card-col">
                    <!-- Education Content Stats Card -->
                    <div class="dashboard-card stats-card-enhanced">
                        <div class="card-header-enhanced">
                            <div class="card-icon-wrapper" style="background: linear-gradient(135deg, rgba(211, 78, 78, 0.15) 0%, rgba(253, 121, 121, 0.1) 100%);">
                                <?php echo icon('education', '', 28, 28); ?>
                            </div>
                            <h3>Statistik Konten</h3>
                        </div>
                        <div class="activity-stats">
                            <div class="big-number-enhanced"><?php echo number_format($totalContent); ?></div>
                            <p class="stats-label">Total Konten</p>
                            <div class="stats-details">
                                <div class="stat-detail-item">
                                    <div class="stat-detail-icon" style="background: rgba(76, 175, 80, 0.15); color: #4CAF50;">
                                        <?php echo icon('completed', '', 18, 18); ?>
                                    </div>
                                    <div class="stat-detail-content">
                                        <span class="stat-detail-label">Published</span>
                                        <strong class="stat-detail-value"><?php echo number_format($publishedContent); ?></strong>
                                    </div>
                                </div>
                                <div class="stat-detail-item">
                                    <div class="stat-detail-icon" style="background: rgba(33, 150, 243, 0.15); color: #2196F3;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </div>
                                    <div class="stat-detail-content">
                                        <span class="stat-detail-label">Total Views</span>
                                        <strong class="stat-detail-value"><?php echo number_format($totalViews); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="card-col">
                    <!-- Education Content Card -->
                    <div class="dashboard-card education-action-card">
                        <div class="card-header-enhanced">
                            <div class="card-icon-wrapper" style="background: linear-gradient(135deg, rgba(211, 78, 78, 0.2) 0%, rgba(253, 121, 121, 0.15) 100%);">
                                <?php echo icon('education', '', 28, 28); ?>
                            </div>
                            <h3 style="color: var(--color-primary);">Konten Edukasi</h3>
                        </div>
                        <p class="education-card-description">
                            Buat dan kelola konten edukasi untuk membantu pengguna memahami pencegahan abuse, tanda-tanda perundungan, dan cara mendapatkan bantuan.
                        </p>
                        <div class="education-action-buttons">
                            <a href="education.php" class="btn-education-action btn-manage">
                                <span class="btn-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                                    </svg>
                                </span>
                                Kelola Konten
                            </a>
                            <a href="education-form.php" class="btn-education-action btn-create">
                                <span class="btn-icon"><?php echo icon('create-report', '', 18, 18); ?></span>
                                + Buat Baru
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Content Card -->
                    <div class="dashboard-card recent-content-card">
                        <div class="card-header-enhanced">
                            <div class="card-icon-wrapper" style="background: linear-gradient(135deg, rgba(156, 39, 176, 0.15) 0%, rgba(186, 104, 200, 0.1) 100%);">
                                <?php echo icon('calendar', '', 28, 28); ?>
                            </div>
                            <h3>Konten Terbaru</h3>
                        </div>
                        <div class="schedule-list-enhanced">
                            <?php if (!empty($recentContent)): ?>
                                <?php foreach ($recentContent as $content): ?>
                                    <div class="schedule-item-enhanced">
                                        <div class="schedule-time-badge">
                                            <span class="time-icon"><?php echo icon('calendar', '', 14, 14); ?></span>
                                            <?php echo formatTime($content['created_at']); ?>
                                        </div>
                                        <div class="schedule-content-enhanced">
                                            <strong class="content-title"><?php echo htmlspecialchars($content['title']); ?></strong>
                                            <div class="content-meta">
                                                <span class="content-badge <?php echo $content['is_published'] ? 'badge-published' : 'badge-draft'; ?>">
                                                    <?php echo $content['is_published'] ? 'Published' : 'Draft'; ?>
                                                </span>
                                                <span class="content-views">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                    <?php echo number_format($content['views']); ?> views
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-content-state">
                                    <div class="empty-icon"><?php echo icon('education', '', 48, 48); ?></div>
                                    <p>Belum ada konten</p>
                                    <a href="education-form.php" class="btn-create-first">Buat Konten Pertama</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            /* Enhanced Dashboard Styles for Psychologist */
            .psychologist-dashboard {
                grid-template-columns: 1fr 1fr;
            }
            
            .stats-card-enhanced {
                background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
                border: 1px solid rgba(253, 121, 121, 0.2);
                position: relative;
                overflow: hidden;
            }
            
            .stats-card-enhanced::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            }
            
            .card-header-enhanced {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 24px;
            }
            
            .card-icon-wrapper {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                transition: all 0.3s ease;
            }
            
            .card-header-enhanced h3 {
                font-size: 20px;
                font-weight: 700;
                color: var(--color-text);
                margin: 0;
            }
            
            .big-number-enhanced {
                font-size: 56px;
                font-weight: 800;
                background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 16px 0 8px 0;
                line-height: 1;
            }
            
            .stats-label {
                color: var(--color-text);
                opacity: 0.7;
                margin: 0 0 20px 0;
                font-size: 15px;
                font-weight: 500;
            }
            
            .stats-details {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid rgba(253, 121, 121, 0.15);
            }
            
            .stat-detail-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 10px;
                transition: all 0.3s ease;
            }
            
            .stat-detail-item:hover {
                background: rgba(255, 255, 255, 0.9);
                transform: translateX(4px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            
            .stat-detail-icon {
                width: 36px;
                height: 36px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .stat-detail-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            
            .stat-detail-label {
                font-size: 12px;
                color: var(--color-text);
                opacity: 0.7;
            }
            
            .stat-detail-value {
                font-size: 20px;
                font-weight: 700;
                color: var(--color-text);
            }
            
            .education-action-card {
                background: linear-gradient(135deg, rgba(253, 121, 121, 0.12) 0%, rgba(249, 223, 223, 0.08) 100%);
                border: 2px solid rgba(253, 121, 121, 0.3);
                position: relative;
            }
            
            .education-card-description {
                color: var(--color-text-light);
                margin-bottom: 24px;
                font-size: 14px;
                line-height: 1.7;
            }
            
            .education-action-buttons {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            
            .btn-education-action {
                flex: 1;
                min-width: 140px;
                text-align: center;
                padding: 14px 20px;
                border-radius: 12px;
                font-weight: 600;
                font-size: 14px;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            
            .btn-manage {
                background: rgba(255, 255, 255, 0.8);
                color: var(--color-text);
                border-color: rgba(253, 121, 121, 0.3);
            }
            
            .btn-manage:hover {
                background: rgba(255, 255, 255, 1);
                border-color: rgba(253, 121, 121, 0.5);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2);
            }
            
            .btn-create {
                background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
                color: white;
                border: none;
            }
            
            .btn-create:hover {
                background: linear-gradient(135deg, #C03D3D 0%, #E86A6A 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(211, 78, 78, 0.4);
            }
            
            .btn-icon {
                display: inline-flex;
                align-items: center;
            }
            
            .recent-content-card {
                margin-top: 20px;
            }
            
            .schedule-list-enhanced {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .schedule-item-enhanced {
                display: flex;
                gap: 12px;
                padding: 16px;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 12px;
                border: 1px solid rgba(253, 121, 121, 0.15);
                transition: all 0.3s ease;
            }
            
            .schedule-item-enhanced:hover {
                background: rgba(255, 255, 255, 0.9);
                transform: translateX(4px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                border-color: rgba(253, 121, 121, 0.3);
            }
            
            .schedule-time-badge {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 8px 12px;
                background: linear-gradient(135deg, rgba(156, 39, 176, 0.15) 0%, rgba(186, 104, 200, 0.1) 100%);
                border-radius: 8px;
                font-size: 12px;
                font-weight: 600;
                color: #9C27B0;
                white-space: nowrap;
                flex-shrink: 0;
            }
            
            .time-icon {
                display: inline-flex;
                align-items: center;
            }
            
            .schedule-content-enhanced {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .content-title {
                font-size: 15px;
                font-weight: 600;
                color: var(--color-text);
                margin: 0;
                line-height: 1.4;
            }
            
            .content-meta {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .content-badge {
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            
            .badge-published {
                background: rgba(76, 175, 80, 0.15);
                color: #4CAF50;
            }
            
            .badge-draft {
                background: rgba(255, 152, 0, 0.15);
                color: #FF9800;
            }
            
            .content-views {
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                color: var(--color-text);
                opacity: 0.7;
            }
            
            .empty-content-state {
                text-align: center;
                padding: 40px 20px;
            }
            
            .empty-icon {
                margin: 0 auto 16px;
                opacity: 0.3;
            }
            
            .empty-content-state p {
                color: var(--color-text);
                opacity: 0.6;
                margin: 0 0 20px 0;
            }
            
            .btn-create-first {
                display: inline-block;
                padding: 10px 20px;
                background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
                color: white;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            
            .btn-create-first:hover {
                background: linear-gradient(135deg, #C03D3D 0%, #E86A6A 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(211, 78, 78, 0.3);
            }
            
            @media (max-width: 768px) {
                .psychologist-dashboard {
                    grid-template-columns: 1fr;
                }
                
                .education-action-buttons {
                    flex-direction: column;
                }
                
                .btn-education-action {
                    width: 100%;
                }
            }
            </style>
        </main>
    </div>
</body>
</html>

