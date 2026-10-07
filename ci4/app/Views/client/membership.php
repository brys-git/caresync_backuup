<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$state = (string) ($access['state'] ?? 'unregistered');
$program = $program ?? ['name' => 'Damayan Burial Program', 'monthly_fee' => 240.0];
$user = $access['user'] ?? [];
$ph = $plan_holder ?? [];
$beneficiaries = $beneficiaries ?? [];
?>

<?php if ($state === 'unregistered'): ?>
    <div class="cs-restricted">
        <div class="cs-restricted__blur">
            <section class="cs-panel">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Membership record</h2></div>
                <div class="cs-panel__body">
                    <div class="cs-kv"><span class="cs-muted">Package</span><strong><?= esc((string) ($membership_summary['program_name'] ?? $program['name'] ?? 'Damayan Burial Program')) ?></strong></div>
                    <div class="cs-kv"><span class="cs-muted">Status</span><strong>Restricted</strong></div>
                    <div class="cs-kv"><span class="cs-muted">Monthly fee</span><strong><?= cs_money($program['monthly_fee'] ?? 240) ?></strong></div>
                </div>
            </section>
        </div>
        <div class="cs-restricted__overlay">
            <div class="cs-panel cs-restricted__card">
                <div class="cs-panel__body text-center">
                    <h2 class="cs-panel__title mb-2">Register to access this feature</h2>
                    <p class="cs-muted">Membership details are available after plan registration.</p>
                    <a href="<?= base_url('plan-info') ?>" class="btn btn-primary">Register now</a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
<?php
$e = static fn ($v): string => esc((string) ($v ?? ''));
$nonEmpty = static fn ($v): ?string => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);
$p = static fn (string $key): ?string => $nonEmpty($ph[$key] ?? null);
$u = static fn (string $key): ?string => $nonEmpty($user[$key] ?? null);
$dateText = static function ($v): ?string {
    if ($v === null || trim((string) $v) === '') {
        return null;
    }
    $ts = strtotime((string) $v);

    return $ts === false ? null : date('j M Y', $ts);
};
$peso = static fn ($v): string => '₱' . number_format((float) $v, 2);
$value = static function (array $item): string {
    if ($item['value'] !== null) {
        return esc($item['value']);
    }

    return '<span class="mp-empty">' . (! empty($item['pending']) ? 'Pending' : 'Not provided') . '</span>';
};
$icon = static function (string $name): string {
    $paths = [
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'pin'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    ];

    return '<svg class="mp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};

$membershipState = strtolower((string) ($plan['membership_state'] ?? 'active'));
$monthly = (float) ($plan['monthly_fee'] ?? ($program['monthly_fee'] ?? 240));
$overdueMonths = (int) ($plan['overdue_months'] ?? 0);
$nextDue = $nonEmpty($plan['next_due_date'] ?? null);
$coverageUntil = $nonEmpty($plan['payment_coverage_until'] ?? null);
$startDate = $dateText($plan['start_date'] ?? null);
$today = date('Y-m-d');
$isOverdue = $overdueMonths > 0 || ($nextDue !== null && $nextDue < $today);
$daysUntilDue = $nextDue !== null ? (int) round((strtotime($nextDue) - strtotime($today)) / 86400) : null;
$relative = match (true) {
    $daysUntilDue === null => '',
    $daysUntilDue > 1 => 'In ' . $daysUntilDue . ' days',
    $daysUntilDue === 1 => 'Tomorrow',
    default => 'Due today',
};
$memberId = $p('unique_identifier');
$branchName = $nonEmpty($branch_info['branch_name'] ?? null);
$packageName = (string) ($membership_summary['program_name'] ?? $program['name'] ?? 'Damayan Burial Program');
$payUrl = $state === 'awaiting_activation' ? 'initial-payment' : 'client/payment/make';

if ($state === 'awaiting_activation') {
    $statusLabel = 'Awaiting activation';
    $statusTone = 'warn';
} else {
    $statusLabel = ucwords(str_replace('_', ' ', $membershipState));
    $statusTone = match ($membershipState) {
        'active' => 'ok',
        'delinquent' => 'warn',
        'suspended' => 'bad',
        // (membership_state is now only active or suspended; the grace period
        // shows through the banner from PlanLedgerService.)
        default => 'neutral',
    };
}

$addressLine = implode(', ', array_filter([
    $p('address_no'), $p('address_street'), $p('address_barangay'), $p('address_city'),
], static fn ($part) => $part !== null)) ?: null;

$dob = $p('date_of_birth');
$dobText = $dateText($dob);
$age = $dob !== null ? cs_age_from_dob($dob) : null;
if ($dobText !== null && $age !== null) {
    $dobText .= ' (' . $age . ' yrs)';
}

$nameParts = array_map(static fn ($part) => trim((string) $part), [$user['first_name'] ?? '', $user['middle_name'] ?? '', $user['last_name'] ?? '']);
$fullName = implode(' ', array_filter($nameParts, static fn ($part) => $part !== '')) ?: null;

$showSpouse = strtolower(trim((string) ($ph['civil_status'] ?? ''))) !== 'single';

$sections = [
    'Personal' => [
        ['label' => 'Full name', 'value' => $fullName],
        ['label' => 'Date of birth', 'value' => $dobText],
        ['label' => 'Gender', 'value' => $p('gender')],
        ['label' => 'Civil status', 'value' => $p('civil_status')],
        ['label' => 'Citizenship', 'value' => $p('citizenship'), 'optional' => true],
        ['label' => 'Place of birth', 'value' => $p('place_of_birth'), 'optional' => true],
        ['label' => 'Height', 'value' => $p('height'), 'optional' => true],
        ['label' => 'Weight', 'value' => $p('weight'), 'optional' => true],
        ['label' => 'Senior Citizen ID No.', 'value' => $p('senior_citizen_id'), 'optional' => true],
        ['label' => 'Organization affiliation', 'value' => $p('organization_affiliation'), 'optional' => true],
    ],
    'Spouse' => $showSpouse ? [
        ['label' => 'Spouse name', 'value' => $p('spouse_name'), 'optional' => true],
        ['label' => 'Spouse birthdate', 'value' => $dateText($p('spouse_birthdate')), 'optional' => true],
        ['label' => 'Spouse occupation', 'value' => $p('spouse_occupation'), 'optional' => true],
    ] : [],
    'Contact & address' => [
        ['label' => 'Mobile', 'value' => $u('contact_number')],
        ['label' => 'Email', 'value' => $u('email')],
        ['label' => 'Address', 'value' => $addressLine],
    ],
    'Application record' => [
        ['label' => 'Date of application', 'value' => $dateText($p('application_date'))],
        ['label' => 'Branch', 'value' => $branchName],
        ['label' => 'ID control no.', 'value' => $p('id_control_no'), 'pending' => true],
        ['label' => 'Coordinator', 'value' => $p('coordinator'), 'pending' => true],
    ],
];
$emergency = [
    ['label' => 'Emergency contact name', 'value' => $p('emergency_contact_name'), 'optional' => true],
    ['label' => 'Emergency contact number', 'value' => $p('emergency_contact_number'), 'optional' => true],
    ['label' => 'Emergency contact address', 'value' => $p('emergency_contact_address'), 'optional' => true],
];

$missingOptional = [];
$emptyAll = [];
$optionalTotal = 0;
$allItems = $emergency;
foreach ($sections as $items) {
    $allItems = array_merge($allItems, $items);
}
foreach ($allItems as $item) {
    if (! empty($item['optional'])) {
        $optionalTotal++;
    }
    if ($item['value'] === null) {
        $emptyAll[] = $item['label'];
        if (! empty($item['optional'])) {
            $missingOptional[] = $item['label'];
        }
    }
}
$completePct = $optionalTotal > 0 ? (int) round(($optionalTotal - count($missingOptional)) / $optionalTotal * 100) : 100;

$digitsOnly = static fn (?string $phone): string => (string) preg_replace('/\D+/', '', (string) $phone);
$ownDigits = $digitsOnly($u('contact_number'));
$emergencyDigits = $digitsOnly($emergency[1]['value']);
$samePhone = $emergencyDigits !== '' && $emergencyDigits === $ownDigits;
?>
<style>
    .mp-page { --mp-ink: #1b1d2e; --mp-muted: #5b6075; --mp-navy: #23297a; --mp-line: #e3e5ee; max-width: 1180px; margin: 0 auto; padding: 24px 16px 48px; background: #f3f4f8; color: var(--mp-ink); font-family: var(--cs-sans, "Source Sans 3", system-ui, sans-serif); }
    .mp-page * { box-sizing: border-box; }
    .mp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .mp-title { font-size: 28px; font-weight: 700; margin: 0; line-height: 1.2; }
    .mp-sub { margin: 4px 0 0; color: var(--mp-muted); font-size: 15px; }
    .mp-card { background: #fff; border: 1px solid var(--mp-line); border-radius: 16px; padding: 20px; margin-bottom: 16px; }
    .mp-card__head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
    .mp-card__title { font-size: 18px; font-weight: 700; margin: 0; }
    .mp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid var(--mp-navy); font-size: 15px; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; }
    .mp-btn--primary { background: var(--mp-navy); color: #fff; }
    .mp-btn--ghost { background: #fff; color: var(--mp-navy); }
    .mp-btn--block { width: 100%; }
    .mp-btn:hover { filter: brightness(1.06); }
    .mp-btn:focus-visible, .mp-link:focus-visible, .mp-more summary:focus-visible { outline: 3px solid #9aa3e0; outline-offset: 2px; }
    .mp-link { color: var(--mp-navy); font-weight: 600; min-height: 44px; display: inline-flex; align-items: center; text-decoration: none; }
    .mp-muted { color: var(--mp-muted); margin: 0; font-size: 14px; }
    .mp-empty { font-style: italic; font-weight: 400; color: var(--mp-muted); }
    .mp-ok { color: #2f7a4f; }
    .mp-bad { color: #a32b2b; }
    .mp-icon { flex: 0 0 16px; }

    .mp-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .mp-pill { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
    .mp-pill--ok { background: #e6f2ea; color: #2f7a4f; }
    .mp-pill--warn { background: #fbf0da; color: #8f5d00; }
    .mp-pill--bad { background: #f8e7e7; color: #a32b2b; }
    .mp-pill--neutral { background: #eef0f7; color: #414659; }

    .mp-summary { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 340px); gap: 20px; align-items: start; }
    .mp-package { font-size: 26px; font-weight: 700; margin: 0 0 4px; line-height: 1.25; }
    .mp-line { margin: 0; color: var(--mp-muted); font-size: 16px; }
    .mp-due { background: #fff6e8; border-radius: 14px; padding: 18px; }
    .mp-due--overdue { background: #fbe9e9; }
    .mp-due__label { margin: 0 0 4px; font-size: 13px; color: var(--mp-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .mp-due--overdue .mp-due__label, .mp-due--overdue .mp-due__when { color: #a32b2b; }
    .mp-due__amount { margin: 0; font-size: 20px; font-weight: 700; }
    .mp-due__when { margin: 4px 0 14px; font-size: 15px; color: var(--mp-muted); }
    .mp-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin: 20px 0 0; padding-top: 16px; border-top: 1px solid var(--mp-line); }
    .mp-stats dt { font-size: 13px; color: var(--mp-muted); }
    .mp-stats dd { margin: 2px 0 0; font-size: 16px; font-weight: 600; }

    .mp-banner { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; background: #eef0fb; border-radius: 16px; padding: 16px 20px; margin-bottom: 16px; }
    .mp-banner__title { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
    .mp-banner__list { margin: 0 0 10px; color: var(--mp-muted); font-size: 14px; }
    .mp-progress { height: 8px; width: min(320px, 100%); border-radius: 999px; background: #d9ddf2; overflow: hidden; }
    .mp-progress span { display: block; height: 100%; background: var(--mp-navy); }
    .mp-banner__pct { margin: 6px 0 0; font-size: 13px; color: var(--mp-muted); }

    .mp-cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 340px); gap: 16px; align-items: start; }
    .mp-side .mp-card { margin-bottom: 16px; }
    .mp-side .mp-card:last-child { margin-bottom: 0; }
    .mp-group { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--mp-muted); margin: 20px 0 10px; }
    .mp-group:first-of-type { margin-top: 0; }
    .mp-dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px 20px; margin: 0; }
    .mp-field dt { font-size: 13px; color: var(--mp-muted); }
    .mp-field dd { margin: 2px 0 0; font-size: 16px; font-weight: 600; overflow-wrap: anywhere; }
    .mp-more { margin-top: 20px; border-top: 1px solid var(--mp-line); padding-top: 12px; }
    .mp-more summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; color: var(--mp-navy); font-weight: 600; }
    .mp-more__list { margin: 8px 0 0; padding-left: 20px; color: var(--mp-muted); font-size: 14px; columns: 2; }

    .mp-ben { display: flex; align-items: center; gap: 12px; min-height: 44px; padding: 8px 0; }
    .mp-ben + .mp-ben { border-top: 1px solid var(--mp-line); }
    .mp-ben__name { margin: 0; font-weight: 600; font-size: 16px; overflow-wrap: anywhere; }
    .mp-avatar { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; background: #e6e9f7; color: var(--mp-navy); display: grid; place-items: center; font-weight: 700; font-size: 14px; }
    .mp-contact { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .mp-contact li { display: flex; gap: 10px; align-items: flex-start; }
    .mp-contact .mp-icon { margin-top: 4px; color: var(--mp-muted); }
    .mp-contact__value { font-size: 16px; font-weight: 600; overflow-wrap: anywhere; }
    .mp-note { margin: 12px 0 0; padding: 10px 12px; border-radius: 10px; background: #fff6e8; border: 1px solid #f1d9ae; font-size: 14px; color: #6b4300; }

    @media (max-width: 900px) {
        .mp-summary, .mp-cols { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .mp-page { padding: 16px 12px 40px; }
        .mp-title { font-size: 24px; }
        .mp-package { font-size: 22px; }
        .mp-card { padding: 16px; }
        .mp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mp-more__list { columns: 1; }
    }
</style>

<div class="mp-page">
    <header class="mp-head">
        <div>
            <h1 class="mp-title">My plan</h1>
            <p class="mp-sub">Member since <?= $e($startDate ?? 'Pending') ?><?php if ($branchName !== null): ?> · <?= $e($branchName) ?><?php endif; ?></p>
        </div>
        <a class="mp-btn mp-btn--ghost" href="<?= base_url('plan-registration') ?>">Edit details</a>
    </header>

    <?php if ($state === 'awaiting_activation'): ?>
        <?= view('components/alert', ['type' => 'warning', 'dismissible' => false, 'class' => 'mb-3', 'message' => 'Your registration is complete. Please submit your initial payment to activate your membership.']) ?>
    <?php endif; ?>
    <?php if (($access['initial_payment_status'] ?? 'none') === 'cancelled'): ?>
        <?= view('components/alert', ['type' => 'danger', 'dismissible' => false, 'class' => 'mb-3', 'message' => 'Payment rejected. Please resubmit.']) ?>
    <?php endif; ?>
    <?= view('partials/plan_standing', ['standing' => $membership_summary['ledger'] ?? null, 'standing_details' => false]) ?>

    <section class="mp-card" aria-label="Plan summary">
        <div class="mp-summary">
            <div>
                <div class="mp-pills">
                    <span class="mp-pill mp-pill--<?= $statusTone ?>"><?= $e($statusLabel) ?></span>
                    <span class="mp-pill mp-pill--neutral"><?= $memberId !== null ? 'Member ID: ' . $e($memberId) : 'Member ID not yet assigned' ?></span>
                </div>
                <h2 class="mp-package"><?= $e($packageName) ?></h2>
                <p class="mp-line"><?= $peso($monthly) ?> monthly · started <?= $e($startDate ?? 'Pending') ?></p>
            </div>

            <aside class="mp-due<?= $isOverdue ? ' mp-due--overdue' : '' ?>">
                <?php if ($isOverdue): ?>
                    <p class="mp-due__label">Payment overdue</p>
                    <p class="mp-due__amount"><?= $peso($monthly) ?></p>
                    <p class="mp-due__when">Overdue since <?= $e($dateText($nextDue ?? $coverageUntil) ?? 'Pending') ?></p>
                <?php elseif ($nextDue !== null): ?>
                    <p class="mp-due__label">Next payment due</p>
                    <p class="mp-due__amount"><?= $peso($monthly) ?> · <?= $e(date('l, j M Y', strtotime($nextDue))) ?></p>
                    <p class="mp-due__when"><?= $e($relative) ?><?= $coverageUntil !== null ? ' — coverage runs until ' . $e($dateText($coverageUntil)) : '' ?></p>
                <?php else: ?>
                    <p class="mp-due__label">Next payment due</p>
                    <p class="mp-due__amount"><?= $peso($monthly) ?></p>
                    <p class="mp-due__when">No payment scheduled yet.</p>
                <?php endif; ?>
                <a class="mp-btn mp-btn--primary mp-btn--block" href="<?= base_url($payUrl) ?>">Pay now</a>
            </aside>
        </div>

        <dl class="mp-stats">
            <div>
                <dt>Monthly contribution</dt>
                <dd><?= $peso($monthly) ?></dd>
            </div>
            <div>
                <dt>Months paid</dt>
                <dd><?= (int) ($plan['months_paid'] ?? 0) ?></dd>
            </div>
            <div>
                <dt>Covered until</dt>
                <dd><?= $e($dateText($coverageUntil) ?? 'Pending') ?></dd>
            </div>
            <div>
                <dt>Overdue months</dt>
                <dd class="<?= $overdueMonths > 0 ? 'mp-bad' : 'mp-ok' ?>"><?= $overdueMonths > 0 ? $overdueMonths : 'None' ?></dd>
            </div>
        </dl>
    </section>

    <?php if ($missingOptional !== []): ?>
        <?php $moreCount = count($missingOptional) - 3; ?>
        <section class="mp-banner" aria-labelledby="mp-banner-title">
            <div>
                <h2 id="mp-banner-title" class="mp-banner__title">Your profile is missing <?= count($missingOptional) ?> <?= count($missingOptional) === 1 ? 'detail' : 'details' ?></h2>
                <p class="mp-banner__list">
                    Missing: <?= $e(implode(', ', array_slice($missingOptional, 0, 3))) ?><?= $moreCount > 0 ? ' and ' . $moreCount . ' more' : '' ?>
                </p>
                <div class="mp-progress" role="progressbar" aria-label="Profile completeness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $completePct ?>"><span style="width: <?= $completePct ?>%"></span></div>
                <p class="mp-banner__pct"><?= $completePct ?>% complete</p>
            </div>
            <a class="mp-btn mp-btn--primary" href="<?= base_url('client/profile?edit=1') ?>#personal">Complete profile</a>
        </section>
    <?php endif; ?>

    <div class="mp-cols">
        <section class="mp-card" aria-labelledby="mp-registration-title">
            <div class="mp-card__head">
                <h2 id="mp-registration-title" class="mp-card__title">Registration details</h2>
            </div>

            <?php foreach ($sections as $title => $items): ?>
                <?php if ($items === []) continue; ?>
                <h3 class="mp-group"><?= $e($title) ?></h3>
                <dl class="mp-dl">
                    <?php foreach ($items as $item): ?>
                        <div class="mp-field">
                            <dt><?= $e($item['label']) ?></dt>
                            <dd><?= $value($item) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endforeach; ?>

            <details class="mp-more">
                <summary>Show empty fields (<?= count($emptyAll) ?>)</summary>
                <?php if ($emptyAll === []): ?>
                    <p class="mp-muted mt-2">Every field is filled in.</p>
                <?php else: ?>
                    <ul class="mp-more__list">
                        <?php foreach ($emptyAll as $label): ?>
                            <li><?= $e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </details>
        </section>

        <aside class="mp-side">
            <section class="mp-card" aria-labelledby="mp-beneficiaries-title">
                <div class="mp-card__head">
                    <h2 id="mp-beneficiaries-title" class="mp-card__title">Beneficiaries</h2>
                    <a class="mp-link" href="<?= base_url('plan-registration') ?>">+ Add</a>
                </div>
                <?php if ($beneficiaries === []): ?>
                    <p class="mp-muted">No beneficiaries listed yet.</p>
                <?php else: ?>
                    <?php foreach ($beneficiaries as $beneficiary): ?>
                        <?php
                        $bName = mb_convert_case(trim(implode(' ', [
                            (string) ($beneficiary['first_name'] ?? ''),
                            (string) ($beneficiary['middle_name'] ?? ''),
                            (string) ($beneficiary['last_name'] ?? ''),
                            (string) ($beneficiary['name_extension'] ?? ''),
                        ])), MB_CASE_TITLE, 'UTF-8');
                        $bName = preg_replace_callback('/\b(i{2,3}|iv|vi{0,3}|ix)\b/i', static fn ($m) => strtoupper($m[0]), $bName);
                        $bRelationship = $nonEmpty($beneficiary['relationship'] ?? null) ?? 'Relationship not provided';
                        $bBorn = $dateText($beneficiary['date_of_birth'] ?? null);
                        ?>
                        <div class="mp-ben">
                            <span class="mp-avatar" aria-hidden="true"><?= $e(cs_initials($bName)) ?></span>
                            <div>
                                <p class="mp-ben__name"><?= $e($bName) ?></p>
                                <p class="mp-muted"><?= $e($bRelationship) ?> · <?= $bBorn !== null ? 'born ' . $e($bBorn) : 'birthdate not provided' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="mp-card" aria-labelledby="mp-emergency-title">
                <div class="mp-card__head">
                    <h2 id="mp-emergency-title" class="mp-card__title">Emergency contact</h2>
                </div>
                <ul class="mp-contact">
                    <li>
                        <?= $icon('user') ?>
                        <span class="mp-contact__value"><?= $value(['value' => $emergency[0]['value']]) ?></span>
                    </li>
                    <li>
                        <?= $icon('phone') ?>
                        <span class="mp-contact__value">
                            <?php if ($emergency[1]['value'] !== null): ?>
                                <a class="mp-link" href="tel:<?= $e(preg_replace('/[^\d+]/', '', $emergency[1]['value'])) ?>"><?= $e($emergency[1]['value']) ?></a>
                            <?php else: ?>
                                <?= $value($emergency[1]) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <?= $icon('pin') ?>
                        <span class="mp-contact__value"><?= $value(['value' => $emergency[2]['value']]) ?></span>
                    </li>
                </ul>
                <?php if ($samePhone): ?>
                    <p class="mp-note">This is the same number as your own mobile. Please add a different contact number for your emergency contact.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
