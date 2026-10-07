<?php
/**
 * Shared account form - used by the public sign-up page (auth/register) and
 * the staff-side "Create Account" pages (System Admin, Branch Admin, Staff),
 * so they always show the same fields. Rendered with
 * view('auth/_account_form', [...]).
 *
 * Expected data:
 *   string $formAction   form target URL
 *   string $submitLabel  submit button text
 *   bool   $withRole     true on the Create Account pages only: adds the Role
 *                        choice (radios, or a fixed line when there is only one)
 *   array  $roleOptions  [role_id => label]   (only when $withRole)
 *   bool   $branchSelectable  default true; false hides the Branch dropdown
 *   array  $branches     active branches      (only when selectable)
 *   array  $branchRoles  role ids that need a branch (only when selectable)
 *   bool   $tempPasswordOption  true on the Create Account pages: adds the
 *                        "Generate a temporary password" checkbox (checked by
 *                        default), which hides the password fields
 */
$withRole    = $withRole ?? false;
// false on pages where the branch is fixed (Branch Admin, Staff): no dropdown is rendered.
$branchSelectable   = $branchSelectable ?? true;
$tempPasswordOption = $tempPasswordOption ?? false;
$roleOptions = $roleOptions ?? [];
$branches    = $branches ?? [];
$oldRole     = (string) old('role_id');
$oldBranch   = (string) old('branch_id');
$branchRoles = $branchRoles ?? [];
// Re-render keeps whatever the creator chose; first visit defaults to checked.
$useTemp     = $tempPasswordOption && (old('use_temp_password') === null || old('use_temp_password') === '1');
$singleRole  = $withRole && count($roleOptions) === 1;
?>
<form method="post" action="<?= esc($formAction) ?>" data-cs-once>
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php if ($withRole && $singleRole): ?>
            <?php foreach ($roleOptions as $roleId => $roleLabel): ?>
                <input type="hidden" name="role_id" value="<?= (int) $roleId ?>">
                <div class="col-12">
                    <span class="form-label d-block">Account type</span>
                    <div class="fw-semibold"><?= esc($roleLabel) ?></div>
                </div>
            <?php endforeach; ?>
        <?php elseif ($withRole): ?>
            <div class="col-12">
                <span class="form-label d-block" id="role-label">Role</span>
                <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-labelledby="role-label">
                    <?php foreach ($roleOptions as $roleId => $roleLabel): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role_id" id="role_<?= (int) $roleId ?>"
                                   value="<?= (int) $roleId ?>" data-needs-branch="<?= in_array((int) $roleId, $branchRoles, true) ? '1' : '0' ?>"
                                   <?= $oldRole === (string) $roleId ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="role_<?= (int) $roleId ?>"><?= esc($roleLabel) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($withRole && $branchSelectable): ?>
            <div class="col-12" id="branch-field" hidden>
                <label class="form-label" for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" class="form-select">
                    <option value="">Select a branch</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['branch_id'] ?>" <?= $oldBranch === (string) $branch['branch_id'] ? 'selected' : '' ?>>
                            <?= esc($branch['branch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-md-6">
            <label class="form-label" for="username">Username</label>
            <input id="username" name="username" type="text" class="form-control" value="<?= old('username') ?>" placeholder="Choose a username" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control" value="<?= old('email') ?>" placeholder="name@example.com" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="first_name">First Name</label>
            <input id="first_name" name="first_name" type="text" class="form-control" value="<?= old('first_name') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_name">Last Name</label>
            <input id="last_name" name="last_name" type="text" class="form-control" value="<?= old('last_name') ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="unique_identifier">Unique Identifier (Optional, for existing membership linking)</label>
            <input id="unique_identifier" name="unique_identifier" type="text" class="form-control" value="<?= old('unique_identifier') ?>" placeholder="Enter existing membership identifier if available">
        </div>
        <div class="col-12">
            <label class="form-label" for="contact_number">Contact Number</label>
            <input id="contact_number" name="contact_number" type="text" class="form-control" value="<?= old('contact_number') ?>" placeholder="09xxxxxxxxx">
        </div>

        <?php if ($tempPasswordOption): ?>
            <div class="col-12">
                <input type="hidden" name="use_temp_password" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="use_temp_password" id="use_temp_password" value="1" <?= $useTemp ? 'checked' : '' ?>>
                    <label class="form-check-label" for="use_temp_password">Generate a temporary password</label>
                </div>
                <div class="cs-muted signin__small">
                    The system creates a one-time password, valid for 7 days. The person must change it the first time they sign in.
                </div>
            </div>
        <?php endif; ?>

        <div class="col-md-6" data-password-field <?= $useTemp ? 'hidden' : '' ?>>
            <label class="form-label" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control" <?= $useTemp ? 'disabled' : 'required' ?>>
        </div>
        <div class="col-md-6" data-password-field <?= $useTemp ? 'hidden' : '' ?>>
            <label class="form-label" for="password_confirm">Confirm Password</label>
            <input id="password_confirm" name="password_confirm" type="password" class="form-control" <?= $useTemp ? 'disabled' : 'required' ?>>
        </div>
    </div>

    <button class="btn btn-primary w-100 mt-4" type="submit"><?= esc($submitLabel) ?></button>
</form>

<?php if ($withRole && ! $singleRole && $branchSelectable): ?>
<script>
(function () {
    var radios = document.querySelectorAll('input[name="role_id"]');
    var field = document.getElementById('branch-field');
    var select = document.getElementById('branch_id');
    function sync() {
        var checked = document.querySelector('input[name="role_id"]:checked');
        var needs = !!checked && checked.getAttribute('data-needs-branch') === '1';
        field.hidden = !needs;
        select.required = needs;
        if (!needs) { select.value = ''; }
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>

<?php if ($tempPasswordOption): ?>
<script>
(function () {
    var box = document.getElementById('use_temp_password');
    var wrappers = document.querySelectorAll('[data-password-field]');
    function sync() {
        wrappers.forEach(function (w) {
            var input = w.querySelector('input');
            w.hidden = box.checked;
            input.disabled = box.checked;
            input.required = !box.checked;
            if (box.checked) { input.value = ''; }
        });
    }
    box.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
