<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$e = static fn ($v): string => esc((string) ($v ?? ''));
$fmtDate = static fn (?string $d): ?string => ($d !== null && $d !== '' && strtotime($d) !== false) ? date('j M Y', strtotime($d)) : null;
$fmtMobile = static function (?string $v): ?string {
    $digits = preg_replace('/\D+/', '', (string) $v);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 4);
    }

    return trim((string) $v);
};
$notProvided = '<span class="pf-empty">Not provided</span>';
$fullName = trim(implode(' ', array_filter([
    trim((string) ($user['first_name'] ?? '')),
    trim((string) ($user['middle_name'] ?? '')),
    trim((string) ($user['last_name'] ?? '')),
], static fn ($part) => $part !== '')));
$fullName = $fullName !== '' ? $fullName : 'Your profile';
$memberSince = $fmtDate($plan['start_date'] ?? null);
$membershipState = strtolower((string) ($plan['membership_state'] ?? 'active'));
$statusTone = match ($membershipState) {
    'active' => 'ok',
    'suspended' => 'bad',
    default => 'warn',
};
$address = implode(', ', array_filter([
    trim((string) ($plan_holder['address_barangay'] ?? '')),
    trim((string) ($plan_holder['address_city'] ?? '')),
], static fn ($part) => $part !== ''));
$passwordChanged = $fmtDate($user['password_changed_at'] ?? null);
$passwordError = static fn (string $key): string => (string) (($form_errors[$key] ?? '') ?: '');
$firstError = null;
foreach (['current_password', 'new_password', 'confirm_password'] as $field) {
    if ($passwordError($field) !== '' && $firstError === null) {
        $firstError = $field;
    }
}
$initials = cs_initials($fullName);
$mobile = $fmtMobile($user['contact_number'] ?? null);
?>
<link rel="stylesheet" href="<?= cs_asset_url('assets/css/client-profile.css') ?>">

<div class="pf-page">
    <h1 class="pf-title">My profile</h1>

    <section class="pf-card pf-banner" aria-labelledby="pf-name">
        <div class="pf-avatar-wrap">
            <?php if (! empty($photo_url)): ?>
                <img class="pf-avatar" src="<?= $e($photo_url) ?>" alt="Your profile photo">
            <?php else: ?>
                <span class="pf-avatar pf-avatar--initials" aria-hidden="true"><?= $e($initials) ?></span>
            <?php endif; ?>
            <button type="button" class="pf-camera" id="photoTrigger" aria-label="Change profile photo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </button>
        </div>

        <form id="photoForm" method="post" action="<?= base_url('client/profile/photo') ?>" enctype="multipart/form-data" class="pf-photo-form" hidden>
            <?= csrf_field() ?>
            <label for="photoInput" class="pf-sr">Profile photo</label>
            <input id="photoInput" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="pf-sr">
        </form>

        <div class="pf-identity">
            <h2 id="pf-name" class="pf-name"><?= $e($fullName) ?></h2>
            <p class="pf-muted">Plan holder<?= $memberSince !== null ? ' · Member since ' . $e($memberSince) : '' ?></p>
            <span class="pf-status pf-status--<?= $statusTone ?>"><?= $e(ucfirst($membershipState)) ?></span>
            <p class="pf-error" id="photoError" role="alert" hidden></p>
        </div>

        <a class="pf-link" href="<?= base_url('client/membership') ?>">View my plan <span aria-hidden="true">→</span></a>
    </section>

    <section class="pf-card" id="personal" aria-labelledby="pf-personal-title">
        <header class="pf-card__head">
            <div>
                <h2 id="pf-personal-title" class="pf-card__title">Personal information</h2>
                <p class="pf-muted">Used for your membership and claims.</p>
            </div>
            <?php if (! $edit_mode): ?>
                <a class="pf-btn pf-btn--ghost" href="<?= base_url('client/profile?edit=1') ?>#personal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    Edit
                </a>
            <?php endif; ?>
        </header>

        <?php if ($edit_mode): ?>
            <form method="post" action="<?= base_url('client/profile/update') ?>" class="pf-form">
                <?= csrf_field() ?>
                <div class="pf-grid">
                    <div class="pf-field">
                        <label for="first_name">First name <span class="pf-req">Required</span></label>
                        <input id="first_name" name="first_name" class="pf-input" required value="<?= $e(old('first_name', (string) ($user['first_name'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="last_name">Last name <span class="pf-req">Required</span></label>
                        <input id="last_name" name="last_name" class="pf-input" required value="<?= $e(old('last_name', (string) ($user['last_name'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="email">Email <span class="pf-req">Required</span></label>
                        <input id="email" name="email" type="email" class="pf-input" required value="<?= $e(old('email', (string) ($user['email'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="contact_number">Mobile number</label>
                        <input id="contact_number" name="contact_number" type="tel" inputmode="tel" class="pf-input" value="<?= $e(old('contact_number', (string) ($user['contact_number'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="address_barangay">Barangay</label>
                        <input id="address_barangay" name="address_barangay" class="pf-input" value="<?= $e(old('address_barangay', (string) ($plan_holder['address_barangay'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="address_city">City or town</label>
                        <input id="address_city" name="address_city" class="pf-input" value="<?= $e(old('address_city', (string) ($plan_holder['address_city'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="civil_status">Civil status</label>
                        <input id="civil_status" name="civil_status" class="pf-input" value="<?= $e(old('civil_status', (string) ($plan_holder['civil_status'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="citizenship">Citizenship</label>
                        <input id="citizenship" name="citizenship" class="pf-input" value="<?= $e(old('citizenship', (string) ($plan_holder['citizenship'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="place_of_birth">Place of birth</label>
                        <input id="place_of_birth" name="place_of_birth" class="pf-input" value="<?= $e(old('place_of_birth', (string) ($plan_holder['place_of_birth'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="height">Height (cm)</label>
                        <input id="height" name="height" type="number" inputmode="decimal" min="0" step="0.01" class="pf-input" value="<?= $e(old('height', (string) ($plan_holder['height'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="weight">Weight (kg)</label>
                        <input id="weight" name="weight" type="number" inputmode="decimal" min="0" step="0.01" class="pf-input" value="<?= $e(old('weight', (string) ($plan_holder['weight'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="senior_citizen_id">Senior Citizen ID no.</label>
                        <input id="senior_citizen_id" name="senior_citizen_id" class="pf-input" value="<?= $e(old('senior_citizen_id', (string) ($plan_holder['senior_citizen_id'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="organization_affiliation">Organization affiliation</label>
                        <input id="organization_affiliation" name="organization_affiliation" class="pf-input" value="<?= $e(old('organization_affiliation', (string) ($plan_holder['organization_affiliation'] ?? ''))) ?>">
                    </div>
                    <?php if (strtolower(trim((string) ($plan_holder['civil_status'] ?? ''))) !== 'single'): ?>
                        <div class="pf-field">
                            <label for="spouse_name">Spouse name</label>
                            <input id="spouse_name" name="spouse_name" class="pf-input" value="<?= $e(old('spouse_name', (string) ($plan_holder['spouse_name'] ?? ''))) ?>">
                        </div>
                        <div class="pf-field">
                            <label for="spouse_birthdate">Spouse birthdate</label>
                            <input id="spouse_birthdate" name="spouse_birthdate" type="date" class="pf-input" value="<?= $e(old('spouse_birthdate', (string) ($plan_holder['spouse_birthdate'] ?? ''))) ?>">
                        </div>
                        <div class="pf-field">
                            <label for="spouse_occupation">Spouse occupation</label>
                            <input id="spouse_occupation" name="spouse_occupation" class="pf-input" value="<?= $e(old('spouse_occupation', (string) ($plan_holder['spouse_occupation'] ?? ''))) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="pf-field">
                        <label for="emergency_contact_name">Emergency contact name</label>
                        <input id="emergency_contact_name" name="emergency_contact_name" class="pf-input" value="<?= $e(old('emergency_contact_name', (string) ($plan_holder['emergency_contact_name'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="emergency_contact_number">Emergency contact number</label>
                        <input id="emergency_contact_number" name="emergency_contact_number" type="tel" inputmode="tel" class="pf-input" value="<?= $e(old('emergency_contact_number', (string) ($plan_holder['emergency_contact_number'] ?? ''))) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="emergency_contact_address">Emergency contact address</label>
                        <input id="emergency_contact_address" name="emergency_contact_address" class="pf-input" value="<?= $e(old('emergency_contact_address', (string) ($plan_holder['emergency_contact_address'] ?? ''))) ?>">
                    </div>
                </div>
                <div class="pf-actions">
                    <a class="pf-btn pf-btn--text" href="<?= base_url('client/profile') ?>#personal">Cancel</a>
                    <button type="submit" class="pf-btn pf-btn--primary">Save changes</button>
                </div>
            </form>
        <?php else: ?>
            <dl class="pf-rows">
                <div class="pf-row"><dt>Full name</dt><dd><?= $e($fullName) ?></dd></div>
                <div class="pf-row"><dt>Email</dt><dd><?= $e($user['email'] ?? '') !== '' ? $e($user['email']) : $notProvided ?></dd></div>
                <div class="pf-row"><dt>Mobile number</dt><dd><?= $mobile !== null ? $e($mobile) : $notProvided ?></dd></div>
                <div class="pf-row"><dt>Address</dt><dd><?= $address !== '' ? $e($address) : $notProvided ?></dd></div>
                <div class="pf-row"><dt>Civil status</dt><dd><?= trim((string) ($plan_holder['civil_status'] ?? '')) !== '' ? $e($plan_holder['civil_status']) : $notProvided ?></dd></div>
            </dl>
        <?php endif; ?>
    </section>

    <section class="pf-card" id="security" aria-labelledby="pf-security-title">
        <header class="pf-card__head">
            <div>
                <h2 id="pf-security-title" class="pf-card__title">Sign-in &amp; security</h2>
                <p class="pf-muted">How you log in to CareSync.</p>
            </div>
        </header>

        <?php if ($password_updated): ?>
            <p class="pf-success" role="status">Password updated</p>
        <?php endif; ?>

        <dl class="pf-rows">
            <div class="pf-row"><dt>Username</dt><dd><?= $e($user['username'] ?? '') ?></dd></div>
            <div class="pf-row pf-row--action">
                <dt>Password</dt>
                <dd class="pf-row__value">
                    <span class="pf-muted"><?= $passwordChanged !== null ? 'Last changed <span id="lastChanged">' . $e($passwordChanged) . '</span>' : '' ?></span>
                    <button type="button" class="pf-btn pf-btn--ghost" id="pwToggle" aria-expanded="<?= $password_open ? 'true' : 'false' ?>" aria-controls="pwForm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <span id="pwToggleLabel"><?= $password_open ? 'Close' : 'Change password' ?></span>
                    </button>
                </dd>
            </div>
        </dl>

        <form id="pwForm" method="post" action="<?= base_url('client/profile/update-password') ?>" class="pf-pw" novalidate data-username="<?= $e($user['username'] ?? '') ?>" <?= $password_open ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <div class="pf-field">
                <label for="current_password">Current password <span class="pf-req">Required</span></label>
                <input id="current_password" name="current_password" type="password" class="pf-input" autocomplete="current-password" required aria-describedby="err-current_password" <?= $passwordError('current_password') !== '' ? 'aria-invalid="true"' : '' ?>>
                <p class="pf-error" id="err-current_password" <?= $passwordError('current_password') === '' ? 'hidden' : '' ?>><?= $e($passwordError('current_password')) ?></p>
            </div>
            <div class="pf-field">
                <label for="new_password">New password <span class="pf-req">Required</span></label>
                <input id="new_password" name="new_password" type="password" class="pf-input" autocomplete="new-password" minlength="8" required aria-describedby="hint-new_password err-new_password" <?= $passwordError('new_password') !== '' ? 'aria-invalid="true"' : '' ?>>
                <p class="pf-hint" id="hint-new_password">At least 8 characters. It can't contain your username, and it must be different from your current password.</p>
                <p class="pf-error" id="err-new_password" <?= $passwordError('new_password') === '' ? 'hidden' : '' ?>><?= $e($passwordError('new_password')) ?></p>
            </div>
            <div class="pf-field">
                <label for="confirm_password">Confirm new password <span class="pf-req">Required</span></label>
                <input id="confirm_password" name="confirm_password" type="password" class="pf-input" autocomplete="new-password" required aria-describedby="err-confirm_password" <?= $passwordError('confirm_password') !== '' ? 'aria-invalid="true"' : '' ?>>
                <p class="pf-error" id="err-confirm_password" <?= $passwordError('confirm_password') === '' ? 'hidden' : '' ?>><?= $e($passwordError('confirm_password')) ?></p>
            </div>
            <div class="pf-actions">
                <button type="button" class="pf-btn pf-btn--text" id="pwCancel">Cancel</button>
                <button type="submit" class="pf-btn pf-btn--primary">Update password</button>
            </div>
        </form>
    </section>

    <section class="pf-card pf-signout" aria-labelledby="pf-signout-title">
        <div>
            <h2 id="pf-signout-title" class="pf-card__title">Sign out</h2>
            <p class="pf-muted">Sign out of CareSync on this device.</p>
        </div>
        <a class="pf-btn pf-btn--danger" href="<?= base_url('logout') ?>">Sign out</a>
    </section>
</div>

<script>
(function () {
    var MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    var trigger = document.getElementById('photoTrigger');
    var input = document.getElementById('photoInput');
    var photoForm = document.getElementById('photoForm');
    var photoError = document.getElementById('photoError');

    trigger.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) { return; }
        var problem = '';
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            problem = 'Choose a JPG, PNG, or WebP image.';
        } else if (file.size > MAX_PHOTO_BYTES) {
            problem = 'Photos must be 5 MB or smaller.';
        }
        if (problem) {
            photoError.textContent = problem;
            photoError.hidden = false;
            input.value = '';
            return;
        }
        photoForm.submit();
    });

    var toggle = document.getElementById('pwToggle');
    var label = document.getElementById('pwToggleLabel');
    var form = document.getElementById('pwForm');
    var setOpen = function (open) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        label.textContent = open ? 'Close' : 'Change password';
        form.hidden = !open;
        if (open) { document.getElementById('current_password').focus(); }
    };
    var clearForm = function () {
        ['current_password', 'new_password', 'confirm_password'].forEach(function (id) {
            var field = document.getElementById(id);
            field.value = '';
            field.removeAttribute('aria-invalid');
            setError(id, '');
        });
    };
    function setError(id, message) {
        var el = document.getElementById('err-' + id);
        var field = document.getElementById(id);
        el.textContent = message;
        el.hidden = message === '';
        if (message) { field.setAttribute('aria-invalid', 'true'); } else { field.removeAttribute('aria-invalid'); }
        return message;
    }

    toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') !== 'true';
        if (!open) { clearForm(); }
        setOpen(open);
    });
    document.getElementById('pwCancel').addEventListener('click', function () {
        clearForm();
        setOpen(false);
    });

    var rules = {
        current_password: function (value) {
            return value === '' ? 'Enter your current password.' : '';
        },
        new_password: function (value) {
            var current = document.getElementById('current_password').value;
            var username = form.getAttribute('data-username') || '';
            if (value.length < 8) { return 'New password must be at least 8 characters.'; }
            if (value === current) { return 'New password must be different from your current password.'; }
            if (username !== '' && value.toLowerCase().indexOf(username.toLowerCase()) !== -1) { return 'New password cannot contain your username.'; }
            return '';
        },
        confirm_password: function (value) {
            if (value === '') { return 'Confirm your new password.'; }
            return value === document.getElementById('new_password').value ? '' : 'Passwords do not match.';
        }
    };
    function validate(id) {
        return setError(id, rules[id](document.getElementById(id).value));
    }
    Object.keys(rules).forEach(function (id) {
        document.getElementById(id).addEventListener('blur', function () { validate(id); });
    });
    form.addEventListener('submit', function (event) {
        var first = null;
        Object.keys(rules).forEach(function (id) {
            if (validate(id) && !first) { first = document.getElementById(id); }
        });
        if (first) {
            event.preventDefault();
            first.focus();
        }
    });

    var serverError = <?= json_encode($firstError) ?>;
    if (serverError) {
        document.getElementById(serverError).focus();
    } else if (<?= $password_open ? 'true' : 'false' ?>) {
        document.getElementById('current_password').focus();
    }
})();
</script>
<?= $this->endSection() ?>
