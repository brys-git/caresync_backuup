<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title>Create account · CareSync</title>
    <meta name="description" content="Create a CareSync plan holder account. CareSync is the member plan and payment records system of Kaagapay.">
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
            <h1 class="signin__statement">Register your plan holder account.</h1>
            <p class="signin__sub">
                Create your personal account first, then complete plan holder
                registration after signing in.
            </p>
        </div>

        <div class="signin__facts signin__facts--stacked">
            <div>
                <div class="signin__fact-value">Unique identifier</div>
                <div class="signin__fact-label">Every profile is trackable from day one</div>
            </div>
            <div>
                <div class="signin__fact-value">Post-login step</div>
                <div class="signin__fact-label">Full plan holder registration comes after sign-in</div>
            </div>
            <div>
                <div class="signin__fact-value">Branch-aware</div>
                <div class="signin__fact-label">Records stay isolated to the right branch</div>
            </div>
        </div>
    </aside>

    <div class="signin__main">
        <div class="signin__form">
            <h2 class="signin__title">Register as Plan Holder</h2>
            <p class="cs-muted mb-4">Create your personal account, then complete plan holder registration after signing in.</p>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="ti ti-alert-circle" aria-hidden="true"></i>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (! empty($isFirstAccount)): ?>
                <div class="alert alert-info" role="status">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span>This first account will be the System Administrator.</span>
                </div>
            <?php endif; ?>

            <?= view('auth/_account_form', [
                'formAction'  => base_url('register'),
                'submitLabel' => 'Submit Registration',
            ]) ?>

            <hr class="cs-hairline">

            <p class="cs-muted signin__small mb-0">
                Already have an account?
                <a href="<?= base_url('login') ?>">Back to sign in</a>
            </p>

            <p class="cs-muted signin__small mt-4 mb-0">
                CareSync is the official records system of Kaagapay.<br>
                Questions or account problems? Contact
                <a href="mailto:bry041005@gmail.com">bry041005@gmail.com</a>.
            </p>
        </div>
    </div>
</main>

<script src="<?= base_url('assets/js/caresync.js') ?>" defer></script>
</body>
</html>
