<?php
/**
 * Stat rail — one divided panel rather than four identical floating cards,
 * so the figures read as a single summary line instead of four competing tiles.
 *
 * <?= view('components/stat_rail', ['stats' => [
 *     ['label' => 'Plan holders', 'value' => 1284, 'icon' => 'ti-users'],
 *     ['label' => 'Collected', 'value' => 482300, 'money' => true, 'tone' => 'money'],
 *     ['label' => 'Overdue', 'value' => 37, 'tone' => 'stop', 'meta' => 'Needs follow-up'],
 * ]]) ?>
 */
$stats = $stats ?? [];
if ($stats === []) {
    return;
}
?>
<div class="cs-statrail">
    <?php foreach ($stats as $stat): ?>
        <?php
        $tone  = (string) ($stat['tone'] ?? '');
        $money = (bool) ($stat['money'] ?? false);
        $value = $stat['value'] ?? 0;
        ?>
        <div class="cs-stat">
            <div class="cs-stat__label">
                <?php if (! empty($stat['icon'])): ?>
                    <i class="ti <?= esc((string) $stat['icon']) ?>" aria-hidden="true"></i>
                <?php endif; ?>
                <?= esc((string) ($stat['label'] ?? '')) ?>
            </div>

            <div class="cs-stat__value <?= $tone !== '' ? 'cs-stat__value--' . esc($tone, 'attr') : '' ?>">
                <?= $money ? cs_money($value) : esc(is_numeric($value) ? number_format((float) $value) : (string) $value) ?>
            </div>

            <?php if (! empty($stat['meta'])): ?>
                <div class="cs-stat__meta"><?= esc((string) $stat['meta']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
