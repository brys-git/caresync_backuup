<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php $a = $analytics ?? []; ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Branch members', 'icon' => 'ti-users', 'value' => $a['staff_members'] ?? 0],
    ['label' => 'Approved services', 'icon' => 'ti-checkbox', 'value' => $a['assigned_services'] ?? 0, 'tone' => 'ok'],
    ['label' => 'Pending requests', 'icon' => 'ti-clipboard-list', 'value' => $a['pending_requests'] ?? 0, 'tone' => ((int) ($a['pending_requests'] ?? 0) > 0) ? 'warn' : ''],
    ['label' => 'Cash payments today', 'icon' => 'ti-cash-banknote', 'value' => $a['cash_payments_today'] ?? 0],
]]) ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <span class="cs-muted">Cash collected today (pending verification)</span>
        <div class="cs-stat__value mt-1"><?= cs_money($a['cash_collected_today'] ?? 0) ?></div>
    </div>
</section>
<?= $this->endSection() ?>
