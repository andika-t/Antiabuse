<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'psychologist') {
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
    
    // Get content details - psychologist can see their own content (including unpublished)
    $stmt = $conn->prepare("
        SELECT ec.*,
               CASE 
                   WHEN ec.author_role = 'admin' THEN ad.full_name
                   WHEN ec.author_role = 'psychologist' THEN pd.full_name
               END as author_name
        FROM education_content ec
        LEFT JOIN admin_details ad ON ec.author_id = ad.user_id AND ec.author_role = 'admin'
        LEFT JOIN psychologist_details pd ON ec.author_id = pd.user_id AND ec.author_role = 'psychologist'
        WHERE ec.id = ? AND ec.author_id = ? AND ec.author_role = 'psychologist'
    ");
    $stmt->execute([$contentId, $_SESSION['user_id']]);
    $content = $stmt->fetch();
    
    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Content not found or you do not have access']);
        exit();
    }
    
    echo json_encode(['success' => true, 'content' => $content]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

