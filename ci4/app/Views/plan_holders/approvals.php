<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($registrations)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-user-check',
                'title' => 'No registration requests',
                'text'  => 'Pending account registration requests will appear here.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table align-middle">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Branch</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Reviewed by</th>
                            <th class="cs-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                            <?php
                            $status = (string) ($registration['status'] ?? 'pending');
                            $fullAddress = trim(
                                (string) ($registration['address_no'] ?? '')
                                . ' ' . (string) ($registration['address_street'] ?? '')
                                . ', ' . (string) ($registration['address_barangay'] ?? '')
                                . ', ' . (string) ($registration['address_city'] ?? '')
                            );
                            $reviewerName = trim((string) ($registration['reviewer_first_name'] ?? '') . ' ' . (string) ($registration['reviewer_last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc((string) ($registration['first_name'] ?? '')) ?> <?= esc((string) ($registration['last_name'] ?? '')) ?></div>
                                    <small class="cs-muted"><?= esc((string) ($registration['email'] ?? '—')) ?></small>
                                </td>
                                <td><?= esc((string) ($registration['branch_name'] ?? '—')) ?></td>
                                <td><?= esc((string) ($registration['contact_number'] ?? '—')) ?></td>
                                <td>
                                    <div><?= esc($fullAddress !== '' ? $fullAddress : '—') ?></div>
                                    <small class="cs-muted">DOB: <?= cs_date($registration['date_of_birth'] ?? null) ?></small>
                                </td>
                                <td>
                                    <?= cs_status($status) ?>
                                    <?php if ($status === 'rejected' && ! empty($registration['rejection_notes'])): ?>
                                        <div class="small text-danger mt-1">Reason: <?= esc((string) $registration['rejection_notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= cs_datetime($registration['created_at'] ?? null) ?></td>
                                <td>
                                    <?= esc($reviewerName !== '' ? $reviewerName : '—') ?>
                                    <?php if (! empty($registration['reviewed_at'])): ?>
                                        <div class="small cs-muted"><?= cs_datetime($registration['reviewed_at']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="cs-table__actions">
                                    <?php if ($status === 'pending'): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <form method="post" action="<?= base_url('plan-holders/approvals/approve/' . (int) $registration['pending_registration_id']) ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-primary btn-sm w-100">Approve registration</button>
                                            </form>
                                            <form method="post" action="<?= base_url('plan-holders/approvals/reject/' . (int) $registration['pending_registration_id']) ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <textarea name="rejection_notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Optional rejection reason"></textarea>
                                                <button type="submit" class="btn btn-danger btn-sm w-100" data-cs-confirm="Reject this registration?">Reject registration</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="cs-muted small">Already reviewed</span>
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
