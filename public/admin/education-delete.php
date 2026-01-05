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
    $contentId = (int)($_POST['content_id'] ?? 0);
    
    if (!$contentId) {
        echo json_encode(['success' => false, 'message' => 'Invalid content ID']);
        exit();
    }
    
    // Verify content exists
    $stmt = $conn->prepare("SELECT id, title FROM education_content WHERE id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();
    
    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Konten tidak ditemukan']);
        exit();
    }
    
    // Delete content (CASCADE will handle bookmarks)
    $stmt = $conn->prepare("DELETE FROM education_content WHERE id = ?");
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

