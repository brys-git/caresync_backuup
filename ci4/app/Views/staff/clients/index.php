<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php if (! empty($branch_issue)): ?>
    <div class="alert alert-warning"><?= esc($branch_issue) ?></div>
<?php endif; ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <ul class="nav nav-tabs" id="clientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link active"
                    id="holders-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#holders-panel"
                    type="button"
                    role="tab"
                    aria-controls="holders-panel"
                    aria-selected="true"
                >
                    Branch Plan Holders
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link"
                    id="register-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#register-panel"
                    type="button"
                    role="tab"
                    aria-controls="register-panel"
                    aria-selected="false"
                >
                    Register Plan Holder
                </button>
            </li>
        </ul>

        <div class="tab-content pt-3" id="clientTabsContent">
            <div
                class="tab-pane fade show active"
                id="holders-panel"
                role="tabpanel"
                aria-labelledby="holders-tab"
                tabindex="0"
            >
                <p class="cs-muted mb-3">View and manage all registered plan holders in your branch</p>

                <?= view('partials/search_bar', [
                    'action'      => base_url('staff/client-management'),
                    'term'        => $search ?? '',
                    'placeholder' => 'Name, email, mobile number, or plan number',
                ]) ?>

                <?php if (empty($clients)): ?>
                    <?= view('components/empty_state', [
                        'icon'  => 'ti-users',
                        'title' => ! empty($search) ? 'No plan holders match "' . $search . '"' : 'No plan holders found for this branch',
                        'text'  => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search to see everyone.' : '',
                    ]) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th class="cs-table__actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td>
                                            <a
                                                href="<?= base_url('staff/client/view/' . $client['plan_holder_id']) ?>"
                                                class="cs-table__primary"
                                            >
                                                <?= esc($client['first_name'] . ' ' . $client['last_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= esc((string) ($client['email'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($client['contact_number'] ?? '-')) ?></td>
                                        <td><?= cs_status((string) ($client['plan_holder_status'] ?? 'inactive')) ?></td>
                                        <td class="cs-table__actions">
                                            <a
                                                href="<?= base_url('staff/client/edit/' . $client['plan_holder_id']) ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit client"
                                            >
                                                Edit
                                            </a>
                                            <a
                                                href="<?= base_url('staff/client/view/' . $client['plan_holder_id']) ?>"
                                                class="btn btn-sm btn-primary"
                                                title="View full details"
                                            >
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

            <div
                class="tab-pane fade"
                id="register-panel"
                role="tabpanel"
                aria-labelledby="register-tab"
                tabindex="0"
            >
                <p class="cs-muted mb-3">Register a new plan holder in your branch</p>

                <div class="alert alert-info mb-3">
                    <strong>Heads up!</strong> You can register a walk-in client or link an existing user account to a new plan holder.
                </div>

                <form method="post" action="<?= base_url('staff/client/store') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="quick_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="quick_first_name"
                                name="first_name"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="quick_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="quick_last_name"
                                name="last_name"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="quick_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                class="form-control"
                                id="quick_email"
                                name="email"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="quick_contact" class="form-label">Contact Number</label>
                            <input
                                type="tel"
                                class="form-control"
                                id="quick_contact"
                                name="contact_number"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="quick_city" class="form-label">City/Municipality</label>
                            <input
                                type="text"
                                class="form-control"
                                id="quick_city"
                                name="address_city"
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="quick_barangay" class="form-label">Barangay</label>
                            <input
                                type="text"
                                class="form-control"
                                id="quick_barangay"
                                name="address_barangay"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Program</label>
                            <div class="form-control-plaintext fw-semibold">
                                <?= esc((string) (($program['name'] ?? '') ?: 'Damayan Burial Program')) ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Fee</label>
                            <div class="form-control-plaintext fw-semibold">
                                <?= cs_money($program['monthly_fee'] ?? 240) ?>
                            </div>
                        </div>

                        <input type="hidden" name="middle_name" value="">
                        <input type="hidden" name="date_of_birth" value="">
                        <input type="hidden" name="place_of_birth" value="">
                        <input type="hidden" name="age" value="">
                        <input type="hidden" name="gender" value="">
                        <input type="hidden" name="civil_status" value="">
                        <input type="hidden" name="citizenship" value="">
                        <input type="hidden" name="height" value="">
                        <input type="hidden" name="weight" value="">
                        <input type="hidden" name="address_no" value="">
                        <input type="hidden" name="address_street" value="">
                        <input type="hidden" name="spouse_name" value="">
                        <input type="hidden" name="spouse_birthdate" value="">
                        <input type="hidden" name="spouse_occupation" value="">
                        <input type="hidden" name="senior_citizen_id" value="">
                        <input type="hidden" name="organization_affiliation" value="">
                    </div>

                    <div class="d-flex gap-2 mt-4 justify-content-end">
                        <a href="<?= base_url('staff/client') ?>" class="btn btn-ghost">Clear</a>
                        <a
                            href="<?= base_url('staff/client/register') ?>"
                            class="btn btn-outline-primary me-2"
                            title="Open full registration form"
                        >
                            Full Form
                        </a>
                        <button type="submit" class="btn btn-primary">Quick Register</button>
                    </div>

                    <div class="alert alert-light mt-3">
                        <small class="text-muted">
                            <strong>Quick Register:</strong> Minimal fields for fast registration. <br>
                            <strong>Full Form:</strong> Complete form for detailed information including personal details, address, beneficiaries, and more.
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
