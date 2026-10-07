<?php
/**
 * Shared tab strip for the Section-7 reports suite. Expects $active_report
 * (one of: overdue, ledger, collections, commission, remittance) and
 * $reports_base_path (e.g. '/admin/reports' or '/branch-admin/reports') set
 * by the including view/controller.
 */
$base = (string) ($reports_base_path ?? '/admin/reports');
$active = (string) ($active_report ?? '');
$tabs = [
    'overdue' => 'Overdue',
    'ledger' => 'Ledger of Plan Holder',
    'collections' => 'Collections',
    'commission' => 'Commission',
    'remittance' => 'Remittance',
];
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <?php if (! empty($reports_hidden_tabs ?? []) && in_array($key, $reports_hidden_tabs, true)) continue; ?>
        <li class="nav-item">
            <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= site_url($base . '/' . $key) ?>"><?= esc($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
