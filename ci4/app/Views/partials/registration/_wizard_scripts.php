<?php
/**
 * Shared script include for every page built on partials/registration/*
 * (the 4 registration wizards and the 3 plan-holder edit pages) - one copy
 * of the id-verification-widget/caresync-wizard/psgc-address bootstrap
 * instead of each register.php pasting its own near-identical <script>
 * block. See _wizard.php for the $wizard contract this reads.
 *
 * Safe to include even where a section the wizard drives doesn't exist on
 * the page (e.g. no Government ID step on an edit page): both
 * initIdVerificationWidget() and CareSyncWizard.init()'s other pieces
 * (spouse hint, beneficiary "Other" toggle, account-mode toggle) already
 * no-op when their expected DOM elements aren't present.
 */
$wizard = $wizard ?? [];
?>
<script src="<?= base_url('assets/js/id-verification-widget.js') ?>"></script>
<script src="<?= base_url('assets/js/caresync-wizard.js') ?>"></script>
<script src="<?= base_url('assets/js/data/oriental-mindoro-address.js') ?>"></script>
<script src="<?= base_url('assets/js/psgc-address.js') ?>"></script>
<script>
(function () {
    window.CareSyncWizard.init({
        idVerify: {
            endpoint: '<?= esc((string) ($wizard['id_verify_endpoint'] ?? ''), 'js') ?>',
            resultFieldId: '<?= esc((string) ($wizard['id_result_field_id'] ?? 'government_id_pending_token'), 'js') ?>',
            resultFieldKey: '<?= esc((string) ($wizard['id_result_field_key'] ?? 'pending_token'), 'js') ?>',
            initialStatus: <?= ! empty($wizard['id_initial_status']) ? json_encode((string) $wizard['id_initial_status']) : 'null' ?>,
            csrfName: '<?= csrf_token() ?>',
            csrfValue: '<?= csrf_hash() ?>',
        },
        <?php if (! empty($wizard['show_account_section'])): ?>
        existingUsers: <?= json_encode(array_values(array_map(static function ($user) {
            return [
                'email' => strtolower(trim((string) ($user['email'] ?? ''))),
                'first_name' => (string) ($user['first_name'] ?? ''),
                'middle_name' => (string) ($user['middle_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'contact_number' => (string) ($user['contact_number'] ?? ''),
            ];
        }, $wizard['existing_users'] ?? []))) ?>,
        <?php endif; ?>
    });

    window.CareSyncPsgc.init();
})();
</script>
