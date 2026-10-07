<?php
/**
 * Sign-in style page chrome for the short auth pages (forgot / reset password).
 * Child views fill: title (string via $pageTitle), statement, sub, content.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title><?= esc($pageTitle ?? 'CareSync') ?> · CareSync</title>
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
            <h1 class="signin__statement"><?= $this->renderSection('statement') ?></h1>
            <p class="signin__sub"><?= $this->renderSection('sub') ?></p>
        </div>
    </aside>

    <div class="signin__main">
        <div class="signin__form">
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

            <?= $this->renderSection('content') ?>

            <hr class="cs-hairline">
            <p class="cs-muted signin__small mb-0">
                <a href="<?= base_url('login') ?>">Back to sign in</a>
            </p>
        </div>
    </div>
</main>

<script src="<?= base_url('assets/js/caresync.js') ?>" defer></script>
</body>
</html>
