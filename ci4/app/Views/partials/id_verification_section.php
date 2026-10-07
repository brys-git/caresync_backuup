<?php
/**
 * Shared "Government ID Verification" section - upload/camera-capture UI
 * backed by public/assets/js/id-verification-widget.js. Used by every
 * CareSync registration form that creates or links a plan holder account
 * on someone else's behalf (plan_holders/register.php, branch_admin/
 * clients/register.php, staff/clients/register.php) so this markup only
 * has to exist once.
 *
 * Expects (via $this->setData() before $this->include(), since CI4's
 * include() does not inherit the caller's local view variables):
 *   $id_types            array<string,string> value => label
 *   $pending_field_id     id AND name of the hidden input the result gets
 *                          written into (default 'government_id_pending_token').
 *                          Also used by the client wizard's self-service
 *                          step (partials/registration/_step_government_id.php),
 *                          passed as 'government_id_verification_id' there
 *                          since that endpoint returns a real verification
 *                          id, not a pending token.
 *   $pending_field_value  pre-fills the hidden field (default '') - the
 *                          client wizard uses this to carry a prior
 *                          verification_id through a page reload without
 *                          forcing a re-check; the 3 staff-side callers
 *                          never had this (their pending token is always
 *                          scoped to one in-progress attempt), so leave unset.
 *   $selected_id_type     value of $id_types to preselect (default none) -
 *                          same "prior attempt on file" case as above.
 */
$idTypes = $id_types ?? [];
$pendingFieldId = $pending_field_id ?? 'government_id_pending_token';
$pendingFieldValue = $pending_field_value ?? '';
$selectedIdType = $selected_id_type ?? '';
?>
<div class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Government ID Verification</h2>
        <p class="cs-panel__note">Upload or take a photo of the applicant's valid government-issued ID so it can be confirmed against the name entered.</p>
    </div>
    <div class="cs-panel__body">
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <label class="form-label" for="id_type">Government ID Type</label>
                <select id="id_type" class="form-select">
                    <option value="">Select ID type</option>
                    <?php foreach ($idTypes as $value => $label): ?>
                        <option value="<?= esc((string) $value) ?>" <?= $selectedIdType === (string) $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn btn-ghost btn-sm" id="btnChooseUpload"><i class="ti ti-upload me-1"></i>Upload Government ID</button>
            <button type="button" class="btn btn-ghost btn-sm" id="btnChooseCamera"><i class="ti ti-camera me-1"></i>Take Photo</button>
            <input type="file" id="idFileInput" accept="image/jpeg,image/png,image/webp" class="d-none">
        </div>

        <div id="cameraPanel" class="d-none mb-3">
            <div class="id-verify-camera-frame mb-2">
                <video id="cameraVideo" autoplay playsinline muted></video>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="btnCapture"><i class="ti ti-camera me-1"></i>Capture</button>
                <button type="button" class="btn btn-ghost btn-sm" id="btnCancelCamera">Cancel</button>
            </div>
            <p class="text-danger small mt-2 d-none" id="cameraError"></p>
        </div>

        <div id="idPreviewWrap" class="d-none mb-3">
            <img id="idPreviewImg" alt="ID preview" class="id-verify-preview">
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-ghost btn-sm" id="btnRetake"><i class="ti ti-refresh me-1"></i>Retake / Choose Another</button>
            </div>
        </div>

        <canvas id="captureCanvas" class="d-none"></canvas>

        <p class="text-muted small mb-2">Verification runs automatically once an ID image and type are selected. Use this only to retry.</p>
        <div class="d-flex align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-ghost btn-sm" id="btnVerifyId" disabled>Re-check ID</button>
            <span class="text-muted small" id="verifyHint">Choose or capture an image first.</span>
        </div>

        <div id="verificationResult" class="d-none"></div>

        <input type="hidden" name="<?= esc($pendingFieldId, 'attr') ?>" id="<?= esc($pendingFieldId) ?>" value="<?= esc($pendingFieldValue) ?>">
    </div>
</div>
