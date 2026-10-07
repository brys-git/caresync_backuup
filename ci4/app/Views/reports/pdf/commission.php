<?php
/** Commission report body - same columns as admin/reports/commission.php. */
$rows = $rows ?? [];
$sumTx = 0;
$sumCollected = 0.0;
$sumCommission = 0.0;
$sumNet = 0.0;
?>
<table class="rpt-table">
    <thead>
        <tr>
            <th>Collector</th>
            <th>Role</th>
            <th class="center">Transactions</th>
            <th class="num">Total Collected</th>
            <th class="num">Commission (10%)</th>
            <th class="num">Net Remitted</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $sumTx += (int) ($row['transaction_count'] ?? 0);
            $sumCollected += (float) ($row['total_collected'] ?? 0);
            $sumCommission += (float) ($row['commission'] ?? 0);
            $sumNet += (float) ($row['net_remitted'] ?? 0);
            ?>
            <tr>
                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                <td><?= esc((string) ($row['role_name'] ?? '-')) ?></td>
                <td class="center"><?= (int) ($row['transaction_count'] ?? 0) ?></td>
                <td class="num"><?= esc(cs_peso($row['total_collected'] ?? 0)) ?></td>
                <td class="num"><?= esc(cs_peso($row['commission'] ?? 0)) ?></td>
                <td class="num"><?= esc(cs_peso($row['net_remitted'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="6" class="rpt-empty">No records for the selected filters.</td></tr>
        <?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?>
        <tfoot>
            <tr class="rpt-total">
                <td colspan="2" class="num">Total</td>
                <td class="center"><?= $sumTx ?></td>
                <td class="num"><?= esc(cs_peso($sumCollected)) ?></td>
                <td class="num"><?= esc(cs_peso($sumCommission)) ?></td>
                <td class="num"><?= esc(cs_peso($sumNet)) ?></td>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>
