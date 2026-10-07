<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<div class="mb-3 text-end">
    <a class="btn btn-primary btn-sm" href="<?= base_url('branch-admin/client/register') ?>">
        <i class="ti ti-user-plus" aria-hidden="true"></i> Register plan holder
    </a>
</div>

<?= view('partials/search_bar', [
    'action'      => base_url('branch-admin/client-management'),
    'term'        => $search ?? '',
    'placeholder' => 'Name, email, mobile number, or plan number',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($holders)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-users',
                'title' => ! empty($search) ? 'No plan holders match "' . $search . '"' : 'No plan holders found for your branch',
                'text'  => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search to see everyone.' : '',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder</th>
                            <th>Unique ID</th>
                            <th>Plan Holder Status</th>
                            <th>Initial Payment</th>
                            <th class="cs-table__actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holders as $holder): ?>
                            <?php
                                $holderStatus = strtolower((string) ($holder['plan_holder_status'] ?? 'inactive'));
                                $paymentStatus = strtolower((string) ($holder['initial_payment_status'] ?? 'none'));
                                $paymentLabel = match ($paymentStatus) {
                                    'pending' => 'Pending Payment',
                                    'paid' => 'Paid',
                                    'cancelled' => 'Cancelled',
                                    default => 'No payment',
                                };
                            ?>
                            <tr>
                                <td>
                                    <?= esc((string) ($holder['first_name'] . ' ' . $holder['last_name'])) ?>
                                    <div class="cs-table__sub"><?= esc((string) ($holder['email'] ?? '-')) ?></div>
                                </td>
                                <td><?= esc((string) ($holder['unique_identifier'] ?: 'Not assigned')) ?></td>
                                <td><?= cs_status($holderStatus) ?></td>
                                <td><?= cs_status($paymentStatus, $paymentLabel) ?></td>
                                <td class="cs-table__actions">
                                    <a href="<?= base_url('branch-admin/client-management/view/' . (int) $holder['plan_holder_id']) ?>" class="btn btn-sm btn-outline-primary me-1">View Details</a>
                                    <?php if ($paymentStatus === 'pending'): ?>
                                        <form method="post" action="<?= base_url('branch-admin/client-management/approve/' . (int) $holder['plan_holder_id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-primary" data-cs-confirm="Approve this initial payment? This will activate the membership.">Approve payment</button>
                                        </form>
                                    <?php endif; ?>
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
