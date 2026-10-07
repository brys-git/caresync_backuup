<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are surfaced as toasts by layouts/_shell.php. ?>

<div class="mb-3 d-flex flex-wrap gap-2 justify-content-end">
    <a class="btn btn-outline-primary btn-sm" href="<?= esc($base . '/import') ?>"><i class="ti ti-file-import" aria-hidden="true"></i> Import past payments</a>
    <?php if ($can_verify): ?>
        <a class="btn btn-primary btn-sm" href="<?= esc($base . '/pending') ?>">
            <i class="ti ti-circle-check" aria-hidden="true"></i> Records to verify<?= $pending > 0 ? ' (' . (int) $pending . ')' : '' ?>
        </a>
    <?php endif; ?>
</div>

<?= view('partials/search_bar', [
    'action'      => $base,
    'term'        => $search,
    'placeholder' => 'Client name, mobile number, email or plan number',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($search === ''): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-history',
                'title' => 'Find a member to start',
                'text'  => 'Search for the client\'s account by name, plan number or mobile number. If they do not have an account yet, create one from "Create Account" first.',
            ]) ?>
        <?php elseif ($clients === []): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-search',
                'title' => 'No clients match "' . $search . '"',
                'text'  => 'Check the spelling or try fewer words. Only clients of your branch are shown.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Plan number</th>
                            <th>Branch</th>
                            <th>Account</th>
                            <th class="cs-table__actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td>
                                    <?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?>
                                    <div class="cs-table__sub"><?= esc((string) ($c['contact_number'] ?: $c['email'])) ?></div>
                                </td>
                                <td><?= esc((string) ($c['unique_identifier'] ?: 'Not assigned yet')) ?></td>
                                <td><?= esc((string) ($c['branch_name'] ?: '—')) ?></td>
                                <td><?= cs_status((string) ($c['plan_holder_status'] ?: $c['account_status'])) ?></td>
                                <td class="cs-table__actions">
                                    <a class="btn btn-primary btn-sm" href="<?= esc($base . '/client/' . (int) $c['user_id']) ?>">Open records</a>
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
