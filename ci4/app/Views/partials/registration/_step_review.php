<?php
/** Step 5: Review & Certification. See _wizard.php for the $wizard contract. */
$certifyLabel = (string) ($wizard['certify_label'] ?? 'I certify that the information I provided is true and correct to the best of my knowledge.');
$submitLabel = (string) ($wizard['submit_label'] ?? 'Submit Registration');
?>
<!-- ============ STEP 5: Review & Certification ============ -->
<div class="wizard-panel d-none" data-step-panel="5">
    <div class="cs-panel mb-3">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Applicant Information</h2>
            <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="1">Edit</button>
        </div>
        <div class="cs-panel__body small" id="reviewApplicant"></div>
    </div>

    <div class="cs-panel mb-3">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Government ID Verification</h2>
            <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="2">Edit</button>
        </div>
        <div class="cs-panel__body small" id="reviewIdVerification"></div>
    </div>

    <div class="cs-panel mb-3">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Beneficiaries</h2>
            <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="3">Edit</button>
        </div>
        <div class="cs-panel__body small" id="reviewBeneficiaries"></div>
    </div>

    <div class="cs-panel mb-4">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Emergency Contact</h2>
            <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="4">Edit</button>
        </div>
        <div class="cs-panel__body small" id="reviewEmergency"></div>
    </div>

    <div class="cs-panel">
        <div class="cs-panel__body">
            <h2 class="cs-panel__title mb-2">Certification</h2>
            <p class="text-muted small mb-2">This is to certify that the above information is TRUE AND CORRECT to the best of my knowledge.</p>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="certify" name="certify" value="1" <?= old('certify') ? 'checked' : '' ?> required>
                <label class="form-check-label" for="certify">
                    <?= esc($certifyLabel) ?>
                </label>
            </div>
            <div class="wizard-nav mb-0">
                <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
                <button type="submit" class="btn btn-primary"><?= esc($submitLabel) ?></button>
            </div>
        </div>
    </div>
</div>
