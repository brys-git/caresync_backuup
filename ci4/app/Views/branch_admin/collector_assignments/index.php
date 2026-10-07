<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php $collectors = $collectors ?? []; ?>
<?php $assignmentsByUser = $assignments_by_user ?? []; ?>
<?php $barangayOptions = $barangay_options ?? []; ?>
<?php $barangayNames = array_column($barangayOptions, 'address_barangay', 'barangay_code'); ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add Assignment</h2></div>
    <div class="cs-panel__body">
        <?php if (empty($collectors)): ?>
            <p class="cs-muted mb-0">No collector-capable accounts found in your branch yet. Mark a Staff account as a collector from Staff Management, or create a Collector account, first.</p>
        <?php else: ?>
            <form method="post" action="<?= base_url('branch-admin/collector-assignments/store') ?>" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label" for="user_id">Collector</label>
                    <select id="user_id" name="user_id" class="form-select" required>
                        <option value="">Select a collector&hellip;</option>
                        <?php foreach ($collectors as $c): ?>
                            <option value="<?= (int) $c['user_id'] ?>"><?= esc((string) ($c['first_name'] . ' ' . $c['last_name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="scope">Scope</label>
                    <select id="scope" name="scope" class="form-select" onchange="document.getElementById('barangay_code').disabled = this.value === 'branch_wide';">
                        <option value="barangay">One barangay</option>
                        <option value="branch_wide">Whole branch</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="barangay_code">Barangay</label>
                    <select id="barangay_code" name="barangay_code" class="form-select">
                        <option value="">Select a barangay&hellip;</option>
                        <?php foreach ($barangayOptions as $b): ?>
                            <option value="<?= esc((string) $b['barangay_code'], 'attr') ?>"><?= esc((string) $b['address_barangay']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
            <?php if (empty($barangayOptions)): ?>
                <p class="cs-muted small mt-2 mb-0">No barangays with active plan holders yet in your branch - "Whole branch" is still available.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Collectors &amp; Their Areas</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($collectors)): ?>
            <?= view('components/empty_state', [
                'icon' => 'ti-map-pin-off',
                'title' => 'No collectors in your branch yet',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Collector</th>
                            <th>Areas Covered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collectors as $c): ?>
                            <?php $rows = $assignmentsByUser[(int) $c['user_id']] ?? []; ?>
                            <tr>
                                <td>
                                    <?= esc((string) ($c['first_name'] . ' ' . $c['last_name'])) ?>
                                    <div class="cs-table__sub"><?= (int) $c['role_id'] === 5 ? 'Collector account' : 'Staff (collector-flagged)' ?></div>
                                </td>
                                <td>
                                    <?php if (empty($rows)): ?>
                                        <span class="text-muted">Not assigned yet</span>
                                    <?php else: ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($rows as $row): ?>
                                                <li class="d-flex align-items-center gap-2 mb-1">
                                                    <?php if ($row['barangay_code'] === null): ?>
                                                        <span><strong>Whole branch</strong></span>
                                                    <?php else: ?>
                                                        <span><?= esc((string) ($barangayNames[$row['barangay_code']] ?? $row['barangay_code'])) ?></span>
                                                    <?php endif; ?>
                                                    <form method="post" action="<?= base_url('branch-admin/collector-assignments/delete/' . (int) $row['assignment_id']) ?>" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-sm btn-danger" data-cs-confirm="Remove this assignment?">Remove</button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
