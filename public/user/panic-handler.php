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

try {
    $conn = getDBConnection();
    $action = $_POST['action'] ?? 'activate';
    
    if ($action === 'add_contact') {
        // Add emergency contact
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Nama dan nomor telepon harus diisi']);
            exit();
        }
        
        // Get current emergency contacts
        $stmt = $conn->prepare("SELECT emergency_contacts FROM general_user_details WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userDetails = $stmt->fetch();
        
        $contacts = [];
        if ($userDetails && $userDetails['emergency_contacts']) {
            $contacts = json_decode($userDetails['emergency_contacts'], true) ?: [];
        }
        
        // Add new contact
        $contacts[] = [
            'name' => $name,
            'phone' => $phone
        ];
        
        // Update database
        $stmt = $conn->prepare("
            INSERT INTO general_user_details (user_id, emergency_contacts)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE emergency_contacts = ?
        ");
        $contactsJson = json_encode($contacts);
        $stmt->execute([$_SESSION['user_id'], $contactsJson, $contactsJson]);
        
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'remove_contact') {
        // Remove emergency contact
        $index = (int)($_POST['index'] ?? -1);
        
        if ($index < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid index']);
            exit();
        }
        
        // Get current emergency contacts
        $stmt = $conn->prepare("SELECT emergency_contacts FROM general_user_details WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userDetails = $stmt->fetch();
        
        $contacts = [];
        if ($userDetails && $userDetails['emergency_contacts']) {
            $contacts = json_decode($userDetails['emergency_contacts'], true) ?: [];
        }
        
        // Remove contact at index
        if (isset($contacts[$index])) {
            array_splice($contacts, $index, 1);
            
            // Update database
            $stmt = $conn->prepare("
                UPDATE general_user_details 
                SET emergency_contacts = ?
                WHERE user_id = ?
            ");
            $contactsJson = json_encode($contacts);
            $stmt->execute([$contactsJson, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contact not found']);
        }
        
    } else {
        // Activate panic button
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit();
        }
        
        // Get user location if provided
        $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
        $location = null;
        
        // Create panic alert
        $stmt = $conn->prepare("
            INSERT INTO panic_alerts (user_id, latitude, longitude, location, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$_SESSION['user_id'], $latitude, $longitude, $location]);
        $panicAlertId = $conn->lastInsertId();
        
        // Get all police users
        $stmt = $conn->prepare("
            SELECT u.id FROM users u
            WHERE u.role = 'police' AND u.status = 'active'
        ");
        $stmt->execute();
        $policeUsers = $stmt->fetchAll();
        
        // Send notifications to police
        foreach ($policeUsers as $police) {
            $stmt = $conn->prepare("
                INSERT INTO panic_notifications (panic_alert_id, recipient_type, recipient_id, notification_method, status)
                VALUES (?, 'police', ?, 'in_app', 'pending')
            ");
            $stmt->execute([$panicAlertId, $police['id']]);
        }
        
        // Get emergency contacts
        $stmt = $conn->prepare("SELECT emergency_contacts FROM general_user_details WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userDetails = $stmt->fetch();
        
        if ($userDetails && $userDetails['emergency_contacts']) {
            $contacts = json_decode($userDetails['emergency_contacts'], true);
            if (is_array($contacts)) {
                foreach ($contacts as $contact) {
                    $stmt = $conn->prepare("
                        INSERT INTO panic_notifications (panic_alert_id, recipient_type, notification_method, status)
                        VALUES (?, 'emergency_contact', 'sms', 'pending')
                    ");
                    $stmt->execute([$panicAlertId]);
                }
            }
        }
        
        // Send notification to admin
        $stmt = $conn->prepare("
            SELECT u.id FROM users u
            WHERE u.role = 'admin' AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch();
        if ($admin) {
            $stmt = $conn->prepare("
                INSERT INTO panic_notifications (panic_alert_id, recipient_type, recipient_id, notification_method, status)
                VALUES (?, 'admin', ?, 'in_app', 'pending')
            ");
            $stmt->execute([$panicAlertId, $admin['id']]);
        }
        
        echo json_encode(['success' => true, 'alert_id' => $panicAlertId]);
    }
    
} catch(PDOException $e) {
    // Check if table doesn't exist
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
        echo json_encode(['success' => false, 'message' => 'Tabel panic_alerts belum dibuat. Silakan jalankan script database schema terlebih dahulu.']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>

