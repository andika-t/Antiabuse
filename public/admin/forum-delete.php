<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $conn = getDBConnection();
    $postId = (int)($_POST['post_id'] ?? 0);
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit();
    }
    
    // Verify post exists
    $stmt = $conn->prepare("SELECT id FROM forum_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post tidak ditemukan']);
        exit();
    }
    
    // Delete post (CASCADE will handle comments and likes)
    $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = ?");
    $stmt->execute([$postId]);
    
    echo json_encode(['success' => true, 'message' => 'Post berhasil dihapus']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

