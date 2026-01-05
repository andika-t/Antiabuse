<?php
// Logout Handler

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/classes/Auth.php';

$auth = new Auth();
$auth->logout();
?>

