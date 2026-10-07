<?php
/**
 * Client statement body (same header/layout as the other reports).
 * Numbers come from ClientPlanSummary; the payment list is the one on the
 * client page, with the same filters applied.
 */
$summary  = $summary ?? null;
$payments = $payments ?? ['filtered' => [], 'totals' => ['verified' => 0, 'pending' => 0, 'rejected' => 0]];
$methods  = \App\Services\ClientDetailService::METHODS;
$rows     = $payments['filtered'];
?>
<h2 style="margin:0 0 4px;font-size:14px;"><?= esc((string) ($client_name ?? '')) ?></h2>
<p class="rpt-note" style="margin:0 0 10px;">Plan holder #<?= esc((string) (($client['unique_identifier'] ?? '') ?: ($client['plan_holder_id'] ?? ''))) ?></p>

<?php if ($summary !== null): ?>
    <table class="rpt-table" style="margin-bottom:12px;">
        <tbody>
            <tr>
                <td><strong>Status</strong><br><?= esc((string) $summary['status_label']) ?></td>
                <td><strong>Contract</strong><br><?= esc(cs_peso($summary['contract_amount'])) ?></td>
                <td><strong>Months paid</strong><br><?= (int) $summary['months_paid'] ?> of <?= (int) $summary['total_months'] ?></td>
                <td><strong>Total paid</strong><br><?= esc(cs_peso($summary['total_paid'])) ?></td>
                <td><strong>Remaining balance</strong><br><?= esc(cs_peso($summary['remaining_balance'])) ?></td>
                <td><strong>Overdue</strong><br><?= (int) $summary['overdue_count'] ?> month(s), <?= esc(cs_peso($summary['overdue_amount'])) ?></td>
                <td><strong>Paid in advance</strong><br><?= (int) $summary['advance_count'] ?> month(s)</td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>

<table class="rpt-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Period covered</th>
            <th class="num">Amount</th>
            <th>OR number</th>
            <th>Method</th>
            <th>Received by</th>
            <th>Status</th>
            <th>Source</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= cs_date($r['date']) ?></td>
                <td><?= esc($r['period']) ?></td>
                <td class="num"><?= esc(cs_peso($r['amount'])) ?></td>
                <td><?= esc($r['or_number'] !== '' ? $r['or_number'] : '—') ?></td>
                <td><?= esc($methods[$r['method']] ?? $r['method']) ?></td>
                <td><?= esc($r['received_by'] !== '' ? $r['received_by'] : '—') ?></td>
                <td><?= esc(ucfirst($r['status'])) ?></td>
                <td><?= $r['source'] === 'past' ? 'Past record (recorded by branch)' : 'Paid in system' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="8" class="rpt-empty">No payments for the selected filters.</td></tr>
        <?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?>
        <tfoot>
            <tr class="rpt-total">
                <td colspan="2" class="num">Verified total</td>
                <td class="num"><?= esc(cs_peso($payments['totals']['verified'])) ?></td>
                <td colspan="5">Pending <?= esc(cs_peso($payments['totals']['pending'])) ?> (not counted) · Rejected <?= esc(cs_peso($payments['totals']['rejected'])) ?> (not counted)</td>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>
