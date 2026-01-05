<?php
// Report Model

require_once __DIR__ . '/../config/database.php';

class ReportModel {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get all reports with optional filters
     */
    public function getAll($filters = []) {
        try {
            $sql = "SELECT r.*, u.username, u.full_name 
                    FROM reports r 
                    LEFT JOIN users u ON r.user_id = u.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND r.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['report_type'])) {
                $sql .= " AND r.report_type = ?";
                $params[] = $filters['report_type'];
            }
            
            if (!empty($filters['user_id'])) {
                $sql .= " AND r.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['is_anonymous']) && $filters['is_anonymous'] !== '') {
                $sql .= " AND r.is_anonymous = ?";
                $params[] = $filters['is_anonymous'];
            }
            
            $sql .= " ORDER BY r.created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting reports: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get reports by user ID
     */
    public function getByUserId($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user reports: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get report by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, u.username, u.full_name 
                FROM reports r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting report: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new report
     * @return int|false Report ID on success, false on failure
     */
    public function create($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO reports (
                    user_id, report_type, report_type_other, title, description,
                    location, incident_date, incident_time, is_anonymous,
                    priority, additional_info, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $result = $stmt->execute([
                $data['user_id'] ?? null,
                $data['report_type'] ?? null,
                $data['report_type_other'] ?? null,
                $data['title'],
                $data['description'],
                $data['location'] ?? null,
                $data['incident_date'] ?? null,
                $data['incident_time'] ?? null,
                $data['is_anonymous'] ?? 0,
                $data['priority'] ?? 'medium',
                $data['additional_info'] ?? null
            ]);
            
            if ($result) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Error creating report: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update report status
     */
    public function updateStatus($id, $status) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE reports 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$status, $id]);
        } catch(PDOException $e) {
            error_log("Error updating report status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update report
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE reports SET ";
            $updates = [];
            $params = [];
            
            $allowedFields = ['status', 'assigned_to', 'assigned_role', 'admin_notes', 
                            'resolution_notes', 'rejection_reason', 'priority'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $updates[] = "updated_at = NOW()";
            $sql .= implode(', ', $updates);
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Error updating report: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get report attachments
     */
    public function getAttachments($reportId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM report_attachments 
                WHERE report_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$reportId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting attachments: " . $e->getMessage());
            return [];
        }
    }
}

