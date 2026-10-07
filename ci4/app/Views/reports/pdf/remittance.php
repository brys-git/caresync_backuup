<?php
/**
 * Remittance report body. $rows come from the same query as the on-screen
 * table; $totals is a list of ['label', 'amount', 'strong'?] lines built by the
 * controller so the PDF quotes the exact figures the screen shows.
 */
$rows = $rows ?? [];
$totals = $totals ?? [];
?>
<table class="rpt-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Plan Holder</th>
            <th>I.D. Control No.</th>
            <th class="center">Months</th>
            <th>Method</th>
            <th>Reference / OR</th>
            <th>Recorded By</th>
            <th>Status</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= cs_date($row['payment_date'] ?? null) ?></td>
                <td><?= esc(trim((string) ($row['client_first'] ?? '') . ' ' . (string) ($row['client_last'] ?? ''))) ?></td>
                <td><?= esc((string) ($row['unique_identifier'] ?? '-')) ?></td>
                <td class="center"><?= (int) ($row['months_covered'] ?? 1) ?></td>
                <td><?= esc(strtoupper((string) ($row['payment_method'] ?? '-'))) ?></td>
                <td><?= esc((string) (($row['reference_number'] ?? '') !== '' ? $row['reference_number'] : (($row['official_receipt_number'] ?? '') !== '' ? $row['official_receipt_number'] : '-'))) ?></td>
                <td><?= esc(trim((string) ($row['staff_first'] ?? '') . ' ' . (string) ($row['staff_last'] ?? ''))) ?></td>
                <td><?= esc(ucfirst((string) ($row['status'] ?? 'pending'))) ?></td>
                <td class="num"><?= esc(cs_peso($row['amount'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="rpt-empty">No records for the selected filters.</td></tr>
        <?php endif; ?>
    </tbody>
    <?php if ($rows !== [] && $totals !== []): ?>
        <tfoot>
            <?php foreach ($totals as $line): ?>
                <tr class="<?= ! empty($line['strong']) ? 'rpt-total' : '' ?>">
                    <td colspan="8" class="num"><?= esc((string) $line['label']) ?></td>
                    <td class="num"><?= esc(cs_peso($line['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tfoot>
    <?php endif; ?>
</table>
