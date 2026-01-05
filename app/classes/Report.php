<?php
// Report Controller

require_once __DIR__ . '/../models/ReportModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/config.php';

class Report {
    private $model;
    
    public function __construct() {
        $this->model = new ReportModel();
    }
    
    /**
     * Handle create report
     */
    public function create() {
        requireLogin();
        
        if (!canCreateReport()) {
            redirectTo(BASE_URL . '/user/index.php', 'Anda tidak memiliki izin untuk membuat laporan', 'error');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Method tidak diizinkan'];
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            return ['error' => 'Invalid security token'];
        }
        
        // Validate input
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($title) || strlen($title) < 10) {
            return ['error' => 'Judul minimal 10 karakter'];
        }
        
        if (empty($description) || strlen($description) < 50) {
            return ['error' => 'Deskripsi minimal 50 karakter'];
        }
        
        // Prepare data
        $data = [
            'user_id' => getUserId(),
            'title' => $title,
            'description' => $description,
            'report_type' => sanitize($_POST['report_type'] ?? 'other'),
            'report_type_other' => !empty($_POST['report_type_other']) ? sanitize($_POST['report_type_other']) : null,
            'location' => !empty($_POST['location']) ? sanitize($_POST['location']) : null,
            'incident_date' => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
            'incident_time' => !empty($_POST['incident_time']) ? $_POST['incident_time'] : null,
            'is_anonymous' => isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0,
            'priority' => sanitize($_POST['priority'] ?? 'medium'),
            'additional_info' => !empty($_POST['additional_info']) ? sanitize($_POST['additional_info']) : null
        ];
        
        // Create report
        $reportId = $this->model->create($data);
        
        if ($reportId !== false) {
            // Handle file uploads
            $this->handleFileUploads($reportId);
            
            // Clear draft from session after successful submit
            if (isset($_SESSION['report_draft'])) {
                unset($_SESSION['report_draft']);
            }
            
            redirectTo(BASE_URL . '/user/report-success.php?id=' . $reportId, 'Laporan berhasil dibuat', 'success');
        } else {
            return ['error' => 'Gagal membuat laporan'];
        }
    }
    
    /**
     * Get all reports (with permission check)
     */
    public function getAll() {
        requireLogin();
        
        $role = getUserRole();
        
        if ($role === 'general_user') {
            return $this->model->getByUserId(getUserId());
        } else {
            return $this->model->getAll();
        }
    }
    
    /**
     * Get report by ID (with permission check)
     */
    public function getById($id) {
        requireLogin();
        
        $report = $this->model->getById($id);
        
        if (!$report) {
            return null;
        }
        
        // Check permission
        if (!canViewReport($report['user_id'])) {
            return null;
        }
        
        return $report;
    }
    
    /**
     * Update report status
     */
    public function updateStatus($id, $status) {
        requireLogin();
        
        if (!canProcessReport()) {
            return ['error' => 'Anda tidak memiliki izin untuk memproses laporan'];
        }
        
        if ($this->model->updateStatus($id, $status)) {
            return ['success' => 'Status laporan berhasil diupdate'];
        } else {
            return ['error' => 'Gagal update status laporan'];
        }
    }
    
    /**
     * Handle file uploads for report
     */
    private function handleFileUploads($reportId) {
        if (empty($_FILES['attachments']['name'][0])) {
            return;
        }
        
        try {
            $conn = getDBConnection();
            
            // Create upload directory
            $uploadDir = UPLOADS_PATH . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $reportId . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = [
                'image/jpeg', 
                'image/png', 
                'image/jpg', 
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileType = $_FILES['attachments']['type'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    $tmpName = $_FILES['attachments']['tmp_name'][$key];
                    
                    if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
                        $newFilename = uniqid() . '_' . time() . '.' . $fileExt;
                        $filePath = $uploadDir . $newFilename;
                        
                        if (move_uploaded_file($tmpName, $filePath)) {
                            // Save to database
                            $stmt = $conn->prepare("
                                INSERT INTO report_attachments (report_id, file_name, file_path, file_size, file_type, attachment_type)
                                VALUES (?, ?, ?, ?, ?, 'user_upload')
                            ");
                            // Use relative path for database
                            $relativePath = 'uploads/reports/' . $reportId . '/' . $newFilename;
                            $stmt->execute([$reportId, $filename, $relativePath, $fileSize, $fileType]);
                        }
                    }
                }
            }
        } catch(PDOException $e) {
            error_log("Error handling file uploads: " . $e->getMessage());
            // Don't fail the report creation if file upload fails
        }
    }
}

