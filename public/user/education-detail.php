<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();
if (getUserRole() != 'general_user') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

$contentId = (int)($_GET['id'] ?? 0);

try {
    $conn = getDBConnection();
    
    // Get content details
    $stmt = $conn->prepare("
        SELECT ec.*,
               CASE 
                   WHEN ec.author_role = 'admin' THEN ad.full_name
                   WHEN ec.author_role = 'psychologist' THEN pd.full_name
               END as author_name
        FROM education_content ec
        LEFT JOIN admin_details ad ON ec.author_id = ad.user_id AND ec.author_role = 'admin'
        LEFT JOIN psychologist_details pd ON ec.author_id = pd.user_id AND ec.author_role = 'psychologist'
        WHERE ec.id = ? AND ec.is_published = TRUE
    ");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();
    
    if (!$content) {
        header('Location: ' . BASE_URL . '/user/index.php?section=education&error=content_not_found');
        exit();
    }
    
    // Increment views
    $stmt = $conn->prepare("UPDATE education_content SET views = views + 1 WHERE id = ?");
    $stmt->execute([$contentId]);
    
    // Get related content (same category)
    $stmt = $conn->prepare("
        SELECT ec.*,
               CASE 
                   WHEN ec.author_role = 'admin' THEN ad.full_name
                   WHEN ec.author_role = 'psychologist' THEN pd.full_name
               END as author_name
        FROM education_content ec
        LEFT JOIN admin_details ad ON ec.author_id = ad.user_id AND ec.author_role = 'admin'
        LEFT JOIN psychologist_details pd ON ec.author_id = pd.user_id AND ec.author_role = 'psychologist'
        WHERE ec.category = ? AND ec.id != ? AND ec.is_published = TRUE
        ORDER BY ec.views DESC
        LIMIT 5
    ");
    $stmt->execute([$content['category'], $contentId]);
    $relatedContent = $stmt->fetchAll();
    
    // Extract video ID from URL
    function getVideoEmbedUrl($url) {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }
        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }
        return $url;
    }
    
    $embedUrl = getVideoEmbedUrl($content['video_url']);
    
    $categoryLabels = [
        'bullying' => 'Perundungan',
        'violence' => 'Kekerasan',
        'harassment' => 'Pelecehan',
        'abuse' => 'Abuse',
        'prevention' => 'Pencegahan',
        'support' => 'Dukungan',
        'legal' => 'Hukum',
        'mental_health' => 'Kesehatan Mental'
    ];
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($content['title'] ?? 'Konten Edukasi'); ?> - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--color-primary);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateX(-5px);
        }
        
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .content-header {
            margin-bottom: 20px;
        }
        
        .content-title {
            font-size: 32px;
            color: var(--color-text);
            margin-bottom: 15px;
        }
        
        .content-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--color-text-light);
            margin-bottom: 20px;
        }
        
        .content-description {
            font-size: 16px;
            line-height: 1.8;
            color: var(--color-text);
            margin-bottom: 30px;
        }
        
        .related-content-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .related-content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .related-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .related-thumbnail {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #F9DFDF 0%, #FD7979 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .related-card-content {
            padding: 15px;
        }
        
        .related-card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="content-container">
                <a href="<?php echo BASE_URL; ?>/user/index.php?section=education" class="back-link">
                    ← Kembali ke Konten Edukasi
                </a>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px; padding: 15px; background: rgba(239, 83, 80, 0.1); border: 1px solid rgba(239, 83, 80, 0.3); border-radius: 10px; color: #EF5350;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($content): ?>
                    <div class="content-header">
                        <h1 class="content-title"><?php echo htmlspecialchars($content['title']); ?></h1>
                        <div class="content-meta">
                            <span class="badge badge-type" style="background: rgba(253, 121, 121, 0.1); color: var(--color-primary); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;">
                                <?php echo $categoryLabels[$content['category']] ?? $content['category']; ?>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('users', '', 16, 16); ?> <?php echo htmlspecialchars($content['author_name'] ?? 'Admin'); ?></span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> <?php echo number_format($content['views']); ?> views</span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> <?php echo formatDate($content['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <div class="video-container">
                        <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    </div>
                    
                    <?php if ($content['description']): ?>
                        <div class="content-description">
                            <?php echo nl2br(htmlspecialchars($content['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($relatedContent)): ?>
                        <div class="related-content-section">
                            <h2 style="font-size: 24px; color: var(--color-text); margin-bottom: 20px;">Konten Terkait</h2>
                            <div class="related-content-grid">
                                <?php foreach ($relatedContent as $related): ?>
                                    <div class="related-card" onclick="window.location.href='education-detail.php?id=<?php echo $related['id']; ?>'">
                                        <div class="related-thumbnail">
                                            <?php if ($related['thumbnail']): ?>
                                                <img src="<?php echo htmlspecialchars($related['thumbnail']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"><?php echo icon('education', '', 36, 36); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="related-card-content">
                                            <div class="related-card-title"><?php echo htmlspecialchars($related['title']); ?></div>
                                            <div style="font-size: 11px; color: var(--color-text-light); margin-top: 5px; display: flex; align-items: center; gap: 4px;">
                                                <?php echo icon('reports', '', 14, 14); ?> <?php echo number_format($related['views']); ?> views
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

