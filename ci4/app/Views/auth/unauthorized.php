<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title>Access denied · CareSync</title>
    <link rel="icon" href="<?= base_url('assets/favicon_io/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= cs_asset_url('assets/css/caresync.css') ?>">
</head>
<body class="cs-center-screen">
    <section class="cs-panel cs-center-card">
        <div class="cs-panel__body text-center">
            <i class="ti ti-lock cs-center-card__icon" aria-hidden="true"></i>
            <h1 class="h4 mt-3 mb-2">Access denied</h1>
            <p class="cs-muted mb-4">You do not have permission to access this page.</p>
            <a href="<?= base_url('dashboard') ?>" class="btn btn-primary">Go to dashboard</a>
        </div>
    </section>
</body>
</html>
