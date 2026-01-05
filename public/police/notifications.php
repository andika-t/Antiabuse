<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'police') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        // Mark all notifications as read
        $stmt = $conn->prepare("
            UPDATE panic_notifications pn
            INNER JOIN panic_alerts pa ON pn.panic_alert_id = pa.id
            SET pn.read_at = NOW()
            WHERE pn.recipient_id = ?
            AND pn.recipient_type = 'police'
            AND pn.read_at IS NULL
            AND pa.status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        exit();
    }
    
    // Get notifications
    $stmt = $conn->prepare("
        SELECT pa.*, 
               gud.full_name as user_name,
               pn.id as notification_id,
               pn.read_at
        FROM panic_alerts pa
        INNER JOIN panic_notifications pn ON pa.id = pn.panic_alert_id
        LEFT JOIN general_user_details gud ON pa.user_id = gud.user_id
        WHERE pn.recipient_id = ?
        AND pn.recipient_type = 'police'
        ORDER BY pa.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    
} catch(PDOException $e) {
    // Check if table doesn't exist
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
        echo json_encode(['success' => false, 'message' => 'Tabel panic_alerts belum dibuat. Silakan jalankan script database schema terlebih dahulu.']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>

