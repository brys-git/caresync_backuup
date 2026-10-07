<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Route Sheet - CareSync</title>
    <link rel="stylesheet" href="<?= cs_asset_url('assets/css/caresync.css') ?>">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: var(--cs-sans); font-size: 10pt; color: #111; margin: 0; }
        h1 { font-size: 14pt; margin: 0 0 2px; padding-bottom: 4px; border-bottom: 2px solid var(--cs-pine); }
        .cl-meta { font-size: 9pt; color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; font-size: 9pt; text-align: left; vertical-align: top; }
        th { background: #eee; }
        td.num { text-align: right; }
        .cl-sign { min-width: 90px; }
        @media print {
            .no-print { display: none; }
        }
        .no-print { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print</button>
    </div>

    <h1>Collection Route Sheet</h1>
    <div class="cl-meta">As of <?= cs_date($as_of) ?> — generated <?= cs_datetime($generated_at) ?></div>

    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th>Barangay</th>
                <th>Plan No.</th>
                <th>Contact</th>
                <th class="num">Monthly</th>
                <th>Behind</th>
                <th class="num">Amount Due</th>
                <th class="cl-sign">Amount Collected</th>
                <th class="cl-sign">Signature</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9">No accounts to visit for this period.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= esc($row['client_name']) ?></td>
                        <td><?= esc($row['barangay']) ?></td>
                        <td><?= esc($row['plan_number']) ?></td>
                        <td><?= esc($row['contact_number']) ?></td>
                        <td class="num"><?= cs_money($row['monthly_fee']) ?></td>
                        <td><?= $row['months_behind'] > 0 ? esc((string) $row['months_behind']) : '-' ?></td>
                        <td class="num"><?= cs_money($row['amount_due']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
