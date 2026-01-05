<?php
// Alert Component

if (!isset($flash)) {
    $flash = getFlashMessage();
}

if ($flash):
    $type = $flash['type'] ?? 'success';
    $message = $flash['message'] ?? '';
    
    // Map type to CSS class
    $alertClass = 'alert-' . $type;
?>
<div class="alert <?= htmlspecialchars($alertClass) ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

