<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url('/branch-admin/services/edit/' . (int) ($service['service_list_id'] ?? 0)) ?>" class="btn btn-primary btn-sm">Edit</a>
    <a href="<?= site_url('/branch-admin/service-package/services') ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<section class="cs-panel">
    <div class="cs-panel__body">
        <div class="row g-3">
            <div class="col-md-6"><strong>Service Name:</strong> <?= esc($service['service_name'] ?? '-') ?></div>
            <div class="col-md-3"><strong>Base Price:</strong> <?= cs_money($service['base_price'] ?? 0) ?></div>
            <div class="col-md-3"><strong>Status:</strong> <?= cs_status((string) ($service['status'] ?? '')) ?></div>
            <div class="col-12"><strong>Description:</strong> <?= esc($service['description'] ?? '-') ?></div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
