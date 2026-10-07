<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title>Change password · CareSync</title>
    <link rel="icon" href="<?= base_url('assets/favicon_io/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= cs_asset_url('assets/css/caresync.css') ?>">
</head>
<body>
<main class="signin">
    <aside class="signin__aside">
        <div class="signin__wordmark">
            <span class="signin__logo"><img src="<?= cs_asset_url('assets/images/company-logo.png') ?>" alt=""></span>
            <span>CareSync</span>
        </div>

        <div>
            <h1 class="signin__statement">Secure your account with a new password.</h1>
            <p class="signin__sub">
                Temporary passwords are used for secure onboarding. You must
                update it before continuing.
            </p>
        </div>

        <div class="signin__facts signin__facts--stacked">
            <div>
                <div class="signin__fact-value">Step 1</div>
                <div class="signin__fact-label">Temporary credential verification</div>
            </div>
            <div>
                <div class="signin__fact-value">Step 2</div>
                <div class="signin__fact-label">Set a private password</div>
            </div>
            <div>
                <div class="signin__fact-value">Step 3</div>
                <div class="signin__fact-label">Continue to your role dashboard</div>
            </div>
        </div>
    </aside>

    <div class="signin__main">
        <div class="signin__form">
            <h2 class="signin__title"><?= (int) session('must_change_password') === 1 ? 'Set your new password' : 'Change Your Password' ?></h2>
            <p class="cs-muted mb-4">This account uses a temporary password. Set a new password to continue.</p>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="ti ti-alert-circle" aria-hidden="true"></i>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success" role="status">
                    <i class="ti ti-circle-check" aria-hidden="true"></i>
                    <span><?= esc(session()->getFlashdata('success')) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= base_url('change-password') ?>" data-cs-once>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="current_password"><?= (int) session('must_change_password') === 1 ? 'Temporary Password' : 'Current Password' ?></label>
                    <input id="current_password" name="current_password" type="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New Password</label>
                    <input id="new_password" name="new_password" type="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password_confirm">Confirm New Password</label>
                    <input id="new_password_confirm" name="new_password_confirm" type="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Update password</button>
            </form>
        </div>
    </div>
</main>

<script src="<?= base_url('assets/js/caresync.js') ?>" defer></script>
</body>
</html>
