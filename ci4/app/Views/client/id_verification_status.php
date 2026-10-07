<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<?php
$status = $status ?? null;
$planHolder = $plan_holder ?? [];
$user = $user ?? [];
?>
<div class="cs-narrow--sm">

    <?php if ($status === 'needs_review' || $status === 'pending'): ?>
        <section class="cs-panel">
            <div class="cs-panel__body text-center py-5">
                <div class="cs-empty__icon"><i class="ti ti-clock-hour-4" aria-hidden="true"></i></div>
                <h2 class="cs-panel__title mb-2">Your ID is being reviewed</h2>
                <p class="cs-muted mb-4">
                    A staff member at your branch is checking your Government ID against the details you submitted.
                    You'll be notified as soon as it's verified, and you can then proceed to your Initial Payment.
                </p>
                <a href="<?= base_url('id-verification-status') ?>" class="btn btn-outline-primary">
                    <i class="ti ti-refresh me-1" aria-hidden="true"></i> Check status
                </a>
            </div>
        </section>

    <?php elseif ($status === 'failed'): ?>
        <?= view('components/alert', ['type' => 'danger', 'dismissible' => false, 'title' => 'Your Government ID could not be confirmed.', 'message' => 'Please upload a valid, clear photo of a government-issued ID that matches your registration details.']) ?>

        <?php $this->setData([
            'id_types' => $id_types ?? [],
            'pending_field_id' => 'government_id_verification_id',
        ]) ?>
        <?= $this->include('partials/id_verification_section') ?>

        <div class="text-center mt-3">
            <a href="<?= base_url('id-verification-status') ?>" class="btn btn-primary" id="btnContinue">
                <i class="ti ti-refresh me-1" aria-hidden="true"></i> I've uploaded my ID — check status
            </a>
        </div>

    <?php else: ?>
        <section class="cs-panel">
            <div class="cs-panel__body text-center py-5">
                <div class="cs-empty__icon"><i class="ti ti-id-badge-2" aria-hidden="true"></i></div>
                <h2 class="cs-panel__title mb-2">No Government ID on file</h2>
                <p class="cs-muted mb-4">We couldn't find a Government ID verification for your account. Please contact your branch for assistance.</p>
            </div>
        </section>
    <?php endif; ?>
</div>

<script src="<?= base_url('assets/js/id-verification-widget.js') ?>"></script>
<script>
(function () {
    const widget = initIdVerificationWidget({
        endpoint: '<?= base_url('api/id-verification/verify') ?>',
        csrfName: '<?= csrf_token() ?>',
        csrfValue: '<?= csrf_hash() ?>',
        getIdentity: function () {
            return {
                first_name: <?= json_encode((string) ($user['first_name'] ?? '')) ?>,
                middle_name: <?= json_encode((string) ($user['middle_name'] ?? '')) ?>,
                last_name: <?= json_encode((string) ($user['last_name'] ?? '')) ?>,
                date_of_birth: <?= json_encode((string) ($planHolder['date_of_birth'] ?? '')) ?>,
                gender: <?= json_encode((string) ($planHolder['gender'] ?? '')) ?>,
            };
        },
        resultFieldId: 'government_id_verification_id',
        resultFieldKey: 'verification_id',
        initialStatus: null,
    });
})();
</script>
<?= $this->endSection() ?>
