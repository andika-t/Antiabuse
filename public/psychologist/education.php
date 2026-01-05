<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();
if (getUserRole() != 'psychologist') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get psychologist's education content
    $stmt = $conn->prepare("
        SELECT * FROM education_content
        WHERE author_id = ? AND author_role = 'psychologist'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myContent = $stmt->fetchAll();
    
    // Get statistics
    $totalContent = count($myContent);
    $totalViews = array_sum(array_column($myContent, 'views'));
    $publishedContent = count(array_filter($myContent, function($c) { return $c['is_published']; }));
    
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
    $myContent = [];
    $totalContent = 0;
    $totalViews = 0;
    $publishedContent = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konten Edukasi Saya - AntiAbuse</title>
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
        
        .btn-create {
            background: #D34E4E !important;
            color: white !important;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
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
            transform: translateY(-3px);
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
            color: var(--color-primary);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .content-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .content-thumbnail {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #F9DFDF 0%, #FD7979 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .content-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .content-body {
            padding: 20px;
        }
        
        .content-badge {
            display: inline-block;
            background: rgba(253, 121, 121, 0.1);
            color: var(--color-primary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .content-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .content-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--color-text-light);
            margin-bottom: 15px;
        }
        
        .content-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #42A5F5;
            color: white;
        }
        
        .btn-edit:hover {
            background: #1E88E5;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #EF5350;
            color: white;
        }
        
        .btn-delete:hover {
            background: #D32F2F;
            transform: translateY(-2px);
        }
        
        .btn-view {
            background: var(--color-primary);
            color: white;
        }
        
        .btn-view:hover {
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
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>Konten Edukasi Saya</h1>
                <a href="education-form.php" class="btn-create">+ Buat Konten Baru</a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error" style="margin-bottom: 20px; padding: 15px; background: rgba(239, 83, 80, 0.1); border: 1px solid rgba(239, 83, 80, 0.3); border-radius: 10px; color: #EF5350;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Konten</h3>
                    <div class="number"><?php echo $totalContent; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Published</h3>
                    <div class="number"><?php echo $publishedContent; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Views</h3>
                    <div class="number"><?php echo number_format($totalViews); ?></div>
                </div>
            </div>
            
            <!-- Content List -->
            <?php if (empty($myContent)): ?>
                <div class="empty-state">
                    <div style="margin-bottom: 20px;"><?php echo icon('education', '', 64, 64); ?></div>
                    <h3>Tidak Ada Konten</h3>
                    <p style="margin-bottom: 30px;">Anda belum membuat konten edukasi. Klik tombol di bawah untuk membuat konten pertama Anda.</p>
                    <a href="education-form.php" class="btn-create" style="font-size: 16px; padding: 15px 30px;">
                        + Buat Konten Edukasi Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="content-grid">
                    <?php foreach ($myContent as $content): ?>
                        <div class="content-card">
                            <div class="content-thumbnail">
                                <?php if ($content['thumbnail']): ?>
                                    <img src="<?php echo htmlspecialchars($content['thumbnail']); ?>" alt="<?php echo htmlspecialchars($content['title']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"><?php echo icon('education', '', 48, 48); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="content-body">
                                <span class="content-badge"><?php echo $categoryLabels[$content['category']] ?? $content['category']; ?></span>
                                <h3 class="content-title"><?php echo htmlspecialchars($content['title']); ?></h3>
                                <div class="content-meta" style="display: flex; align-items: center; gap: 8px;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> <?php echo number_format($content['views']); ?> views</span>
                                    <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo $content['is_published'] ? icon('completed', '', 16, 16) . ' Published' : icon('create-report', '', 16, 16) . ' Draft'; ?></span>
                                </div>
                                <div class="content-actions">
                                    <a href="education-form.php?id=<?php echo $content['id']; ?>" class="btn-action btn-edit">Edit</a>
                                    <button type="button" class="btn-action btn-view" onclick="viewContent(<?php echo $content['id']; ?>)">Lihat</button>
                                    <button type="button" class="btn-action btn-delete" onclick="deleteContent(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars($content['title'], ENT_QUOTES); ?>')">Hapus</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Content Detail Modal -->
    <div id="contentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; border-radius: 20px; padding: 30px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); position: relative;">
            <button onclick="closeContentModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 28px; color: var(--color-text); cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;">&times;</button>
            <div id="contentModalContent"></div>
        </div>
    </div>
    
    <script>
        function viewContent(contentId) {
            fetch('<?php echo BASE_URL; ?>/psychologist/education-detail-api.php?id=' + contentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const content = data.content;
                        const categoryLabel = {
                            'bullying': 'Perundungan',
                            'violence': 'Kekerasan',
                            'harassment': 'Pelecehan',
                            'abuse': 'Abuse',
                            'prevention': 'Pencegahan',
                            'support': 'Dukungan',
                            'legal': 'Hukum',
                            'mental_health': 'Kesehatan Mental'
                        }[content.category] || content.category;
                        
                        // Get embed URL
                        let embedUrl = '';
                        if (content.video_url.includes('youtube.com/watch?v=')) {
                            const urlParams = new URLSearchParams(content.video_url.split('?')[1]);
                            embedUrl = 'https://www.youtube.com/embed/' + urlParams.get('v');
                        } else if (content.video_url.includes('youtu.be/')) {
                            const videoId = content.video_url.split('youtu.be/')[1].split('?')[0];
                            embedUrl = 'https://www.youtube.com/embed/' + videoId;
                        } else if (content.video_url.includes('vimeo.com/')) {
                            const videoId = content.video_url.split('vimeo.com/')[1].split('?')[0];
                            embedUrl = 'https://player.vimeo.com/video/' + videoId;
                        }
                        
                        document.getElementById('contentModalContent').innerHTML = `
                            <h2 style="color: var(--color-text); margin-bottom: 10px;">${escapeHtml(content.title)}</h2>
                            <div style="margin-bottom: 15px;">
                                <span class="badge" style="background: rgba(253, 121, 121, 0.1); color: var(--color-primary); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; margin-right: 10px;">${categoryLabel}</span>
                                <span style="color: var(--color-text-light); font-size: 12px;">${escapeHtml(content.author_name || 'Admin')}</span>
                                <span style="color: var(--color-text-light); font-size: 12px; margin-left: 10px;">• ${formatNumber(content.views)} views</span>
                            </div>
                            ${embedUrl ? `
                                <div style="margin-bottom: 20px;">
                                    <iframe src="${embedUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width: 100%; height: 450px; border-radius: 15px;"></iframe>
                                </div>
                            ` : ''}
                            ${content.description ? `<p style="color: var(--color-text); line-height: 1.6; margin-bottom: 20px;">${escapeHtml(content.description)}</p>` : ''}
                            <div style="color: var(--color-text-light); font-size: 12px;">
                                <p>Dipublish: ${formatDate(content.published_at || content.created_at)}</p>
                            </div>
                        `;
                        document.getElementById('contentModal').style.display = 'flex';
                    } else {
                        alert('Error: ' + (data.message || 'Gagal memuat konten'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat konten');
                });
        }
        
        function closeContentModal() {
            document.getElementById('contentModal').style.display = 'none';
            const iframe = document.getElementById('contentModalContent').querySelector('iframe');
            if (iframe) {
                iframe.src = '';
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('contentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContentModal();
            }
        });
        
        function deleteContent(contentId, contentTitle) {
            if (!confirm(`Apakah Anda yakin ingin menghapus konten ini?\n\n"${contentTitle}"\n\nTindakan ini tidak dapat dibatalkan.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('content_id', contentId);
            
            const deleteBtn = event.target;
            const originalText = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = 'Menghapus...';
            
            fetch('<?php echo BASE_URL; ?>/psychologist/education-delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Konten berhasil dihapus!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal menghapus konten'));
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus konten');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            // Convert to Jakarta timezone (UTC+7)
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' });
        }
    </script>
</body>
</html>

