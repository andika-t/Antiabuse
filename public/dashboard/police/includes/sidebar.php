<?php
// Sidebar navigation for police dashboard
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" title="Dashboard">
            <span class="nav-icon"><?php echo icon('home', '', 24, 24); ?></span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <a href="reports.php" class="nav-item <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" title="Manajemen Laporan">
            <span class="nav-icon"><?php echo icon('reports', '', 24, 24); ?></span>
            <span class="nav-text">Manajemen Laporan</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-item logout-nav" title="Logout">
            <span class="nav-icon"><?php echo icon('logout', '', 24, 24); ?></span>
            <span class="nav-text">Logout</span>
        </a>
    </nav>
</aside>

