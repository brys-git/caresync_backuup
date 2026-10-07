<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$isClaim = (bool) $is_entitlement;
$mode = $isClaim ? 'claim' : 'avail';
$e = static fn ($v): string => esc((string) ($v ?? ''));
$basePrice = (float) ($package['base_price'] ?? 0);
$statusLabels = ['pending' => 'under review', 'approved' => 'approved'];
$claimReference = $existing_claim ? 'CLM-' . str_pad((string) (int) $existing_claim['application_id'], 6, '0', STR_PAD_LEFT) : '';
?>
<link rel="stylesheet" href="<?= cs_asset_url('assets/css/burial-request.css') ?>">

<div class="br-page">
    <a class="br-back" href="<?= $isClaim ? base_url('client/membership') : base_url('client/service?tab=packages') ?>"><?= $isClaim ? '← Back to my plan' : '← Back to packages' ?></a>
    <header class="br-head">
        <h1 class="br-title"><?= $isClaim ? 'Claim your casket' : 'Avail package' ?></h1>
        <p class="br-sub">We're sorry for your loss. Fill in what you can — our staff will review your <?= $isClaim ? 'claim' : 'request' ?> and call you to arrange everything.</p>
    </header>

    <ol class="br-steps" aria-label="What happens next">
        <li class="br-step is-current">
            <span class="br-step__num" aria-hidden="true">1</span>
            <div><p class="br-step__title">Submit this form</p><p class="br-muted">Details and documents</p></div>
        </li>
        <li class="br-step">
            <span class="br-step__num" aria-hidden="true">2</span>
            <div><p class="br-step__title">Staff review</p><p class="br-muted">An encoder checks your documents</p></div>
        </li>
        <li class="br-step">
            <span class="br-step__num" aria-hidden="true">3</span>
            <div><p class="br-step__title">We call you</p><p class="br-muted">To schedule the service</p></div>
        </li>
    </ol>

    <?php if ($existing_claim): ?>
        <section class="br-card br-status" aria-labelledby="br-status-title">
            <h2 id="br-status-title" class="br-card__title">Your claim is <?= $e($statusLabels[$existing_claim['status']] ?? $existing_claim['status']) ?></h2>
            <p class="br-muted">Reference <span class="br-mono"><?= $e($claimReference) ?></span> · Submitted <?= $e(date('j M Y', strtotime((string) $existing_claim['created_at']))) ?></p>
            <p class="br-body">Our staff are reviewing your documents and will call you. You don't need to submit it again.</p>
            <?php if ((int) $existing_claim['awaiting_death_certificate'] === 1): ?>
                <p class="br-note">We're still waiting for the death certificate. Staff can help you get it.</p>
            <?php endif; ?>
            <a class="br-btn br-btn--ghost" href="<?= base_url('client/membership') ?>">Go to My plan</a>
        </section>
    <?php elseif (! $eligibility['eligible']): ?>
        <section class="br-notice" aria-labelledby="br-notice-title">
            <h2 id="br-notice-title" class="br-notice__title">This isn't available right now</h2>
            <p class="br-body"><?= $e($eligibility['reason'] ?? 'Your plan does not allow this request at the moment.') ?></p>
            <p class="br-body">
                <?php if ($branch_phone !== ''): ?>
                    Contact your branch at <a class="br-link-inline" href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $branch_phone)) ?>"><?= $e($branch_phone) ?></a>.
                <?php else: ?>
                    <a class="br-link-inline" href="<?= base_url('client/membership') ?>">Contact your branch</a> for help.
                <?php endif; ?>
            </p>
            <a class="br-btn br-btn--ghost" href="<?= base_url('client/membership') ?>">Back to My plan</a>
        </section>
    <?php else: ?>
        <?= view('partials/burial_request_form', [
            'mode' => $mode,
            'package' => $package,
            'base_price' => $basePrice,
            'benefit_credit' => (float) $benefit_credit,
            'attire_price' => (float) $attire_price,
            'plan_name' => (string) $plan_name,
            'member_name' => (string) $member_name,
            'member_address' => (string) $member_address,
            'beneficiary_prefill' => (string) $beneficiary_prefill,
            'branch_name' => (string) $branch_name,
            'branch_phone' => (string) $branch_phone,
            'form_errors' => is_array($form_errors) ? $form_errors : [],
        ]) ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
