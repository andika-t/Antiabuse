<?php
// Forum Model

require_once __DIR__ . '/../config/database.php';

class ForumModel {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get all forum posts
     */
    public function getAll($filters = []) {
        try {
            $sql = "SELECT fp.*, u.username, u.full_name 
                    FROM forum_posts fp 
                    LEFT JOIN users u ON fp.user_id = u.id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['user_id'])) {
                $sql .= " AND fp.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            // Order by pinned first, then by created_at
            $sql .= " ORDER BY fp.is_pinned DESC, fp.created_at DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting forum posts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get forum post by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT fp.*, u.username, u.full_name 
                FROM forum_posts fp 
                LEFT JOIN users u ON fp.user_id = u.id 
                WHERE fp.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting forum post: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new forum post
     */
    public function create($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO forum_posts (user_id, content, image_url)
                VALUES (?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['user_id'],
                $data['content'],
                $data['image_url'] ?? null
            ]);
            
            if ($result) {
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Error creating forum post: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete forum post
     */
    public function delete($id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM forum_posts WHERE id = ?");
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            error_log("Error deleting forum post: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get comments for a post
     */
    public function getComments($postId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT fc.*, u.username, u.full_name 
                FROM forum_comments fc 
                LEFT JOIN users u ON fc.user_id = u.id 
                WHERE fc.post_id = ? 
                ORDER BY fc.created_at ASC
            ");
            $stmt->execute([$postId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error getting comments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add comment to post
     */
    public function addComment($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO forum_comments (post_id, user_id, parent_id, content)
                VALUES (?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['post_id'],
                $data['user_id'],
                $data['parent_id'] ?? null,
                $data['content']
            ]);
            
            if ($result) {
                // Update comment count
                $this->incrementCommentCount($data['post_id']);
                return $this->conn->lastInsertId();
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Error adding comment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle like on post
     */
    public function toggleLike($postId, $userId) {
        try {
            // Check if already liked
            $stmt = $this->conn->prepare("
                SELECT id FROM forum_likes 
                WHERE post_id = ? AND user_id = ?
            ");
            $stmt->execute([$postId, $userId]);
            $like = $stmt->fetch();
            
            if ($like) {
                // Unlike: delete like
                $stmt = $this->conn->prepare("
                    DELETE FROM forum_likes 
                    WHERE post_id = ? AND user_id = ?
                ");
                $stmt->execute([$postId, $userId]);
                
                // Decrement likes count
                $stmt = $this->conn->prepare("
                    UPDATE forum_posts 
                    SET likes_count = GREATEST(0, likes_count - 1) 
                    WHERE id = ?
                ");
                $stmt->execute([$postId]);
                
                return false; // Unliked
            } else {
                // Like: add like
                $stmt = $this->conn->prepare("
                    INSERT INTO forum_likes (post_id, user_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$postId, $userId]);
                
                // Increment likes count
                $stmt = $this->conn->prepare("
                    UPDATE forum_posts 
                    SET likes_count = likes_count + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$postId]);
                
                return true; // Liked
            }
        } catch(PDOException $e) {
            error_log("Error toggling like: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Increment comment count
     */
    private function incrementCommentCount($postId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE forum_posts 
                SET comments_count = comments_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$postId]);
        } catch(PDOException $e) {
            error_log("Error incrementing comment count: " . $e->getMessage());
        }
    }
}

