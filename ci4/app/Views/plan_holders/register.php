<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (! ($has_pending_table ?? false)): ?>
            <?= view('components/alert', ['type' => 'warning', 'message' => 'Pending registration queue is unavailable. Run database migrations first.']) ?>
        <?php elseif (empty($approval_registrations)): ?>
            <?= view('components/empty_state', [
                'icon' => 'ti-shield-check',
                'title' => 'No verification requests',
                'text' => 'Government ID verification requests will appear here once a plan holder registers.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table align-middle">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Reviewed by</th>
                            <th style="min-width: 280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approval_registrations as $registration): ?>
                            <?php
                                $status = (string) ($registration['status'] ?? 'pending');
                                $reviewerName = trim((string) ($registration['reviewer_first_name'] ?? '') . ' ' . (string) ($registration['reviewer_last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc((string) ($registration['first_name'] ?? '')) ?> <?= esc((string) ($registration['last_name'] ?? '')) ?></div>
                                    <small class="text-muted"><?= esc((string) ($registration['email'] ?? '-')) ?></small>
                                </td>
                                <td><?= esc((string) ($registration['branch_name'] ?? '-')) ?></td>
                                <td>
                                    <?= cs_status($status) ?>
                                    <?php if ($status === 'rejected' && ! empty($registration['rejection_notes'])): ?>
                                        <div class="small text-danger mt-1">Reason: <?= esc((string) $registration['rejection_notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= cs_date($registration['created_at'] ?? null, true) ?></td>
                                <td>
                                    <?= esc($reviewerName !== '' ? $reviewerName : '-') ?>
                                    <?php if (! empty($registration['reviewed_at'])): ?>
                                        <div class="small text-muted"><?= cs_date($registration['reviewed_at'], true) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="cs-table__actions">
                                    <?php if ($status === 'pending'): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <form method="post" action="<?= base_url('plan-holders/approvals/approve/' . (int) $registration['pending_registration_id']) ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-primary btn-sm w-100">Approve</button>
                                            </form>
                                            <form method="post" action="<?= base_url('plan-holders/approvals/reject/' . (int) $registration['pending_registration_id']) ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <textarea name="rejection_notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Optional rejection reason"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100" data-cs-confirm="Reject this registration?">Reject</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Already reviewed</span>
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
