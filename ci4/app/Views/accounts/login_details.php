<?php
/**
 * One-time login details screen (after Create Account with a temporary
 * password, and after "Resend login details"). The plain password is only in
 * the one-request flash, so reloading this page cannot show it again.
 *
 * @var array $details see LoginDetailsService::details()
 */
$shareText = (new \App\Services\LoginDetailsService())->shareText($details);
?>
<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<div class="alert alert-warning" role="alert">
    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
    <span><strong>Shown only once.</strong> Copy or send these details now. If they are lost, use "Resend login details" on the client's profile to issue a new temporary password.</span>
</div>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Login details for <?= esc($details['name']) ?></h2></div>
    <div class="cs-panel__body">
        <div class="cs-tablewrap mb-3">
            <table class="cs-table cs-table--compact mb-0">
                <tbody>
                    <tr><th class="w-25">Sign-in link</th><td><?= esc($details['sign_in_url']) ?></td></tr>
                    <tr><th>Username</th><td><?= esc($details['username']) ?></td></tr>
                    <?php if ($details['email'] !== ''): ?>
                        <tr><th>Email</th><td><?= esc($details['email']) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Temporary password</th><td><code class="fs-5" id="temp-password"><?= esc($details['password']) ?></code></td></tr>
                    <tr><th>Valid until</th><td><?= esc($details['expires_label']) ?></td></tr>
                    <?php if ($details['role_name'] !== ''): ?>
                        <tr><th>Account</th><td><?= esc($details['role_name']) ?><?= $details['branch'] !== '' ? ' · ' . esc($details['branch']) : '' ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <th>Email sent</th>
                        <td>
                            <?php if ($details['email'] === ''): ?>
                                <span class="cs-muted">No email address on file.</span>
                            <?php elseif ($details['emailed']): ?>
                                <?= cs_status('approved', 'Sent to ' . $details['email']) ?>
                            <?php else: ?>
                                <?= cs_status('failed', 'Not sent') ?>
                                <span class="cs-muted ms-2">Email delivery is not available right now - give the details to the client another way.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="cs-muted">The client will be asked to set a new password the first time they sign in.</p>

        <textarea id="share-text" class="visually-hidden" readonly aria-hidden="true" tabindex="-1"><?= esc($shareText) ?></textarea>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" id="copy-details" data-copied="Copied">
                <i class="ti ti-copy" aria-hidden="true"></i> Copy login details
            </button>
            <?php if (! empty($details['back_url'])): ?>
                <a class="btn btn-ghost" href="<?= esc($details['back_url']) ?>"><?= esc($details['back_label'] ?? 'Back') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    var btn = document.getElementById('copy-details');
    var src = document.getElementById('share-text');
    btn.addEventListener('click', function () {
        var text = src.value;
        function done() {
            var label = btn.innerHTML;
            btn.textContent = btn.getAttribute('data-copied');
            setTimeout(function () { btn.innerHTML = label; }, 2000);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            src.classList.remove('visually-hidden');
            src.select();
            document.execCommand('copy');
            src.classList.add('visually-hidden');
            done();
        }
    });
})();
</script>
<?= $this->endSection() ?>
