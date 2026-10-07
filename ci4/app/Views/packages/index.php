<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">1. Create package</h2></div>
            <form method="post" action="<?= base_url('packages/create') ?>">
                <?= csrf_field() ?>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="package_name">Package name</label>
                            <input id="package_name" name="package_name" type="text" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="base_price">Base price</label>
                            <input id="base_price" name="base_price" type="number" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="initial_effective_date">Initial effective date</label>
                            <input id="initial_effective_date" name="initial_effective_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="initial_version_status">Initial version status</label>
                            <select id="initial_version_status" name="initial_version_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="is_customizable">Customizable</label>
                            <select id="is_customizable" name="is_customizable" class="form-select" required>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="cs-panel__foot d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">Create package</button>
                </div>
            </form>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">2. Add package item</h2></div>
            <form method="post" action="<?= base_url('packages/add-item') ?>">
                <?= csrf_field() ?>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="item_package_id">Package</label>
                            <select id="item_package_id" name="package_id" class="form-select" required>
                                <option value="">Select package</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= esc((string) $package['package_id']) ?>"><?= esc($package['package_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="item_name">Item name</label>
                            <input id="item_name" name="item_name" type="text" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="item_description">Item description</label>
                            <textarea id="item_description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="cs-panel__foot d-flex justify-content-end">
                    <button class="btn btn-outline-primary" type="submit">Add item</button>
                </div>
            </form>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">3. Set price version</h2></div>
            <form method="post" action="<?= base_url('packages/add-version') ?>">
                <?= csrf_field() ?>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="version_package_id">Package</label>
                            <select id="version_package_id" name="package_id" class="form-select" required>
                                <option value="">Select package</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= esc((string) $package['package_id']) ?>"><?= esc($package['package_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="version_price">Price</label>
                            <input id="version_price" name="price" type="number" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="effective_date">Effective date</label>
                            <input id="effective_date" name="effective_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="version_status">Status</label>
                            <select id="version_status" name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cs-panel__foot d-flex justify-content-end">
                    <button class="btn btn-outline-primary" type="submit">Create version</button>
                </div>
            </form>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">4. Create / upgrade plan</h2></div>
            <form method="post" action="<?= base_url('packages/assign-plan') ?>">
                <?= csrf_field() ?>
                <div class="cs-panel__body">
                    <p class="cs-panel__note mb-3">Assign a package to a plan holder. If they already have an active plan, this replaces it (their current plan is marked completed) rather than creating a duplicate.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="plan_holder_id">Plan holder</label>
                            <select id="plan_holder_id" name="plan_holder_id" class="form-select" required>
                                <option value="">Select plan holder</option>
                                <?php foreach ($plan_holders as $holder): ?>
                                    <option value="<?= esc((string) $holder['plan_holder_id']) ?>"
                                        data-current-package="<?= esc((string) ($holder['current_package_name'] ?? '')) ?>"
                                        data-current-fee="<?= esc((string) ($holder['current_monthly_fee'] ?? '')) ?>">
                                        <?= esc($holder['first_name'] . ' ' . $holder['last_name']) ?> (<?= esc($holder['unique_identifier']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="current_plan_info" class="cs-muted d-block mt-1"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="plan_package_id">Package</label>
                            <select id="plan_package_id" name="package_id" class="form-select" required>
                                <option value="">Select package</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= esc((string) $package['package_id']) ?>"><?= esc($package['package_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="version_id">Version</label>
                            <select id="version_id" name="version_id" class="form-select" required>
                                <option value="">Select version</option>
                                <?php foreach ($package_versions as $version): ?>
                                    <option value="<?= esc((string) $version['version_id']) ?>" data-package-id="<?= esc((string) $version['package_id']) ?>">
                                        <?= esc($version['package_name']) ?> - <?= esc((string) $version['price']) ?> (<?= esc($version['effective_date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="cs-muted">The selected version price is locked in plans.monthly_fee.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start_date">Start date</label>
                            <input id="start_date" name="start_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="plan_status">Plan status</label>
                            <select id="plan_status" name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="passbook_fee">Passbook fee</label>
                            <input id="passbook_fee" name="passbook_fee" type="number" step="0.01" class="form-control" value="50.00">
                        </div>
                    </div>
                </div>
                <div class="cs-panel__foot d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">Assign to plan</button>
                </div>
            </form>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Packages</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($packages)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-package', 'title' => 'No packages yet']) ?>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <div class="cs-linkrow cs-linkrow--block">
                            <strong><?= esc($package['package_name']) ?></strong>
                            <div class="cs-table__sub">Base price: <?= cs_money($package['base_price'] ?? 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Package items</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($package_items)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-list', 'title' => 'No items yet']) ?>
                <?php else: ?>
                    <?php foreach ($package_items as $item): ?>
                        <div class="cs-linkrow cs-linkrow--block">
                            <strong><?= esc($item['package_name']) ?></strong> — <?= esc($item['item_name']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Price versions</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($package_versions)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-versions', 'title' => 'No price versions yet']) ?>
                <?php else: ?>
                    <?php foreach ($package_versions as $version): ?>
                        <div class="cs-linkrow cs-linkrow--block">
                            <strong><?= esc($version['package_name']) ?></strong>
                            <div class="cs-table__sub">
                                Version #<?= esc((string) $version['version_id']) ?> · <?= cs_money($version['price'] ?? 0) ?> · <?= cs_date($version['effective_date'] ?? null) ?> · <?= cs_status((string) $version['status']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
    (function () {
        const packageSelect = document.getElementById('plan_package_id');
        const versionSelect = document.getElementById('version_id');
        const planHolderSelect = document.getElementById('plan_holder_id');
        const currentPlanInfo = document.getElementById('current_plan_info');

        function showCurrentPlan() {
            const option = planHolderSelect.options[planHolderSelect.selectedIndex];
            const currentPackage = option ? option.getAttribute('data-current-package') : '';
            const currentFee = option ? option.getAttribute('data-current-fee') : '';

            if (!option || !option.value) {
                currentPlanInfo.textContent = '';
            } else if (currentPackage) {
                currentPlanInfo.textContent = 'Current plan: ' + currentPackage + ' (P' + currentFee + '/month)';
            } else {
                currentPlanInfo.textContent = 'No active plan yet - this will be their first.';
            }
        }

        planHolderSelect.addEventListener('change', showCurrentPlan);
        showCurrentPlan();

        function filterVersions() {
            const selectedPackageId = packageSelect.value;
            const options = Array.from(versionSelect.options);

            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const optionPackageId = option.getAttribute('data-package-id');
                option.hidden = selectedPackageId !== '' && optionPackageId !== selectedPackageId;
            });

            versionSelect.value = '';
        }

        packageSelect.addEventListener('change', filterVersions);
        filterVersions();
    })();
</script>
<?= $this->endSection() ?>
