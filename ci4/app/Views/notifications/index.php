<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>

<?php
$typeOptions = ['general', 'overdue', 'registration_pending', 'payment_approved', 'payment_rejected', 'service_approved', 'service_rejected', 'service_completed'];
$hasFilters = ($selected_type ?? '') !== '' || ($selected_status ?? '') !== '';
?>
<form class="cs-filters" method="get" action="<?= base_url($base_path ?? 'client/notification') ?>">
    <div class="cs-filters__field">
        <label class="form-label" for="f-type">Type</label>
        <select class="form-select" id="f-type" name="type">
            <option value="">All types</option>
            <?php foreach ($typeOptions as $typeOption): ?>
                <option value="<?= esc($typeOption) ?>" <?= ($selected_type ?? '') === $typeOption ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $typeOption))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="f-status">Status</label>
        <select class="form-select" id="f-status" name="status">
            <option value="">All statuses</option>
            <option value="unread" <?= ($selected_status ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option>
            <option value="read" <?= ($selected_status ?? '') === 'read' ? 'selected' : '' ?>>Read</option>
        </select>
    </div>
    <div class="cs-filters__actions">
        <button class="btn btn-primary" type="submit"><i class="ti ti-filter" aria-hidden="true"></i> Apply</button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-ghost" href="<?= base_url($base_path ?? 'client/notification') ?>">Reset</a>
        <?php endif; ?>
    </div>
</form>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($notifications)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-bell-off',
                'title' => 'No notifications',
                'text'  => 'Approvals, payments, and account updates will show up here.',
            ]) ?>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="cs-linkrow">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <?= esc($notification['message']) ?>
                            <span class="cs-status cs-status--info ms-1"><?= esc(ucwords(str_replace('_', ' ', (string) ($notification['type'] ?? 'general')))) ?></span>
                        </div>
                        <small class="cs-muted"><?= cs_datetime($notification['created_at'] ?? null) ?></small>
                    </div>
                    <?php if ((int) ($notification['is_read'] ?? 0) === 0): ?>
                        <form method="post" action="<?= base_url(($base_path ?? 'client/notification') . '/read') ?>" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="notification_id" value="<?= esc((string) $notification['notification_id']) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Mark read</button>
                        </form>
                    <?php else: ?>
                        <?= cs_status('closed', 'Read') ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
