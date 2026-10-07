<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$isClaim = (bool) $is_entitlement;
$reference = 'CLM-' . str_pad((string) (int) $application['application_id'], 6, '0', STR_PAD_LEFT);
$awaitingCertificate = (int) ($application['awaiting_death_certificate'] ?? 0) === 1;
?>
<link rel="stylesheet" href="<?= cs_asset_url('assets/css/burial-request.css') ?>">

<div class="br-page">
    <section class="br-card br-done" aria-labelledby="br-done-title">
        <span class="br-done__icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
        </span>
        <h1 id="br-done-title" class="br-title"><?= $isClaim ? 'Claim submitted' : 'Request submitted' ?></h1>
        <p class="br-sub">Thank you. Our staff will review your details and call you to arrange everything.</p>
        <p class="br-muted">Reference <span class="br-mono br-ref"><?= esc($reference) ?></span></p>
        <?php if ($awaitingCertificate): ?>
            <p class="br-note">We're still waiting for the death certificate. Staff can help you get it.</p>
        <?php endif; ?>
    </section>

    <ol class="br-steps" aria-label="What happens next">
        <li class="br-step">
            <span class="br-step__num" aria-hidden="true">1</span>
            <div><p class="br-step__title">Submit this form</p><p class="br-muted">Done</p></div>
        </li>
        <li class="br-step is-current" aria-current="step">
            <span class="br-step__num" aria-hidden="true">2</span>
            <div><p class="br-step__title">Staff review</p><p class="br-muted">An encoder checks your documents</p></div>
        </li>
        <li class="br-step">
            <span class="br-step__num" aria-hidden="true">3</span>
            <div><p class="br-step__title">We call you</p><p class="br-muted">To schedule the service</p></div>
        </li>
    </ol>

    <div class="br-actions">
        <a class="br-btn br-btn--primary" href="<?= base_url('client/membership') ?>">Go to My plan</a>
    </div>
</div>
<?= $this->endSection() ?>
