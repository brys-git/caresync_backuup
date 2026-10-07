<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$isActive = (int) ($program['is_active'] ?? 0) === 1;
$holderCount = (int) ($plan_holder_count ?? 0);
$approvalStatus = (string) ($program['approval_status'] ?? 'approved');
$isPending = $approvalStatus === 'pending';
$isRejected = $approvalStatus === 'rejected';
$submitterName = trim((string) ($program['submitted_by_first'] ?? '') . ' ' . (string) ($program['submitted_by_last'] ?? ''));
?>

<a class="text-decoration-none text-muted mb-3 d-inline-block" href="<?= base_url('plan-builder') ?>">
    <i class="ti ti-arrow-left me-1"></i> Back to Plan Builder
</a>

<?php if ($isPending && $submitterName !== ''): ?>
    <?= view('components/alert', [
        'type' => 'info',
        'dismissible' => false,
        'message' => 'Submitted by ' . esc($submitterName) . (! empty($program['submitted_by_branch_name']) ? ' (' . esc((string) $program['submitted_by_branch_name']) . ' branch)' : '') . '. Awaiting System Admin approval - not selectable at registration until approved.',
    ]) ?>
<?php elseif ($isRejected): ?>
    <?= view('components/alert', [
        'type' => 'danger',
        'dismissible' => false,
        'message' => 'Rejected' . (! empty($program['rejection_reason']) ? ': ' . esc((string) $program['rejection_reason']) : '.'),
    ]) ?>
<?php endif; ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title"><?= esc((string) $program['program_name']) ?></h2>
        <?php if (! empty($can_manage) && $isPending): ?>
            <div class="d-flex gap-2">
                <form method="post" action="<?= base_url('plan-builder/' . (int) $program['program_id'] . '/approve') ?>" data-cs-confirm="Approve and activate this plan? It will immediately become selectable at registration.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                </form>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectPlanModal">Reject</button>
            </div>
        <?php elseif (! empty($can_manage)): ?>
            <div class="d-flex gap-2">
                <a href="<?= base_url('plan-builder/' . (int) $program['program_id'] . '/edit') ?>" class="btn btn-ghost btn-sm">Edit</a>
                <form method="post" action="<?= base_url('plan-builder/' . (int) $program['program_id'] . '/toggle') ?>" data-cs-confirm="<?= $isActive ? 'Deactivate this plan?' : 'Activate this plan?' ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= base_url('plan-builder/' . (int) $program['program_id'] . '/delete') ?>" data-cs-confirm="Delete this plan? This cannot be undone.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="cs-panel__body">
        <?php if (! empty($program['description'])): ?>
            <p class="cs-muted"><?= esc((string) $program['description']) ?></p>
        <?php endif; ?>
        <?= view('components/stat_rail', ['stats' => [
            ['label' => 'Plan Price', 'value' => $program['plan_price'] ?? 0, 'money' => true, 'tone' => 'money'],
            [
                'label' => 'Monthly Contribution',
                'value' => $program['monthly_fee'] ?? 0,
                'money' => true,
                // Plain text, not cs_money() - stat_rail's 'meta' field is
                // esc()'d as a whole, which would show cs_money()'s <span>
                // markup as literal text instead of rendering it.
                'meta'  => '₱' . number_format((float) ($program['monthly_fee'] ?? 0), 2) . ' / month × ' . ((int) ($program['term_months'] ?? 0)) . ' months',
            ],
            ['label' => 'Approval', 'value' => ucfirst($approvalStatus), 'tone' => $isPending ? '' : ($isRejected ? 'stop' : 'ok')],
            ['label' => 'Status', 'value' => $isActive ? 'Active' : 'Inactive', 'tone' => $isActive ? 'ok' : ''],
            ['label' => 'Plan Holders', 'value' => $holderCount],
        ]]) ?>
    </div>
</section>

<?php if (! empty($can_manage) && $isPending): ?>
    <div class="modal fade" id="rejectPlanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="<?= base_url('plan-builder/' . (int) $program['program_id'] . '/reject') ?>" class="modal-content">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h3 class="modal-title h5">Reject plan</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="reject_reason">Reason (optional, shown to the submitter)</label>
                    <textarea id="reject_reason" name="reason" class="form-control" rows="3" maxlength="255"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Plan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Entitled Package</h2>
    </div>
    <div class="cs-panel__body">
        <?php if (empty($package)): ?>
            <p class="cs-muted mb-0">No entitled package set for this plan.</p>
        <?php else: ?>
            <h3 class="h6 mb-1"><?= esc((string) $package['package_name']) ?></h3>
            <?php if (! empty($package['description'])): ?>
                <p class="cs-muted"><?= esc((string) $package['description']) ?></p>
            <?php endif; ?>

            <h4 class="h6 mt-3 mb-2">Inclusions</h4>
            <?php if (empty($inclusions)): ?>
                <p class="cs-muted mb-0">No inclusions listed.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($inclusions as $item): ?>
                        <li>
                            <strong><?= esc((string) $item['item_name']) ?></strong>
                            <?php if (! empty($item['description'])): ?>
                                <span class="cs-muted"> - <?= esc((string) $item['description']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
