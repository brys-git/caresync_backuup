<?php
/**
 * Claim detail/review page - shared by Branch Admin
 * (BranchAdmin\ServiceApplicationController) and Staff
 * (Staff\ServiceApplicationController), per the panel brief: claims are
 * processed by staff or an Encoder, not just Branch Admin. Set
 * $claim_base_path to the caller's own "requests" route prefix so the
 * download/approve/reject/back links point at the right role.
 */
$claimBasePath = (string) ($claim_base_path ?? '/branch-admin/service-package/requests');
?>
<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url($claimBasePath) ?>" class="btn btn-ghost btn-sm">Back to Claims</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <div class="row gy-3">
            <div class="col-md-4"><strong>Applicant:</strong> <?= esc((string) ($request['first_name'] ?? '') . ' ' . (string) ($request['last_name'] ?? '')) ?></div>
            <div class="col-md-4"><strong>Request ID:</strong> <?= esc((string) ($request['application_id'] ?? '-')) ?></div>
            <div class="col-md-4"><strong>Status:</strong> <?= cs_status((string) ($request['status'] ?? '')) ?></div>
            <div class="col-md-6"><strong>Requested on:</strong> <?= cs_date($request['created_at'] ?? null) ?></div>
            <div class="col-md-6"><strong>Branch ID:</strong> <?= esc((string) ($request['branch_id'] ?? '-')) ?></div>
            <div class="col-md-6"><strong>Type:</strong> <?= esc((string) (($request['service_list_id'] ?? 0) > 0 ? 'Service' : 'Package')) ?></div>
            <div class="col-md-6"><strong>Selected:</strong> <?= esc((string) ($request['service_name'] ?? $request['package_name'] ?? '-')) ?></div>
            <div class="col-md-6"><strong>Price:</strong> <?= cs_money(($request['service_price'] ?? $request['package_price']) ?? 0) ?></div>
        </div>
    </div>
</section>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Deceased Information</h2></div>
            <div class="cs-panel__body">
                <div class="mb-3"><strong>Name:</strong> <?= esc((string) ($request['deceased_name'] ?? '-')) ?></div>
                <div class="mb-3"><strong>Date of death:</strong> <?= cs_date($request['deceased_date_of_death'] ?? null) ?></div>
                <div class="mb-3"><strong>Relationship:</strong> <?= esc((string) ($request['relationship_to_deceased'] ?? '-')) ?></div>
                <div class="mb-3"><strong>Address:</strong> <?= esc((string) ($request['deceased_address'] ?? '-')) ?></div>
            </div>
        </section>
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Beneficiary Details</h2></div>
            <div class="cs-panel__body">
                <div class="mb-3"><strong>Name:</strong> <?= esc((string) ($request['beneficiary_name'] ?? '-')) ?></div>
                <div class="mb-3"><strong>Contact:</strong> <?= esc((string) ($request['beneficiary_contact'] ?? '-')) ?></div>
                <div class="mb-3"><strong>Notes:</strong> <?= esc((string) ($request['application_notes'] ?? '-')) ?></div>
            </div>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Supporting Documents</h2></div>
            <div class="cs-panel__body">
                <?php if ((int) ($request['awaiting_death_certificate'] ?? 0) === 1): ?>
                    <div class="alert alert-warning mb-3">Awaiting death certificate. The client submitted without it.</div>
                <?php endif; ?>
                <?php if (empty($documents)): ?>
                    <div class="text-muted">No documents uploaded yet.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($documents as $document): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <?php $documentTypeLabels = ['death_certificate' => 'Death certificate', 'beneficiary_id' => 'Beneficiary ID', 'other' => 'Other']; ?>
                                    <?php if (! empty($document['document_type'])): ?><strong><?= esc($documentTypeLabels[$document['document_type']] ?? (string) $document['document_type']) ?>:</strong> <?php endif; ?>
                                    <?= esc((string) ($document['original_name'] ?? 'Unnamed document')) ?>
                                </span>
                                <a href="<?= site_url($claimBasePath . '/document/' . (int) $document['document_id']) ?>" class="btn btn-sm btn-outline-primary">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Actions</h2></div>
            <div class="cs-panel__body">
                <?php if (($request['status'] ?? '') === 'pending'): ?>
                    <form action="<?= site_url($claimBasePath . '/approve/' . (int) $request['application_id']) ?>" method="post" class="d-inline-block me-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary">Approve</button>
                    </form>
                    <form action="<?= site_url($claimBasePath . '/reject/' . (int) $request['application_id']) ?>" method="post" class="d-inline-block">
                        <?= csrf_field() ?>
                        <input type="text" name="rejection_reason" class="form-control form-control-sm d-inline-block me-1" style="max-width: 220px;" placeholder="Reason (optional)">
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </form>
                <?php else: ?>
                    <div class="text-muted">This claim is <?= esc(ucfirst((string) ($request['status'] ?? 'unknown'))) ?>.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
