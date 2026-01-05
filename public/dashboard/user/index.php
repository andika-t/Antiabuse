<?php
// ========== BACKEND PROCESSING ==========
require_once '../../../config/config.php';
require_once '../../../app/includes/functions.php';
require_once '../../../config/database.php';

// Check if user is logged in and is general user
requireLogin();
if (getUserRole() != 'general_user') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get user info
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT gud.full_name FROM general_user_details gud WHERE gud.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userInfo = $stmt->fetch();
    $userName = $userInfo['full_name'] ?? 'User';
} catch(PDOException $e) {
    $userName = 'User';
}

// Get reports data for reports section
$statusFilter = $_GET['status'] ?? 'all';
try {
    $query = "SELECT r.*, 
              COUNT(ra.id) as attachment_count
              FROM reports r
              LEFT JOIN report_attachments ra ON r.id = ra.report_id
              WHERE r.user_id = ?
              ";
    
    $params = [$_SESSION['user_id']];
    
    if ($statusFilter != 'all') {
        $query .= " AND r.status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " GROUP BY r.id ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM reports WHERE user_id = ? GROUP BY status");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $reports = [];
    $stats = [];
}

$reportTypeLabels = [
    'bullying' => 'Perundungan',
    'violence' => 'Kekerasan',
    'harassment' => 'Pelecehan',
    'abuse' => 'Abuse',
    'other' => 'Lainnya'
];

$statusLabels = [
    'pending' => 'Pending',
    'in_progress' => 'Sedang Diproses',
    'resolved' => 'Selesai',
    'rejected' => 'Ditolak'
];

$statusColors = [
    'pending' => '#FFA726',
    'in_progress' => '#42A5F5',
    'resolved' => '#66BB6A',
    'rejected' => '#EF5350'
];

// Get emergency contacts
$emergencyContacts = [];
try {
    $stmt = $conn->prepare("SELECT emergency_contacts FROM general_user_details WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userDetails = $stmt->fetch();
    if ($userDetails && $userDetails['emergency_contacts']) {
        $emergencyContacts = json_decode($userDetails['emergency_contacts'], true) ?: [];
    }
} catch(PDOException $e) {
    $emergencyContacts = [];
}

// Get panic history
$panicHistory = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM panic_alerts
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $panicHistory = $stmt->fetchAll();
} catch(PDOException $e) {
    // Table might not exist yet
    $panicHistory = [];
}

// Get education content
$educationContent = [];
$educationCategoryFilter = $_GET['edu_category'] ?? 'all';
try {
    $query = "
        SELECT ec.*,
               CASE 
                   WHEN ec.author_role = 'admin' THEN ad.full_name
                   WHEN ec.author_role = 'psychologist' THEN pd.full_name
               END as author_name
        FROM education_content ec
        LEFT JOIN admin_details ad ON ec.author_id = ad.user_id AND ec.author_role = 'admin'
        LEFT JOIN psychologist_details pd ON ec.author_id = pd.user_id AND ec.author_role = 'psychologist'
        WHERE ec.is_published = TRUE
    ";
    
    $params = [];
    if ($educationCategoryFilter !== 'all') {
        $query .= " AND ec.category = ?";
        $params[] = $educationCategoryFilter;
    }
    
    $query .= " ORDER BY ec.created_at DESC LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $educationContent = $stmt->fetchAll();
} catch(PDOException $e) {
    $educationContent = [];
}

$categoryLabels = [
    'bullying' => 'Perundungan',
    'violence' => 'Kekerasan',
    'harassment' => 'Pelecehan',
    'abuse' => 'Abuse',
    'prevention' => 'Pencegahan',
    'support' => 'Dukungan',
    'legal' => 'Hukum',
    'mental_health' => 'Kesehatan Mental'
];

// Helper function untuk time ago (using Jakarta timezone)
function getTimeAgo($datetime) {
    $dt = convertToJakartaTime($datetime);
    if ($dt === false) {
        return '-';
    }
    
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    if ($diff < 2592000) return floor($diff / 604800) . ' minggu lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan lalu';
    return floor($diff / 31536000) . ' tahun lalu';
}

// Get forum posts
$forumPosts = [];
try {
    // Get all posts with user info
    $stmt = $conn->prepare("
        SELECT fp.*,
               gud.full_name as user_name,
               gud.user_id
        FROM forum_posts fp
        LEFT JOIN general_user_details gud ON fp.user_id = gud.user_id
        ORDER BY fp.is_pinned DESC, fp.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $forumPosts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $forumPosts = [];
}

// Get forum posts
$forumPosts = [];
$userLikedPosts = [];
try {
    // Get all posts with user info
    $stmt = $conn->prepare("
        SELECT fp.*,
               gud.full_name as user_name,
               gud.user_id,
               COUNT(DISTINCT fl.id) as user_liked
        FROM forum_posts fp
        LEFT JOIN general_user_details gud ON fp.user_id = gud.user_id
        LEFT JOIN forum_likes fl ON fp.id = fl.post_id AND fl.user_id = ?
        GROUP BY fp.id
        ORDER BY fp.is_pinned DESC, fp.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $forumPosts = $stmt->fetchAll();
    
    // Get posts that current user has liked
    $stmt = $conn->prepare("
        SELECT post_id FROM forum_likes WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userLikedPosts = array_column($stmt->fetchAll(), 'post_id');
    
} catch(PDOException $e) {
    $forumPosts = [];
    $userLikedPosts = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboard-unified.css'); ?>">
    <style>
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        /* Hanya animasi konten, bukan section-header */
        .content-section.active > *:not(.section-header) {
            animation: fadeIn 0.3s ease;
        }
        
        .section-header {
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border-radius: 16px;
            border: 1px solid rgba(253, 121, 121, 0.2);
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.1);
            opacity: 1 !important;
            visibility: visible !important;
            position: relative;
            z-index: 1;
        }
        
        /* Pastikan section-header selalu terlihat saat section active */
        .content-section.active .section-header {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
            animation: none !important;
        }
        
        .section-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex !important;
            align-items: center;
            gap: 10px;
            opacity: 1 !important;
            visibility: visible !important;
            /* Gunakan warna solid dulu untuk memastikan terlihat */
            color: #D34E4E !important;
        }
        
        /* Gradient text hanya jika didukung */
        .content-section.active .section-header h2 {
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            /* Fallback jika gradient tidak bekerja */
            color: #D34E4E;
        }
        
        /* Pastikan h2 terlihat */
        .content-section.active .section-header h2 {
            opacity: 1 !important;
            visibility: visible !important;
            animation: none !important;
            display: flex !important;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* CRITICAL FIX: Ensure all buttons in content-section are always visible */
        .section-header .btn-create,
        .empty-state .btn-create,
        .forum-create-post .btn-create,
        .comment-form .btn-create {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 999 !important;
        }
        
        /* Ensure buttons in active sections are visible */
        .content-section.active .btn-create,
        .content-section.active .btn-secondary,
        .content-section.active .panic-btn,
        .content-section.active button[type="submit"],
        .content-section.active button[type="button"] {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Force visibility for reports section buttons */
        #section-reports.active .section-header .btn-create,
        #section-reports.active .empty-state .btn-create {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            background: #D34E4E !important;
            color: white !important;
        }
        
        /* Force visibility for forum section buttons */
        #section-forum.active .btn-create {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            background: #D34E4E !important;
            color: white !important;
        }
        
        /* Force visibility for panic section buttons */
        #section-panic.active .btn-secondary {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        #section-panic.active .panic-btn {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
            border: 1px solid rgba(253, 121, 121, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(211, 78, 78, 0.25);
            border-color: rgba(253, 121, 121, 0.4);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--color-text-light);
            margin-bottom: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .filters {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
            border: 1px solid rgba(253, 121, 121, 0.2);
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid rgba(253, 121, 121, 0.2);
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--color-text);
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: #FD7979;
            background: linear-gradient(135deg, rgba(253, 121, 121, 0.15) 0%, rgba(249, 223, 223, 0.4) 100%);
            color: #D34E4E;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2);
        }
        
        .filter-btn.active {
            border-color: #D34E4E;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(211, 78, 78, 0.3);
        }
        
        .reports-list {
            display: grid;
            gap: 20px;
        }
        
        .report-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
            border: 1px solid rgba(253, 121, 121, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 48px rgba(211, 78, 78, 0.25);
            border-color: rgba(253, 121, 121, 0.4);
        }
        
        .report-card:hover::before {
            opacity: 1;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 5px;
        }
        
        .report-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            color: var(--color-text-light);
        }
        
        .report-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-type {
            background: rgba(253, 121, 121, 0.1);
            color: var(--color-primary);
        }
        
        .badge-status {
            color: white;
        }
        
        .report-description {
            color: var(--color-text-light);
            line-height: 1.6;
            margin-top: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
            border: 2px dashed rgba(253, 121, 121, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(253, 121, 121, 0.05) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.3;
            color: #FD7979;
            position: relative;
            z-index: 1;
        }
        
        .empty-state h3 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .empty-state p {
            color: var(--color-text-light);
            margin-bottom: 32px;
            font-size: 16px;
            position: relative;
            z-index: 1;
        }
        
        .btn-create {
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%) !important;
            color: white !important;
            padding: 14px 28px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .btn-create::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-create:hover {
            background: linear-gradient(135deg, #850E35 0%, #D34E4E 100%) !important;
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(211, 78, 78, 0.4);
        }
        
        .btn-create:active::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-create:active {
            transform: translateY(0);
        }
        
        /* Ensure btn-secondary buttons are always visible */
        .btn-secondary {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 999 !important;
        }
        
        /* Ensure panic-btn is always visible */
        .panic-btn {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 999 !important;
        }
        
        .placeholder-content {
            text-align: center;
            padding: 60px 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .placeholder-content h3 {
            font-size: 24px;
            color: var(--color-text);
            margin-bottom: 15px;
        }
        
        .placeholder-content p {
            color: var(--color-text-light);
            line-height: 1.6;
        }
        
        /* Panic Button Styles */
        .panic-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .panic-button-container {
            display: flex;
            justify-content: center;
            margin: 40px 0;
        }
        
        .panic-button-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--glass-border);
            max-width: 500px;
            width: 100%;
        }
        
        .panic-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        .panic-button-card h3 {
            font-size: 28px;
            color: var(--color-text);
            margin-bottom: 15px;
        }
        
        .panic-button-card p {
            color: var(--color-text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .panic-btn {
            background: linear-gradient(135deg, #EF5350 0%, #D32F2F 100%);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 20px 60px;
            font-size: 24px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(239, 83, 80, 0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
            width: 100%;
            max-width: 400px;
        }
        
        .panic-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(239, 83, 80, 0.6);
        }
        
        .panic-btn:active {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(239, 83, 80, 0.5);
        }
        
        .panic-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .panic-btn.active {
            animation: panicPulse 1s infinite;
        }
        
        @keyframes panicPulse {
            0%, 100% {
                box-shadow: 0 8px 32px rgba(239, 83, 80, 0.4);
            }
            50% {
                box-shadow: 0 8px 48px rgba(239, 83, 80, 0.8), 0 0 60px rgba(239, 83, 80, 0.4);
            }
        }
        
        .panic-warning {
            margin-top: 30px;
            padding: 15px;
            background: rgba(239, 83, 80, 0.1);
            border-radius: 12px;
            border: 2px solid rgba(239, 83, 80, 0.3);
        }
        
        .panic-warning p {
            color: #EF5350;
            font-weight: 600;
            margin: 0;
        }
        
        .emergency-contact-item {
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .emergency-contact-info {
            flex: 1;
        }
        
        .emergency-contact-name {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 5px;
        }
        
        .emergency-contact-phone {
            font-size: 14px;
            color: var(--color-text-light);
        }
        
        .panic-history-item {
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .panic-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .panic-history-date {
            font-size: 14px;
            color: var(--color-text-light);
        }
        
        .panic-status-badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .panic-status-active {
            background: #EF5350;
            color: white;
        }
        
        .panic-status-responded {
            background: #42A5F5;
            color: white;
        }
        
        .panic-status-resolved {
            background: #66BB6A;
            color: white;
        }
        
        /* Education Modal Styles */
        .education-modal {
            animation: fadeIn 0.3s ease;
        }
        
        .education-modal-content {
            animation: slideUp 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .education-modal-content button:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        /* Education Card Enhanced */
        .education-card {
            position: relative;
        }
        
        .education-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        
        .education-card:hover {
            transform: translateY(-6px) !important;
            box-shadow: 0 12px 48px rgba(211, 78, 78, 0.25) !important;
            border-color: rgba(253, 121, 121, 0.4) !important;
        }
        
        .education-card:hover::before {
            opacity: 1;
        }
        
        .education-card:hover .education-thumbnail {
            transform: scale(1.05);
        }
        
        /* Forum Styles */
        .forum-create-post {
            margin-bottom: 30px;
            background: var(--glass-bg) !important;
            backdrop-filter: blur(20px) !important;
            border-radius: 20px !important;
            padding: 25px !important;
            box-shadow: 0 8px 32px rgba(253, 121, 121, 0.15) !important;
            border: 1px solid var(--glass-border) !important;
            transition: all 0.3s ease;
        }
        
        .forum-create-post:hover {
            box-shadow: 0 12px 40px rgba(253, 121, 121, 0.2) !important;
            transform: translateY(-2px);
        }
        
        .forum-create-post textarea {
            border: 2px solid rgba(211, 78, 78, 0.3) !important;
            background: #ffffff !important;
            transition: all 0.3s ease;
        }
        
        .forum-create-post textarea:focus {
            outline: none;
            border-color: #D34E4E !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(211, 78, 78, 0.15) !important;
        }
        
        .forum-posts-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .forum-post-card {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(20px) !important;
            border-radius: 20px !important;
            padding: 25px !important;
            box-shadow: 0 8px 32px rgba(253, 121, 121, 0.1) !important;
            border: 1px solid var(--glass-border) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        
        .forum-post-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-soft);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .forum-post-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 12px 48px rgba(253, 121, 121, 0.2) !important;
        }
        
        .forum-post-card:hover::before {
            opacity: 1;
        }
        
        .forum-post-card .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2);
            transition: transform 0.3s ease;
        }
        
        .forum-post-card:hover .user-avatar {
            transform: scale(1.05);
        }
        
        .post-actions {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(253, 121, 121, 0.1);
            margin-top: 15px;
        }
        
        .btn-comment {
            background: rgba(255, 255, 255, 0.5) !important;
            border: 1px solid rgba(253, 121, 121, 0.2) !important;
            color: var(--color-text) !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px !important;
            border-radius: 12px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .btn-comment::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(253, 121, 121, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-comment:active::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-comment:hover {
            background: rgba(253, 121, 121, 0.1) !important;
            border-color: var(--color-primary) !important;
            color: var(--color-primary) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(253, 121, 121, 0.15);
        }
        
        .btn-comment span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            color: inherit;
        }
        
        .btn-comment span svg {
            width: 100%;
            height: 100%;
            transition: transform 0.3s ease;
            color: inherit;
        }
        
        .btn-comment span svg path {
            stroke: currentColor;
        }
        
        .btn-comment:hover span svg {
            transform: scale(1.2);
        }
        
        .comment-count {
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        .comments-section {
            animation: slideDown 0.3s ease;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(253, 121, 121, 0.1);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                max-height: 1000px;
                transform: translateY(0);
            }
        }
        
        .comment-item {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 3px solid rgba(253, 121, 121, 0.3);
            transition: all 0.3s ease;
        }
        
        .comment-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border-left-color: var(--color-primary);
            transform: translateX(5px);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .comment-author {
            font-weight: 600;
            color: var(--color-text);
            font-size: 14px;
        }
        
        .comment-time {
            font-size: 11px;
            color: var(--color-text-light);
        }
        
        .comment-content {
            color: var(--color-text);
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .btn-delete-post {
            background: none;
            border: none;
            color: var(--color-text-light);
            cursor: pointer;
            padding: 6px 12px;
            font-size: 12px;
            transition: all 0.2s;
            border-radius: 6px;
        }
        
        .btn-delete-post:hover {
            color: #EF5350;
            background: rgba(239, 83, 80, 0.1);
        }
        
        .post-content {
            color: var(--color-text);
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .comment-form textarea {
            border: 2px solid rgba(211, 78, 78, 0.3) !important;
            background: #ffffff !important;
            transition: all 0.3s ease;
        }
        
        .comment-form textarea:focus {
            outline: none;
            border-color: #D34E4E !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(211, 78, 78, 0.15) !important;
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }
        
        /* Dashboard Main Section - Enhanced */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 20px;
        }
        
        .action-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 48px rgba(211, 78, 78, 0.25);
            border-color: rgba(253, 121, 121, 0.4);
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.5) 100%);
        }
        
        .action-card:hover::before {
            opacity: 1;
        }
        
        .action-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(253, 121, 121, 0.15) 0%, rgba(249, 223, 223, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #D34E4E;
        }
        
        .action-card:hover .action-icon {
            background: linear-gradient(135deg, rgba(253, 121, 121, 0.25) 0%, rgba(249, 223, 223, 0.15) 100%);
            transform: scale(1.1) rotate(5deg);
        }
        
        .action-card strong {
            font-size: 16px;
            font-weight: 600;
            color: var(--color-text);
            margin: 0;
        }
        
        .action-card small {
            font-size: 13px;
            color: var(--color-text-light);
            margin: 0;
        }
        
        .welcome-message {
            margin-top: 20px;
        }
        
        .welcome-message p {
            font-size: 15px;
            line-height: 1.8;
            color: var(--color-text);
            margin-bottom: 12px;
        }
        
        .welcome-message strong {
            color: #D34E4E;
            font-weight: 700;
            font-size: 18px;
        }
        
        .activity-list {
            margin-top: 20px;
        }
        
        .activity-list > div {
            padding: 18px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            border-radius: 14px;
            border: 1px solid rgba(253, 121, 121, 0.15);
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .activity-list > div:hover {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.4) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
            border-color: rgba(253, 121, 121, 0.3);
        }
        
        .activity-list > div:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        /* Dashboard Card Header Enhanced */
        .dashboard-card h3 {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(253, 121, 121, 0.15);
        }
        
        /* Forum Create Post Enhanced */
        .forum-create-post {
            margin-bottom: 30px;
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%) !important;
            backdrop-filter: blur(20px) !important;
            border-radius: 20px !important;
            padding: 28px !important;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15) !important;
            border: 1px solid rgba(253, 121, 121, 0.2) !important;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .forum-create-post::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .forum-create-post:hover {
            box-shadow: 0 8px 32px rgba(211, 78, 78, 0.25) !important;
            transform: translateY(-2px);
            border-color: rgba(253, 121, 121, 0.4) !important;
        }
        
        .forum-create-post:hover::before {
            opacity: 1;
        }
        
        /* Forum Post Card Enhanced */
        .forum-post-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%) !important;
            backdrop-filter: blur(20px) !important;
            border-radius: 20px !important;
            padding: 28px !important;
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15) !important;
            border: 1px solid rgba(253, 121, 121, 0.2) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        
        .forum-post-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 50%, #F9DFDF 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .forum-post-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 12px 48px rgba(211, 78, 78, 0.25) !important;
            border-color: rgba(253, 121, 121, 0.4) !important;
        }
        
        .forum-post-card:hover::before {
            opacity: 1;
        }
        
        /* Comment Item Enhanced */
        .comment-item {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 12px;
            border-left: 4px solid rgba(253, 121, 121, 0.3);
            transition: all 0.3s ease;
        }
        
        .comment-item:hover {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.5) 100%);
            border-left-color: #D34E4E;
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(253, 121, 121, 0.15);
        }
        
        /* Emergency Contact Item Enhanced */
        .emergency-contact-item {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .emergency-contact-item:hover {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.5) 100%);
            border-color: rgba(253, 121, 121, 0.4);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
        }
        
        /* Panic History Item Enhanced */
        .panic-history-item {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .panic-history-item:hover {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.5) 100%);
            border-color: rgba(253, 121, 121, 0.4);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15);
        }
        
        /* Panic Button Card Enhanced */
        .panic-button-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(253, 121, 121, 0.2);
            border: 2px solid rgba(253, 121, 121, 0.3);
            max-width: 500px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .panic-button-card:hover {
            box-shadow: 0 12px 48px rgba(211, 78, 78, 0.3);
            transform: translateY(-4px);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <!-- Section: Dashboard -->
            <div id="section-dashboard" class="content-section active">
                <!-- Dashboard Grid - 3 Columns -->
                <div class="dashboard-grid">
                    <!-- Left Column -->
                    <div class="card-col">
                        <!-- Quick Actions Card -->
                        <div class="dashboard-card">
                            <h3>Fitur Tersedia</h3>
                            <div class="quick-actions-grid">
                                <a href="<?php echo BASE_URL; ?>/dashboard/user/create-report.php" class="action-card">
                                    <div class="action-icon"><?php echo icon('create-report', '', 32, 32); ?></div>
                                    <strong>Buat Laporan</strong>
                                    <small>Laporkan kejadian</small>
                                </a>
                                <a href="#" class="action-card" onclick="event.preventDefault(); showSection('reports');">
                                    <div class="action-icon"><?php echo icon('reports', '', 32, 32); ?></div>
                                    <strong>Laporan Saya</strong>
                                    <small>Lihat status laporan</small>
                                </a>
                                <a href="#" class="action-card" onclick="event.preventDefault(); showSection('panic');">
                                    <div class="action-icon"><?php echo icon('panic', '', 32, 32); ?></div>
                                    <strong>Panic Button</strong>
                                    <small>Bantuan darurat</small>
                                </a>
                                <a href="#" class="action-card" onclick="event.preventDefault(); showSection('education');">
                                    <div class="action-icon"><?php echo icon('education', '', 32, 32); ?></div>
                                    <strong>Edukasi</strong>
                                    <small>Konten edukatif</small>
                                </a>
                                <a href="#" class="action-card" onclick="event.preventDefault(); showSection('forum');">
                                    <div class="action-icon"><?php echo icon('forum', '', 32, 32); ?></div>
                                    <strong>Forum</strong>
                                    <small>Diskusi komunitas</small>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Middle Column -->
                    <div class="card-col">
                        <!-- Welcome Card -->
                        <div class="dashboard-card">
                            <h3>Selamat Datang</h3>
                            <div class="welcome-message">
                                <p>Halo, <strong><?php echo htmlspecialchars($userName); ?>!</strong></p>
                                <p>Anda dapat menggunakan fitur-fitur di sebelah kiri untuk melaporkan kejadian, melihat status laporan, atau mengakses konten edukasi.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="card-col">
                        <!-- Recent Activity Card -->
                        <div class="dashboard-card">
                            <h3>Aktivitas Terbaru</h3>
                            <div class="activity-list">
                                <?php if (!empty($reports) && count($reports) > 0): ?>
                                    <?php 
                                    $recentReports = array_slice($reports, 0, 3);
                                    foreach ($recentReports as $report): 
                                    ?>
                                        <div style="padding: 15px; border-bottom: 1px solid rgba(253, 121, 121, 0.1);">
                                            <div style="font-weight: 600; color: var(--color-text); margin-bottom: 5px;">
                                                <?php echo htmlspecialchars($report['title']); ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--color-text-light);">
                                                <?php echo formatDate($report['created_at']); ?>
                                                <span style="margin: 0 5px;">•</span>
                                                <span style="color: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                                                    <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: var(--color-text); opacity: 0.6; padding: 20px;">Belum ada aktivitas</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section: Daftar Laporan -->
            <div id="section-reports" class="content-section">
                <div class="section-header">
                    <h2>Daftar Laporan Saya</h2>
                    <a href="<?php echo BASE_URL; ?>/dashboard/user/create-report.php" class="btn-create">+ Buat Laporan Baru</a>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Laporan</h3>
                        <div class="number"><?php echo array_sum($stats); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending</h3>
                        <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Sedang Diproses</h3>
                        <div class="number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Selesai</h3>
                        <div class="number"><?php echo $stats['resolved'] ?? 0; ?></div>
                    </div>
                </div>
                
                <div class="filters">
                    <div class="filter-buttons">
                        <a href="#" class="filter-btn filter-status <?php echo $statusFilter == 'all' ? 'active' : ''; ?>" data-status="all">Semua</a>
                        <a href="#" class="filter-btn filter-status <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>" data-status="pending">Pending</a>
                        <a href="#" class="filter-btn filter-status <?php echo $statusFilter == 'in_progress' ? 'active' : ''; ?>" data-status="in_progress">Sedang Diproses</a>
                        <a href="#" class="filter-btn filter-status <?php echo $statusFilter == 'resolved' ? 'active' : ''; ?>" data-status="resolved">Selesai</a>
                        <a href="#" class="filter-btn filter-status <?php echo $statusFilter == 'rejected' ? 'active' : ''; ?>" data-status="rejected">Ditolak</a>
                    </div>
                </div>
                
                <?php if (empty($reports)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><?php echo icon('reports', '', 64, 64); ?></div>
                        <h3>Tidak Ada Laporan</h3>
                        <p>Anda belum membuat laporan apapun.</p>
                        <a href="<?php echo BASE_URL; ?>/dashboard/user/create-report.php" class="btn-create">Buat Laporan Pertama</a>
                    </div>
                <?php else: ?>
                    <div class="reports-list">
                        <?php foreach ($reports as $report): ?>
                            <a href="<?php echo BASE_URL; ?>/dashboard/user/report-detail.php?id=<?php echo $report['id']; ?>" class="report-card">
                                <div class="report-header">
                                    <div style="flex: 1;">
                                        <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                        <div class="report-meta">
                                            <span>#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                            <span>•</span>
                                            <span><?php echo formatDate($report['created_at']); ?></span>
                                            <?php if ($report['attachment_count'] > 0): ?>
                                                <span>•</span>
                                                <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> <?php echo $report['attachment_count']; ?> file</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="report-badges">
                                        <?php if ($report['is_anonymous']): ?>
                                            <span class="badge" style="background: #9E9E9E; color: white;">Anonim</span>
                                        <?php endif; ?>
                                        <span class="badge badge-type"><?php echo $reportTypeLabels[$report['report_type']] ?? $report['report_type']; ?></span>
                                        <span class="badge badge-status" style="background: <?php echo $statusColors[$report['status']] ?? '#666'; ?>;">
                                            <?php echo $statusLabels[$report['status']] ?? $report['status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="report-description">
                                    <?php echo htmlspecialchars($report['description']); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Section: Panic -->
            <div id="section-panic" class="content-section">
                <div class="section-header">
                    <h2>Panic Button</h2>
                </div>
                
                <div class="panic-container">
                    <!-- Emergency Contacts Management -->
                    <div class="dashboard-card" style="margin-bottom: 30px;">
                        <h3>Kontak Darurat</h3>
                        <p style="color: var(--color-text-light); margin-bottom: 20px;">
                            Tambahkan kontak darurat yang akan menerima notifikasi saat Anda mengaktifkan panic button.
                        </p>
                        <div id="emergency-contacts-list">
                            <?php if (empty($emergencyContacts)): ?>
                                <p style="text-align: center; color: var(--color-text-light); padding: 20px;">
                                    Belum ada kontak darurat. Tambahkan kontak darurat untuk menerima notifikasi.
                                </p>
                            <?php else: ?>
                                <?php foreach ($emergencyContacts as $index => $contact): ?>
                                    <div class="emergency-contact-item">
                                        <div class="emergency-contact-info">
                                            <div class="emergency-contact-name"><?php echo htmlspecialchars($contact['name'] ?? 'Nama tidak tersedia'); ?></div>
                                            <div class="emergency-contact-phone"><?php echo htmlspecialchars($contact['phone'] ?? 'Nomor tidak tersedia'); ?></div>
                                        </div>
                                        <button type="button" class="btn btn-secondary" onclick="removeEmergencyContact(<?php echo $index; ?>)">
                                            Hapus
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="showAddContactModal()" style="margin-top: 15px;">
                            + Tambah Kontak Darurat
                        </button>
                    </div>
                    
                    <!-- Panic Button -->
                    <div class="panic-button-container">
                        <div class="panic-button-card">
                            <div class="panic-icon"><?php echo icon('panic', '', 80, 80); ?></div>
                            <h3>Panic Button</h3>
                            <p>Tekan tombol di bawah untuk mengirim notifikasi darurat ke polisi dan kontak darurat Anda.</p>
                            
                            <button type="button" id="panicButton" class="panic-btn" onclick="activatePanic()">
                                <span class="panic-btn-text">AKTIFKAN PANIC</span>
                            </button>
                            
                            <div class="panic-warning">
                                <p><?php echo icon('panic', '', 20, 20); ?> Hanya gunakan dalam keadaan darurat!</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panic History -->
                    <div class="dashboard-card" style="margin-top: 30px;">
                        <h3>Riwayat Panic Button</h3>
                        <div id="panic-history">
                            <?php if (empty($panicHistory)): ?>
                                <p style="text-align: center; color: var(--color-text-light); padding: 20px;">Belum ada riwayat panic button</p>
                            <?php else: ?>
                                <?php
                                $panicStatusLabels = [
                                    'active' => 'Aktif',
                                    'responded' => 'Ditanggapi',
                                    'resolved' => 'Selesai',
                                    'false_alarm' => 'Alarm Palsu'
                                ];
                                $panicStatusColors = [
                                    'active' => 'panic-status-active',
                                    'responded' => 'panic-status-responded',
                                    'resolved' => 'panic-status-resolved',
                                    'false_alarm' => 'panic-status-resolved'
                                ];
                                foreach ($panicHistory as $alert):
                                ?>
                                    <div class="panic-history-item">
                                        <div class="panic-history-header">
                                            <div>
                                                <strong><?php echo formatDateTime($alert['created_at']); ?></strong>
                                            </div>
                                            <span class="panic-status-badge <?php echo $panicStatusColors[$alert['status']] ?? 'panic-status-active'; ?>">
                                                <?php echo $panicStatusLabels[$alert['status']] ?? $alert['status']; ?>
                                            </span>
                                        </div>
                                        <?php if ($alert['location']): ?>
                                            <p style="color: var(--color-text-light); margin-top: 10px; display: flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> <?php echo htmlspecialchars($alert['location']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($alert['notes']): ?>
                                            <p style="color: var(--color-text-light); margin-top: 10px;"><?php echo htmlspecialchars($alert['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section: Forum -->
            <div id="section-forum" class="content-section">
                <div class="section-header">
                    <h2>Forum Diskusi</h2>
                </div>
                
                <!-- Create Post Form -->
                <div class="forum-create-post">
                    <form id="createPostForm" onsubmit="return createPost(event)">
                        <div style="display: flex; gap: 15px; align-items: flex-start;">
                            <div class="user-avatar" style="width: 50px; height: 50px; border-radius: 50%; background: var(--gradient-soft); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2);">
                                <?php echo icon('users', '', 24, 24); ?>
                            </div>
                            <div style="flex: 1;">
                                <textarea id="postContent" name="content" rows="3" placeholder="Apa yang ingin Anda bagikan hari ini? 💭" required style="width: 100%; padding: 15px; border: 2px solid rgba(253, 121, 121, 0.2); border-radius: 12px; background: rgba(255, 255, 255, 0.7); color: var(--color-text); font-size: 15px; font-family: inherit; resize: vertical; min-height: 100px; transition: all 0.3s ease;"></textarea>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                                    <div style="font-size: 12px; color: var(--color-text-light); font-weight: 500;" id="charCount">0 / 500</div>
                                    <button type="submit" class="btn-create" style="padding: 12px 28px; font-size: 14px; font-weight: 600; border-radius: 12px; box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2); transition: all 0.3s ease;">Post</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Forum Timeline -->
                <div id="forumTimeline">
                    <?php if (empty($forumPosts)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?php echo icon('forum', '', 64, 64); ?></div>
                            <h3>Belum Ada Post</h3>
                            <p>Jadilah yang pertama untuk memulai diskusi di forum!</p>
                        </div>
                    <?php else: ?>
                        <div class="forum-posts-list">
                            <?php foreach ($forumPosts as $post): 
                                $timeAgo = getTimeAgo($post['created_at']);
                            ?>
                                <div class="forum-post-card" data-post-id="<?php echo $post['id']; ?>">
                                    <div style="display: flex; gap: 15px;">
                                        <div class="user-avatar">
                                            <?php echo icon('users', '', 24, 24); ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                                <div>
                                                    <strong style="color: var(--color-text); font-size: 16px; font-weight: 600;"><?php echo htmlspecialchars($post['user_name'] ?? 'User'); ?></strong>
                                                    <span style="color: var(--color-text-light); font-size: 12px; margin-left: 8px;">• <?php echo $timeAgo; ?></span>
                                                </div>
                                                <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn-delete-post" onclick="deletePost(<?php echo $post['id']; ?>)">Hapus</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="post-content">
                                                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                            </div>
                                            <?php if ($post['image_url']): ?>
                                                <div style="margin-bottom: 15px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" style="max-width: 100%; display: block;">
                                                </div>
                                            <?php endif; ?>
                                            <div class="post-actions">
                                                <button type="button" class="btn-comment" onclick="toggleComments(<?php echo $post['id']; ?>, this)" data-post-id="<?php echo $post['id']; ?>">
                                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; color: inherit;">
                                                        <?php echo icon('comment', '', 20, 20); ?>
                                                    </span>
                                                    <span class="comment-count"><?php echo $post['comments_count']; ?></span>
                                                </button>
                                            </div>
                                            
                                            <!-- Comments Section -->
                                            <div class="comments-section" id="comments-<?php echo $post['id']; ?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(253, 121, 121, 0.1);">
                                                <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                                    <!-- Comments will be loaded here -->
                                                </div>
                                                <div class="comment-form" style="margin-top: 15px;">
                                                    <form onsubmit="submitComment(event, <?php echo $post['id']; ?>)">
                                                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                                                            <textarea name="comment" rows="2" placeholder="Tulis komentar..." required style="flex: 1; padding: 12px; border: 2px solid rgba(253, 121, 121, 0.2); border-radius: 10px; background: rgba(255, 255, 255, 0.7); color: var(--color-text); font-size: 14px; font-family: inherit; resize: vertical; transition: all 0.3s ease;"></textarea>
                                                            <button type="submit" class="btn-create" style="padding: 12px 24px; font-size: 14px; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 12px rgba(253, 121, 121, 0.2); transition: all 0.3s ease; white-space: nowrap;">Kirim</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section: Edukasi -->
            <div id="section-education" class="content-section">
                <div class="section-header">
                    <h2>Konten Edukasi</h2>
                </div>
                
                <!-- Filters -->
                <div class="filters" style="margin-bottom: 30px;">
                    <div class="filter-buttons">
                        <a href="javascript:void(0)" onclick="filterEducation('all')" class="filter-btn <?php echo $educationCategoryFilter == 'all' ? 'active' : ''; ?>">Semua</a>
                        <?php foreach ($categoryLabels as $key => $label): ?>
                            <a href="javascript:void(0)" onclick="filterEducation('<?php echo $key; ?>')" class="filter-btn <?php echo $educationCategoryFilter == $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (empty($educationContent)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><?php echo icon('education', '', 64, 64); ?></div>
                        <h3>Tidak Ada Konten</h3>
                        <p>Belum ada konten edukasi yang tersedia.</p>
                    </div>
                <?php else: ?>
                    <div class="education-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($educationContent as $content): ?>
                            <div class="education-card" style="background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.2) 100%); backdrop-filter: blur(20px); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(253, 121, 121, 0.15); border: 1px solid rgba(253, 121, 121, 0.2); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; position: relative;" onclick="openEducationModal(<?php echo $content['id']; ?>)">
                                <div class="education-thumbnail" style="width: 100%; height: 200px; background: linear-gradient(135deg, #F9DFDF 0%, #FD7979 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; transition: transform 0.3s ease;">
                                    <?php if ($content['thumbnail']): ?>
                                        <img src="<?php echo htmlspecialchars($content['thumbnail']); ?>" alt="<?php echo htmlspecialchars($content['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;"><?php echo icon('education', '', 48, 48); ?></div>
                                    <?php endif; ?>
                                    <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                        <?php echo $content['duration'] ? htmlspecialchars($content['duration']) : 'Video'; ?>
                                    </div>
                                </div>
                                <div style="padding: 20px;">
                                    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                        <span class="badge badge-type" style="background: rgba(253, 121, 121, 0.1); color: var(--color-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;">
                                            <?php echo $categoryLabels[$content['category']] ?? $content['category']; ?>
                                        </span>
                                    </div>
                                    <h3 style="font-size: 18px; font-weight: 600; color: var(--color-text); margin: 0 0 10px 0; line-height: 1.4;">
                                        <?php echo htmlspecialchars($content['title']); ?>
                                    </h3>
                                    <?php if ($content['description']): ?>
                                        <p style="font-size: 14px; color: var(--color-text-light); margin: 0 0 10px 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?php echo htmlspecialchars($content['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; font-size: 12px; color: var(--color-text-light);">
                                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('users', '', 16, 16); ?> <?php echo htmlspecialchars($content['author_name'] ?? 'Admin'); ?></span>
                                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> <?php echo number_format($content['views']); ?> views</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
    // SPA Navigation Handler
    (function() {
        const navLinks = document.querySelectorAll('.nav-link[data-section]');
        const sections = document.querySelectorAll('.content-section');
        
        if (navLinks.length === 0 || sections.length === 0) {
            console.warn('Navigation links or sections not found');
            return;
        }
        
        // Function to ensure all buttons are visible
        function ensureButtonsVisible() {
            // Ensure all btn-create buttons are visible
            const btnCreates = document.querySelectorAll('.content-section .btn-create');
            btnCreates.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
                btn.style.background = '#D34E4E';
                btn.style.color = 'white';
            });
            
            // Ensure all btn-secondary buttons are visible
            const btnSecondaries = document.querySelectorAll('.content-section .btn-secondary');
            btnSecondaries.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
            });
            
            // Ensure panic-btn is visible
            const panicBtn = document.getElementById('panicButton');
            if (panicBtn) {
                panicBtn.style.display = 'block';
                panicBtn.style.visibility = 'visible';
                panicBtn.style.opacity = '1';
            }
            
            // Ensure all submit buttons are visible
            const submitButtons = document.querySelectorAll('.content-section button[type="submit"]');
            submitButtons.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
                if (btn.classList.contains('btn-create')) {
                    btn.style.background = '#D34E4E';
                    btn.style.color = 'white';
                }
            });
        }
        
        // Make showSection available globally
        window.showSection = function(sectionId) {
            // Hide all sections
            sections.forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            const targetSection = document.getElementById('section-' + sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Update active nav link
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('data-section') === sectionId) {
                    link.classList.add('active');
                }
            });
            
            // Update indicator position
            if (window.updateNavIndicator) {
                setTimeout(window.updateNavIndicator, 100);
            }
            
            // CRITICAL FIX: Ensure all buttons are visible after section switch
            setTimeout(function() {
                ensureButtonsVisible();
            }, 50);
            
            // Double-check after animation
            setTimeout(function() {
                ensureButtonsVisible();
            }, 300);
            
            // Update URL without reload
            const newUrl = window.location.pathname + '?section=' + sectionId;
            history.pushState({section: sectionId}, '', newUrl);
        };
        
        // Handle nav link clicks
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.getAttribute('data-section');
                window.showSection(sectionId);
            });
        });
        
        // Handle browser back/forward
        window.addEventListener('popstate', function(e) {
            const section = e.state?.section || (new URLSearchParams(window.location.search).get('section') || 'dashboard');
            window.showSection(section);
        });
        
        // Load section from URL on page load
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section') || 'dashboard';
        window.showSection(section);
        
        // CRITICAL FIX: Ensure all buttons are visible on page load
        function ensureAllButtonsVisible() {
            // Ensure all btn-create buttons are visible
            const btnCreates = document.querySelectorAll('.content-section .btn-create');
            btnCreates.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
                btn.style.background = '#D34E4E';
                btn.style.color = 'white';
            });
            
            // Ensure all btn-secondary buttons are visible
            const btnSecondaries = document.querySelectorAll('.content-section .btn-secondary');
            btnSecondaries.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
            });
            
            // Ensure panic-btn is visible
            const panicBtn = document.getElementById('panicButton');
            if (panicBtn) {
                panicBtn.style.display = 'block';
                panicBtn.style.visibility = 'visible';
                panicBtn.style.opacity = '1';
            }
            
            // Ensure all submit buttons are visible
            const submitButtons = document.querySelectorAll('.content-section button[type="submit"]');
            submitButtons.forEach(btn => {
                btn.style.display = 'inline-block';
                btn.style.visibility = 'visible';
                btn.style.opacity = '1';
                if (btn.classList.contains('btn-create')) {
                    btn.style.background = '#D34E4E';
                    btn.style.color = 'white';
                }
            });
        }
        
        // Run immediately and multiple times to ensure visibility
        ensureAllButtonsVisible();
        setTimeout(ensureAllButtonsVisible, 100);
        setTimeout(ensureAllButtonsVisible, 300);
        setTimeout(ensureAllButtonsVisible, 500);
        setTimeout(ensureAllButtonsVisible, 1000);
        
        // Use MutationObserver to watch for section changes
        const observer = new MutationObserver(function(mutations) {
            ensureAllButtonsVisible();
        });
        
        // Observe all content sections
        sections.forEach(section => {
            observer.observe(section, {
                attributes: true,
                attributeFilter: ['class'],
                childList: false,
                subtree: false
            });
        });
        
        // Also observe when buttons are added/removed
        const buttonObserver = new MutationObserver(function(mutations) {
            ensureAllButtonsVisible();
        });
        
        sections.forEach(section => {
            buttonObserver.observe(section, {
                childList: true,
                subtree: true
            });
        });
        
        // Handle filter buttons in reports section
        const filterButtons = document.querySelectorAll('.filter-status');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const status = this.getAttribute('data-status');
                
                // Update active filter
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Reload reports with new filter
                const newUrl = window.location.pathname + '?section=reports&status=' + status;
                window.location.href = newUrl;
            });
        });
    })();
    
    // Panic Button Handler - Make globally available
    window.activatePanic = function() {
        if (!confirm('Apakah Anda yakin ingin mengaktifkan Panic Button? Notifikasi akan dikirim ke polisi dan kontak darurat Anda.')) {
            return;
        }
        
        const panicBtn = document.getElementById('panicButton');
        if (!panicBtn) {
            console.error('Panic button not found');
            return;
        }
        
        panicBtn.disabled = true;
        panicBtn.classList.add('active');
        panicBtn.innerHTML = '<span class="panic-btn-text">MENGIRIM...</span>';
        
        // Get location if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    sendPanicAlert(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    sendPanicAlert(null, null);
                }
            );
        } else {
            sendPanicAlert(null, null);
        }
    };
    
    function sendPanicAlert(latitude, longitude) {
        const formData = new FormData();
        if (latitude) formData.append('latitude', latitude);
        if (longitude) formData.append('longitude', longitude);
        
        fetch('<?php echo BASE_URL; ?>/dashboard/user/panic-handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Panic button berhasil diaktifkan! Notifikasi telah dikirim ke polisi dan kontak darurat Anda.');
                // Reload page to show updated history
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Gagal mengaktifkan panic button'));
            }
            
            const panicBtn = document.getElementById('panicButton');
            if (panicBtn) {
                panicBtn.disabled = false;
                panicBtn.classList.remove('active');
                panicBtn.innerHTML = '<span class="panic-btn-text">AKTIFKAN PANIC</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengaktifkan panic button.');
            
            const panicBtn = document.getElementById('panicButton');
            if (panicBtn) {
                panicBtn.disabled = false;
                panicBtn.classList.remove('active');
                panicBtn.innerHTML = '<span class="panic-btn-text">AKTIFKAN PANIC</span>';
            }
        });
    }
    
    // Emergency Contacts Management - Make globally available
    window.showAddContactModal = function() {
        const name = prompt('Masukkan nama kontak darurat:');
        if (!name) return;
        
        const phone = prompt('Masukkan nomor telepon kontak darurat:');
        if (!phone) return;
        
        // Validate phone number (simple validation)
        if (phone.length < 10) {
            alert('Nomor telepon harus minimal 10 digit!');
            return;
        }
        
        // Add contact via AJAX
        const formData = new FormData();
        formData.append('action', 'add_contact');
        formData.append('name', name);
        formData.append('phone', phone);
        
        fetch('<?php echo BASE_URL; ?>/dashboard/user/panic-handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Kontak darurat berhasil ditambahkan!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Gagal menambahkan kontak darurat'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menambahkan kontak darurat.');
        });
    };
    
    window.removeEmergencyContact = function(index) {
        if (!confirm('Apakah Anda yakin ingin menghapus kontak darurat ini?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'remove_contact');
        formData.append('index', index);
        
        fetch('<?php echo BASE_URL; ?>/dashboard/user/panic-handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Kontak darurat berhasil dihapus!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Gagal menghapus kontak darurat'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menghapus kontak darurat.');
        });
    };
    
    // Education Content Functions
    window.filterEducation = function(category) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('section', 'education');
        if (category === 'all') {
            currentUrl.searchParams.delete('edu_category');
        } else {
            currentUrl.searchParams.set('edu_category', category);
        }
        window.location.href = currentUrl.toString();
    };
    
            window.viewEducationContent = function(contentId) {
                window.location.href = '<?php echo BASE_URL; ?>/dashboard/user/education-detail.php?id=' + contentId;
            };
            
            // Education Modal Functions - Make globally available
            window.openEducationModal = function(contentId) {
                // Show loading
                document.getElementById('educationModal').style.display = 'flex';
                document.getElementById('educationModalContent').innerHTML = '<div style="text-align: center; padding: 40px;"><div style="margin-bottom: 20px; display: inline-block;"><?php echo icon('pending', '', 24, 24); ?></div><p>Memuat konten...</p></div>';
                
                // Fetch content data
                fetch('<?php echo BASE_URL; ?>/dashboard/user/education-detail-api.php?id=' + contentId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayEducationModal(data.content);
                        } else {
                            document.getElementById('educationModalContent').innerHTML = 
                                '<div style="text-align: center; padding: 40px;"><p style="color: #EF5350;">Error: ' + (data.message || 'Gagal memuat konten') + '</p></div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('educationModalContent').innerHTML = 
                            '<div style="text-align: center; padding: 40px;"><p style="color: #EF5350;">Terjadi kesalahan saat memuat konten</p></div>';
                    });
            };
            
            window.closeEducationModal = function() {
                document.getElementById('educationModal').style.display = 'none';
                // Stop video by clearing iframe src
                const iframe = document.getElementById('educationModalContent').querySelector('iframe');
                if (iframe) {
                    iframe.src = '';
                }
            };
            
            function displayEducationModal(content) {
                const categoryLabels = {
                    'bullying': 'Perundungan',
                    'violence': 'Kekerasan',
                    'harassment': 'Pelecehan',
                    'abuse': 'Abuse',
                    'prevention': 'Pencegahan',
                    'support': 'Dukungan',
                    'legal': 'Hukum',
                    'mental_health': 'Kesehatan Mental'
                };
                
                // Extract video embed URL
                function getVideoEmbedUrl(url) {
                    let match;
                    // YouTube
                    match = /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/.exec(url);
                    if (match) {
                        return 'https://www.youtube.com/embed/' + match[1];
                    }
                    // Vimeo
                    match = /vimeo\.com\/(\d+)/.exec(url);
                    if (match) {
                        return 'https://player.vimeo.com/video/' + match[1];
                    }
                    return url;
                }
                
                const embedUrl = getVideoEmbedUrl(content.video_url);
                const categoryLabel = categoryLabels[content.category] || content.category;
                
                document.getElementById('educationModalContent').innerHTML = `
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid rgba(253, 121, 121, 0.2);">
                        <h2 style="margin: 0; color: var(--color-text); font-size: 24px;">${escapeHtml(content.title)}</h2>
                        <button onclick="closeEducationModal()" style="background: none; border: none; font-size: 28px; color: var(--color-text); cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;">&times;</button>
                    </div>
                    
                    <div class="modal-meta" style="display: flex; gap: 15px; flex-wrap: wrap; font-size: 14px; color: var(--color-text-light); margin-bottom: 20px; align-items: center;">
                        <span class="badge badge-type" style="background: rgba(253, 121, 121, 0.1); color: var(--color-primary); padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;">${categoryLabel}</span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('users', '', 16, 16); ?> ${escapeHtml(content.author_name || 'Admin')}</span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('reports', '', 16, 16); ?> ${formatNumber(content.views)} views</span>
                        <span style="display: inline-flex; align-items: center; gap: 4px;"><?php echo icon('calendar', '', 16, 16); ?> ${formatDate(content.created_at)}</span>
                    </div>
                    
                    <div class="modal-video" style="position: relative; width: 100%; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 15px; margin-bottom: 20px; box-shadow: var(--shadow-medium);">
                        <iframe src="${embedUrl}" 
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    </div>
                    
                    ${content.description ? `
                        <div class="modal-description" style="font-size: 16px; line-height: 1.8; color: var(--color-text);">
                            ${escapeHtml(content.description).replace(/\n/g, '<br>')}
                        </div>
                    ` : ''}
                `;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatNumber(num) {
                return new Intl.NumberFormat('id-ID').format(num);
            }
            
            function formatDate(dateString) {
                const date = new Date(dateString);
                // Convert to Jakarta timezone (UTC+7)
                return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' });
            }
            
            // Close modal when clicking outside
            const educationModal = document.getElementById('educationModal');
            if (educationModal) {
                educationModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeEducationModal();
                    }
                });
            }
            
            // Close modal with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeEducationModal();
                }
            });
            
            // Forum Functions
            // Character counter for post
            const postContentTextarea = document.getElementById('postContent');
            if (postContentTextarea) {
                postContentTextarea.addEventListener('input', function() {
                    const count = this.value.length;
                    const maxLength = 500;
                    const charCountEl = document.getElementById('charCount');
                    if (charCountEl) {
                        charCountEl.textContent = count + ' / ' + maxLength;
                        if (count > maxLength) {
                            this.value = this.value.substring(0, maxLength);
                            charCountEl.textContent = maxLength + ' / ' + maxLength;
                        }
                    }
                });
            }
            
            window.createPost = function(event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                const form = document.getElementById('createPostForm');
                if (!form) {
                    console.error('Form not found');
                    return false;
                }
                
                const content = form.querySelector('#postContent').value.trim();
                
                if (!content) {
                    alert('Konten post tidak boleh kosong!');
                    return false;
                }
                
                if (content.length > 500) {
                    alert('Konten post maksimal 500 karakter!');
                    return false;
                }
                
                const formData = new FormData();
                formData.append('action', 'create_post');
                formData.append('content', content);
                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Posting...';
                
                const apiUrl = '<?php echo BASE_URL; ?>/dashboard/user/forum-action.php';
                console.log('Sending POST request to:', apiUrl);
                
                fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        // If response is not OK, try to get error message
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch(e) {
                                throw new Error('Server error: ' + response.status);
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        form.querySelector('#postContent').value = '';
                        const charCountEl = document.getElementById('charCount');
                        if (charCountEl) charCountEl.textContent = '0 / 500';
                        
                        // Update URL to stay on forum section before reload
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('section', 'forum');
                        window.history.replaceState({}, '', currentUrl.toString());
                        
                        // Reload page to show new post, staying on forum section
                        window.location.href = currentUrl.toString();
                    } else {
                        alert('Error: ' + (data.message || 'Gagal membuat post'));
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat membuat post. Pastikan Anda sudah login dan tabel forum sudah dibuat. Error: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
                
                return false;
            };
            
            window.toggleComments = function(postId, button) {
                const commentsSection = document.getElementById('comments-' + postId);
                if (commentsSection) {
                    if (commentsSection.style.display === 'none') {
                        commentsSection.style.display = 'block';
                        loadComments(postId);
                    } else {
                        commentsSection.style.display = 'none';
                    }
                }
            };
            
            function loadComments(postId) {
                const commentsList = document.getElementById('comments-list-' + postId);
                if (!commentsList) return;
                
                if (commentsList.dataset.loaded === 'true') {
                    return;
                }
                
                fetch('<?php echo BASE_URL; ?>/dashboard/user/forum-action.php?action=get_comments&post_id=' + postId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayComments(postId, data.comments);
                            commentsList.dataset.loaded = 'true';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading comments:', error);
                    });
            }
            
            function displayComments(postId, comments) {
                const commentsList = document.getElementById('comments-list-' + postId);
                if (!commentsList) return;
                
                if (comments.length === 0) {
                    commentsList.innerHTML = '<p style="text-align: center; color: var(--color-text-light); padding: 20px; font-size: 14px;">Belum ada komentar</p>';
                    return;
                }
                
                commentsList.innerHTML = comments.map(comment => {
                    const timeAgo = getTimeAgoJS(comment.created_at);
                    return `
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="comment-author">${escapeHtml(comment.user_name || 'User')}</span>
                                <span class="comment-time">${timeAgo}</span>
                            </div>
                            <div class="comment-content">${escapeHtml(comment.content).replace(/\n/g, '<br>')}</div>
                        </div>
                    `;
                }).join('');
            }
            
            window.submitComment = function(event, postId) {
                event.preventDefault();
                const form = event.target;
                const content = form.querySelector('textarea[name="comment"]').value.trim();
                
                if (!content) {
                    alert('Komentar tidak boleh kosong!');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'add_comment');
                formData.append('post_id', postId);
                formData.append('content', content);
                
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Mengirim...';
                
                fetch('<?php echo BASE_URL; ?>/dashboard/user/forum-action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        form.querySelector('textarea[name="comment"]').value = '';
                        const commentsList = document.getElementById('comments-list-' + postId);
                        if (commentsList) {
                            commentsList.dataset.loaded = 'false';
                            loadComments(postId);
                        }
                        
                        const commentBtn = document.querySelector(`[data-post-id="${postId}"].btn-comment`);
                        if (commentBtn) {
                            const commentCount = commentBtn.querySelector('.comment-count');
                            if (commentCount) commentCount.textContent = data.comments_count;
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Gagal menambahkan komentar'));
                    }
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menambahkan komentar');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            };
            
            window.deletePost = function(postId) {
                if (!confirm('Apakah Anda yakin ingin menghapus post ini?')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'delete_post');
                formData.append('post_id', postId);
                
                fetch('<?php echo BASE_URL; ?>/dashboard/user/forum-action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const postCard = document.querySelector(`[data-post-id="${postId}"]`);
                        if (postCard) {
                            postCard.style.animation = 'fadeOut 0.3s ease';
                            setTimeout(() => {
                                postCard.remove();
                                if (document.querySelectorAll('.forum-post-card').length === 0) {
                                    window.location.reload();
                                }
                            }, 300);
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Gagal menghapus post'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus post');
                });
            };
            
            function getTimeAgoJS(datetime) {
                const date = new Date(datetime);
                const now = new Date();
                const diff = Math.floor((now - date) / 1000);
                
                if (diff < 60) return 'Baru saja';
                if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
                if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
                if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
                if (diff < 2592000) return Math.floor(diff / 604800) + ' minggu lalu';
                if (diff < 31536000) return Math.floor(diff / 2592000) + ' bulan lalu';
                return Math.floor(diff / 31536000) + ' tahun lalu';
            }
    }); // End DOMContentLoaded
    </script>
    
    <!-- Education Modal -->
    <div id="educationModal" class="education-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
        <div class="education-modal-content" id="educationModalContent" style="background: white; border-radius: 20px; padding: 30px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); position: relative;">
            <!-- Content will be loaded here -->
        </div>
    </div>
</body>
</html>
