<?php
/** Step 4: Emergency Contact. See _wizard.php for the $wizard contract. */
$planHolder = $wizard['plan_holder'] ?? [];
$isEdit = ($wizard['context'] ?? 'register') === 'edit';
?>
<!-- ============ STEP 4: Emergency Contact ============ -->
<div<?= $isEdit ? '' : ' class="wizard-panel d-none" data-step-panel="4"' ?>>
    <div class="cs-panel">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Emergency Contact</h2>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="emergency_contact_name">Name</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" class="form-control" value="<?= esc(old('emergency_contact_name', (string) ($planHolder['emergency_contact_name'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emergency_contact_relationship">Relationship</label>
                    <input id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" maxlength="60" value="<?= esc(old('emergency_contact_relationship', (string) ($planHolder['emergency_contact_relationship'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emergency_contact_number">Contact #</label>
                    <input id="emergency_contact_number" name="emergency_contact_number" class="form-control" value="<?= esc(old('emergency_contact_number', (string) ($planHolder['emergency_contact_number'] ?? ''))) ?>">
                    <small class="text-danger d-none" id="emergencyContactError">Please enter a valid contact number (at least 10 digits).</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="emergency_contact_address">Address</label>
                    <input id="emergency_contact_address" name="emergency_contact_address" class="form-control" value="<?= esc(old('emergency_contact_address', (string) ($planHolder['emergency_contact_address'] ?? ''))) ?>">
                </div>
            </div>
        </div>
    </div>

    <?php if (! $isEdit): ?>
    <div class="wizard-nav">
        <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
        <button type="button" class="btn btn-primary wizard-next">Next: Review <i class="ti ti-arrow-right ms-1"></i></button>
    </div>
    <?php endif; ?>
</div>
