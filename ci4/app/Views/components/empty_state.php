<?php
/**
 * Empty state. An empty screen is an invitation to act, not a dead end —
 * so this always says what would be here and how to put something in it.
 *
 * <?= view('components/empty_state', [
 *     'icon'   => 'ti-users',
 *     'title'  => 'No plan holders yet',
 *     'text'   => 'Registered members appear here once a branch approves them.',
 *     'action' => ['label' => 'Register a plan holder', 'url' => 'staff/clients/register'],
 * ]) ?>
 */
$icon   = (string) ($icon  ?? 'ti-inbox');
$title  = (string) ($title ?? 'Nothing here yet');
$text   = (string) ($text  ?? '');
$action = $action ?? null;
?>
<div class="cs-empty">
    <div class="cs-empty__icon"><i class="ti <?= esc($icon) ?>" aria-hidden="true"></i></div>
    <div class="cs-empty__title"><?= esc($title) ?></div>
    <?php if ($text !== ''): ?>
        <p class="cs-empty__text"><?= esc($text) ?></p>
    <?php endif; ?>
    <?php if ($action): ?>
        <a class="btn btn-primary btn-sm" href="<?= base_url((string) ($action['url'] ?? '#')) ?>">
            <i class="ti ti-plus" aria-hidden="true"></i>
            <?= esc((string) ($action['label'] ?? 'Get started')) ?>
        </a>
    <?php endif; ?>
</div>
