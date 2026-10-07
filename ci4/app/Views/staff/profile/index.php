<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Profile Details</h2></div>
            <div class="cs-panel__body">
                <form method="post" action="<?= site_url($update_url ?? '/staff/profile/update') ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" value="<?= esc($user['first_name'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" value="<?= esc($user['middle_name'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" value="<?= esc($user['last_name'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= esc($user['username'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" value="<?= esc(old('contact_number', $user['contact_number'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Security</h2></div>
            <div class="cs-panel__body">
                <p class="text-muted small">Use this to change your password regularly for account safety.</p>
                <a href="<?= site_url('/change-password') ?>" class="btn btn-ghost w-100">Change Password</a>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
