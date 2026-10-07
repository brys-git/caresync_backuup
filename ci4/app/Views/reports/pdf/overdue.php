<?php
/** Overdue report body - same columns and wording as admin/reports/overdue.php. */
$rows = $rows ?? [];
$totalBalance = 0.0;
?>
<table class="rpt-table">
    <thead>
        <tr>
            <th>Plan Holder</th>
            <th>Branch</th>
            <th>Contact</th>
            <th class="num">Remaining Balance</th>
            <th class="center">Months Paid</th>
            <th class="center">Overdue Months</th>
            <th class="center">Days Overdue</th>
            <th>Next Due Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $totalBalance += (float) ($row['remaining_balance'] ?? 0);
            $countdown = (string) ($row['status_text'] ?? '');
            ?>
            <tr>
                <td>
                    <?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?><br>
                    <span class="rpt-note"><?= esc((string) ($row['unique_identifier'] ?? '-')) ?></span>
                </td>
                <td><?= esc((string) ($row['branch_name'] ?? '-')) ?></td>
                <td><?= esc((string) ($row['contact_number'] ?? '-')) ?></td>
                <td class="num"><?= esc(cs_peso($row['remaining_balance'] ?? 0)) ?></td>
                <td class="center"><?= (int) ($row['months_paid'] ?? 0) ?></td>
                <td class="center"><?= (int) ($row['overdue_months'] ?? 0) ?></td>
                <td class="center"><?= (int) ($row['days_overdue'] ?? 0) ?> days</td>
                <td><?= cs_date($row['next_due_date'] ?? null) ?></td>
                <td><?= esc($countdown) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="rpt-empty">No records for the selected filters.</td></tr>
        <?php endif; ?>
    </tbody>
    <?php if ($rows !== []): ?>
        <tfoot>
            <tr class="rpt-total">
                <td colspan="3" class="num">Total (<?= count($rows) ?> account<?= count($rows) === 1 ? '' : 's' ?>)</td>
                <td class="num"><?= esc(cs_peso($totalBalance)) ?></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>
