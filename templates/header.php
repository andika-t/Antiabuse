<?php
// Header Template

// Initialize variables with default values
if (!isset($pageTitle)) {
    $pageTitle = 'AntiAbuse';
}

if (!isset($additionalCSS)) {
    $additionalCSS = [];
}

// Get flash message
$flash = getFlashMessage();

// Get current page for navigation
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - AntiAbuse</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <?php if (!empty($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= asset($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php if ($flash): ?>
        <?php include __DIR__ . '/components/alert.php'; ?>
    <?php endif; ?>
    
    <header>
        <nav>
            <a href="<?= BASE_URL ?>/index.php">Home</a>
            <?php if (isLoggedIn()): ?>
                <?php 
                $role = getUserRole();
                $dashboardUrl = BASE_URL . '/' . ($role === 'admin' ? 'admin' : ($role === 'police' ? 'police' : ($role === 'psychologist' ? 'psychologist' : 'user')));
                ?>
                <a href="<?= $dashboardUrl ?>/index.php">Dashboard</a>
                <a href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php">Login</a>
                <a href="<?= BASE_URL ?>/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    
    <main>

