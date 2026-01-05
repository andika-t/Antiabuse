<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'psychologist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $conn = getDBConnection();
    $contentId = (int)($_POST['content_id'] ?? 0);
    
    if (!$contentId) {
        echo json_encode(['success' => false, 'message' => 'Invalid content ID']);
        exit();
    }
    
    // Verify ownership - psikolog hanya bisa delete konten sendiri
    $stmt = $conn->prepare("
        SELECT id, author_id, title 
        FROM education_content 
        WHERE id = ? AND author_id = ? AND author_role = 'psychologist'
    ");
    $stmt->execute([$contentId, $_SESSION['user_id']]);
    $content = $stmt->fetch();
    
    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Konten tidak ditemukan atau Anda tidak memiliki akses']);
        exit();
    }
    
    // Delete konten
    $stmt = $conn->prepare("DELETE FROM education_content WHERE id = ?");
    $stmt->execute([$contentId]);
    
    // Also delete related bookmarks (CASCADE will handle this if foreign key exists)
    $stmt = $conn->prepare("DELETE FROM education_bookmarks WHERE content_id = ?");
    $stmt->execute([$contentId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Konten berhasil dihapus',
        'content_title' => $content['title']
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

