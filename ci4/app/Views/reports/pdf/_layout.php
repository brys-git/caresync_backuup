<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc((string) ($meta['title'] ?? 'Report')) ?> - Kaagapay Mo Karamay</title>
    <link rel="stylesheet" href="<?= esc($css_path, 'attr') ?>">
</head>
<body class="rpt rpt--<?= esc((string) ($orientation ?? 'portrait'), 'attr') ?>">
    <?= view('reports/_pdf_header', ['meta' => $meta]) ?>
    <?= $body /* already-rendered, escaped report body */ ?>
</body>
</html>
