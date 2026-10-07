<?php
/**
 * Shared "What's Included" list for package details/apply pages.
 * Expects $inclusions (rows with item_name/description) via $this->setData()
 * before $this->include('client/partials/inclusions_list') - CI4's include() does
 * not inherit the caller's local view variables automatically.
 * Optional $compact = true renders a tighter list for the apply-review page.
 */
$rows = $inclusions ?? [];
$compact = $compact ?? false;
$number = 0;
?>
<div class="inclusions-list">
    <?php foreach ($rows as $row): ?>
        <?php
            $name = (string) ($row['item_name'] ?? '');
            $isSubItem = str_starts_with($name, 'Viewing Setup: ');
        ?>
        <?php if ($isSubItem): ?>
            <div class="cs-incl__sub d-flex align-items-start gap-2 mb-1 ms-4">
                <i class="ti ti-point cs-muted"></i>
                <div>
                    <span class="fw-medium"><?= esc(substr($name, strlen('Viewing Setup: '))) ?></span>
                    <?php if (! $compact && ! empty($row['description'])): ?>
                        <div class="cs-muted small"><?= esc((string) $row['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php $number++; ?>
            <div class="inclusion-item d-flex align-items-start gap-2 mb-2">
                <span class="cs-incl__check"><i class="ti ti-circle-check-filled"></i></span>
                <div>
                    <span class="fw-semibold"><?= $number ?>. <?= esc($name) ?></span>
                    <?php if (! $compact && ! empty($row['description'])): ?>
                        <div class="cs-muted small"><?= esc((string) $row['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
        <p class="cs-muted small mb-0">Inclusions have not been configured for this package yet.</p>
    <?php endif; ?>
</div>

