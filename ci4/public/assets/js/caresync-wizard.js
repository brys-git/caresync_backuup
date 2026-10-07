/**
 * Registration wizard step navigation, per-step validation, review-summary
 * rendering, and the Government ID Verification widget bootstrap -
 * extracted verbatim (Prompt 7, Step A) from client/plan_registration.php's
 * own inline <script> so every registration page built on
 * partials/registration/* gets the identical 5-step wizard behavior.
 *
 * Usage:
 *   window.CareSyncWizard.init({
 *     idVerify: {
 *       endpoint: '<?= base_url("api/id-verification/verify") ?>',
 *       resultFieldId: 'government_id_verification_id',
 *       resultFieldKey: 'verification_id',
 *       initialStatus: null,
 *       csrfName: '<?= csrf_token() ?>',
 *       csrfValue: '<?= csrf_hash() ?>',
 *     },
 *   });
 * csrfName/csrfValue must come from the page's own inline bootstrap script
 * (this file is a static asset with no PHP access) - previously this read
 * the CSRF field directly off the DOM via a PHP-injected selector; that
 * selector can't live in an external .js file, so the value itself is
 * passed through config instead.
 *
 * Fixed field/element ids this expects (same ones every
 * partials/registration/_step_*.php renders): the .wizard-panel/
 * .wizard-progress__item pair, #civil_status/#spouse_name/#spouseHint,
 * #city_municipality_code/#barangay_code + .psgc-error[data-field], the
 * .beneficiary-row set, #emergency_contact_number, #branch_id (optional -
 * only present when _step_applicant.php's show_branch_select is on), and
 * whatever id-verification-widget.js itself expects (see that file).
 */
window.CareSyncWizard = (function () {
    function init(config) {
        config = config || {};

        // ==================================================================
        // Wizard step navigation
        // ==================================================================
        const TOTAL_STEPS = 5;
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

            if (currentStep === 5) {
                populateReview();
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function validateStep(step) {
            if (step === 1) {
                const requiredIds = ['last_name', 'first_name', 'branch_id', 'email'];
                for (const id of requiredIds) {
                    const el = document.getElementById(id);
                    if (el && !el.value.trim()) {
                        el.reportValidity ? el.reportValidity() : null;
                        el.focus();
                        return false;
                    }
                }

                const civilStatus = document.getElementById('civil_status').value;
                const spouseName = document.getElementById('spouse_name');
                const spouseHint = document.getElementById('spouseHint');
                if (civilStatus === 'Married' && !spouseName.value.trim()) {
                    spouseHint.style.display = 'block';
                    spouseHint.classList.add('text-danger');
                    spouseName.focus();
                    return false;
                }
                spouseHint.style.display = civilStatus === 'Married' ? 'block' : 'none';
                spouseHint.classList.remove('text-danger');

                if (document.getElementById('city_municipality_code').value.trim() === ''
                    || document.getElementById('barangay_code').value.trim() === '') {
                    document.querySelectorAll('.psgc-error').forEach(function (el) {
                        if (document.getElementById('city_municipality_code').value.trim() === '' && el.dataset.field === 'city') {
                            el.textContent = 'Please select a Town/City from the list.';
                            el.classList.remove('d-none');
                        }
                        if (document.getElementById('barangay_code').value.trim() === '' && el.dataset.field === 'barangay') {
                            el.textContent = 'Please select a Barangay from the list.';
                            el.classList.remove('d-none');
                        }
                    });
                    return false;
                }

                return true;
            }

            if (step === 2) {
                // Verification now runs automatically, so "Next" only needs to
                // block on states that mean the applicant hasn't actually
                // cleared this step yet: nothing attempted, a check still in
                // flight, a stale result being re-checked after an edit, or a
                // hard mismatch. 'verified' and 'needs_review' both proceed -
                // a provider outage or a low-confidence result shouldn't trap
                // the applicant; staff review the rest afterward.
                const hint = document.getElementById('verifyHint');

                if (!idVerificationWidget) {
                    return true;
                }

                if (idVerificationWidget.isRunning()) {
                    hint.textContent = 'Please wait - checking the ID…';
                    hint.classList.remove('text-danger');
                    return false;
                }

                if (idVerificationWidget.isStale()) {
                    hint.textContent = 'Details changed - please wait for the re-check to finish.';
                    hint.classList.add('text-danger');
                    return false;
                }

                const status = idVerificationWidget.getStatus();
                if (status === 'failed') {
                    hint.textContent = 'This ID does not match the details entered. Please correct the details above, or upload a different ID.';
                    hint.classList.add('text-danger');
                    return false;
                }

                if (status !== 'verified' && status !== 'needs_review') {
                    hint.textContent = 'Please upload or capture the ID before continuing.';
                    hint.classList.add('text-danger');
                    return false;
                }

                return true;
            }

            if (step === 3) {
                const rows = document.querySelectorAll('.beneficiary-row');
                const errorEl = document.getElementById('beneficiaryError');
                let hasOne = false;

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const firstName = row.querySelector('.beneficiary-first-name').value.trim();
                    const middleName = row.querySelector('.beneficiary-middle-name').value.trim();
                    const lastName = row.querySelector('.beneficiary-last-name').value.trim();
                    const birthday = row.querySelector('input[type="date"]').value.trim();
                    const relationship = row.querySelector('.beneficiary-relationship').value.trim();
                    const relationshipOther = row.querySelector('.beneficiary-relationship-other').value.trim();

                    const isEmpty = firstName === '' && middleName === '' && lastName === '' && birthday === '' && relationship === '' && relationshipOther === '';
                    if (isEmpty) {
                        continue;
                    }

                    const relationshipComplete = relationship !== '' && (relationship !== 'Other' || relationshipOther !== '');
                    if (firstName === '' || lastName === '' || !relationshipComplete) {
                        errorEl.textContent = 'Each beneficiary row needs a first name, last name, and relationship' + (relationship === 'Other' ? ' (please specify the relationship).' : '.');
                        errorEl.classList.remove('d-none');
                        return false;
                    }

                    hasOne = true;
                }

                if (!hasOne) {
                    errorEl.textContent = 'Please provide at least one beneficiary with a first name, last name, and relationship.';
                    errorEl.classList.remove('d-none');
                    return false;
                }

                errorEl.classList.add('d-none');
                return true;
            }

            if (step === 4) {
                const numberEl = document.getElementById('emergency_contact_number');
                const errorEl = document.getElementById('emergencyContactError');
                const value = numberEl.value.trim();
                if (value !== '' && !/^[0-9+\-()\s]{10,20}$/.test(value)) {
                    errorEl.classList.remove('d-none');
                    numberEl.focus();
                    return false;
                }
                errorEl.classList.add('d-none');
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

        function fieldValue(id) {
            const el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        function row(label, value) {
            return '<div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">' + label + '</span><span class="fw-medium text-end">' + (value || '&mdash;') + '</span></div>';
        }

        function esc(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function populateReview() {
            // Branch Admin/Staff render #branch_id as a plain hidden input
            // alongside a read-only #branch_name_display text field (not a
            // picker) - only a real <select> has .selectedOptions.
            const branchEl = document.getElementById('branch_id');
            const branchNameDisplay = document.getElementById('branch_name_display');
            const branchLabel = branchEl && branchEl.tagName === 'SELECT'
                ? (branchEl.selectedOptions[0] ? branchEl.selectedOptions[0].text : '')
                : (branchNameDisplay ? branchNameDisplay.value : '');
            // Only present when 2+ approved plans exist - see
            // _step_applicant.php's "Choose a Plan" panel.
            const checkedPlan = document.querySelector('input[name="program_id"]:checked');
            const planRow = checkedPlan
                ? row('Plan', esc(checkedPlan.closest('.cs-plan-option').querySelector('strong').textContent.trim()))
                : '';
            const applicantHtml = [
                planRow,
                row('Name', esc([fieldValue('last_name'), fieldValue('first_name'), fieldValue('middle_name')].filter(Boolean).join(', '))),
                row('Address', esc([fieldValue('address_no'), fieldValue('address_street'), fieldValue('address_barangay'), fieldValue('address_city')].filter(Boolean).join(', '))),
                row('Date of Birth', esc(fieldValue('date_of_birth'))),
                row('Gender', esc(fieldValue('gender'))),
                row('Civil Status', esc(fieldValue('civil_status'))),
                row('Citizenship', esc(fieldValue('citizenship'))),
                row('Branch Office', esc(branchLabel)),
                row('Cellphone', esc(fieldValue('contact_number'))),
                row('Email', esc(fieldValue('email'))),
            ].join('');
            document.getElementById('reviewApplicant').innerHTML = applicantHtml;

            const idTypeSelect = document.getElementById('id_type');
            const idTypeLabel = idTypeSelect && idTypeSelect.selectedOptions[0] ? idTypeSelect.selectedOptions[0].text : 'Not selected';
            const currentIdStatus = idVerificationWidget ? idVerificationWidget.getStatus() : null;
            const statusText = currentIdStatus
                ? currentIdStatus.charAt(0).toUpperCase() + currentIdStatus.slice(1).replace('_', ' ')
                : 'Not yet verified';
            document.getElementById('reviewIdVerification').innerHTML = row('ID Type', esc(idTypeLabel)) + row('Status', esc(statusText));

            const beneficiaryRows = [];
            document.querySelectorAll('.beneficiary-row').forEach(function (rowEl) {
                const firstName = rowEl.querySelector('.beneficiary-first-name').value.trim();
                const middleName = rowEl.querySelector('.beneficiary-middle-name').value.trim();
                const lastName = rowEl.querySelector('.beneficiary-last-name').value.trim();
                const relationship = rowEl.querySelector('.beneficiary-relationship').value.trim();
                const relationshipOther = rowEl.querySelector('.beneficiary-relationship-other').value.trim();

                if (firstName === '' && lastName === '') {
                    return;
                }

                const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
                const relationshipLabel = relationship === 'Other' ? relationshipOther : relationship;
                beneficiaryRows.push(row(esc(fullName), esc(relationshipLabel)));
            });
            document.getElementById('reviewBeneficiaries').innerHTML = beneficiaryRows.length ? beneficiaryRows.join('') : '<span class="text-muted">None added.</span>';

            document.getElementById('reviewEmergency').innerHTML = [
                row('Name', esc(fieldValue('emergency_contact_name'))),
                row('Contact #', esc(fieldValue('emergency_contact_number'))),
                row('Address', esc(fieldValue('emergency_contact_address'))),
            ].join('');
        }

        const civilStatusEl = document.getElementById('civil_status');
        if (civilStatusEl) {
            civilStatusEl.addEventListener('change', function () {
                document.getElementById('spouseHint').style.display = this.value === 'Married' ? 'block' : 'none';
            });
        }

        // Beneficiary "Other" relationship - reveal the free-text box only for
        // rows where it's actually needed. Delegated on the tbody once rather
        // than per-select, since rows are static (10 pre-rendered, not added
        // dynamically).
        document.querySelectorAll('.beneficiary-relationship').forEach(function (select) {
            select.addEventListener('change', function () {
                const otherInput = select.closest('.beneficiary-row').querySelector('.beneficiary-relationship-other');
                otherInput.classList.toggle('d-none', select.value !== 'Other');
                if (select.value !== 'Other') {
                    otherInput.value = '';
                }
            });
        });

        // ==================================================================
        // Staff-mode Account section (_step_applicant_account.php) - the
        // "Create a new account" / "Link to an existing account" toggle.
        // No-ops entirely when the page has neither radio (self mode, or a
        // page that doesn't show_account_section).
        // ==================================================================
        const modeNew = document.getElementById('mode_new');
        const modeExisting = document.getElementById('mode_existing');
        if (modeNew && modeExisting) {
            const existingUsers = config.existingUsers || [];
            const emailField = document.getElementById('email');
            const firstNameField = document.getElementById('first_name');
            const middleNameField = document.getElementById('middle_name');
            const lastNameField = document.getElementById('last_name');
            const contactField = document.getElementById('contact_number');
            const helpEl = document.getElementById('accountModeHelp');

            function isExistingMode() {
                return modeExisting.checked;
            }

            function autofillByEmail() {
                if (!isExistingMode() || !emailField) {
                    return;
                }
                const email = (emailField.value || '').trim().toLowerCase();
                if (!email) {
                    if (helpEl) helpEl.textContent = 'Enter the registered email and details auto-fill.';
                    return;
                }
                const matched = existingUsers.find(function (u) { return u.email === email; });
                if (!matched) {
                    if (helpEl) helpEl.textContent = 'No existing account found for this email.';
                    return;
                }
                if (firstNameField) firstNameField.value = matched.first_name || '';
                if (middleNameField) middleNameField.value = matched.middle_name || '';
                if (lastNameField) lastNameField.value = matched.last_name || '';
                if (contactField) contactField.value = matched.contact_number || '';
                if (helpEl) helpEl.textContent = 'Existing account found. Details were auto-filled.';
            }

            // Only clears/autofills on a user-triggered mode switch - never
            // on initial page load, so a failed-validation repopulation
            // (old() values already in the fields) is never wiped out just
            // because the radio's own old() default happens to read 'new'.
            function syncAccountMode(userTriggered) {
                const existing = isExistingMode();
                [firstNameField, middleNameField, lastNameField, contactField].forEach(function (el) {
                    if (el) el.readOnly = existing;
                });
                if (existing) {
                    if (helpEl) helpEl.textContent = 'Enter the registered email and details auto-fill.';
                    if (userTriggered) autofillByEmail();
                } else {
                    if (userTriggered) {
                        [firstNameField, middleNameField, lastNameField, contactField].forEach(function (el) {
                            if (el) el.value = '';
                        });
                    }
                    if (helpEl) helpEl.textContent = "Fill in the applicant's name and details below, then their email.";
                }
            }

            modeNew.addEventListener('change', function () { syncAccountMode(true); });
            modeExisting.addEventListener('change', function () { syncAccountMode(true); });
            if (emailField) {
                emailField.addEventListener('input', autofillByEmail);
                emailField.addEventListener('change', autofillByEmail);
            }
            syncAccountMode(false);
        }

        // ==================================================================
        // Government ID Verification (Step 2) - shared widget, see
        // public/assets/js/id-verification-widget.js.
        // ==================================================================
        const idVerify = config.idVerify || {};
        const idVerificationWidget = initIdVerificationWidget({
            endpoint: idVerify.endpoint,
            csrfName: idVerify.csrfName,
            csrfValue: idVerify.csrfValue,
            getIdentity: function () {
                return {
                    first_name: fieldValue('first_name'),
                    middle_name: fieldValue('middle_name'),
                    last_name: fieldValue('last_name'),
                    date_of_birth: fieldValue('date_of_birth'),
                    gender: fieldValue('gender'),
                };
            },
            resultFieldId: idVerify.resultFieldId,
            resultFieldKey: idVerify.resultFieldKey,
            initialStatus: idVerify.initialStatus || null,
        });

        showStep(1);

        return { showStep: showStep };
    }

    return { init: init };
})();
