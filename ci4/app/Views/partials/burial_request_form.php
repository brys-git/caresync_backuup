<?php
/**
 * Shared deceased / contact / documents form for "Claim Regular Casket" and
 * "Avail Package". $mode is 'claim' or 'avail' and only changes the package
 * card, the cost summary rows, the amount-due display and the submit label.
 *
 * Expects: $mode, $package, $base_price, $benefit_credit, $attire_price,
 * $plan_name, $member_name, $member_address, $beneficiary_prefill,
 * $branch_name, $branch_phone, $form_errors.
 */
$e = static fn ($v): string => esc((string) ($v ?? ''));
$money = static fn (float $v): string => '₱' . number_format($v, 2);
$isClaim = $mode === 'claim';
$attireOn = (bool) old('burial_attire');
$dueBase = $isClaim ? 0.0 : max(0.0, (float) $base_price - (float) $benefit_credit);
$dueNow = $dueBase + ($attireOn ? (float) $attire_price : 0.0);
$errorFor = static fn (string $key): string => (string) ($form_errors[$key] ?? '');
$description = trim((string) ($package['description'] ?? ''));
if ($description === '') {
    $description = $isClaim ? 'The standard casket package for Damayan plan holders.' : 'Package details are confirmed by staff when they call.';
}
$relationships = ['Spouse', 'Child', 'Parent', 'Sibling', 'Other relative'];
$currentRelationship = (string) old('relationship_to_deceased', '');
if ($currentRelationship !== '' && ! in_array($currentRelationship, $relationships, true)) {
    array_unshift($relationships, $currentRelationship);
}
$noteZero = $isClaim
    ? 'You pay nothing for the casket. Staff will confirm the details when they call.'
    : 'Staff will confirm the details when they call.';
$noteDue = 'Only the add-on is charged. Staff will confirm payment options when they call — nothing is charged now.';
if (! $isClaim) {
    $noteDue = 'Staff will confirm payment options when they call — nothing is charged now.';
}
$today = date('Y-m-d');
?>
<form id="brForm" method="post" enctype="multipart/form-data" action="<?= site_url('/client/apply-package/' . (int) ($package['package_id'] ?? 0)) ?>" novalidate data-cs-once
      data-base-due="<?= $e(number_format($dueBase, 2, '.', '')) ?>"
      data-attire-price="<?= $e(number_format((float) $attire_price, 2, '.', '')) ?>"
      data-note-zero="<?= $e($noteZero) ?>"
      data-note-due="<?= $e($noteDue) ?>">
    <?= csrf_field() ?>

    <div class="br-layout">
        <div class="br-main">
            <section class="br-card" aria-labelledby="br-pkg-title">
                <div class="br-pkg-head">
                    <div>
                        <p class="br-label"><?= $isClaim ? 'Your entitlement' : 'Package' ?></p>
                        <h2 id="br-pkg-title" class="br-pkg-name"><?= $e($package['package_name'] ?? '—') ?></h2>
                    </div>
                    <p class="br-pkg-value br-num"><?= $money((float) $base_price) ?></p>
                </div>
                <p class="br-muted"><?= $e($description) ?></p>

                <?php if ($isClaim): ?>
                    <div class="br-covered">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
                        <div>
                            <p class="br-covered__title">Fully covered by your Damayan plan</p>
                            <p class="br-covered__sub">Plan: <?= $e($plan_name) ?> · Member <?= $e($member_name) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" id="attireSwitch" role="switch" aria-checked="<?= $attireOn ? 'true' : 'false' ?>" aria-labelledby="attireTitle" class="br-addon<?= $attireOn ? ' is-on' : '' ?>">
                    <span class="br-addon__text">
                        <span id="attireTitle" class="br-addon__title">Add Burial Attire Package</span>
                        <span class="br-muted">Optional add-on · + <?= $money((float) $attire_price) ?></span>
                    </span>
                    <span class="br-switch" aria-hidden="true"></span>
                </button>
                <input type="checkbox" id="burialAttire" name="burial_attire" value="1" class="br-sr" tabindex="-1" aria-hidden="true" <?= $attireOn ? 'checked' : '' ?>>
            </section>

            <section class="br-card" aria-labelledby="br-deceased-title">
                <h2 id="br-deceased-title" class="br-card__title">About the deceased</h2>
                <div class="br-grid">
                    <div class="br-field br-span-2">
                        <label for="deceased_name">Full name <span class="br-req">Required</span></label>
                        <input id="deceased_name" name="deceased_name" class="br-input" type="text" autocomplete="off" value="<?= old('deceased_name') ?>" aria-describedby="err-deceased_name" required>
                        <p class="br-error" id="err-deceased_name" <?= $errorFor('deceased_name') === '' ? 'hidden' : '' ?>><?= $e($errorFor('deceased_name')) ?></p>
                    </div>
                    <div class="br-field br-span-2">
                        <label class="br-check">
                            <input type="checkbox" id="deceased_is_member" class="br-check__input">
                            <span>The deceased is the plan holder<?= $member_name !== '' ? ' (' . $e($member_name) . ')' : '' ?></span>
                        </label>
                    </div>
                    <div class="br-field">
                        <label for="deceased_date_of_death">Date of death</label>
                        <input id="deceased_date_of_death" name="deceased_date_of_death" class="br-input" type="date" max="<?= $today ?>" value="<?= old('deceased_date_of_death') ?>" aria-describedby="err-deceased_date_of_death">
                        <p class="br-error" id="err-deceased_date_of_death" <?= $errorFor('deceased_date_of_death') === '' ? 'hidden' : '' ?>><?= $e($errorFor('deceased_date_of_death')) ?></p>
                    </div>
                    <div class="br-field">
                        <label for="relationship_to_deceased">Your relationship to the deceased</label>
                        <select id="relationship_to_deceased" name="relationship_to_deceased" class="br-input">
                            <option value="">Select one</option>
                            <?php foreach ($relationships as $option): ?>
                                <option value="<?= $e($option) ?>" <?= $currentRelationship === $option ? 'selected' : '' ?>><?= $e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="br-field br-span-2">
                        <label for="deceased_address">Address of the deceased</label>
                        <input id="deceased_address" name="deceased_address" class="br-input" type="text" autocomplete="off" value="<?= old('deceased_address') ?>" aria-describedby="err-deceased_address">
                        <p class="br-error" id="err-deceased_address" <?= $errorFor('deceased_address') === '' ? 'hidden' : '' ?>><?= $e($errorFor('deceased_address')) ?></p>
                    </div>
                </div>
            </section>

            <section class="br-card" aria-labelledby="br-contact-title">
                <h2 id="br-contact-title" class="br-card__title">Contact person</h2>
                <div class="br-grid">
                    <div class="br-field">
                        <label for="beneficiary_name">Contact person's full name <span class="br-req">Required</span></label>
                        <input id="beneficiary_name" name="beneficiary_name" class="br-input" type="text" autocomplete="name" value="<?= old('beneficiary_name', (string) $beneficiary_prefill) ?>" aria-describedby="err-beneficiary_name" required>
                        <p class="br-error" id="err-beneficiary_name" <?= $errorFor('beneficiary_name') === '' ? 'hidden' : '' ?>><?= $e($errorFor('beneficiary_name')) ?></p>
                    </div>
                    <div class="br-field">
                        <label for="beneficiary_contact">Mobile number <span class="br-req">Required</span></label>
                        <input id="beneficiary_contact" name="beneficiary_contact" class="br-input" type="tel" inputmode="tel" autocomplete="tel" placeholder="09XX XXX XXXX" value="<?= old('beneficiary_contact') ?>" aria-describedby="hint-beneficiary_contact err-beneficiary_contact" required>
                        <p class="br-hint" id="hint-beneficiary_contact">11 digits, starts with 09.</p>
                        <p class="br-error" id="err-beneficiary_contact" <?= $errorFor('beneficiary_contact') === '' ? 'hidden' : '' ?>><?= $e($errorFor('beneficiary_contact')) ?></p>
                    </div>
                </div>
            </section>

            <section class="br-card" aria-labelledby="br-docs-title">
                <h2 id="br-docs-title" class="br-card__title">Supporting documents</h2>
                <p class="br-muted br-docs-intro">Photos or PDFs, up to 5 MB each.</p>

                <div class="br-doc" data-doc="death_certificate">
                    <span class="br-doc__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span>
                    <div class="br-doc__body">
                        <div class="br-doc__head">
                            <label for="doc_death_certificate" class="br-doc__title">Death certificate</label>
                            <span class="br-tag br-tag--req">Required</span>
                        </div>
                        <p class="br-hint">Don't have it yet? Submit anyway — staff can help.</p>
                        <div class="br-file" hidden>
                            <span class="br-file__name"></span>
                            <button type="button" class="br-remove" aria-label="Remove death certificate" data-remove="doc_death_certificate">×</button>
                        </div>
                        <input id="doc_death_certificate" name="documents[death_certificate]" type="file" class="br-sr" accept="image/*,application/pdf" aria-describedby="err-documents_death_certificate">
                        <p class="br-error" id="err-documents_death_certificate" <?= $errorFor('documents_death_certificate') === '' ? 'hidden' : '' ?>><?= $e($errorFor('documents_death_certificate')) ?></p>
                    </div>
                    <button type="button" class="br-btn br-btn--ghost br-doc__upload" data-upload="doc_death_certificate">Upload</button>
                </div>

                <div class="br-doc" data-doc="beneficiary_id">
                    <span class="br-doc__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M14 10h4M14 14h3"/></svg></span>
                    <div class="br-doc__body">
                        <div class="br-doc__head">
                            <label for="doc_beneficiary_id" class="br-doc__title">Valid ID of the contact person</label>
                            <span class="br-tag br-tag--req">Required</span>
                        </div>
                        <p class="br-hint">A clear photo of a government-issued ID.</p>
                        <div class="br-file" hidden>
                            <span class="br-file__name"></span>
                            <button type="button" class="br-remove" aria-label="Remove ID" data-remove="doc_beneficiary_id">×</button>
                        </div>
                        <input id="doc_beneficiary_id" name="documents[beneficiary_id]" type="file" class="br-sr" accept="image/*,application/pdf" aria-describedby="err-documents_beneficiary_id">
                        <p class="br-error" id="err-documents_beneficiary_id" <?= $errorFor('documents_beneficiary_id') === '' ? 'hidden' : '' ?>><?= $e($errorFor('documents_beneficiary_id')) ?></p>
                    </div>
                    <button type="button" class="br-btn br-btn--ghost br-doc__upload" data-upload="doc_beneficiary_id">Upload</button>
                </div>

                <div class="br-doc" data-doc="other">
                    <span class="br-doc__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/></svg></span>
                    <div class="br-doc__body">
                        <div class="br-doc__head">
                            <label for="doc_other" class="br-doc__title">Other documents</label>
                            <span class="br-tag">Optional</span>
                        </div>
                        <p class="br-hint">Anything else that helps, such as a permit. You can add more than one.</p>
                        <div class="br-file" hidden>
                            <span class="br-file__name"></span>
                            <button type="button" class="br-remove" aria-label="Remove other documents" data-remove="doc_other">×</button>
                        </div>
                        <input id="doc_other" name="documents[other][]" type="file" multiple class="br-sr" accept="image/*,application/pdf" aria-describedby="err-documents_other">
                        <p class="br-error" id="err-documents_other" <?= $errorFor('documents_other') === '' ? 'hidden' : '' ?>><?= $e($errorFor('documents_other')) ?></p>
                    </div>
                    <button type="button" class="br-btn br-btn--ghost br-doc__upload" data-upload="doc_other">Upload</button>
                </div>
            </section>
        </div>

        <aside class="br-summary" aria-labelledby="br-sum-title">
            <h2 id="br-sum-title" class="br-card__title">Cost summary</h2>
            <dl class="br-rows">
                <div class="br-row">
                    <dt><?= $e($package['package_name'] ?? '—') ?></dt>
                    <dd class="br-num"><?= $money((float) $base_price) ?></dd>
                </div>
                <div class="br-row" id="addonRow" <?= $attireOn ? '' : 'hidden' ?>>
                    <dt>Burial attire add-on</dt>
                    <dd class="br-num" id="addonAmount"><?= $money((float) $attire_price) ?></dd>
                </div>
                <?php if ($isClaim): ?>
                    <div class="br-row br-row--credit">
                        <dt>Damayan entitlement</dt>
                        <dd class="br-num">−<?= $money((float) $base_price) ?></dd>
                    </div>
                <?php elseif ((float) $benefit_credit > 0): ?>
                    <div class="br-row br-row--credit">
                        <dt>Damayan benefit credit</dt>
                        <dd class="br-num">−<?= $money((float) $benefit_credit) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
            <div class="br-divider"></div>
            <p class="br-label">Amount due</p>
            <p id="amountDue" class="<?= $dueNow > 0 ? 'br-amount br-num' : 'br-amount br-amount--covered' ?>"><?= $dueNow > 0 ? $money($dueNow) : 'Fully covered' ?></p>
            <p id="amountNote" class="br-muted br-amount-note"><?= $e($dueNow > 0 ? $noteDue : $noteZero) ?></p>

            <button type="submit" id="submitBtn" class="br-btn br-btn--primary br-btn--block"><?= $isClaim ? 'Submit claim' : 'Submit request' ?></button>
            <a class="br-link" href="<?= site_url('/client/service?tab=packages') ?>">Cancel</a>
            <p class="br-help">Need help now?
                <?php if ($branch_phone !== ''): ?>
                    <a href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $branch_phone)) ?>">Call your branch <?= $e($branch_phone) ?></a>
                <?php else: ?>
                    Contact your branch.
                <?php endif; ?>
            </p>
        </aside>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('brForm');
    if (!form) { return; }

    var today = '<?= $today ?>';
    var attireSwitch = document.getElementById('attireSwitch');
    var attireBox = document.getElementById('burialAttire');
    var addonRow = document.getElementById('addonRow');
    var amountDue = document.getElementById('amountDue');
    var amountNote = document.getElementById('amountNote');
    var submitBtn = document.getElementById('submitBtn');
    var baseDue = Number(form.getAttribute('data-base-due'));
    var attirePrice = Number(form.getAttribute('data-attire-price'));

    function money(value) {
        return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderAmount() {
        var on = attireBox.checked;
        var due = Math.round((baseDue + (on ? attirePrice : 0)) * 100) / 100;
        attireSwitch.setAttribute('aria-checked', on ? 'true' : 'false');
        attireSwitch.classList.toggle('is-on', on);
        addonRow.hidden = !on;
        if (due > 0) {
            amountDue.textContent = money(due);
            amountDue.className = 'br-amount br-num';
            amountNote.textContent = form.getAttribute('data-note-due');
        } else {
            amountDue.textContent = 'Fully covered';
            amountDue.className = 'br-amount br-amount--covered';
            amountNote.textContent = form.getAttribute('data-note-zero');
        }
    }

    attireSwitch.addEventListener('click', function () {
        attireBox.checked = !attireBox.checked;
        renderAmount();
    });

    var memberName = <?= json_encode((string) $member_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var memberAddress = <?= json_encode((string) $member_address, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var memberBox = document.getElementById('deceased_is_member');
    memberBox.addEventListener('change', function () {
        if (!memberBox.checked) { return; }
        if (memberName) { document.getElementById('deceased_name').value = memberName; }
        if (memberAddress) { document.getElementById('deceased_address').value = memberAddress; }
        validateField('deceased_name');
        validateField('deceased_address');
    });

    var rules = {
        deceased_name: function (value) {
            if (value.trim() === '') { return 'Enter the full name of the deceased.'; }
            if (value.trim().length > 150) { return 'Keep the name under 150 characters.'; }
            return '';
        },
        deceased_date_of_death: function (value) {
            if (value && value > today) { return 'The date of death can’t be in the future.'; }
            return '';
        },
        deceased_address: function (value) {
            return value.length > 255 ? 'Keep the address under 255 characters.' : '';
        },
        beneficiary_name: function (value) {
            if (value.trim() === '') { return 'Enter the name of the contact person.'; }
            return '';
        },
        beneficiary_contact: function (value) {
            var digits = value.replace(/[\s-]/g, '');
            return /^09\d{9}$/.test(digits) ? '' : 'Enter an 11-digit mobile number that starts with 09.';
        },
        documents_beneficiary_id: function () {
            return document.getElementById('doc_beneficiary_id').files.length > 0 ? '' : 'Upload a valid ID of the contact person.';
        }
    };

    var inputFor = {
        documents_beneficiary_id: 'doc_beneficiary_id'
    };

    function setError(name, message) {
        var errorEl = document.getElementById('err-' + name);
        var input = document.getElementById(inputFor[name] || name);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.hidden = message === '';
        }
        if (input) {
            if (message) { input.setAttribute('aria-invalid', 'true'); } else { input.removeAttribute('aria-invalid'); }
        }
        return message;
    }

    function validateField(name) {
        var input = document.getElementById(inputFor[name] || name);
        if (!input || !rules[name]) { return ''; }
        var value = input.type === 'file' ? '' : input.value;
        return setError(name, rules[name](value));
    }

    ['deceased_name', 'deceased_date_of_death', 'deceased_address', 'beneficiary_name', 'beneficiary_contact'].forEach(function (name) {
        var input = document.getElementById(name);
        input.addEventListener('blur', function () { validateField(name); });
    });
    document.getElementById('doc_beneficiary_id').addEventListener('change', function () {
        validateField('documents_beneficiary_id');
        renderFile(this);
    });

    function renderFile(input) {
        var row = input.closest('.br-doc');
        var box = row.querySelector('.br-file');
        var upload = row.querySelector('.br-doc__upload');
        var names = Array.prototype.map.call(input.files, function (f) { return f.name; });
        box.hidden = names.length === 0;
        box.querySelector('.br-file__name').textContent = names.join(', ');
        upload.hidden = names.length > 0;
    }

    ['doc_death_certificate', 'doc_other'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () { renderFile(this); });
    });

    document.querySelectorAll('[data-upload]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(button.getAttribute('data-upload')).click();
        });
    });
    document.querySelectorAll('[data-remove]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-remove'));
            input.value = '';
            renderFile(input);
            if (input.id === 'doc_beneficiary_id') { validateField('documents_beneficiary_id'); }
        });
    });

    form.addEventListener('submit', function (event) {
        var names = Object.keys(rules);
        var firstInvalid = null;
        names.forEach(function (name) {
            var message = validateField(name);
            if (message && !firstInvalid) {
                firstInvalid = document.getElementById(inputFor[name] || name);
            }
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting…';
    });

    var firstServerError = form.querySelector('.br-error:not([hidden])');
    if (firstServerError) {
        var serverKey = firstServerError.id.replace('err-', '');
        var target = document.getElementById(inputFor[serverKey] || serverKey);
        if (target) { target.focus(); }
    }

    renderAmount();
})();
</script>
