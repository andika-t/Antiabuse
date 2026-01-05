<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'general_user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$contentId = (int)($_GET['id'] ?? 0);

if (!$contentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid content ID']);
    exit();
}

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
        echo json_encode(['success' => false, 'message' => 'Content not found']);
        exit();
    }
    
    // Increment views
    $stmt = $conn->prepare("UPDATE education_content SET views = views + 1 WHERE id = ?");
    $stmt->execute([$contentId]);
    
    // Reload to get updated views count
    $stmt = $conn->prepare("
        SELECT views FROM education_content WHERE id = ?
    ");
    $stmt->execute([$contentId]);
    $updated = $stmt->fetch();
    $content['views'] = $updated['views'];
    
    echo json_encode(['success' => true, 'content' => $content]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

