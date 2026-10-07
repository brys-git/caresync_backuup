<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title>Sign in · CareSync</title>
    <meta name="description" content="CareSync is the member plan and payment records system of Kaagapay. Staff and plan holders sign in here to view plans and payment history.">
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
            <h1 class="signin__statement">A plan paid for today, honoured decades from now.</h1>
            <p class="signin__sub">
                Branch records, collections, and member plans held in one place —
                so a family is never asked to prove what they already paid for.
            </p>
        </div>

        <div class="signin__facts">
            <div>
                <div class="signin__fact-value">Locked</div>
                <div class="signin__fact-label">Package pricing</div>
            </div>
            <div>
                <div class="signin__fact-value">Per branch</div>
                <div class="signin__fact-label">Record isolation</div>
            </div>
            <div>
                <div class="signin__fact-value">Full</div>
                <div class="signin__fact-label">Payment history</div>
            </div>
        </div>
    </aside>

    <div class="signin__main">
        <div class="signin__form">
            <h2 class="signin__title">Sign in</h2>
            <p class="cs-muted mb-4">Use the username or email your branch issued you.</p>

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

            <form method="post" action="<?= base_url('login') ?>" data-cs-once>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="identifier">Username or email</label>
                    <input class="form-control" id="identifier" name="identifier" type="text"
                           value="<?= old('identifier') ?>" required autofocus autocomplete="username">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password"
                           required autocomplete="current-password">
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label signin__small" for="remember">Keep me signed in</label>
                    </div>
                    <a class="signin__small" href="<?= base_url('forgot-password') ?>">Forgot password?</a>
                </div>

                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>

            <hr class="cs-hairline">

            <p class="cs-muted signin__small mb-0">
                Buying a plan for the first time?
                <a href="<?= base_url('register') ?>">Create a plan holder account</a>
            </p>

            <p class="cs-muted signin__small mt-4 mb-0">
                CareSync is the official records system of Kaagapay.<br>
                Questions or account problems? Contact
                <a href="mailto:kaagapay00@gmail.com">kaagapay00@gmail.com</a>.
            </p>
        </div>
    </div>
</main>

<script src="<?= base_url('assets/js/caresync.js') ?>" defer></script>
</body>
</html>
