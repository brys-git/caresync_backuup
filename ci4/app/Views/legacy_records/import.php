<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are surfaced as toasts by layouts/_shell.php. ?>

<div class="mb-3 text-end">
    <a class="btn btn-ghost btn-sm" href="<?= esc($base) ?>">Back to Existing member records</a>
</div>

<?php if ($preview === null): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">1. Get the template</h2></div>
    <div class="cs-panel__body">
        <p>One row per past payment. The same file can hold payments for many clients - each row names its client by <strong>plan number</strong>.</p>
        <div class="cs-tablewrap mb-3"><table class="cs-table cs-table--compact">
            <thead><tr><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody><tr>
                <td>PH-1-202601011200-ABC123</td><td>2024-03-15</td><td>240.00</td><td>3</td><td>2024</td><td>1</td><td>OR-10456</td><td>branch</td><td>Juan Collector</td><td>Paid at the branch</td>
            </tr></tbody>
        </table></div>
        <ul class="cs-muted">
            <li><strong>plan_number</strong>, <strong>payment_date</strong> (YYYY-MM-DD), <strong>amount</strong>, <strong>period_month</strong> (1-12) and <strong>period_year</strong> are required.</li>
            <li><strong>payment_method</strong> is one of: branch, collector, gcash, bank (blank = branch). <strong>months_covered</strong> defaults to 1.</li>
        </ul>
        <a class="btn btn-outline-primary btn-sm" href="<?= esc($base . '/import/template') ?>"><i class="ti ti-download" aria-hidden="true"></i> Download CSV template</a>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">2. Upload and preview</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/import/preview') ?>" enctype="multipart/form-data" data-cs-once>
            <?= csrf_field() ?>
            <label class="form-label" for="import_file">CSV or Excel (.xlsx) file, up to 10 MB</label>
            <input class="form-control" type="file" id="import_file" name="import_file" accept=".csv,.xlsx" required>
            <button class="btn btn-primary mt-3" type="submit">Preview</button>
            <p class="cs-muted mt-2 mb-0">Nothing is saved yet - you will review every row first.</p>
        </form>
    </div>
</section>

<?php else: ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Preview</h2></div>
    <div class="cs-panel__body">
        <div class="d-flex flex-wrap gap-4 mb-3">
            <div><div class="cs-muted">Valid rows</div><div class="fs-4 fw-semibold text-success"><?= (int) $preview['valid'] ?></div></div>
            <div><div class="cs-muted">Rows with errors</div><div class="fs-4 fw-semibold <?= $preview['invalid'] ? 'text-danger' : '' ?>"><?= (int) $preview['invalid'] ?></div></div>
            <div><div class="cs-muted">Total of valid rows</div><div class="fs-4 fw-semibold"><?= cs_money($preview['total_amount']) ?></div></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($preview['valid'] > 0): ?>
                <form method="post" action="<?= esc($base . '/import/commit') ?>" class="d-inline" data-cs-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= esc($preview['token']) ?>">
                    <button class="btn btn-primary" type="submit" data-cs-confirm="Save <?= (int) $preview['valid'] ?> valid row(s)? <?= $preview['invalid'] ? 'The ' . (int) $preview['invalid'] . ' row(s) with errors will be skipped.' : '' ?>">
                        Save <?= (int) $preview['valid'] ?> valid row(s)
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($preview['invalid'] > 0): ?>
                <a class="btn btn-outline-primary" href="<?= esc($base . '/import/errors/' . $preview['token']) ?>"><i class="ti ti-download" aria-hidden="true"></i> Download the error rows to fix</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= esc($base . '/import') ?>">Cancel</a>
        </div>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
            <thead><tr><th>Row</th><th>Client</th><th>Paid on</th><th>Period</th><th class="text-end">Amount</th><th>OR no.</th><th>Method</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($preview['rows'] as $r): $in = $r['input']; ?>
                <tr class="<?= $r['errors'] ? 'table-danger' : '' ?>">
                    <td><?= (int) $r['line'] ?></td>
                    <td><?= esc($r['client'] ?: ($in['plan_number'] ?? '')) ?></td>
                    <td><?= esc((string) ($in['payment_date'] ?? '')) ?></td>
                    <td><?= esc(($in['period_month'] ?? '') . '/' . ($in['period_year'] ?? '')) ?></td>
                    <td class="text-end"><?= esc((string) ($in['amount'] ?? '')) ?></td>
                    <td><?= esc((string) ($in['or_number'] ?? '')) ?></td>
                    <td><?= esc((string) ($in['payment_method'] ?? '')) ?></td>
                    <td>
                        <?php if ($r['errors']): ?>
                            <span class="text-danger"><?= esc(implode('; ', $r['errors'])) ?></span>
                        <?php else: ?>
                            <?= cs_status('verified', 'Valid') ?>
                            <?php foreach ($r['warnings'] as $w): ?><div class="text-warning small"><?= esc($w) ?></div><?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
