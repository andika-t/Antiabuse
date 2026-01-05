<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'general_user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'create_post') {
        // Create new post
        $content = trim($_POST['content'] ?? '');
        
        if (empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Konten post tidak boleh kosong']);
            exit();
        }
        
        if (strlen($content) > 500) {
            echo json_encode(['success' => false, 'message' => 'Konten post maksimal 500 karakter']);
            exit();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO forum_posts (user_id, content, likes_count, comments_count)
            VALUES (?, ?, 0, 0)
        ");
        $stmt->execute([$_SESSION['user_id'], $content]);
        
        echo json_encode(['success' => true, 'message' => 'Post berhasil dibuat']);
        
    } elseif ($action === 'toggle_like') {
        // Toggle like on post
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
            exit();
        }
        
        // Check if already liked
        $stmt = $conn->prepare("SELECT id FROM forum_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $_SESSION['user_id']]);
        $existingLike = $stmt->fetch();
        
        if ($existingLike) {
            // Unlike
            $stmt = $conn->prepare("DELETE FROM forum_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $_SESSION['user_id']]);
            
            // Update likes count
            $stmt = $conn->prepare("UPDATE forum_posts SET likes_count = likes_count - 1 WHERE id = ?");
            $stmt->execute([$postId]);
            
            $isLiked = false;
        } else {
            // Like
            $stmt = $conn->prepare("INSERT INTO forum_likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$postId, $_SESSION['user_id']]);
            
            // Update likes count
            $stmt = $conn->prepare("UPDATE forum_posts SET likes_count = likes_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);
            
            $isLiked = true;
        }
        
        // Get updated likes count
        $stmt = $conn->prepare("SELECT likes_count FROM forum_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'is_liked' => $isLiked,
            'likes_count' => $post['likes_count'] ?? 0
        ]);
        
    } elseif ($action === 'add_comment') {
        // Add comment to post
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if (!$postId || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID or empty content']);
            exit();
        }
        
        // Insert comment
        $stmt = $conn->prepare("
            INSERT INTO forum_comments (post_id, user_id, content, likes_count)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$postId, $_SESSION['user_id'], $content]);
        
        // Update comments count
        $stmt = $conn->prepare("UPDATE forum_posts SET comments_count = comments_count + 1 WHERE id = ?");
        $stmt->execute([$postId]);
        
        // Get updated comments count
        $stmt = $conn->prepare("SELECT comments_count FROM forum_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan',
            'comments_count' => $post['comments_count'] ?? 0
        ]);
        
    } elseif ($action === 'get_comments') {
        // Get comments for a post
        $postId = (int)($_GET['post_id'] ?? 0);
        
        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
            exit();
        }
        
        $stmt = $conn->prepare("
            SELECT fc.*,
                   gud.full_name as user_name
            FROM forum_comments fc
            LEFT JOIN general_user_details gud ON fc.user_id = gud.user_id
            WHERE fc.post_id = ?
            ORDER BY fc.created_at ASC
        ");
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'comments' => $comments
        ]);
        
    } elseif ($action === 'delete_post') {
        // Delete post (only own posts)
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
            exit();
        }
        
        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM forum_posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $_SESSION['user_id']]);
        $post = $stmt->fetch();
        
        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post tidak ditemukan atau Anda tidak memiliki akses']);
            exit();
        }
        
        // Delete post (CASCADE will handle likes and comments)
        $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->execute([$postId]);
        
        echo json_encode(['success' => true, 'message' => 'Post berhasil dihapus']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch(PDOException $e) {
    // Check if table doesn't exist
    $errorMessage = $e->getMessage();
    if (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, "Unknown table") !== false) {
        echo json_encode(['success' => false, 'message' => 'Tabel forum belum dibuat. Silakan jalankan script database schema terlebih dahulu.']);
    } else {
        // Log error for debugging but don't expose sensitive info
        error_log("Forum action error: " . $errorMessage);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses permintaan. Silakan coba lagi.']);
    }
} catch(Exception $e) {
    error_log("Forum action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses permintaan.']);
}
?>

