<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() != 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get filters
$categoryFilter = $_GET['category'] ?? 'all';
$authorFilter = $_GET['author'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';

// Get education content
try {
    $conn = getDBConnection();
    
    $query = "SELECT ec.*, 
              COALESCE(ad.full_name, psd.full_name) as author_name,
              u.role as author_role
              FROM education_content ec
              LEFT JOIN users u ON ec.author_id = u.id
              LEFT JOIN admin_details ad ON u.id = ad.user_id AND u.role = 'admin'
              LEFT JOIN psychologist_details psd ON u.id = psd.user_id AND u.role = 'psychologist'
              WHERE 1=1";
    
    $params = [];
    
    if ($categoryFilter != 'all') {
        $query .= " AND ec.category = ?";
        $params[] = $categoryFilter;
    }
    
    if ($authorFilter == 'admin') {
        $query .= " AND ec.author_role = 'admin'";
    } elseif ($authorFilter == 'psychologist') {
        $query .= " AND ec.author_role = 'psychologist'";
    }
    
    if ($dateFilter == 'today') {
        $query .= " AND DATE(ec.created_at) = CURDATE()";
    } elseif ($dateFilter == 'week') {
        $query .= " AND ec.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateFilter == 'month') {
        $query .= " AND ec.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    if ($search) {
        $query .= " AND (ec.title LIKE ? OR ec.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Sort
    if ($sortBy == 'newest') {
        $query .= " ORDER BY ec.created_at DESC";
    } elseif ($sortBy == 'oldest') {
        $query .= " ORDER BY ec.created_at ASC";
    } elseif ($sortBy == 'most_views') {
        $query .= " ORDER BY ec.views DESC";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $educationContent = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->query("SELECT COUNT(*) as count FROM education_content");
    $totalContent = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM education_content WHERE DATE(created_at) = CURDATE()");
    $todayContent = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT SUM(views) as total FROM education_content");
    $totalViews = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $conn->query("SELECT COUNT(DISTINCT author_id) as count FROM education_content");
    $totalAuthors = $stmt->fetch()['count'];
    
    // Get most popular content
    $stmt = $conn->query("SELECT title, views FROM education_content ORDER BY views DESC LIMIT 1");
    $mostPopular = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $educationContent = [];
    $totalContent = 0;
    $todayContent = 0;
    $totalViews = 0;
    $totalAuthors = 0;
    $mostPopular = null;
}

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konten Edukasi - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
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
            padding: 25px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--color-text-light);
            margin: 0 0 10px 0;
            font-weight: 600;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }
        
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
        }
        
        .search-box button:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .data-table {
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: rgba(255, 255, 255, 0.3);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            font-size: 14px;
            border-bottom: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(253, 121, 121, 0.1);
            color: var(--color-text);
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: rgba(253, 121, 121, 0.05);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(66, 165, 245, 0.1);
            color: #42A5F5;
        }
        
        .btn-view:hover {
            background: #42A5F5;
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 83, 80, 0.1);
            color: #EF5350;
        }
        
        .btn-delete:hover {
            background: #EF5350;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-light);
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
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Manajemen Konten Edukasi</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Konten</h3>
                    <div class="number"><?php echo number_format($totalContent); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Views</h3>
                    <div class="number"><?php echo number_format($totalViews); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Konten Hari Ini</h3>
                    <div class="number"><?php echo number_format($todayContent); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Author</h3>
                    <div class="number" style="color: #66BB6A;"><?php echo number_format($totalAuthors); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Kategori</label>
                            <select name="category">
                                <option value="all" <?php echo $categoryFilter == 'all' ? 'selected' : ''; ?>>Semua Kategori</option>
                                <?php foreach ($categoryLabels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $categoryFilter == $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Author</label>
                            <select name="author">
                                <option value="all" <?php echo $authorFilter == 'all' ? 'selected' : ''; ?>>Semua Author</option>
                                <option value="admin" <?php echo $authorFilter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="psychologist" <?php echo $authorFilter == 'psychologist' ? 'selected' : ''; ?>>Psychologist</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Periode</label>
                            <select name="date">
                                <option value="all" <?php echo $dateFilter == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                                <option value="today" <?php echo $dateFilter == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                                <option value="week" <?php echo $dateFilter == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                                <option value="month" <?php echo $dateFilter == 'month' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Urutkan</label>
                            <select name="sort">
                                <option value="newest" <?php echo $sortBy == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="oldest" <?php echo $sortBy == 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                                <option value="most_views" <?php echo $sortBy == 'most_views' ? 'selected' : ''; ?>>Paling Banyak Views</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Cari berdasarkan judul atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">Cari</button>
                    </div>
                </form>
            </div>
            
            <!-- Data Table -->
            <div class="data-table">
                <?php if (empty($educationContent)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><?php echo icon('education', '', 64, 64); ?></div>
                        <h3>Tidak Ada Data</h3>
                        <p>Belum ada konten edukasi yang tercatat.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Author</th>
                                <th>Views</th>
                                <th>Waktu Publish</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($educationContent as $content): ?>
                                <tr>
                                    <td>#<?php echo str_pad($content['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td>
                                        <span class="badge" style="background: rgba(253, 121, 121, 0.1); color: var(--color-primary);">
                                            <?php echo $categoryLabels[$content['category']] ?? $content['category']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($content['author_name'] ?? 'Admin'); ?>
                                        <span style="font-size: 11px; color: var(--color-text-light);">
                                            (<?php echo $content['author_role'] == 'admin' ? 'Admin' : 'Psychologist'; ?>)
                                        </span>
                                    </td>
                                    <td><?php echo number_format($content['views']); ?></td>
                                    <td><?php echo formatDateTime($content['published_at'] ?? $content['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn-action btn-view" onclick="viewContent(<?php echo $content['id']; ?>)">Detail</button>
                                        <?php if ($content['author_role'] == 'psychologist'): ?>
                                            <button type="button" class="btn-action btn-delete" onclick="deleteContent(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars(addslashes($content['title'])); ?>')">Hapus</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
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
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        function viewContent(contentId) {
            fetch('<?php echo BASE_URL; ?>/dashboard/admin/education-detail-api.php?id=' + contentId)
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
                        alert('Error: ' + (data.message || 'Gagal memuat detail konten'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail konten');
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
            if (!confirm('Apakah Anda yakin ingin menghapus konten ini?\n\n"' + contentTitle + '"\n\nTindakan ini tidak dapat dibatalkan.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('content_id', contentId);
            
            fetch('<?php echo BASE_URL; ?>/dashboard/admin/education-delete.php', {
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
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus konten');
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
        
        // Close modal on outside click
        document.getElementById('contentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContentModal();
            }
        });
    </script>
</body>
</html>
