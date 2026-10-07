<?= $this->extend('layouts/auth_card') ?>

<?= $this->section('statement') ?>Locked out? We will help you back in.<?= $this->endSection() ?>
<?= $this->section('sub') ?>Enter the email on your account and we will send you a link to choose a new password.<?= $this->endSection() ?>

<?= $this->section('content') ?>
<h2 class="signin__title">Forgot password</h2>
<p class="cs-muted mb-4">Enter the email address you registered with.</p>

<form method="post" action="<?= base_url('forgot-password') ?>" data-cs-once>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" name="email" type="email" value="<?= old('email') ?>"
               required autofocus autocomplete="email" placeholder="name@example.com">
    </div>
    <button class="btn btn-primary w-100" type="submit">Send reset link</button>
</form>
<?= $this->endSection() ?>
