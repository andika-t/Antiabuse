<?php
// Admin Controller

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/ReportModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/config.php';

class Admin {
    private $userModel;
    private $reportModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
        $this->reportModel = new ReportModel();
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStatistics() {
        requireLogin();
        requireRole('admin');
        
        try {
            $conn = getDBConnection();
            
            // Total users by role
            $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $usersByRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Total users
            $totalUsers = array_sum($usersByRole);
            
            // Recent users (last 7 days)
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $recentUsers = $stmt->fetch()['count'];
            
            // Total reports
            $totalReports = 0;
            $pendingReports = 0;
            try {
                $stmt = $conn->query("SELECT COUNT(*) as count FROM reports");
                $totalReports = $stmt->fetch()['count'] ?? 0;
                
                $stmt = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
                $pendingReports = $stmt->fetch()['count'] ?? 0;
            } catch(PDOException $e) {
                // Table doesn't exist yet
            }
            
            // Get admin name
            $adminDetails = $this->userModel->getDetails($_SESSION['user_id'], 'admin');
            $adminName = $adminDetails['full_name'] ?? 'Administrator';
            
            // Get user registrations per day for last 7 days (for chart)
            $chartBars = [0, 0, 0, 0, 0, 0, 0];
            $maxCount = 1;
            try {
                $stmt = $conn->query("
                    SELECT 
                        DATE(created_at) as date,
                        DAYOFWEEK(created_at) as day_of_week,
                        COUNT(*) as count
                    FROM users
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // DAYOFWEEK returns: 1=Sunday, 2=Monday, 3=Tuesday, 4=Wednesday, 5=Thursday, 6=Friday, 7=Saturday
                // We need to map to: 0=Monday(Sen), 1=Tuesday(Sel), 2=Wednesday(Rab), 3=Thursday(Kam), 4=Friday(Jum), 5=Saturday(Sab), 6=Sunday(Min)
                $dayOfWeekMap = [
                    1 => 6, // Sunday -> index 6 (Min)
                    2 => 0, // Monday -> index 0 (Sen)
                    3 => 1, // Tuesday -> index 1 (Sel)
                    4 => 2, // Wednesday -> index 2 (Rab)
                    5 => 3, // Thursday -> index 3 (Kam)
                    6 => 4, // Friday -> index 4 (Jum)
                    7 => 5  // Saturday -> index 5 (Sab)
                ];
                
                // Fill chart data
                foreach ($chartData as $row) {
                    $dayOfWeek = (int)$row['day_of_week'];
                    if (isset($dayOfWeekMap[$dayOfWeek])) {
                        $dayIndex = $dayOfWeekMap[$dayOfWeek];
                        $count = (int)$row['count'];
                        $chartBars[$dayIndex] = $count;
                    }
                }
                
                // Find max value for percentage calculation
                $maxCount = max($chartBars) > 0 ? max($chartBars) : 1;
            } catch(PDOException $e) {
                // Keep default values
                $chartBars = [0, 0, 0, 0, 0, 0, 0];
                $maxCount = 1;
            }
            
            return [
                'usersByRole' => $usersByRole,
                'totalUsers' => $totalUsers,
                'recentUsers' => $recentUsers,
                'totalReports' => $totalReports,
                'pendingReports' => $pendingReports,
                'adminName' => $adminName,
                'chartBars' => $chartBars,
                'maxCount' => $maxCount
            ];
            
        } catch(PDOException $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return [
                'error' => 'Error loading statistics',
                'usersByRole' => [],
                'totalUsers' => 0,
                'recentUsers' => 0,
                'totalReports' => 0,
                'pendingReports' => 0,
                'adminName' => 'Administrator',
                'chartBars' => [0, 0, 0, 0, 0, 0, 0],
                'maxCount' => 1
            ];
        }
    }
    
    /**
     * Get users with filters
     */
    public function getUsers($filters = []) {
        requireLogin();
        requireRole('admin');
        
        return $this->userModel->getAll($filters);
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($id, $status) {
        requireLogin();
        requireRole('admin');
        
        if (!in_array($status, ['active', 'inactive'])) {
            return ['error' => 'Invalid status'];
        }
        
        if ($this->userModel->update($id, ['status' => $status])) {
            return ['success' => 'Status user berhasil diupdate'];
        } else {
            return ['error' => 'Gagal update status user'];
        }
    }
}

