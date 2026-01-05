<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

requireLogin();
if (getUserRole() != 'police') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $conn = getDBConnection();
    
    $action = $_POST['action'] ?? '';
    $alertId = (int)($_POST['alert_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$alertId) {
        echo json_encode(['success' => false, 'message' => 'Invalid alert ID']);
        exit();
    }
    
    // Verify that alert exists (semua panic alerts bisa diakses)
    $stmt = $conn->prepare("SELECT * FROM panic_alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $alert = $stmt->fetch();
    
    if (!$alert) {
        echo json_encode(['success' => false, 'message' => 'Alert not found']);
        exit();
    }
    
    if ($action === 'respond') {
        // Check if alert is still active
        if ($alert['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Alert sudah tidak aktif']);
            exit();
        }
        
        // Update status to responded
        $stmt = $conn->prepare("
            UPDATE panic_alerts 
            SET status = 'responded', 
                responded_by = ?,
                responded_at = NOW(),
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $notes, $alertId]);
        
        echo json_encode(['success' => true, 'message' => 'Panic alert berhasil ditandai sebagai ditanggapi']);
        
    } elseif ($action === 'resolve') {
        // Check if alert can be resolved
        if (!in_array($alert['status'], ['active', 'responded'])) {
            echo json_encode(['success' => false, 'message' => 'Alert tidak dapat diselesaikan']);
            exit();
        }
        
        // Update status to resolved
        $stmt = $conn->prepare("
            UPDATE panic_alerts 
            SET status = 'resolved', 
                resolved_at = NOW(),
                notes = COALESCE(NULLIF(?, ''), notes)
            WHERE id = ?
        ");
        $stmt->execute([$notes, $alertId]);
        
        // If not yet responded, also set responded_by
        if ($alert['status'] === 'active') {
            $stmt = $conn->prepare("
                UPDATE panic_alerts 
                SET responded_by = ?,
                    responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $alertId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Panic alert berhasil diselesaikan']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

