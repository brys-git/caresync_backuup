<?php
/**
 * Alert Component
 * 
 * Renders a standardized Bootstrap alert with consistent styling
 * 
 * @param string $type Alert type (success, info, warning, danger)
 * @param string $message Alert message/content
 * @param bool $dismissible Whether alert can be dismissed
 * @param string $title Optional title/heading
 * @param string $class Additional CSS classes
 * 
 * Usage:
 * <?= view('components/alert', ['type' => 'success', 'message' => 'Operation completed']) ?>
 * <?= view('components/alert', ['type' => 'danger', 'title' => 'Error', 'message' => 'Something went wrong', 'dismissible' => true]) ?>
 */

$type = strtolower((string) ($type ?? 'info'));
$message = (string) ($message ?? '');
$dismissible = (bool) ($dismissible ?? true);
$title = (string) ($title ?? '');
$class = (string) ($class ?? '');

// Validate alert type
$validTypes = ['success', 'info', 'warning', 'danger'];
$type = in_array($type, $validTypes, true) ? $type : 'info';
?>
<div class="alert alert-<?= esc($type) ?> <?= $dismissible ? 'alert-dismissible fade show' : '' ?> <?= esc($class) ?>" role="alert">
    <?php if ($title): ?>
        <strong><?= esc($title) ?></strong> 
    <?php endif; ?>
    <?= esc($message) ?>
    <?php if ($dismissible): ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <?php endif; ?>
</div>
