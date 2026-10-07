<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$filters        = $filters ?? [];
$selectedBranch = (int) ($filters['branch_id'] ?? 0);
$selectedStatus = (string) ($filters['status'] ?? '');
$search         = (string) ($filters['search'] ?? '');
$clients        = $clients ?? [];
$hasFilters     = $selectedBranch > 0 || $selectedStatus !== '' || $search !== '';
?>

<form class="cs-filters" method="get" action="<?= base_url('admin/client-management') ?>">
    <div class="cs-filters__field cs-filters__field--wide">
        <label class="form-label" for="f-search">Search</label>
        <input class="form-control" id="f-search" type="search" name="search"
               value="<?= esc($search) ?>" placeholder="Name, email, mobile number, or plan number">
    </div>

    <div class="cs-filters__field">
        <label class="form-label" for="f-branch">Branch</label>
        <select class="form-select" id="f-branch" name="branch_id">
            <option value="">Every branch</option>
            <?php foreach (($branches ?? []) as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>"
                    <?= $selectedBranch === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                    <?= esc((string) $branch['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="cs-filters__field">
        <label class="form-label" for="f-status">Plan status</label>
        <select class="form-select" id="f-status" name="status">
            <option value="">Any status</option>
            <option value="active"   <?= $selectedStatus === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="pending"  <?= $selectedStatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
            <option value="inactive" <?= $selectedStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>

    <div class="cs-filters__actions">
        <button class="btn btn-primary" type="submit">
            <i class="ti ti-filter" aria-hidden="true"></i> Apply
        </button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-ghost" href="<?= base_url('admin/client-management') ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<section class="cs-panel">
    <div class="cs-panel__head">
        <div>
            <h2 class="cs-panel__title">
                <?= esc(number_format(count($clients))) ?>
                <?= count($clients) === 1 ? 'plan holder' : 'plan holders' ?>
            </h2>
            <?php if ($hasFilters): ?>
                <p class="cs-panel__note">Filtered view. Clear the filters to see everyone.</p>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-primary btn-sm" href="<?= base_url('admin/client/register') ?>">
                <i class="ti ti-user-plus" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Register plan holder</span>
            </a>
            <button class="btn btn-ghost btn-sm" type="button" data-cs-density aria-pressed="false">
                <i class="ti ti-baseline-density-medium" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Density</span>
            </button>
            <button class="btn btn-outline-primary btn-sm" type="button" onclick="window.print()">
                <i class="ti ti-printer" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Print</span>
            </button>
        </div>
    </div>

    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($clients === []): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-users',
                'title' => $hasFilters ? 'No plan holders match those filters' : 'No plan holders yet',
                'text'  => $hasFilters
                    ? 'Try a wider branch or status, or clear the search box.'
                    : 'Members appear here once a branch approves their registration.',
                'action' => $hasFilters ? null : ['label' => 'Review pending registrations', 'url' => 'admin/registration-approvals'],
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan holder</th>
                            <th>Branch</th>
                            <th>Package</th>
                            <th>Plan</th>
                            <th>Account</th>
                            <th class="cs-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <?php
                            $id   = (int) ($client['plan_holder_id'] ?? 0);
                            $name = trim((string) ($client['first_name'] ?? '') . ' ' . (string) ($client['last_name'] ?? ''));
                            $name = $name !== '' ? $name : 'Unnamed record';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="cs-avatar" aria-hidden="true"><?= esc(cs_initials($name)) ?></span>
                                        <div class="lh-sm">
                                            <a class="cs-table__primary" href="<?= base_url('admin/client-management/view/' . $id) ?>">
                                                <?= esc($name) ?>
                                            </a>
                                            <div class="cs-table__sub">
                                                <?= esc((string) ($client['email'] ?? $client['contact_number'] ?? '—')) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= esc((string) ($client['branch_name'] ?? '—')) ?></td>
                                <td><?= esc((string) ($client['package_name'] ?? '—')) ?></td>
                                <td><?= cs_status((string) ($client['plan_status'] ?? 'inactive')) ?></td>
                                <td><?= cs_status((string) ($client['plan_holder_status'] ?? 'inactive')) ?></td>
                                <td class="cs-table__actions">
                                    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/client-management/edit/' . $id) ?>">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                        <span class="cs-visually-hidden">Edit <?= esc($name) ?></span>
                                    </a>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= base_url('admin/client-management/view/' . $id) ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($clients !== []): ?>
        <div class="cs-panel__foot cs-muted">
            Showing <?= esc(number_format(count($clients))) ?> records.
        </div>
    <?php endif; ?>
</section>
<?= $this->endSection() ?>
