<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() != 'admin') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
}

// Get filters
$dateFilter = $_GET['date'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';

// Get forum posts
try {
    $conn = getDBConnection();
    
    $query = "SELECT fp.*, 
              gud.full_name as user_name,
              COUNT(DISTINCT fc.id) as comment_count
              FROM forum_posts fp
              LEFT JOIN general_user_details gud ON fp.user_id = gud.user_id
              LEFT JOIN forum_comments fc ON fp.id = fc.post_id
              WHERE 1=1";
    
    $params = [];
    
    if ($dateFilter == 'today') {
        $query .= " AND DATE(fp.created_at) = CURDATE()";
    } elseif ($dateFilter == 'week') {
        $query .= " AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateFilter == 'month') {
        $query .= " AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    if ($search) {
        $query .= " AND (fp.content LIKE ? OR gud.full_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " GROUP BY fp.id";
    
    // Sort
    if ($sortBy == 'newest') {
        $query .= " ORDER BY fp.created_at DESC";
    } elseif ($sortBy == 'oldest') {
        $query .= " ORDER BY fp.created_at ASC";
    } elseif ($sortBy == 'most_comments') {
        $query .= " ORDER BY comment_count DESC";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $forumPosts = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->query("SELECT COUNT(*) as count FROM forum_posts");
    $totalPosts = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM forum_posts WHERE DATE(created_at) = CURDATE()");
    $todayPosts = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM forum_comments");
    $totalComments = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM forum_posts");
    $activeUsers = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $forumPosts = [];
    $totalPosts = 0;
    $todayPosts = 0;
    $totalComments = 0;
    $activeUsers = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderasi Forum - AntiAbuse</title>
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
        
        .post-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                <h1>Moderasi Forum</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Post</h3>
                    <div class="number"><?php echo number_format($totalPosts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Komentar</h3>
                    <div class="number"><?php echo number_format($totalComments); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Post Hari Ini</h3>
                    <div class="number"><?php echo number_format($todayPosts); ?></div>
                </div>
                <div class="stat-card">
                    <h3>User Aktif</h3>
                    <div class="number" style="color: #66BB6A;"><?php echo number_format($activeUsers); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
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
                                <option value="most_comments" <?php echo $sortBy == 'most_comments' ? 'selected' : ''; ?>>Paling Banyak Komentar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Cari berdasarkan konten atau nama user..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">Cari</button>
                    </div>
                </form>
            </div>
            
            <!-- Data Table -->
            <div class="data-table">
                <?php if (empty($forumPosts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><?php echo icon('forum', '', 64, 64); ?></div>
                        <h3>Tidak Ada Data</h3>
                        <p>Belum ada post forum yang tercatat.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Konten</th>
                                <th>Komentar</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forumPosts as $post): ?>
                                <tr>
                                    <td>#<?php echo str_pad($post['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($post['user_name'] ?? 'User'); ?></td>
                                    <td class="post-preview" title="<?php echo htmlspecialchars($post['content']); ?>">
                                        <?php echo htmlspecialchars(mb_substr($post['content'], 0, 100)) . (mb_strlen($post['content']) > 100 ? '...' : ''); ?>
                                    </td>
                                    <td><?php echo number_format($post['comment_count']); ?></td>
                                    <td><?php echo formatDateTime($post['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn-action btn-view" onclick="viewPost(<?php echo $post['id']; ?>)">Detail</button>
                                        <button type="button" class="btn-action btn-delete" onclick="deletePost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes(mb_substr($post['content'], 0, 50))); ?>')">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Post Detail Modal -->
    <div id="postModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; border-radius: 20px; padding: 30px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); position: relative;">
            <button onclick="closePostModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 28px; color: var(--color-text); cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;">&times;</button>
            <div id="postModalContent"></div>
        </div>
    </div>
    
    <script>
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Make functions globally available
        window.viewPost = function(postId) {
            fetch('<?php echo BASE_URL; ?>/admin/forum-detail.php?id=' + postId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const post = data.post;
                        const comments = data.comments || [];
                        
                        let commentsHtml = '';
                        if (comments.length > 0) {
                            commentsHtml = '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(253, 121, 121, 0.1);"><h3 style="margin-bottom: 15px; color: var(--color-text);">Komentar (' + comments.length + ')</h3>';
                            comments.forEach(comment => {
                                commentsHtml += `
                                    <div style="background: rgba(255, 255, 255, 0.5); border-radius: 12px; padding: 15px; margin-bottom: 10px; border-left: 3px solid rgba(253, 121, 121, 0.3);">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                            <strong style="color: var(--color-text);">${escapeHtml(comment.user_name || 'User')}</strong>
                                            <span style="font-size: 12px; color: var(--color-text-light);">${formatDate(comment.created_at)}</span>
                                        </div>
                                        <p style="color: var(--color-text); margin: 0; line-height: 1.6;">${escapeHtml(comment.content)}</p>
                                    </div>
                                `;
                            });
                            commentsHtml += '</div>';
                        } else {
                            commentsHtml = '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(253, 121, 121, 0.1);"><p style="text-align: center; color: var(--color-text-light);">Belum ada komentar</p></div>';
                        }
                        
                        document.getElementById('postModalContent').innerHTML = `
                            <h2 style="color: var(--color-text); margin-bottom: 10px;">Post #${post.id}</h2>
                            <div style="margin-bottom: 15px;">
                                <strong style="color: var(--color-text);">${escapeHtml(post.user_name || 'User')}</strong>
                                <span style="color: var(--color-text-light); font-size: 12px; margin-left: 10px;">${formatDate(post.created_at)}</span>
                            </div>
                            <div style="background: rgba(255, 255, 255, 0.5); border-radius: 12px; padding: 20px; margin-bottom: 20px; color: var(--color-text); line-height: 1.8; white-space: pre-wrap;">${escapeHtml(post.content)}</div>
                            ${commentsHtml}
                        `;
                        document.getElementById('postModal').style.display = 'flex';
                    } else {
                        alert('Error: ' + (data.message || 'Gagal memuat detail post'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail post');
                });
        };
        
        window.closePostModal = function() {
            document.getElementById('postModal').style.display = 'none';
        };
        
        window.deletePost = function(postId, postPreview) {
            if (!confirm('Apakah Anda yakin ingin menghapus post ini?\n\n"' + postPreview + '..."\n\nTindakan ini tidak dapat dibatalkan.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('post_id', postId);
            
            fetch('<?php echo BASE_URL; ?>/admin/forum-delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Post berhasil dihapus!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Gagal menghapus post'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus post');
            });
        };
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            // Convert to Jakarta timezone (UTC+7)
            const jakartaDate = new Date(date.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' });
        }
        
        // Close modal when clicking outside
        document.getElementById('postModal').addEventListener('click', function(e) {
            if (e.target === this) {
                window.closePostModal();
            }
        });
    </script>
</body>
</html>
