<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<?php $row = $row ?? []; ?>

<div class="mb-3">
    <a href="<?= esc((string) ($queue_url ?? '#')) ?>" class="btn btn-ghost btn-sm">
        <i class="ti ti-arrow-left" aria-hidden="true"></i> Back to Queue
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Uploaded ID</h2></div>
            <div class="cs-panel__body text-center">
                <?php if (in_array((string) ($row['mime_type'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true) || $row['mime_type'] === null): ?>
                    <img
                        src="<?= esc(base_url(((int) session('role_id') === 2 ? 'branch-admin' : 'staff') . '/id-verification/' . (int) $row['verification_id'] . '/image'), 'attr') ?>"
                        alt="Uploaded government ID"
                        class="img-fluid rounded border"
                        style="max-height: 480px;"
                    >
                <?php else: ?>
                    <p class="text-muted mb-0">This file type can't be previewed inline.</p>
                <?php endif; ?>
                <p class="text-muted small mt-2 mb-0">
                    ID Type: <?= esc(strtoupper(str_replace('_', ' ', (string) ($row['id_type'] ?? '-')))) ?>
                    &middot; Submitted <?= cs_date($row['created_at'] ?? null, true) ?>
                </p>
            </div>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Typed in the Registration Form</h2></div>
            <div class="cs-panel__body">
                <p class="text-muted small mb-3">Compare each of these against the ID image on the left.</p>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr><th class="text-muted" style="width: 40%;">Full Name</th><td><?= esc(trim((string) ($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']))) ?></td></tr>
                        <tr><th class="text-muted">Date of Birth</th><td><?= cs_date($row['claimed_date_of_birth'] ?? null) ?></td></tr>
                        <tr><th class="text-muted">Gender</th><td><?= esc((string) ($row['claimed_gender'] ?? '-')) ?></td></tr>
                        <tr><th class="text-muted">Civil Status</th><td><?= esc((string) ($row['civil_status'] ?: '-')) ?></td></tr>
                        <tr><th class="text-muted">Citizenship</th><td><?= esc((string) ($row['citizenship'] ?: '-')) ?></td></tr>
                        <tr><th class="text-muted">Address</th><td><?= esc(trim((string) ($row['address_no'] . ' ' . $row['address_street'] . ', ' . $row['address_barangay'] . ', ' . $row['address_city']), ' ,')) ?></td></tr>
                        <tr><th class="text-muted">Email</th><td><?= esc((string) ($row['email'] ?? '-')) ?></td></tr>
                        <tr><th class="text-muted">Contact #</th><td><?= esc((string) ($row['contact_number'] ?? '-')) ?></td></tr>
                    </tbody>
                </table>

                <?php if (! empty($row['extracted_name']) || ! empty($row['extracted_birth_date']) || ! empty($row['extracted_gender'])): ?>
                    <hr>
                    <p class="text-muted small mb-2">Automatically extracted from the ID image:</p>
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <?php if (! empty($row['extracted_name'])): ?><tr><th class="text-muted" style="width: 40%;">Name on ID</th><td><?= esc((string) $row['extracted_name']) ?></td></tr><?php endif; ?>
                            <?php if (! empty($row['extracted_birth_date'])): ?><tr><th class="text-muted">DOB on ID</th><td><?= cs_date($row['extracted_birth_date']) ?></td></tr><?php endif; ?>
                            <?php if (! empty($row['extracted_gender'])): ?><tr><th class="text-muted">Gender on ID</th><td><?= esc((string) $row['extracted_gender']) ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (! empty($row['mismatch_reason'])): ?>
                    <div class="alert alert-warning mt-3 mb-0"><?= esc((string) $row['mismatch_reason']) ?></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="cs-panel mt-3">
    <div class="cs-panel__body">
        <h3 class="h6 text-primary mb-2">Decision</h3>
        <p class="text-muted small mb-3">Does the ID image match the name, date of birth, and gender typed above?</p>
        <div class="d-flex gap-2">
            <form method="post" action="<?= esc((string) $action_base . '/' . (int) $row['verification_id'] . '/verify', 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" data-cs-confirm="Confirm this ID matches the applicant's details?"><i class="ti ti-circle-check me-1" aria-hidden="true"></i> Matches - Mark Verified</button>
            </form>
            <form method="post" action="<?= esc((string) $action_base . '/' . (int) $row['verification_id'] . '/fail', 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-cs-confirm="Mark this ID as not matching? The applicant will be notified."><i class="ti ti-circle-x me-1" aria-hidden="true"></i> Doesn't Match</button>
            </form>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
