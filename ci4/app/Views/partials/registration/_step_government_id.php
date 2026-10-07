<?php
/**
 * Step 2: Government ID Verification. See _wizard.php for the $wizard
 * contract. Reuses partials/id_verification_section.php - the same
 * upload/camera markup the 3 staff-side forms already used, so this is
 * now the only copy of that Step-2 markup anywhere, including the client
 * wizard.
 */
$this->setData([
    'id_types' => $wizard['id_types'] ?? [],
    'pending_field_id' => (string) ($wizard['id_result_field_id'] ?? 'government_id_pending_token'),
    'pending_field_value' => (string) ($wizard['id_result_field_value'] ?? ''),
    'selected_id_type' => (string) ($wizard['selected_id_type'] ?? ''),
]);
?>
<!-- ============ STEP 2: Government ID Verification ============ -->
<div class="wizard-panel d-none" data-step-panel="2">
    <?= $this->include('partials/id_verification_section') ?>

    <div class="wizard-nav">
        <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
        <button type="button" class="btn btn-primary wizard-next">Next: Beneficiaries <i class="ti ti-arrow-right ms-1"></i></button>
    </div>
</div>
