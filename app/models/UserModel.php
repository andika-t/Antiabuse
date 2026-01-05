<?php
// User Model

require_once __DIR__ . '/../config/database.php';

class UserModel {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user by username
     */
    public function getByUsername($username) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user by username: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user by email
     */
    public function getByEmail($email) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user by email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password, role, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            
            $result = $stmt->execute([
                $data['username'],
                $data['email'] ?? null,
                $data['password'],
                $data['role'] ?? 'general_user'
            ]);
            
            if ($result) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE users SET ";
            $updates = [];
            $params = [];
            
            $allowedFields = ['username', 'email', 'password', 'role', 'status'];
            
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
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users with optional filters
     */
    public function getAll($filters = []) {
        try {
            $sql = "SELECT * FROM users WHERE 1=1";
            $params = [];
            
            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting users: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user details by role
     */
    public function getDetails($userId, $role) {
        try {
            $tableMap = [
                'admin' => 'admin_details',
                'police' => 'police_details',
                'psychologist' => 'psychologist_details',
                'general_user' => 'general_user_details'
            ];
            
            if (!isset($tableMap[$role])) {
                return null;
            }
            
            $table = $tableMap[$role];
            $stmt = $this->conn->prepare("SELECT * FROM $table WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting user details: " . $e->getMessage());
            return null;
        }
    }
}

