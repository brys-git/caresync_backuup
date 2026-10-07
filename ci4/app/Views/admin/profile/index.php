<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php if (! ($edit_mode ?? false)): ?>
    <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
        <a href="<?= site_url('/admin/profile/edit') ?>" class="btn btn-primary btn-sm">Edit Profile</a>
        <a href="#security-section" class="btn btn-ghost btn-sm">Change Password</a>
    </div>
<?php endif; ?>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Account Information</h2></div>
            <div class="cs-panel__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Username</label>
                        <div class="fw-semibold"><?= esc($user['username'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Email</label>
                        <div class="fw-semibold"><?= esc($user['email'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Full Name</label>
                        <div class="fw-semibold">
                            <?= esc(trim(implode(' ', array_filter([
                                $user['first_name'] ?? '',
                                $user['middle_name'] ?? '',
                                $user['last_name'] ?? '',
                                $user['name_extension'] ?? '',
                            ])))) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Contact Number</label>
                        <div class="fw-semibold"><?= esc($user['contact_number'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Role</label>
                        <div><?= cs_status('active', 'Admin') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted mb-1">Account Status</label>
                        <div><?= cs_status((string) ($user['account_status'] ?? $user['status'] ?? 'active')) ?></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Account Meta</h2></div>
            <div class="cs-panel__body">
                <div class="mb-2">
                    <span class="text-muted d-block">Last Login</span>
                    <strong><?= cs_date($user['last_login'] ?? null, true) ?></strong>
                </div>
                <div>
                    <span class="text-muted d-block">Password</span>
                    <strong>••••••••••••</strong>
                </div>
            </div>
        </section>
    </div>
</div>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Profile Details</h2></div>
    <div class="cs-panel__body">
        <?php if ($edit_mode ?? false): ?>
            <form method="post" action="<?= site_url('/admin/profile/update') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= esc(old('contact_number', $user['contact_number'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= esc(old('first_name', $user['first_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?= esc(old('middle_name', $user['middle_name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= esc(old('last_name', $user['last_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Name Extension</label>
                        <input type="text" name="name_extension" class="form-control" value="<?= esc(old('name_extension', $user['name_extension'] ?? '')) ?>">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="<?= site_url('/admin/profile') ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label text-muted mb-1">First Name</label>
                    <div class="fw-semibold"><?= esc($user['first_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted mb-1">Middle Name</label>
                    <div class="fw-semibold"><?= esc($user['middle_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted mb-1">Last Name</label>
                    <div class="fw-semibold"><?= esc($user['last_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted mb-1">Name Extension</label>
                    <div class="fw-semibold"><?= esc($user['name_extension'] ?? '-') ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cs-panel" id="security-section">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Security</h2></div>
    <div class="cs-panel__body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-8">
                <form method="post" action="<?= site_url('/admin/profile/change-password') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-outline-primary">Change Password</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="bg-light rounded-3 p-3 h-100">
                    <small class="text-muted d-block mb-1">Password Policy</small>
                    <ul class="mb-0 ps-3">
                        <li>At least 8 characters</li>
                        <li>Use a unique password</li>
                        <li>Never share credentials</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
