<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php
$commissionQuery = 'date_from=' . urlencode((string) ($filters['date_from'] ?? '')) . '&date_to=' . urlencode((string) ($filters['date_to'] ?? ''));
if (! empty($filters['search'])) {
    $commissionQuery .= '&search=' . urlencode((string) $filters['search']);
}
// Branch filter is System Admin only ($branches is only passed by Reports::commission).
if (! empty($filters['branch_id'])) {
    $commissionQuery .= '&branch_id=' . (int) $filters['branch_id'];
}
?>
<div class="mb-3 text-end">
    <a class="btn btn-ghost btn-sm" href="<?= site_url(($reports_base_path ?? '/admin/reports') . '/commission?mode=print&' . $commissionQuery) ?>" target="_blank" rel="noopener">
        <i class="ti ti-printer me-1"></i> Print
    </a>
    <a class="btn btn-ghost btn-sm" href="<?= site_url(($reports_base_path ?? '/admin/reports') . '/commission?mode=csv&' . $commissionQuery) ?>">
        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
    </a>
    <a class="btn btn-primary btn-sm" href="<?= site_url(($reports_base_path ?? '/admin/reports') . '/commission?mode=pdf&' . $commissionQuery) ?>">
        <i class="ti ti-file-type-pdf me-1"></i> Export PDF
    </a>
</div>

<?php $this->setData(['reports_base_path' => $reports_base_path ?? '/admin/reports']) ?>
<?= $this->include('partials/reports_nav') ?>

<form class="cs-filters mb-3" method="get" action="<?= site_url(($reports_base_path ?? '/admin/reports') . '/commission') ?>">
    <div class="cs-filters__field cs-filters__field--wide">
        <label class="form-label">Search</label>
        <input type="search" name="search" class="form-control" maxlength="100" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>" placeholder="Collector name">
    </div>
    <div class="cs-filters__field">
        <label class="form-label">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
    </div>
    <div class="cs-filters__field">
        <label class="form-label">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
    </div>
    <?php if (! empty($branches)): ?>
        <div class="cs-filters__field">
            <label class="form-label">Branch</label>
            <select class="form-select" name="branch_id">
                <option value="0">All Branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['branch_id'] ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                        <?= esc((string) $branch['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="cs-filters__actions">
        <button type="submit" class="btn btn-primary">Apply</button>
    </div>
</form>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-cash',
                'title' => 'No collections found for this period',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Collector</th>
                            <th>Role</th>
                            <th>Transactions</th>
                            <th class="cs-num">Total Collected</th>
                            <th class="cs-num">Commission (10%)</th>
                            <th class="cs-num">Net Remitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalCommission = 0.0;
                        foreach ($rows as $row):
                            $totalCommission += (float) ($row['commission'] ?? 0);
                        ?>
                            <tr>
                                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                                <td><?= esc((string) ($row['role_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($row['transaction_count'] ?? 0)) ?></td>
                                <td class="cs-num"><?= cs_money($row['total_collected'] ?? 0) ?></td>
                                <td class="cs-num"><?= cs_money($row['commission'] ?? 0) ?></td>
                                <td class="cs-num"><?= cs_money($row['net_remitted'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="fw-semibold">
                            <td colspan="4" class="text-end">Total Commission</td>
                            <td class="cs-num"><?= cs_money($totalCommission) ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
