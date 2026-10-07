<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>

<?= view('partials/search_bar', [
    'action'      => base_url('plan-builder'),
    'term'        => $search ?? '',
    'placeholder' => 'Plan name, entitled package or description',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Plan Builder</h2>
        <?php if (! empty($can_create)): ?>
            <a href="<?= base_url('plan-builder/create') ?>" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1" aria-hidden="true"></i>New Plan</a>
        <?php endif; ?>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($plans)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-clipboard-plus',
                'title' => ! empty($search) ? 'No plans match "' . $search . '"' : 'No plans yet',
                'text'  => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : 'Create the first plan to start building the catalogue.',
                'action' => (! empty($can_create) && empty($search)) ? ['label' => 'New Plan', 'url' => 'plan-builder/create'] : null,
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th class="cs-num">Price</th>
                            <th class="cs-num">Monthly</th>
                            <th class="cs-num">Term</th>
                            <th>Entitled Package</th>
                            <th class="cs-num">Inclusions</th>
                            <th class="cs-num">Plan Holders</th>
                            <th>Approval</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $isActive = (int) ($plan['is_active'] ?? 0) === 1;
                            $approvalStatus = (string) ($plan['approval_status'] ?? 'approved');
                            $isPending = $approvalStatus === 'pending';
                            $submitterName = trim((string) ($plan['submitted_by_first'] ?? '') . ' ' . (string) ($plan['submitted_by_last'] ?? ''));
                            ?>
                            <tr>
                                <td><?= esc((string) $plan['program_name']) ?></td>
                                <td class="cs-num"><?= cs_money($plan['plan_price'] ?? 0, true, 'cs-money--brass') ?></td>
                                <td class="cs-num"><?= cs_money($plan['monthly_fee'] ?? 0) ?></td>
                                <td class="cs-num"><?= esc((string) ((int) ($plan['term_months'] ?? 0))) ?> mo.</td>
                                <td><?= esc((string) ($plan['package_name'] ?? '-')) ?></td>
                                <td class="cs-num"><?= esc((string) $plan['inclusion_count']) ?></td>
                                <td class="cs-num"><?= esc((string) $plan['plan_holder_count']) ?></td>
                                <td>
                                    <?= cs_status($approvalStatus) ?>
                                    <?php if ($isPending && $submitterName !== ''): ?>
                                        <div class="cs-table__sub">by <?= esc($submitterName) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= cs_status($isActive ? 'active' : 'inactive') ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= base_url('plan-builder/' . (int) $plan['program_id']) ?>" class="btn btn-ghost btn-sm">View</a>
                                    <?php if (! empty($can_manage) && $isPending): ?>
                                        <form method="post" action="<?= base_url('plan-builder/' . (int) $plan['program_id'] . '/approve') ?>" class="d-inline" data-cs-confirm="Approve and activate this plan?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-ghost btn-sm">Approve</button>
                                        </form>
                                    <?php elseif (! empty($can_manage)): ?>
                                        <a href="<?= base_url('plan-builder/' . (int) $plan['program_id'] . '/edit') ?>" class="btn btn-ghost btn-sm">Edit</a>
                                        <form method="post" action="<?= base_url('plan-builder/' . (int) $plan['program_id'] . '/toggle') ?>" class="d-inline" data-cs-confirm="<?= $isActive ? 'Deactivate this plan?' : 'Activate this plan?' ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-ghost btn-sm"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
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
