<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>

<?= view('partials/search_bar', [
    'action'      => $search_action ?? '',
    'term'        => $search ?? '',
    'placeholder' => 'Applicant name, email or GCash reference',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-user-check',
                'title' => ! empty($search) ? 'No pending registrations match "' . $search . '"' : 'No pending registrations for approval',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Client</th>
                            <th>Branch</th>
                            <th>Date</th>
                            <th class="cs-num">Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Government ID</th>
                            <th class="cs-table__actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>#<?= esc((string) $row['payment_id']) ?></td>
                                <td>
                                    <?= esc((string) $row['first_name'] . ' ' . $row['last_name']) ?><br>
                                    <small class="cs-muted"><?= esc((string) $row['email']) ?></small>
                                </td>
                                <td><?= esc((string) ($row['branch_name'] ?? '—')) ?></td>
                                <td class="text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                <td><?= esc(strtoupper((string) $row['payment_method'])) ?></td>
                                <td><?= esc((string) ($row['reference_number'] ?: '—')) ?></td>
                                <td><?= cs_status('pending') ?></td>
                                <td>
                                    <?php $idv = $row['id_verification'] ?? null; ?>
                                    <?php if ($idv === null): ?>
                                        <?= cs_status('idle', 'No attempt') ?>
                                    <?php elseif ((string) $idv['verification_status'] === 'verified'): ?>
                                        <?= cs_status('verified') ?>
                                    <?php elseif ((string) $idv['verification_status'] === 'needs_review'): ?>
                                        <?= cs_status('for_review', 'ID needs manual review') ?>
                                        <details class="small mt-1">
                                            <summary class="cs-muted">Extracted vs entered</summary>
                                            <div class="mt-1">
                                                <div><strong>On the ID:</strong>
                                                    <?= esc((string) ($idv['extracted_name'] ?? 'Not read')) ?>
                                                    <?php if (! empty($idv['extracted_birth_date'])): ?>, DOB <?= cs_date($idv['extracted_birth_date']) ?><?php endif; ?>
                                                    <?php if (! empty($idv['extracted_gender'])): ?>, <?= esc((string) $idv['extracted_gender']) ?><?php endif; ?>
                                                </div>
                                                <div><strong>Entered:</strong>
                                                    <?= esc(trim((string) ($idv['claimed_first_name'] ?? '') . ' ' . (string) ($idv['claimed_middle_name'] ?? '') . ' ' . (string) ($idv['claimed_last_name'] ?? '')) ?: '—') ?>
                                                    <?php if (! empty($idv['claimed_date_of_birth'])): ?>, DOB <?= cs_date($idv['claimed_date_of_birth']) ?><?php endif; ?>
                                                    <?php if (! empty($idv['claimed_gender'])): ?>, <?= esc((string) $idv['claimed_gender']) ?><?php endif; ?>
                                                </div>
                                                <?php if (! empty($idv['mismatch_reason'])): ?>
                                                    <div class="cs-muted"><?= esc((string) $idv['mismatch_reason']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php elseif ((string) $idv['verification_status'] === 'failed'): ?>
                                        <?= cs_status('failed') ?>
                                    <?php else: ?>
                                        <?= cs_status((string) $idv['verification_status']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="cs-table__actions">
                                    <?php if (! empty($can_verify)): ?>
                                        <form method="post" action="<?= base_url('payments/verify-initial/' . (int) $row['payment_id']) ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-primary btn-sm">Verify</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="cs-muted small">View only</span>
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
