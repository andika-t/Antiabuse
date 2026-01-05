<?php
// Sidebar navigation for admin dashboard
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" title="Dashboard">
            <span class="nav-icon"><?php echo icon('home', '', 24, 24); ?></span>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <a href="users.php" class="nav-item <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" title="Manajemen Pengguna">
            <span class="nav-icon"><?php echo icon('users', '', 24, 24); ?></span>
            <span class="nav-text">Manajemen Pengguna</span>
        </a>
        
        <a href="reports.php" class="nav-item <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>" title="Manajemen Laporan">
            <span class="nav-icon"><?php echo icon('reports', '', 24, 24); ?></span>
            <span class="nav-text">Manajemen Laporan</span>
        </a>
        
        <a href="panic-button.php" class="nav-item <?php echo $currentPage == 'panic-button.php' ? 'active' : ''; ?>" title="Panic Button">
            <span class="nav-icon"><?php echo icon('panic', '', 24, 24); ?></span>
            <span class="nav-text">Panic Button</span>
        </a>
        
        <a href="forum.php" class="nav-item <?php echo $currentPage == 'forum.php' ? 'active' : ''; ?>" title="Moderasi Forum">
            <span class="nav-icon"><?php echo icon('forum', '', 24, 24); ?></span>
            <span class="nav-text">Moderasi Forum</span>
        </a>
        
        <a href="education.php" class="nav-item <?php echo $currentPage == 'education.php' ? 'active' : ''; ?>" title="Konten Edukasi">
            <span class="nav-icon"><?php echo icon('education', '', 24, 24); ?></span>
            <span class="nav-text">Konten Edukasi</span>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="nav-item logout-nav" title="Logout">
            <span class="nav-icon"><?php echo icon('logout', '', 24, 24); ?></span>
            <span class="nav-text">Logout</span>
        </a>
    </nav>
</aside>

