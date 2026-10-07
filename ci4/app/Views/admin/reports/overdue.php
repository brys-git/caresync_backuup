<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php
// Branch filter only exists for System Admin ($branches is only passed by Reports::overdue);
// Branch Admin is always scoped to their own branch server-side.
$overdueBase = ($reports_base_path ?? '/admin/reports') . '/overdue';
$overdueQuery = (! empty($filters['branch_id']) ? '&branch_id=' . (int) $filters['branch_id'] : '')
    . (! empty($filters['search']) ? '&search=' . urlencode((string) $filters['search']) : '');
?>
<div class="mb-3 text-end">
    <a class="btn btn-ghost btn-sm" href="<?= site_url($overdueBase . '?mode=print' . $overdueQuery) ?>" target="_blank" rel="noopener">
        <i class="ti ti-printer me-1"></i> Print
    </a>
    <a class="btn btn-ghost btn-sm" href="<?= site_url($overdueBase . '?mode=csv' . $overdueQuery) ?>">
        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
    </a>
    <a class="btn btn-primary btn-sm" href="<?= site_url($overdueBase . '?mode=pdf' . $overdueQuery) ?>">
        <i class="ti ti-file-type-pdf me-1"></i> Export PDF
    </a>
</div>

<?php $this->setData(['reports_base_path' => $reports_base_path ?? '/admin/reports']) ?>
<?= $this->include('partials/reports_nav') ?>

<?php // The search box shows for every role; only System Admin gets the branch picker. ?>
    <form class="cs-filters mb-3" method="get" action="<?= site_url($overdueBase) ?>">
        <div class="cs-filters__field cs-filters__field--wide">
            <label class="form-label">Search</label>
            <input type="search" name="search" class="form-control" maxlength="100" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>" placeholder="Name, mobile number or plan number">
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
            <?php if (! empty($filters['search'])): ?>
                <a class="btn btn-ghost" href="<?= site_url($overdueBase) ?><?= ! empty($filters['branch_id']) ? '?branch_id=' . (int) $filters['branch_id'] : '' ?>">Clear search</a>
            <?php endif; ?>
        </div>
    </form>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-alert-triangle',
                'title' => ! empty($filters['search']) ? 'No overdue accounts match "' . $filters['search'] . '"' : 'No overdue accounts found',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder</th>
                            <th>Branch</th>
                            <th>Contact</th>
                            <th class="cs-num">Remaining Balance</th>
                            <th>Months Paid</th>
                            <th>Overdue Months</th>
                            <th>Days Overdue</th>
                            <th>Next Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?>
                                    <div class="cs-table__sub"><?= esc((string) ($row['unique_identifier'] ?? '-')) ?></div>
                                </td>
                                <td><?= esc((string) ($row['branch_name'] ?? '-')) ?></td>
                                <td><?= esc((string) ($row['contact_number'] ?? '-')) ?></td>
                                <td class="cs-num"><?= cs_money($row['remaining_balance'] ?? 0) ?></td>
                                <td><?= esc((string) ($row['months_paid'] ?? 0)) ?></td>
                                <td><?= esc((string) ($row['overdue_months'] ?? 0)) ?> <span class="cs-table__sub"><?= cs_peso($row['overdue_amount'] ?? 0) ?></span></td>
                                <td><?= cs_status('overdue', (string) ($row['days_overdue'] ?? 0) . ' days') ?></td>
                                <td><?= cs_date($row['next_due_date'] ?? null) ?></td>
                                <td><?= cs_status((string) ($row['lifecycle'] ?? 'grace'), (string) ($row['status_text'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
