<?= $this->extend($role_layout ?? 'layouts/plan_holder') ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>

<?php if (! empty($show_search)): ?>
    <?= view('partials/search_bar', [
        'action'      => base_url($route_prefix),
        'term'        => $search ?? '',
        'placeholder' => 'Plan holder, plan number, package or beneficiary',
    ]) ?>
<?php endif; ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($balances)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-receipt-2',
                'title' => ! empty($search) ? 'No service balances match "' . $search . '"' : 'No service balances found',
                'text'  => 'Funeral package balances tracked separately from membership contributions will appear here.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Service</th>
                            <th class="cs-num">Package cost</th>
                            <th class="cs-num">Assistance</th>
                            <th class="cs-num">Remaining</th>
                            <th>Status</th>
                            <th class="cs-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($balances as $balance): ?>
                            <tr>
                                <td><?= esc(trim((string) ($balance['first_name'] ?? '') . ' ' . (string) ($balance['last_name'] ?? ''))) ?></td>
                                <td><?= esc((string) ($balance['service_name'] ?? $balance['package_name'] ?? '—')) ?></td>
                                <td class="cs-num"><?= cs_money($balance['package_cost'] ?? 0) ?></td>
                                <td class="cs-num"><?= cs_money($balance['assistance_amount'] ?? 0) ?></td>
                                <td class="cs-num"><?= cs_money($balance['remaining_balance'] ?? 0) ?></td>
                                <td><?= cs_status((string) ($balance['status'] ?? '')) ?></td>
                                <td class="cs-table__actions">
                                    <a class="btn btn-ghost btn-sm" href="<?= site_url($route_prefix . '/' . (int) ($balance['service_balance_id'] ?? 0)) ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
