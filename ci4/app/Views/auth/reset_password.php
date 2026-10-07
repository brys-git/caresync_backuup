<?php
/**
 * @var string $status  valid | expired | used | invalid
 * @var string $token   raw token (only used to post back to the same link)
 */
?>
<?= $this->extend('layouts/auth_card') ?>

<?= $this->section('statement') ?>Choose a new password.<?= $this->endSection() ?>
<?= $this->section('sub') ?>Pick something private. Your old password stops working as soon as you save.<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($status === 'valid'): ?>
    <h2 class="signin__title">Set a new password</h2>
    <p class="cs-muted mb-4">Use at least 8 characters.</p>

    <form method="post" action="<?= base_url('reset-password/' . $token) ?>" data-cs-once>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="password">New password</label>
            <input class="form-control" id="password" name="password" type="password" required autofocus autocomplete="new-password">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirm">Confirm new password</label>
            <input class="form-control" id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100" type="submit">Save new password</button>
    </form>
<?php else: ?>
    <h2 class="signin__title">
        <?= $status === 'used' ? 'This link was already used' : ($status === 'expired' ? 'This link has expired' : 'This link is not valid') ?>
    </h2>
    <p class="cs-muted mb-4">
        <?php if ($status === 'used'): ?>
            A reset link only works once. Request a new one if you still need to change your password.
        <?php elseif ($status === 'expired'): ?>
            Reset links expire after 60 minutes. Request a new one to continue.
        <?php else: ?>
            The link may be incomplete or mistyped. Request a new one to continue.
        <?php endif; ?>
    </p>
    <a class="btn btn-primary w-100" href="<?= base_url('forgot-password') ?>">Request a new link</a>
<?php endif; ?>
<?= $this->endSection() ?>
