<?php
/**
 * Client details - the ONE page System Admin, Branch Admin and Staff share.
 * Expects the array built by ClientDetailService::page() plus what
 * ClientDetailController::show() adds (actions, urls, tab, approval).
 * Every plan number comes from $summary (ClientPlanSummary).
 */
use App\Services\ClientDetailService;

$client   = $client ?? [];
$phId     = (int) ($client['plan_holder_id'] ?? 0);
$summary  = $summary ?? null;
$fullName = (string) ($page_title ?? '');
$methods  = ClientDetailService::METHODS;
$e = static fn ($v): string => esc((string) $v);
$dash = static fn ($v): string => trim((string) $v) === '' ? '—' : esc((string) $v);
$editUrl = site_url($urls['edit'] . $phId);

$statusKey   = $summary['status'] ?? (string) ($client['status'] ?? 'inactive');
$statusLabel = $summary['status_label'] ?? ucfirst((string) ($client['status'] ?? 'inactive'));
if ($statusKey === 'grace' && $summary['grace_days_left'] !== null) {
    $statusLabel .= ' · ' . (int) $summary['grace_days_left'] . ' days left to pay';
}

$address = trim(implode(', ', array_filter([
    (string) ($client['address_no'] ?? ''), (string) ($client['address_street'] ?? ''),
    (string) ($client['address_barangay'] ?? ''), (string) ($client['address_city'] ?? ''),
])));
$age = cs_age_from_dob((string) ($client['date_of_birth'] ?? ''));

$tabs = ['overview' => 'Overview', 'payments' => 'Payment history', 'services' => 'Services and claims', 'beneficiaries' => 'Beneficiaries', 'documents' => 'Documents', 'activity' => 'Activity'];
$filters = $payments['filters'];
$qs = static function (array $over) use ($filters): string {
    $q = array_merge([
        'tab' => 'payments', 'pf_from' => $filters['date_from'], 'pf_to' => $filters['date_to'],
        'pf_status' => $filters['status'], 'pf_method' => $filters['method'], 'pf_or' => $filters['or_no'],
    ], $over);

    return '?' . http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== null));
};
?>

<!-- A. Page header -->
<header class="cs-pagehead">
    <div>
        <h1 class="cs-pagehead__title"><?= $e($fullName) ?></h1>
        <p class="cs-pagehead__sub">
            Plan holder #<?= $e(($client['unique_identifier'] ?? '') ?: $phId) ?> · <?= $e($client['branch_name'] ?: 'No branch') ?>
            <?php if (! empty($member_since)): ?>
                · <?= $e(ClientDetailService::memberFor($member_since)) ?> (since <?= cs_date($member_since) ?>)
            <?php endif; ?>
        </p>
        <div class="mt-2"><?= cs_status($statusKey, $statusLabel) ?></div>
    </div>
    <div class="cs-pagehead__actions">
        <a href="<?= site_url($urls['list']) ?>" class="btn btn-ghost btn-sm"><i class="ti ti-arrow-left" aria-hidden="true"></i> Back to list</a>
        <?php foreach ($actions as $a): ?>
            <?php if (isset($a['post'])): ?>
                <form method="post" action="<?= $a['post'] ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn <?= $e($a['class']) ?> btn-sm" data-cs-confirm="<?= $e($a['confirm'] ?? '') ?>"><i class="ti <?= $e($a['icon']) ?>" aria-hidden="true"></i> <?= $e($a['label']) ?></button>
                </form>
            <?php else: ?>
                <a href="<?= $a['href'] ?>" class="btn <?= $e($a['class']) ?> btn-sm"<?= ! empty($a['target']) ? ' target="' . $e($a['target']) . '" rel="noopener"' : '' ?>><i class="ti <?= $e($a['icon']) ?>" aria-hidden="true"></i> <?= $e($a['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</header>

<?= view('partials/plan_ledger_panel', ['ledger' => $summary['ledger'] ?? null, 'reinstate_url' => $urls['reinstate']]) ?>

<?php if ($summary === null): ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__body">
            <?= view('components/empty_state', ['icon' => 'ti-file-off', 'title' => 'This client has no plan yet', 'text' => 'Plan numbers appear here once a plan is created for them.']) ?>
        </div>
    </section>
<?php else: ?>
    <!-- B. Summary strip -->
    <?php
    $overdue = (int) $summary['overdue_count'] > 0;
    $adv     = (int) $summary['advance_count'] > 0;
    ?>
    <div class="cs-statrail cs-cd-strip">
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-package" aria-hidden="true"></i> Plan</div>
            <div class="cs-stat__value cs-cd-text"><?= $e($plan['plan_name'] ?? '—') ?></div>
            <div class="cs-stat__meta">Contract <?= cs_money($summary['contract_amount']) ?></div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-calendar-check" aria-hidden="true"></i> Months paid</div>
            <div class="cs-stat__value"><?= (int) $summary['months_paid'] ?> of <?= (int) $summary['total_months'] ?> <small class="text-muted fw-normal fs-6">months</small></div>
            <div class="cs-meter mt-2" role="progressbar" aria-valuenow="<?= (float) $summary['progress_pct'] ?>" aria-valuemin="0" aria-valuemax="100"><div class="cs-meter__fill" style="width:<?= min(100, (float) $summary['progress_pct']) ?>%"></div></div>
            <div class="cs-stat__meta"><?= $e(rtrim(rtrim(number_format((float) $summary['progress_pct'], 1), '0'), '.')) ?>% complete<?= $summary['partial_months'] !== [] ? ' · ' . count($summary['partial_months']) . ' part-paid' : '' ?></div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-hourglass" aria-hidden="true"></i> Months left</div>
            <div class="cs-stat__value"><?= (int) $summary['months_left'] ?></div>
            <div class="cs-stat__meta">to finish the contribution</div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-alert-triangle" aria-hidden="true"></i> Overdue</div>
            <div class="cs-stat__value <?= $overdue ? 'cs-stat__value--stop' : '' ?>"><?= (int) $summary['overdue_count'] ?> <small class="fw-normal fs-6">month<?= (int) $summary['overdue_count'] === 1 ? '' : 's' ?></small></div>
            <div class="cs-stat__meta"><?= $overdue ? cs_money($summary['overdue_amount']) . ' overdue' : 'Nothing overdue' ?></div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-player-track-next" aria-hidden="true"></i> Advance</div>
            <div class="cs-stat__value <?= $adv ? 'cs-stat__value--ok' : '' ?>"><?= (int) $summary['advance_count'] ?> <small class="fw-normal fs-6">month<?= (int) $summary['advance_count'] === 1 ? '' : 's' ?></small></div>
            <div class="cs-stat__meta"><?= $adv ? cs_money($summary['advance_amount']) . ' paid ahead' : 'Not paid ahead' ?></div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-calendar-due" aria-hidden="true"></i> Next due</div>
            <div class="cs-stat__value cs-cd-text"><?= $summary['next_due_date'] ? cs_date($summary['next_due_date']) : '—' ?></div>
            <div class="cs-stat__meta"><?= $summary['next_due_amount'] !== null ? cs_money($summary['next_due_amount']) . ' due' : 'Nothing due' ?></div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-coin" aria-hidden="true"></i> Total paid</div>
            <div class="cs-stat__value cs-cd-text"><?= cs_money($summary['total_paid']) ?></div>
            <div class="cs-stat__meta">verified payments only</div>
        </div>
        <div class="cs-stat">
            <div class="cs-stat__label"><i class="ti ti-scale" aria-hidden="true"></i> Remaining balance</div>
            <div class="cs-stat__value cs-cd-text"><?= cs_money($summary['remaining_balance']) ?></div>
            <div class="cs-stat__meta">of <?= cs_money($summary['contract_amount']) ?></div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- C. Main column -->
    <div class="col-lg-8">
        <section class="cs-panel">
            <div class="cs-panel__body">
                <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
                    <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap <?= $tab === $key ? 'active' : '' ?>" id="cd-<?= $key ?>-tab" data-bs-toggle="tab" data-bs-target="#cd-<?= $key ?>" type="button" role="tab" aria-controls="cd-<?= $key ?>" aria-selected="<?= $tab === $key ? 'true' : 'false' ?>">
                                <?= $e($label) ?>
                                <?php if ($key === 'payments' && ($pending_payments ?? 0) > 0): ?><span class="badge text-bg-warning ms-1"><?= (int) $pending_payments ?> pending</span><?php endif; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content">
                    <!-- 1. Overview -->
                    <div class="tab-pane fade <?= $tab === 'overview' ? 'show active' : '' ?>" id="cd-overview" role="tabpanel" tabindex="0">
                        <?php if ($approval !== null): ?>
                            <div class="border rounded p-3 mb-3">
                                <h2 class="h6 mb-2">Initial payment verification</h2>
                                <?php if (! empty($approval['initial'])): $ip = $approval['initial']; ?>
                                    <p class="mb-2 small">Payment #<?= (int) $ip['payment_id'] ?> · <?= cs_money($ip['amount']) ?> · <?= cs_date($ip['payment_date']) ?> · <?= $e(strtoupper((string) $ip['payment_method'])) ?> · <?= cs_status((string) $ip['status']) ?></p>
                                <?php else: ?>
                                    <p class="text-muted small mb-2">No initial payment record found.</p>
                                <?php endif; ?>
                                <?php if ($approval['can']): ?>
                                    <form method="post" action="<?= $approval['url'] ?>"><?= csrf_field() ?><button type="submit" class="btn btn-primary btn-sm">Approve registration</button></form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-ghost btn-sm" disabled>Approve registration</button>
                                    <div class="form-text"><?= $e($approval['message']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($summary === null): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-file-off', 'title' => 'No plan on file']) ?>
                        <?php else: ?>
                            <h2 class="h6 mb-2">Plan and contribution details</h2>
                            <div class="row g-2 mb-3">
                                <?php
                                $facts = [
                                    'Plan / package'        => (string) ($plan['plan_name'] ?? ''),
                                    'Contract amount'       => cs_money($summary['contract_amount']),
                                    'Monthly contribution'  => cs_money($summary['monthly_fee']),
                                    'Payment frequency'     => ucfirst(str_replace('_', ' ', (string) ($plan['payment_frequency'] ?? 'monthly'))),
                                    'Start date'            => cs_date($plan['start_date'] ?? null),
                                    'Expected completion'   => $summary['expected_completion'] ? cs_date($summary['expected_completion']) : '—',
                                    'Assigned collector'    => $collectors !== [] ? implode(', ', array_map('esc', $collectors)) : 'No collector assigned',
                                    'Contract number'       => (string) ($plan['contract_number'] ?? ''),
                                ];
                                $raw = ['Contract amount', 'Monthly contribution', 'Start date', 'Expected completion', 'Assigned collector'];
                                ?>
                                <?php foreach ($facts as $label => $value): if ($label === 'Contract number' && $value === '') { continue; } ?>
                                    <div class="col-sm-6"><div class="cs-fact"><span class="cs-fact__label"><?= $e($label) ?></span><span class="cs-fact__value"><?= in_array($label, $raw, true) ? $value : $dash($value) ?></span></div></div>
                                <?php endforeach; ?>
                            </div>

                            <h3 class="h6 mb-2">What the plan includes</h3>
                            <?php if ($inclusions === []): ?>
                                <p class="text-muted small mb-3">No inclusions are listed for this plan.</p>
                            <?php else: ?>
                                <ul class="small mb-3 ps-3"><?php foreach ($inclusions as $item): ?><li><?= $e($item) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>

                            <h3 class="h6 mb-2">Month by month</h3>
                            <div class="cs-mgrid mb-2" role="list">
                                <?php
                                $stateText = ['paid' => 'Paid', 'partial' => 'Partly paid', 'overdue' => 'Overdue', 'advance' => 'Paid in advance', 'upcoming' => 'Upcoming'];
                                foreach ($summary['grid'] as $m):
                                    $tip = 'Month ' . $m['number'] . ' · due ' . date('j M Y', strtotime($m['due'])) . ' · ' . $stateText[$m['state']] . ' · paid ' . cs_peso($m['paid']) . ' of ' . cs_peso($m['amount']);
                                    ?>
                                    <span class="cs-mg cs-mg--<?= $m['state'] ?>" role="listitem" title="<?= $e($tip) ?>" aria-label="<?= $e($tip) ?>"><?= (int) $m['number'] ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="cs-mlegend small text-muted mb-3">
                                <?php foreach ($stateText as $k => $t): ?><span><i class="cs-mg cs-mg--<?= $k ?> cs-mg--key"></i> <?= $e($t) ?></span><?php endforeach; ?>
                            </div>
                            <?php if ($summary['overdue_months'] !== []): ?>
                                <p class="small mb-1"><strong>Overdue months:</strong>
                                    <?= implode(', ', array_map(static fn ($o) => '#' . (int) $o['number'] . ' (' . date('j M Y', strtotime($o['due'])) . ')', $summary['overdue_months'])) ?></p>
                            <?php endif; ?>
                            <?php if ($summary['partial_months'] !== []): ?>
                                <p class="small mb-0"><strong>Part-paid months (not counted as paid until complete):</strong>
                                    <?= implode(', ', array_map(static fn ($o) => '#' . (int) $o['number'] . ' — ' . cs_peso($o['paid']) . ' of ' . cs_peso($o['amount']), $summary['partial_months'])) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Payment history -->
                    <div class="tab-pane fade <?= $tab === 'payments' ? 'show active' : '' ?>" id="cd-payments" role="tabpanel" tabindex="0">
                        <form method="get" action="<?= $urls['self'] ?>" class="cs-filters mb-3">
                            <input type="hidden" name="tab" value="payments">
                            <div class="cs-filters__field"><label class="form-label small mb-1" for="pf_from">From</label><input type="date" id="pf_from" name="pf_from" class="form-control form-control-sm" value="<?= $e($filters['date_from']) ?>"></div>
                            <div class="cs-filters__field"><label class="form-label small mb-1" for="pf_to">To</label><input type="date" id="pf_to" name="pf_to" class="form-control form-control-sm" value="<?= $e($filters['date_to']) ?>"></div>
                            <div class="cs-filters__field"><label class="form-label small mb-1" for="pf_status">Status</label>
                                <select id="pf_status" name="pf_status" class="form-select form-select-sm"><option value="">All</option>
                                    <?php foreach (['verified' => 'Verified', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="cs-filters__field"><label class="form-label small mb-1" for="pf_method">Method</label>
                                <select id="pf_method" name="pf_method" class="form-select form-select-sm"><option value="">All</option>
                                    <?php foreach ($methods as $k => $l): ?><option value="<?= $k ?>" <?= $filters['method'] === $k ? 'selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="cs-filters__field"><label class="form-label small mb-1" for="pf_or">OR number</label><input type="search" id="pf_or" name="pf_or" class="form-control form-control-sm" value="<?= $e($filters['or_no']) ?>" placeholder="Search OR number" maxlength="60"></div>
                            <div class="cs-filters__actions">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <?php if ($payments['is_filtered']): ?><a class="btn btn-ghost btn-sm" href="<?= $urls['self'] ?>?tab=payments">Clear</a><?php endif; ?>
                            </div>
                        </form>

                        <?php if ($payments['all_count'] === 0): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-receipt', 'title' => 'No payments recorded yet', 'text' => 'Payments and past records for this plan appear here.']) ?>
                        <?php elseif ($payments['count'] === 0): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-filter-off', 'title' => 'No payments match these filters']) ?>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted"><?= (int) $payments['count'] ?> payment<?= $payments['count'] === 1 ? '' : 's' ?><?= $payments['is_filtered'] ? ' (filtered)' : '' ?></span>
                                <span class="d-flex gap-2">
                                    <a class="btn btn-ghost btn-sm" href="<?= $urls['statement'] . $qs(['tab' => '']) ?>" target="_blank" rel="noopener"><i class="ti ti-printer" aria-hidden="true"></i> Print</a>
                                    <a class="btn btn-ghost btn-sm" href="<?= $urls['statement'] . $qs(['tab' => '', 'download' => 1]) ?>"><i class="ti ti-download" aria-hidden="true"></i> Export PDF</a>
                                </span>
                            </div>
                            <div class="cs-tablewrap">
                                <table class="cs-table cs-table--compact">
                                    <thead><tr><th>Date</th><th>Period covered</th><th class="cs-num">Amount</th><th>OR number</th><th>Method</th><th>Received by</th><th>Status</th><th>Source</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($payments['rows'] as $r): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= cs_date($r['date']) ?></td>
                                            <td><?= $e($r['period']) ?></td>
                                            <td class="cs-num"><?= cs_money($r['amount']) ?><?= $r['note'] !== '' ? '<div class="small text-muted">' . $e($r['note']) . '</div>' : '' ?></td>
                                            <td><?= $dash($r['or_number']) ?></td>
                                            <td><?= $e($methods[$r['method']] ?? $r['method']) ?></td>
                                            <td><?= $dash($r['received_by']) ?></td>
                                            <td><?= cs_status($r['status']) ?></td>
                                            <td class="small"><?= $r['source'] === 'past' ? 'Recorded by branch<br><span class="text-muted">(past record)</span>' : 'Paid in system' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr><th colspan="2" class="text-end">Verified total</th><th class="cs-num"><?= cs_money($payments['totals']['verified']) ?></th><td colspan="5" class="small text-muted">Pending <?= cs_peso($payments['totals']['pending']) ?> and rejected <?= cs_peso($payments['totals']['rejected']) ?> are not counted.</td></tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php if ($payments['pages'] > 1): ?>
                                <nav class="mt-3" aria-label="Payment pages"><ul class="pagination pagination-sm mb-0">
                                    <?php for ($p = 1; $p <= $payments['pages']; $p++): ?>
                                        <li class="page-item <?= $p === $payments['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= $urls['self'] . $qs(['pp' => $p]) ?>"><?= $p ?></a></li>
                                    <?php endfor; ?>
                                </ul></nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Services and claims -->
                    <div class="tab-pane fade <?= $tab === 'services' ? 'show active' : '' ?>" id="cd-services" role="tabpanel" tabindex="0">
                        <?php if ($services === []): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-heart-handshake', 'title' => 'No services claimed yet']) ?>
                        <?php else: ?>
                            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                                <thead><tr><th>Requested</th><th>Service / package</th><th>For</th><th>Status</th><th>Assigned staff</th><th>Completed</th><th class="cs-num">Amount</th></tr></thead>
                                <tbody>
                                <?php foreach ($services as $s): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= cs_date($s['requested_on']) ?></td>
                                        <td><?= $e($s['item']) ?><div class="small text-muted"><?= $e($s['kind']) ?><?= $s['source'] === 'past' ? ' · past record' : '' ?></div></td>
                                        <td><?= $dash($s['for']) ?></td>
                                        <td><?= cs_status($s['status']) ?></td>
                                        <td><?= $dash($s['staff']) ?></td>
                                        <td class="text-nowrap"><?= $s['completed_on'] !== '' ? cs_date($s['completed_on']) : '—' ?></td>
                                        <td class="cs-num"><?= (float) $s['amount'] > 0 ? cs_money($s['amount']) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table></div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Beneficiaries -->
                    <div class="tab-pane fade <?= $tab === 'beneficiaries' ? 'show active' : '' ?>" id="cd-beneficiaries" role="tabpanel" tabindex="0">
                        <?php if ($beneficiaries === []): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-users', 'title' => 'No beneficiaries on file', 'text' => 'Add them from Edit details.']) ?>
                        <?php else: ?>
                            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                                <thead><tr><th>Name</th><th>Relationship</th><th>Birth date</th><th>Age</th><th>Contact number</th><th>Claim made</th></tr></thead>
                                <tbody>
                                <?php foreach ($beneficiaries as $b): ?>
                                    <tr>
                                        <td><?= $dash($b['full_name']) ?><?= (int) $b['is_primary'] === 1 ? ' <span class="small text-muted">(primary)</span>' : '' ?></td>
                                        <td><?= $dash($b['relationship']) ?></td>
                                        <td class="text-nowrap"><?= cs_date($b['date_of_birth']) ?></td>
                                        <td><?= $b['age'] !== null ? (int) $b['age'] : '—' ?></td>
                                        <td><?= $dash($b['contact_number']) ?></td>
                                        <td><?= $b['claimed'] ? cs_status('completed', 'Claim made') : '<span class="text-muted">No claim</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table></div>
                        <?php endif; ?>
                    </div>

                    <!-- 5. Documents -->
                    <div class="tab-pane fade <?= $tab === 'documents' ? 'show active' : '' ?>" id="cd-documents" role="tabpanel" tabindex="0">
                        <?php if ($documents === []): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-files', 'title' => 'No documents uploaded', 'text' => 'IDs, contracts, scanned ledgers and payment receipts appear here.']) ?>
                        <?php else: ?>
                            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                                <thead><tr><th>Document</th><th>File</th><th>Date</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($documents as $d): $fileUrl = $urls['file'] . $d['kind'] . '/' . (int) $d['id']; ?>
                                    <tr>
                                        <td><?= $e($d['label']) ?></td>
                                        <td class="small text-break"><?= $e($d['name']) ?></td>
                                        <td class="text-nowrap"><?= cs_date($d['date']) ?></td>
                                        <td><?= $d['status'] !== '' ? cs_status($d['status']) : '—' ?></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-ghost btn-sm" href="<?= $fileUrl ?>" target="_blank" rel="noopener">View</a>
                                            <a class="btn btn-ghost btn-sm" href="<?= $fileUrl ?>?download=1">Download</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table></div>
                        <?php endif; ?>
                    </div>

                    <!-- 6. Activity -->
                    <div class="tab-pane fade <?= $tab === 'activity' ? 'show active' : '' ?>" id="cd-activity" role="tabpanel" tabindex="0">
                        <?php if ($activity === []): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-timeline', 'title' => 'No activity recorded yet']) ?>
                        <?php else: ?>
                            <ol class="cs-timeline list-unstyled mb-0">
                                <?php foreach ($activity as $ev): ?>
                                    <li>
                                        <div class="cs-timeline__text"><?= $e($ev['text']) ?></div>
                                        <div class="small text-muted"><?= cs_date($ev['at'], true) ?><?= $ev['actor'] !== '' ? ' · ' . $e($ev['actor']) : '' ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- D. Side column -->
    <div class="col-lg-4">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Personal details</h2></div>
            <div class="cs-panel__body">
                <div class="cs-kv"><span class="text-muted">Full name</span><span class="text-end"><?= $e($fullName) ?></span></div>
                <div class="cs-kv"><span class="text-muted">Birth date</span><span class="text-end"><?= cs_date($client['date_of_birth'] ?? null) ?><?= $age !== null ? ' (age ' . (int) $age . ')' : '' ?></span></div>
                <div class="cs-kv"><span class="text-muted">Sex</span><span class="text-end"><?= $dash($client['gender'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Civil status</span><span class="text-end"><?= $dash($client['civil_status'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Mobile number</span><span class="text-end"><?= $dash($client['contact_number'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Email</span><span class="text-end text-break"><?= $dash($client['email'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Address</span><span class="text-end"><?= $dash($address) ?></span></div>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Emergency contact</h2></div>
            <div class="cs-panel__body">
                <?php if (trim((string) ($client['emergency_contact_name'] ?? '')) === '' && trim((string) ($client['emergency_contact_number'] ?? '')) === ''): ?>
                    <p class="text-muted small mb-2">No emergency contact on file</p>
                    <a class="btn btn-ghost btn-sm" href="<?= $editUrl ?>"><i class="ti ti-plus" aria-hidden="true"></i> Add</a>
                <?php else: ?>
                    <div class="cs-kv"><span class="text-muted">Name</span><span class="text-end"><?= $dash($client['emergency_contact_name'] ?? '') ?></span></div>
                    <div class="cs-kv"><span class="text-muted">Relationship</span><span class="text-end"><?= $dash($client['emergency_contact_relationship'] ?? '') ?></span></div>
                    <div class="cs-kv"><span class="text-muted">Mobile number</span><span class="text-end"><?= $dash($client['emergency_contact_number'] ?? '') ?></span></div>
                    <div class="cs-kv"><span class="text-muted">Address</span><span class="text-end"><?= $dash($client['emergency_contact_address'] ?? '') ?></span></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Account</h2></div>
            <div class="cs-panel__body">
                <div class="cs-kv"><span class="text-muted">Account status</span><span><?= cs_status((string) ($client['user_status'] ?? 'inactive')) ?></span></div>
                <div class="cs-kv"><span class="text-muted">Sign-in email</span><span class="text-end text-break"><?= $dash($client['email'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Username</span><span class="text-end"><?= $dash($client['username'] ?? '') ?></span></div>
                <div class="cs-kv"><span class="text-muted">Last sign-in</span><span class="text-end"><?= ! empty($client['last_login']) && strtotime((string) $client['last_login']) > 0 ? cs_date($client['last_login'], true) : 'Never signed in' ?></span></div>
                <div class="cs-kv"><span class="text-muted">Temporary password</span>
                    <span class="text-end"><?php if ((int) ($client['must_change_password'] ?? 0) === 1): ?>
                        <span class="text-warning-emphasis">Still has to change it</span><?= ! empty($client['temp_password_expires_at']) ? '<div class="small text-muted">expires ' . cs_date($client['temp_password_expires_at']) . '</div>' : '' ?>
                    <?php else: ?>Changed — using own password<?php endif; ?></span></div>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Notes</h2><span class="cs-panel__note mt-0">Staff only — the client cannot see these</span></div>
            <div class="cs-panel__body">
                <form method="post" action="<?= $urls['note'] ?>">
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="cd-note">Internal note</label>
                    <textarea id="cd-note" name="remarks" rows="3" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="Write a note about this client…"><?= $e($notes['remarks']) ?></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Save note</button>
                </form>
                <?php if ($notes['history'] !== []): ?>
                    <hr>
                    <h3 class="h6">History notes</h3>
                    <?php foreach ($notes['history'] as $n): ?>
                        <div class="small mb-2"><?= nl2br($e($n['note'])) ?><div class="text-muted"><?= cs_date($n['encoded_at']) ?><?= trim($n['author']) !== '' ? ' · ' . $e(trim($n['author'])) : '' ?><?= $n['record_status'] !== 'verified' ? ' · awaiting verification' : '' ?></div></div>
                    <?php endforeach; ?>
                <?php elseif (trim($notes['remarks']) === ''): ?>
                    <p class="text-muted small mt-2 mb-0">No notes yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
