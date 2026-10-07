<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php if (! empty($branch_issue)): ?>
    <div class="alert alert-warning"><?= esc($branch_issue) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services?tab=services') ?>">Services</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services?tab=packages') ?>">Packages</a></li>
    <li class="nav-item"><a class="nav-link active" href="<?= site_url('/staff/services/requests') ?>">Claims</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services/ongoing') ?>">Ongoing Services</a></li>
</ul>

<?= view('partials/search_bar', [
    'action'      => site_url('/staff/services/requests'),
    'term'        => $search ?? '',
    'placeholder' => 'Plan holder, plan number, or package / service claimed',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php $rows = $requests ?? []; ?>
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', ['icon' => 'ti-clipboard-list', 'title' => ! empty($search) ? 'No claims match "' . $search . '"' : 'No claims found for your branch', 'text' => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : '']) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder</th>
                            <th>Claiming</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th class="cs-table__actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $request): ?>
                            <?php $status = (string) ($request['status'] ?? 'pending'); ?>
                            <tr>
                                <td><?= esc(trim((string) (($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')))) ?></td>
                                <td><?= esc((string) ($request['claim_label'] ?? $request['package_name'] ?? $request['service_name'] ?? '-')) ?></td>
                                <td class="text-nowrap"><?= cs_date($request['created_at'] ?? null) ?></td>
                                <td><?= cs_status($status) ?></td>
                                <td class="cs-table__actions">
                                    <a href="<?= site_url('/staff/services/requests/' . (int) $request['application_id']) ?>" class="btn btn-sm btn-primary me-1">View</a>
                                    <?php if ($status === 'pending'): ?>
                                        <form action="<?= site_url('/staff/services/requests/approve/' . (int) $request['application_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                        </form>
                                        <form action="<?= site_url('/staff/services/requests/reject/' . (int) $request['application_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="text" name="rejection_reason" class="form-control form-control-sm d-inline-block" style="max-width: 160px;" placeholder="Reason (optional)">
                                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
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
