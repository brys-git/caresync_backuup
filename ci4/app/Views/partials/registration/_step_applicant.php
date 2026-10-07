<?php
/** Step 1: Applicant Information. See _wizard.php for the $wizard contract. */
$planHolder = $wizard['plan_holder'] ?? [];
$user = $wizard['user'] ?? [];
$program = $wizard['program'] ?? ['name' => 'Damayan Burial Program', 'monthly_fee' => 240.0];
$branches = $wizard['branches'] ?? [];
$applicationDate = (string) old('application_date', (string) ($planHolder['application_date'] ?? date('Y-m-d')));
$civilStatus = (string) old('civil_status', (string) ($planHolder['civil_status'] ?? ''));
$gender = (string) old('gender', (string) ($planHolder['gender'] ?? ''));
$isEdit = ($wizard['context'] ?? 'register') === 'edit';
?>
<!-- ============ STEP 1: Applicant Information ============ -->
<div<?= $isEdit ? '' : ' class="wizard-panel" data-step-panel="1"' ?>>
    <?php if (! $isEdit): ?>
    <div class="cs-panel mb-4">
        <div class="cs-panel__body">
            <h2 class="cs-panel__title mb-2">KAAGAPAY MO KARAMAY FUNERAL HOMES CO.</h2>
            <p class="mb-1"><strong>Main Office Address:</strong> #65 J.F. Diaz Ave. Ampid 1, San Mateo, Rizal</p>
            <p class="mb-1"><strong>Branch Offices:</strong> Dakila Cor. Constitutional Rd. Batasan Hills Q.C.; Sta Isabel, Calapan City; Babangonan, Victoria; Poblacion, Bansud; C-5 Diversion Rd. Bongabong; Upper Odiong, Roxas; Don Pedro, Mansalay Oriental Mindoro</p>
            <p class="mb-1"><strong>Contact Numbers:</strong> Smart 0962-571-9780; Globe 0997-512-7828 / 0927-735-0239</p>
            <p class="mb-1"><strong>Website &amp; Facebook Page:</strong> KaagapayMoKaramayFuneralHomes</p>
            <p class="mb-1"><strong>Registration Details:</strong> SEC. REG. PG 201520567, TIN # 009-196-436-0000</p>
            <p class="mb-0"><strong>Founder/CEO:</strong> Ricardo C. Ramilo</p>
        </div>
    </div>

    <div class="cs-panel mb-4">
        <div class="cs-panel__body">
            <h2 class="cs-panel__title mb-3">Instructions</h2>
            <ul class="mb-0">
                <li>Fill out correctly and neatly.</li>
                <li>Use capital letters for clarity (e.g., JUAN DELA CRUZ).</li>
                <li>Do not use stapler; use glue, tape, or paste for attaching the photo.</li>
            </ul>
        </div>
    </div>
    <?php if (! empty($wizard['show_plan_select'])): ?>
    <div class="cs-panel mb-4">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Choose a Plan</h2>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <?php $selectedProgramId = (string) old('program_id', (string) ($wizard['plans'][0]['program_id'] ?? '')); ?>
                <?php foreach ($wizard['plans'] as $planOption): ?>
                    <?php $optionId = 'program_id_' . (int) $planOption['program_id']; ?>
                    <div class="col-md-6">
                        <label class="cs-plan-option d-block border rounded p-3 h-100" for="<?= esc($optionId, 'attr') ?>">
                            <div class="d-flex align-items-start gap-2">
                                <input class="form-check-input mt-1" type="radio" name="program_id" id="<?= esc($optionId, 'attr') ?>"
                                    value="<?= (int) $planOption['program_id'] ?>"
                                    <?= $selectedProgramId === (string) $planOption['program_id'] ? 'checked' : '' ?> required>
                                <span>
                                    <strong class="d-block"><?= esc((string) $planOption['program_name']) ?></strong>
                                    <span class="cs-muted small d-block">
                                        <?= cs_money($planOption['plan_price'] ?? 0) ?> total &middot;
                                        <?= cs_money($planOption['monthly_fee'] ?? 0) ?>/month &middot;
                                        <?= (int) ($planOption['term_months'] ?? 0) ?> months
                                    </span>
                                    <?php if (! empty($planOption['description'])): ?>
                                        <span class="cs-muted small d-block mt-1"><?= esc((string) $planOption['description']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php elseif (! empty($wizard['plans'])): ?>
        <input type="hidden" name="program_id" value="<?= (int) $wizard['plans'][0]['program_id'] ?>">
    <?php endif; ?>
    <?php endif; ?>

    <div class="cs-panel mb-4">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Applicant Information</h2>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="id_control_no">ID Control No.</label>
                    <input id="id_control_no" name="id_control_no" class="form-control" value="<?= esc(old('id_control_no', (string) ($planHolder['id_control_no'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="coordinator">Coordinator</label>
                    <input id="coordinator" name="coordinator" class="form-control" value="<?= esc(old('coordinator', (string) ($planHolder['coordinator'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="application_date">Date of Application</label>
                    <input id="application_date" name="application_date" type="date" class="form-control" value="<?= esc($applicationDate) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" class="form-control" value="<?= esc(old('last_name', (string) ($user['last_name'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="first_name">Given Name</label>
                    <input id="first_name" name="first_name" class="form-control" value="<?= esc(old('first_name', (string) ($user['first_name'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="middle_name">Middle Name</label>
                    <input id="middle_name" name="middle_name" class="form-control" value="<?= esc(old('middle_name', (string) ($user['middle_name'] ?? ''))) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="address_no">Address No.</label>
                    <input id="address_no" name="address_no" class="form-control" value="<?= esc(old('address_no', (string) ($planHolder['address_no'] ?? ''))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="address_street">Street</label>
                    <input id="address_street" name="address_street" class="form-control" value="<?= esc(old('address_street', (string) ($planHolder['address_street'] ?? ''))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="city_search">Town/City</label>
                    <div class="psgc-field position-relative">
                        <input
                            id="city_search"
                            type="text"
                            class="form-control"
                            autocomplete="off"
                            placeholder="Search town/city..."
                            value="<?= esc(old('address_city', (string) ($planHolder['address_city'] ?? ''))) ?>"
                        >
                        <input type="hidden" id="address_city" name="address_city" value="<?= esc(old('address_city', (string) ($planHolder['address_city'] ?? ''))) ?>">
                        <input type="hidden" id="city_municipality_code" name="city_municipality_code" value="<?= esc(old('city_municipality_code', (string) ($planHolder['city_municipality_code'] ?? ''))) ?>">
                        <div class="psgc-dropdown list-group shadow-sm d-none"></div>
                    </div>
                    <small class="text-danger d-none psgc-error" data-field="city"></small>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="barangay_search">Barangay</label>
                    <div class="psgc-field position-relative">
                        <input
                            id="barangay_search"
                            type="text"
                            class="form-control"
                            autocomplete="off"
                            placeholder="Select a Town/City first"
                            value="<?= esc(old('address_barangay', (string) ($planHolder['address_barangay'] ?? ''))) ?>"
                            disabled
                        >
                        <input type="hidden" id="address_barangay" name="address_barangay" value="<?= esc(old('address_barangay', (string) ($planHolder['address_barangay'] ?? ''))) ?>">
                        <input type="hidden" id="barangay_code" name="barangay_code" value="<?= esc(old('barangay_code', (string) ($planHolder['barangay_code'] ?? ''))) ?>">
                        <div class="psgc-dropdown list-group shadow-sm d-none"></div>
                    </div>
                    <small class="text-danger d-none psgc-error" data-field="barangay"></small>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="date_of_birth">Date of Birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= esc(old('date_of_birth', (string) ($planHolder['date_of_birth'] ?? ''))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="place_of_birth">Place of Birth</label>
                    <input id="place_of_birth" name="place_of_birth" class="form-control" value="<?= esc(old('place_of_birth', (string) ($planHolder['place_of_birth'] ?? ''))) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="age">Age</label>
                    <input id="age" name="age" type="number" class="form-control" readonly tabindex="-1" data-age-source="#date_of_birth" value="<?= esc((string) (cs_age_from_dob($planHolder['date_of_birth'] ?? null) ?? ($planHolder['age'] ?? ''))) ?>">
                    <div class="form-text">Calculated from date of birth.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-select">
                        <option value="">Select</option>
                        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="civil_status">Civil Status</label>
                    <select id="civil_status" name="civil_status" class="form-select">
                        <option value="">Select</option>
                        <option value="Single" <?= $civilStatus === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= $civilStatus === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Divorced" <?= $civilStatus === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="Widowed" <?= $civilStatus === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="citizenship">Citizenship</label>
                    <input id="citizenship" name="citizenship" class="form-control" value="<?= esc(old('citizenship', (string) ($planHolder['citizenship'] ?? ''))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="height">Height</label>
                    <input id="height" name="height" type="number" step="0.01" class="form-control" value="<?= esc(old('height', (string) ($planHolder['height'] ?? ''))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="weight">Weight</label>
                    <input id="weight" name="weight" type="number" step="0.01" class="form-control" value="<?= esc(old('weight', (string) ($planHolder['weight'] ?? ''))) ?>">
                </div>
                <?php if (! $isEdit): ?>
                <div class="col-md-3">
                    <label class="form-label" for="branch_id">Branch Office</label>
                    <?php if (! empty($wizard['show_branch_select'])): ?>
                        <select id="branch_id" name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php $selectedBranch = (int) old('branch_id', (string) ($wizard['selected_branch_id'] ?? $planHolder['branch_id'] ?? 0)); ?>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int) $branch['branch_id'] ?>" <?= $selectedBranch === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $branch['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="branch_name_display" class="form-control" value="<?= esc((string) ($wizard['branch_name'] ?? '')) ?>" readonly>
                        <input type="hidden" id="branch_id" name="branch_id" value="<?= (int) ($wizard['selected_branch_id'] ?? 0) ?>">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (! empty($wizard['show_account_section'])): ?>
        <?= $this->include('partials/registration/_step_applicant_account') ?>
    <?php endif; ?>

    <div class="cs-panel mb-4">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Spouse Information</h2>
            <p class="cs-panel__note" id="spouseHint" style="display:none;">Required when Civil Status is Married.</p>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="spouse_name">Name of Spouse</label>
                    <input id="spouse_name" name="spouse_name" class="form-control" value="<?= esc(old('spouse_name', (string) ($planHolder['spouse_name'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="spouse_birthdate">Date of Birth (Spouse)</label>
                    <input id="spouse_birthdate" name="spouse_birthdate" type="date" class="form-control" value="<?= esc(old('spouse_birthdate', (string) ($planHolder['spouse_birthdate'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="spouse_occupation">Occupation (Spouse)</label>
                    <input id="spouse_occupation" name="spouse_occupation" class="form-control" value="<?= esc(old('spouse_occupation', (string) ($planHolder['spouse_occupation'] ?? ''))) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="cs-panel">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Contact Info</h2>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="contact_number">Cellphone No.</label>
                    <input id="contact_number" name="contact_number" class="form-control" value="<?= esc(old('contact_number', (string) ($user['contact_number'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" value="<?= esc(old('email', (string) ($user['email'] ?? ''))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="senior_citizen_id">Senior Citizen ID No.</label>
                    <input id="senior_citizen_id" name="senior_citizen_id" class="form-control" value="<?= esc(old('senior_citizen_id', (string) ($planHolder['senior_citizen_id'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="organization_affiliation">Organization Affiliation</label>
                    <input id="organization_affiliation" name="organization_affiliation" class="form-control" value="<?= esc(old('organization_affiliation', (string) ($planHolder['organization_affiliation'] ?? ''))) ?>">
                </div>
            </div>
        </div>
    </div>

    <?php if (! $isEdit): ?>
    <div class="wizard-nav">
        <a href="<?= esc((string) ($wizard['cancel_url'] ?? '#'), 'attr') ?>" class="btn btn-ghost">Cancel</a>
        <button type="button" class="btn btn-primary wizard-next">Next: Government ID <i class="ti ti-arrow-right ms-1"></i></button>
    </div>
    <?php endif; ?>
</div>
