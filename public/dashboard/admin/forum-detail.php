<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$postId = (int)($_GET['id'] ?? 0);

if (!$postId) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get post details
    $stmt = $conn->prepare("
        SELECT fp.*, 
               gud.full_name as user_name
        FROM forum_posts fp
        LEFT JOIN general_user_details gud ON fp.user_id = gud.user_id
        WHERE fp.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit();
    }
    
    // Get comments
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
        'post' => $post,
        'comments' => $comments
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

