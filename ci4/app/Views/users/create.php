<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
    $idTypes = $id_types ?? [];
    $assignableRoles = $roles;
    if ($current_role_id === 2) {
        $assignableRoles = array_values(array_filter($roles, static fn ($r) => in_array((int) $r['role_id'], [3, 4, 5], true)));
    }
    $ownBranchLocked = in_array($current_role_id, [2, 3], true);
?>
<div class="cs-wizard">
    <?php if (session()->getFlashdata('error')): ?>
        <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
        <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
    <?php endif; ?>

    <!-- Progress indicator -->
    <ol class="wizard-progress mb-4">
        <li class="wizard-progress__item is-active" data-progress-step="1"><span class="wizard-progress__badge">1</span><span class="wizard-progress__label">Personal Info</span></li>
        <li class="wizard-progress__item" data-progress-step="2"><span class="wizard-progress__badge">2</span><span class="wizard-progress__label">Government ID</span></li>
        <li class="wizard-progress__item" data-progress-step="3"><span class="wizard-progress__badge">3</span><span class="wizard-progress__label">Account & Role</span></li>
        <li class="wizard-progress__item" data-progress-step="4"><span class="wizard-progress__badge">4</span><span class="wizard-progress__label">Review</span></li>
    </ol>

    <form method="post" action="<?= base_url('users/create') ?>" id="createUserForm">
        <?= csrf_field() ?>
        <input type="hidden" name="government_id_pending_token" id="government_id_pending_token" value="">

        <!-- ============ STEP 1: Personal Information ============ -->
        <div class="wizard-panel" data-step-panel="1">
            <div class="cs-panel">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Personal Information</h2>
                </div>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="middle_name">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= old('middle_name') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= old('username') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_number">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= old('contact_number') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-nav">
                <span></span>
                <button type="button" class="btn btn-primary wizard-next">Next: Government ID <i class="ti ti-arrow-right ms-1"></i></button>
            </div>
        </div>

        <!-- ============ STEP 2: Government ID Verification ============ -->
        <div class="wizard-panel d-none" data-step-panel="2">
            <div class="cs-panel">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Government ID Verification</h2>
                    <p class="cs-panel__note">Upload or take a photo of the new account holder's valid government-issued ID so it can be confirmed against the name entered.</p>
                </div>
                <div class="cs-panel__body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label" for="id_type">Government ID Type</label>
                            <select id="id_type" class="form-select">
                                <option value="">Select ID type</option>
                                <?php foreach ($idTypes as $value => $label): ?>
                                    <option value="<?= esc((string) $value) ?>"><?= esc((string) $label) ?></option>
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
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
                <button type="button" class="btn btn-primary wizard-next">Next: Account &amp; Role <i class="ti ti-arrow-right ms-1"></i></button>
            </div>
        </div>

        <!-- ============ STEP 3: Account & Role Information ============ -->
        <div class="wizard-panel d-none" data-step-panel="3">
            <div class="cs-panel">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Account &amp; Role Information</h2>
                </div>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="role_id">Role</label>
                            <select class="form-select" id="role_id" name="role_id" <?= $current_role_id === 3 ? 'disabled' : '' ?> required>
                                <?php foreach ($assignableRoles as $role): ?>
                                    <option value="<?= esc((string) $role['role_id']) ?>" <?= old('role_id') == $role['role_id'] ? 'selected' : '' ?>>
                                        <?= esc($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($current_role_id === 3): ?>
                                <input type="hidden" name="role_id" value="4">
                                <small class="text-muted">Staff can only create Plan Holder accounts.</small>
                            <?php elseif ($current_role_id === 2): ?>
                                <small class="text-muted">Branch Admins can only create Staff, Plan Holder, or Collector accounts.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="branch_id">Branch</label>
                            <select class="form-select" id="branch_id" name="branch_id" <?= $ownBranchLocked ? 'disabled' : '' ?>>
                                <option value="">No Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= esc((string) $branch['branch_id']) ?>" <?= ($ownBranchLocked ? (int) session('branch_id') === (int) $branch['branch_id'] : old('branch_id') == $branch['branch_id']) ? 'selected' : '' ?>>
                                        <?= esc($branch['branch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($ownBranchLocked): ?>
                                <input type="hidden" name="branch_id" value="<?= esc((string) session('branch_id')) ?>">
                                <small class="text-muted">New accounts are always assigned to your own branch.</small>
                            <?php endif; ?>
                        </div>

                        <?php if ($current_role_id === 1): ?>
                            <div class="col-md-3">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="password_confirm">Confirm Password</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <?= view('components/alert', [
                                    'type' => 'info',
                                    'dismissible' => false,
                                    'message' => 'A temporary password will be auto-generated, sent via email, and the user will be forced to change password on first login.',
                                ]) ?>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-3">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="account_status">Account Status</label>
                            <select class="form-select" id="account_status" name="account_status" <?= $current_role_id === 3 ? 'disabled' : '' ?> required>
                                <option value="pending" <?= old('account_status', 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="verified" <?= old('account_status') === 'verified' ? 'selected' : '' ?>>Verified</option>
                            </select>
                            <?php if ($current_role_id === 3): ?>
                                <input type="hidden" name="account_status" value="verified">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_plan_holder" name="is_plan_holder" value="1" <?= old('is_plan_holder') == '1' ? 'checked' : '' ?> <?= $current_role_id === 3 ? 'checked disabled' : '' ?>>
                                <label class="form-check-label" for="is_plan_holder">Is Plan Holder</label>
                                <?php if ($current_role_id === 3): ?>
                                    <input type="hidden" name="is_plan_holder" value="1">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="must_change_password" name="must_change_password" value="1" <?= old('must_change_password') == '1' ? 'checked' : '' ?> <?= $current_role_id === 3 ? 'checked disabled' : '' ?>>
                                <label class="form-check-label" for="must_change_password">Force Password Change</label>
                                <?php if ($current_role_id === 3): ?>
                                    <input type="hidden" name="must_change_password" value="1">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
                <button type="button" class="btn btn-primary wizard-next">Next: Review <i class="ti ti-arrow-right ms-1"></i></button>
            </div>
        </div>

        <!-- ============ STEP 4: Review ============ -->
        <div class="wizard-panel d-none" data-step-panel="4">
            <div class="cs-panel mb-3">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Personal Information</h2>
                    <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="1">Edit</button>
                </div>
                <div class="cs-panel__body small" id="reviewPersonal"></div>
            </div>

            <div class="cs-panel mb-3">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Government ID Verification</h2>
                    <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="2">Edit</button>
                </div>
                <div class="cs-panel__body small" id="reviewIdVerification"></div>
            </div>

            <div class="cs-panel mb-4">
                <div class="cs-panel__head">
                    <h2 class="cs-panel__title">Account &amp; Role</h2>
                    <button type="button" class="btn btn-ghost btn-sm wizard-edit" data-goto-step="3">Edit</button>
                </div>
                <div class="cs-panel__body small" id="reviewAccount"></div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </div>
    </form>
</div>

<script src="<?= base_url('assets/js/id-verification-widget.js') ?>"></script>
<script>
(function () {
    const TOTAL_STEPS = 4;
    let currentStep = 1;

    const panels = Array.from(document.querySelectorAll('.wizard-panel'));
    const progressItems = Array.from(document.querySelectorAll('.wizard-progress__item'));

    function showStep(step) {
        currentStep = Math.min(Math.max(step, 1), TOTAL_STEPS);
        panels.forEach(function (panel) {
            panel.classList.toggle('d-none', parseInt(panel.dataset.stepPanel, 10) !== currentStep);
        });
        progressItems.forEach(function (item) {
            const n = parseInt(item.dataset.progressStep, 10);
            item.classList.toggle('is-active', n === currentStep);
            item.classList.toggle('is-done', n < currentStep);
        });
        if (currentStep === 4) {
            populateReview();
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fieldValue(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function row(label, value) {
        return '<div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">' + label + '</span><span class="fw-medium text-end">' + (value || '&mdash;') + '</span></div>';
    }

    function validateStep(step) {
        if (step === 1) {
            for (const id of ['first_name', 'last_name', 'username', 'email']) {
                const el = document.getElementById(id);
                if (!el.value.trim()) {
                    el.reportValidity ? el.reportValidity() : null;
                    el.focus();
                    return false;
                }
            }
            return true;
        }

        if (step === 2) {
            if (!idVerificationWidget || !idVerificationWidget.wasAttempted()) {
                document.getElementById('verifyHint').textContent = 'Please verify the ID before continuing.';
                document.getElementById('verifyHint').classList.add('text-danger');
                return false;
            }
            return true;
        }

        if (step === 3) {
            const roleEl = document.getElementById('role_id');
            if (roleEl && !roleEl.disabled && !roleEl.value) {
                roleEl.focus();
                return false;
            }
            const pwd = document.getElementById('password');
            if (pwd) {
                const confirm = document.getElementById('password_confirm');
                if (pwd.value.length < 8) {
                    pwd.focus();
                    return false;
                }
                if (pwd.value !== confirm.value) {
                    confirm.focus();
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    document.querySelectorAll('.wizard-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        });
    });

    document.querySelectorAll('.wizard-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(currentStep - 1);
        });
    });

    document.querySelectorAll('.wizard-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(parseInt(btn.dataset.gotoStep, 10));
        });
    });

    function populateReview() {
        document.getElementById('reviewPersonal').innerHTML = [
            row('Name', esc([fieldValue('first_name'), fieldValue('middle_name'), fieldValue('last_name')].filter(Boolean).join(' '))),
            row('Username', esc(fieldValue('username'))),
            row('Email', esc(fieldValue('email'))),
            row('Contact Number', esc(fieldValue('contact_number'))),
        ].join('');

        const idTypeSelect = document.getElementById('id_type');
        const idTypeLabel = idTypeSelect.selectedOptions[0] ? idTypeSelect.selectedOptions[0].text : 'Not selected';
        const currentIdStatus = idVerificationWidget ? idVerificationWidget.getStatus() : null;
        const statusText = currentIdStatus
            ? currentIdStatus.charAt(0).toUpperCase() + currentIdStatus.slice(1).replace('_', ' ')
            : 'Not yet verified';
        document.getElementById('reviewIdVerification').innerHTML = row('ID Type', esc(idTypeLabel)) + row('Status', esc(statusText));

        const roleSelect = document.getElementById('role_id');
        const roleLabel = roleSelect.selectedOptions[0] ? roleSelect.selectedOptions[0].text : '';
        const branchSelect = document.getElementById('branch_id');
        const branchLabel = branchSelect.selectedOptions[0] ? branchSelect.selectedOptions[0].text : '';
        document.getElementById('reviewAccount').innerHTML = [
            row('Role', esc(roleLabel)),
            row('Branch', esc(branchLabel)),
            row('Status', esc(fieldValue('status'))),
            row('Account Status', esc(fieldValue('account_status'))),
        ].join('');
    }

    // ==================================================================
    // Government ID Verification (Step 2) - shared widget, see
    // public/assets/js/id-verification-widget.js. The account being
    // created doesn't exist yet, so this uses the "pending" verify
    // endpoint - the server stashes the result under a random token
    // (written into #government_id_pending_token) and only finalizes it
    // once Users::store() actually creates the account.
    // ==================================================================
    const idVerificationWidget = initIdVerificationWidget({
        endpoint: '<?= base_url('api/id-verification/verify-pending') ?>',
        csrfName: document.querySelector('input[name="<?= csrf_token() ?>"]').name,
        csrfValue: document.querySelector('input[name="<?= csrf_token() ?>"]').value,
        getIdentity: function () {
            return {
                first_name: fieldValue('first_name'),
                middle_name: fieldValue('middle_name'),
                last_name: fieldValue('last_name'),
                // This form has no date_of_birth/gender fields at all (it
                // creates a bare users row, not a plan holder profile) -
                // gender is sent as empty (the server treats an empty
                // claimed gender as "not checked"); date_of_birth is left
                // out of this object entirely so the widget never waits
                // on a field that can't exist here.
                gender: '',
            };
        },
        resultFieldId: 'government_id_pending_token',
        resultFieldKey: 'pending_token',
        initialStatus: null,
    });

    showStep(1);
})();
</script>
<?= $this->endSection() ?>
