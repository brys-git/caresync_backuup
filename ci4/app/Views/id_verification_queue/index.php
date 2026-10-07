<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<?php $rows = $rows ?? []; ?>

<?= view('partials/search_bar', [
    'action'      => $search_action ?? '',
    'term'        => $search ?? '',
    'placeholder' => 'Applicant name or email',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Awaiting Review (<?= count($rows) ?>)</h2>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon' => 'ti-id-badge-2',
                'title' => ! empty($search) ? 'No applicants match "' . $search . '"' : 'Nothing to review right now',
                'text' => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : 'Applicants whose Government ID needs a manual look will show up here.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>ID Type</th>
                            <th>Submitted</th>
                            <th class="cs-table__actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <span class="cs-table__primary"><?= esc((string) ($row['first_name'] . ' ' . $row['last_name'])) ?></span>
                                    <div class="cs-table__sub"><?= esc((string) ($row['email'] ?? '-')) ?></div>
                                </td>
                                <td><?= esc(strtoupper(str_replace('_', ' ', (string) ($row['id_type'] ?? '-')))) ?></td>
                                <td class="text-nowrap"><?= cs_date($row['created_at'] ?? null, true) ?></td>
                                <td class="cs-table__actions">
                                    <a class="btn btn-primary btn-sm" href="<?= esc((string) $action_base . '/' . (int) $row['verification_id'], 'attr') ?>">
                                        Review
                                    </a>
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
