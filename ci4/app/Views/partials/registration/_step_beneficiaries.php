<?php
/** Step 3: Beneficiaries. See _wizard.php for the $wizard contract. */
$beneficiaries = $wizard['beneficiaries'] ?? [];
$relationships = config(\Config\Beneficiary::class)->relationships;
$isEdit = ($wizard['context'] ?? 'register') === 'edit';
?>
<!-- ============ STEP 3: Beneficiaries ============ -->
<div<?= $isEdit ? '' : ' class="wizard-panel d-none" data-step-panel="3"' ?>>
    <div class="cs-panel<?= $isEdit ? ' mb-4' : '' ?>">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Beneficiaries <span class="text-danger">*</span></h2>
            <p class="cs-panel__note">Add at least one beneficiary. You can leave empty rows blank.</p>
        </div>
        <div class="cs-panel__body">
            <div class="cs-tablewrap">
                <table class="cs-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 20%;">First Name</th>
                            <th style="width: 18%;">Middle Name</th>
                            <th style="width: 20%;">Last Name</th>
                            <th style="width: 16%;">Birthday</th>
                            <th style="width: 26%;">Relationship</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 10; $i++): ?>
                            <?php
                            $beneficiary = $beneficiaries[$i] ?? [];
                            $storedRelationship = (string) ($beneficiary['relationship'] ?? '');
                            $isKnownRelationship = $storedRelationship !== '' && array_key_exists($storedRelationship, $relationships);
                            $selectedRelationship = old('beneficiaries.' . $i . '.relationship', $isKnownRelationship ? $storedRelationship : ($storedRelationship !== '' ? 'Other' : ''));
                            $relationshipOtherValue = old('beneficiaries.' . $i . '.relationship_other', $isKnownRelationship ? '' : $storedRelationship);
                            ?>
                            <tr class="beneficiary-row">
                                <td>
                                    <input
                                        class="form-control beneficiary-first-name"
                                        name="beneficiaries[<?= $i ?>][first_name]"
                                        placeholder="First name"
                                        value="<?= esc(old('beneficiaries.' . $i . '.first_name', (string) ($beneficiary['first_name'] ?? ''))) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        class="form-control beneficiary-middle-name"
                                        name="beneficiaries[<?= $i ?>][middle_name]"
                                        placeholder="Optional"
                                        value="<?= esc(old('beneficiaries.' . $i . '.middle_name', (string) ($beneficiary['middle_name'] ?? ''))) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        class="form-control beneficiary-last-name"
                                        name="beneficiaries[<?= $i ?>][last_name]"
                                        placeholder="Last name"
                                        value="<?= esc(old('beneficiaries.' . $i . '.last_name', (string) ($beneficiary['last_name'] ?? ''))) ?>"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="date"
                                        class="form-control"
                                        name="beneficiaries[<?= $i ?>][birthday]"
                                        max="<?= date('Y-m-d') ?>"
                                        value="<?= esc(old('beneficiaries.' . $i . '.birthday', (string) ($beneficiary['date_of_birth'] ?? ''))) ?>"
                                    >
                                </td>
                                <td>
                                    <select class="form-select beneficiary-relationship" name="beneficiaries[<?= $i ?>][relationship]">
                                        <option value="">Select relationship</option>
                                        <?php foreach ($relationships as $value => $label): ?>
                                            <option value="<?= esc($value) ?>" <?= $selectedRelationship === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input
                                        type="text"
                                        class="form-control beneficiary-relationship-other mt-1 <?= $selectedRelationship === 'Other' ? '' : 'd-none' ?>"
                                        name="beneficiaries[<?= $i ?>][relationship_other]"
                                        placeholder="Specify relationship"
                                        maxlength="50"
                                        value="<?= esc($relationshipOtherValue) ?>"
                                    >
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-danger small mb-0 d-none" id="beneficiaryError">Please provide at least one beneficiary with a first name, last name, and relationship.</p>
        </div>
    </div>

    <?php if (! $isEdit): ?>
    <div class="wizard-nav">
        <button type="button" class="btn btn-ghost wizard-back"><i class="ti ti-arrow-left me-1"></i>Back</button>
        <button type="button" class="btn btn-primary wizard-next">Next: Emergency Contact <i class="ti ti-arrow-right ms-1"></i></button>
    </div>
    <?php endif; ?>
</div>
