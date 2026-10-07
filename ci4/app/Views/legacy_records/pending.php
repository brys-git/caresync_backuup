<?php
use App\Services\LegacyRecordService as L;

/** One-line description of a pending row, by type. */
$describe = static function (array $r) use ($labels): string {
    switch ($r['_type']) {
        case 'plan':
            return ($r['plan_name'] ?: 'Plan') . ' · member since ' . cs_date($r['original_membership_date']) . ' · ' . cs_peso($r['monthly_contribution']) . '/' . strtolower(L::FREQUENCIES[$r['payment_frequency']] ?? '') . ' · status ' . (L::PLAN_STATUSES[$r['plan_status']] ?? $r['plan_status']);
        case 'payment':
            return cs_peso($r['amount']) . ' for ' . date('M Y', mktime(0, 0, 0, (int) $r['period_month'], 1, (int) $r['period_year'])) . ((int) $r['months_covered'] > 1 ? ' (+' . ((int) $r['months_covered'] - 1) . ' mo)' : '') . ' · paid ' . cs_date($r['payment_date']) . ($r['or_number'] ? ' · OR ' . $r['or_number'] : '') . ' · ' . (L::CHANNELS[$r['channel']] ?? $r['channel']);
        case 'remittance':
            return cs_peso($r['amount']) . ' remitted ' . cs_date($r['remittance_date']) . ' by ' . ($r['collector_name'] ?: 'collector') . ($r['reference_number'] ? ' · ref ' . $r['reference_number'] : '');
        case 'service':
            return $r['service_name'] . ' · ' . cs_date($r['service_date']) . ' · ' . (L::SERVICE_STATUSES[$r['service_status']] ?? '') . ($r['amount'] !== null ? ' · ' . cs_peso($r['amount']) : '');
        case 'document':
            return (L::DOC_TYPES[$r['doc_type']] ?? 'Document') . ': ' . $r['original_name'];
        case 'note':
            return mb_strimwidth((string) $r['note'], 0, 140, '…');
        case 'beneficiary':
            return trim($r['first_name'] . ' ' . $r['last_name']) . ' · ' . $r['relationship'] . ($r['contact_number'] ? ' · ' . $r['contact_number'] : '');
    }

    return '';
};
?>
<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are surfaced as toasts by layouts/_shell.php. ?>
<?php if (session()->getFlashdata('warning')): ?>
    <div class="alert alert-warning" role="alert"><i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span><?= esc((string) session()->getFlashdata('warning')) ?></span></div>
<?php endif; ?>

<div class="mb-3 text-end">
    <a class="btn btn-ghost btn-sm" href="<?= esc($base) ?>">Back to Existing member records</a>
</div>

<?php if ($groups === []): ?>
    <section class="cs-panel"><div class="cs-panel__body cs-panel__body--flush">
        <?= view('components/empty_state', ['icon' => 'ti-circle-check', 'title' => 'Nothing waiting for verification', 'text' => 'Records encoded by Staff appear here until you verify or reject them.']) ?>
    </div></section>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__head">
            <div>
                <h2 class="cs-panel__title"><?= esc($g['name']) ?></h2>
                <p class="cs-panel__note"><?= esc((string) ($g['plan_number'] ?: '')) ?> · <?= count($g['items']) ?> record(s) waiting</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-ghost btn-sm" href="<?= esc($base . '/client/' . (int) $g['user_id']) ?>">Open records</a>
                <form method="post" action="<?= esc($base . '/verify-client/' . (int) $g['plan_holder_id']) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-sm" type="submit" data-cs-confirm="Verify all <?= count($g['items']) ?> record(s) for <?= esc($g['name'], 'attr') ?>? They will count toward the client's totals.">Verify all</button>
                </form>
            </div>
        </div>
        <div class="cs-panel__body cs-panel__body--flush">
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Type</th><th>Details</th><th>Encoded by</th><th class="cs-table__actions">Decision</th></tr></thead>
                <tbody>
                <?php foreach ($g['items'] as $item): ?>
                    <tr>
                        <td><?= esc($item['_label']) ?></td>
                        <td><?= esc($describe($item)) ?></td>
                        <td><?= esc(trim(($item['encoder_first'] ?? '') . ' ' . ($item['encoder_last'] ?? '')) ?: '—') ?><div class="cs-table__sub"><?= esc($item['encoded_at'] ? cs_date($item['encoded_at']) : '') ?></div></td>
                        <td class="cs-table__actions">
                            <form method="post" action="<?= esc($base . '/verify/' . $item['_type'] . '/' . (int) $item['_id']) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm" type="submit">Verify</button>
                            </form>
                            <details class="d-inline">
                                <summary class="btn btn-ghost btn-sm" role="button">Reject</summary>
                                <form method="post" action="<?= esc($base . '/reject/' . $item['_type'] . '/' . (int) $item['_id']) ?>" class="border rounded p-2 mt-2 text-start" style="min-width:16rem">
                                    <?= csrf_field() ?>
                                    <label class="form-label" for="reason-<?= esc($item['_type'] . $item['_id']) ?>">Reason</label>
                                    <input class="form-control form-control-sm" id="reason-<?= esc($item['_type'] . $item['_id']) ?>" name="reason" maxlength="255" required>
                                    <button class="btn btn-danger btn-sm mt-2" type="submit" data-cs-confirm="Reject this record?">Reject</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </section>
<?php endforeach; ?>
<?= $this->endSection() ?>
