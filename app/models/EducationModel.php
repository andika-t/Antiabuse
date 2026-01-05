<?php
// Education Model

require_once __DIR__ . '/../config/database.php';

class EducationModel {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get all education content
     */
    public function getAll($filters = []) {
        try {
            $sql = "SELECT ec.*, u.username as author_name 
                    FROM education_content ec 
                    LEFT JOIN users u ON ec.author_id = u.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['category'])) {
                $sql .= " AND ec.category = ?";
                $params[] = $filters['category'];
            }
            
            if (isset($filters['is_published'])) {
                $sql .= " AND ec.is_published = ?";
                $params[] = $filters['is_published'];
            } else {
                // Default: only published
                $sql .= " AND ec.is_published = 1";
            }
            
            if (!empty($filters['author_id'])) {
                $sql .= " AND ec.author_id = ?";
                $params[] = $filters['author_id'];
            }
            
            $sql .= " ORDER BY ec.created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting education content: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get education content by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT ec.*, u.username as author_name 
                FROM education_content ec 
                LEFT JOIN users u ON ec.author_id = u.id 
                WHERE ec.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting education content: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new education content
     */
    public function create($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO education_content (
                    title, description, video_url, thumbnail, category,
                    duration, author_id, author_role, is_published
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['video_url'],
                $data['thumbnail'] ?? null,
                $data['category'],
                $data['duration'] ?? null,
                $data['author_id'],
                $data['author_role'],
                $data['is_published'] ?? true
            ]);
            
            if ($result) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Error creating education content: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update education content
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE education_content SET ";
            $updates = [];
            $params = [];
            
            $allowedFields = ['title', 'description', 'video_url', 'thumbnail', 'category', 
                            'duration', 'is_published'];
            
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
            error_log("Error updating education content: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete education content
     */
    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM education_content WHERE id = ?");
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            error_log("Error deleting education content: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment views
     */
    public function incrementViews($id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE education_content 
                SET views = views + 1 
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            error_log("Error incrementing views: " . $e->getMessage());
            return false;
        }
    }
}

