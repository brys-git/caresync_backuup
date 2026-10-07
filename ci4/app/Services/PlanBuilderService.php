<?php

namespace App\Services;

use App\Models\MembershipProgramModel;
use App\Models\PackageItemModel;
use App\Models\PackageModel;

/**
 * PlanBuilderService
 *
 * Fix Prompts Task 5A. A "plan" here = one membership_programs row +
 * exactly one package it entitles (a packages row - the thing a plan
 * holder actually gets to claim).
 *
 * The entitled package is picked from the existing Services & Packages
 * catalog (a dropdown, see availablePackages()) rather than typed fresh
 * here - Plan Builder used to create its own bespoke package + inclusions
 * per plan, which meant re-describing a casket/inclusion set that likely
 * already existed in the catalog. Package creation/editing (name,
 * description, inclusions) is owned by the Services & Packages page
 * (Admin\ServiceOfferController / BranchAdmin\PackageController); this
 * service only links a plan to whichever package_id was chosen there. This
 * also means delete() no longer removes the package itself - it's a shared
 * catalog resource, not owned by any one plan.
 *
 * Branch Admin can create a plan too (not just System Admin), but it's
 * created with approval_status='pending' and is_active forced to 0 - it
 * exists in the table but stays invisible everywhere a plan needs to be
 * approved+active (the registration plan-picker, any "sellable plans"
 * listing) until System Admin calls approve(). System Admin's own
 * submissions skip this - approval_status='approved' immediately, matching
 * how Branch-Admin-submitted packages/services already work in this app.
 *
 * Registration now does read the chosen plan (see
 * PlanHolderRegistrationService) - the "runs on Damayan constants until a
 * later phase wires registration to a chosen plan" note this docblock used
 * to carry is resolved.
 */
class PlanBuilderService
{
    private MembershipProgramModel $programModel;
    private PackageModel $packageModel;
    private PackageItemModel $itemModel;

    public function __construct()
    {
        $this->programModel = new MembershipProgramModel();
        $this->packageModel = new PackageModel();
        $this->itemModel = new PackageItemModel();
    }

    /**
     * Plans a plan holder can actually be registered onto right now:
     * approved by System Admin (or System-Admin-created, which is
     * auto-approved) AND currently active. Used by the registration
     * plan-picker - PlanHolderRegistrationService validates a submitted
     * program_id against this same set.
     */
    public function listApprovedActive(): array
    {
        return $this->programModel
            ->select('program_id, program_name, plan_price, monthly_fee, term_months, package_id, description')
            ->where('approval_status', 'approved')
            ->where('is_active', 1)
            ->orderBy('program_id', 'ASC')
            ->findAll();
    }

    /**
     * Every package in the Services & Packages catalog, for the "Entitled
     * package" dropdown on the plan create/edit form - all of them, not
     * just is_available/approved ones, so an edit still shows whichever
     * package a plan already points at even if it was since deactivated
     * there (missing from the dropdown would silently unset it on save).
     */
    public function availablePackages(): array
    {
        return $this->packageModel
            ->select('package_id, package_name, base_price, is_available, status')
            ->orderBy('package_name', 'ASC')
            ->findAll();
    }

    /**
     * Every plan for the index table: entitled package name, inclusion
     * count, and how many plan holders are actually on that package
     * (plans.package_id, restored as the system of record - see
     * caresync-panel-revision-project notes).
     */
    public function list(): array
    {
        $db = db_connect();

        $rows = $this->programModel
            ->select('membership_programs.*, packages.package_name, sub.first_name AS submitted_by_first, sub.last_name AS submitted_by_last, br.branch_name AS submitted_by_branch_name')
            ->join('packages', 'packages.package_id = membership_programs.package_id', 'left')
            ->join('users sub', 'sub.user_id = membership_programs.submitted_by', 'left')
            ->join('branches br', 'br.branch_id = membership_programs.submitted_by_branch_id', 'left')
            ->orderBy('membership_programs.program_id', 'ASC')
            ->findAll();

        foreach ($rows as &$row) {
            $packageId = (int) ($row['package_id'] ?? 0);

            $row['inclusion_count'] = $packageId > 0
                ? $this->itemModel->where('package_id', $packageId)->countAllResults()
                : 0;

            $row['plan_holder_count'] = $packageId > 0
                ? (int) $db->table('plans')->where('package_id', $packageId)->countAllResults()
                : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * One plan + its package + inclusions + how many plan holders are on
     * it, for the show/edit pages (edit ignores plan_holder_count; show
     * displays it, per the brief's "N plan holders on this plan" line).
     */
    public function find(int $programId): ?array
    {
        $program = $this->programModel
            ->select('membership_programs.*, sub.first_name AS submitted_by_first, sub.last_name AS submitted_by_last, br.branch_name AS submitted_by_branch_name, rev.first_name AS reviewed_by_first, rev.last_name AS reviewed_by_last')
            ->join('users sub', 'sub.user_id = membership_programs.submitted_by', 'left')
            ->join('branches br', 'br.branch_id = membership_programs.submitted_by_branch_id', 'left')
            ->join('users rev', 'rev.user_id = membership_programs.reviewed_by', 'left')
            ->find($programId);
        if (! $program) {
            return null;
        }

        $packageId = (int) ($program['package_id'] ?? 0);
        $package = $packageId > 0 ? $this->packageModel->find($packageId) : null;
        $inclusions = $packageId > 0
            ? $this->itemModel->where('package_id', $packageId)->orderBy('item_id', 'ASC')->findAll()
            : [];
        $planHolderCount = $packageId > 0
            ? (int) db_connect()->table('plans')->where('package_id', $packageId)->countAllResults()
            : 0;

        return [
            'program'            => $program,
            'package'            => $package,
            'inclusions'         => $inclusions,
            'plan_holder_count'  => $planHolderCount,
        ];
    }

    /**
     * The one place ceil(plan_price / monthly_fee) lives - save() computes
     * the real, persisted term_months from this; the create/edit form
     * only mirrors it client-side for a live preview.
     */
    public function termMonths(float $planPrice, float $monthlyFee): int
    {
        if ($monthlyFee <= 0) {
            return 0;
        }

        return (int) ceil($planPrice / $monthlyFee);
    }

    /**
     * Create or update a plan: the packages row, its package_items
     * (replaced wholesale - reordering isn't supported, matching the
     * brief), and the membership_programs row, all in one transaction.
     * term_months is always computed here (ceil(plan_price /
     * monthly_fee)) - never trusted from the form, which only shows it
     * live-calculated for information.
     *
     * @param array{plan_name:string, plan_price:float, monthly_fee:float, description:string, is_active:int, package_id:int} $data
     * @param array{auto_approve?:bool, submitted_by?:int, submitted_by_branch_id?:int} $submission
     *   Only consulted when creating a new plan (not editing - edits are
     *   System-Admin-only at the route level and never touch approval
     *   fields). auto_approve=false (Branch Admin) forces is_active=0 and
     *   approval_status='pending' regardless of what $data['is_active']
     *   asked for - a plan can't go live before System Admin approves it.
     * @return array{success: bool, errors: array<string,string>, program_id: ?int}
     */
    public function save(array $data, ?int $programId = null, array $submission = []): array
    {
        $errors = $this->validate($data, $programId);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'program_id' => null];
        }

        $db = db_connect();
        $db->transStart();

        $packageId = (int) $data['package_id'];
        $termMonths = $this->termMonths((float) $data['plan_price'], (float) $data['monthly_fee']);

        $programPayload = [
            'program_name' => $data['plan_name'],
            'plan_price'   => $data['plan_price'],
            'monthly_fee'  => $data['monthly_fee'],
            'term_months'  => $termMonths,
            'package_id'   => $packageId,
            'description'  => $data['description'] ?? '',
            'is_active'    => $data['is_active'] ?? 1,
        ];

        if ($programId) {
            $this->programModel->update($programId, $programPayload);
        } else {
            $autoApprove = $submission['auto_approve'] ?? true;
            if ($autoApprove) {
                $programPayload['approval_status'] = 'approved';
            } else {
                $programPayload['approval_status'] = 'pending';
                $programPayload['is_active'] = 0;
            }
            $programPayload['submitted_by'] = $submission['submitted_by'] ?? null;
            $programPayload['submitted_by_branch_id'] = $submission['submitted_by_branch_id'] ?? null;

            $programId = (int) $this->programModel->insert($programPayload, true);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'errors' => ['general' => 'Unable to save plan. Please try again.'], 'program_id' => null];
        }

        return ['success' => true, 'errors' => [], 'program_id' => $programId];
    }

    /**
     * Refuses to deactivate the last active plan - the system always
     * needs at least one sellable plan. See delete() below for the
     * matching "refuse to delete a plan with plan holders on it" rule.
     */
    public function setActive(int $programId, bool $active): array
    {
        $current = $this->programModel->find($programId);
        if (! $current) {
            return ['success' => false, 'error' => 'Plan not found.'];
        }

        if ((string) $current['approval_status'] !== 'approved') {
            return ['success' => false, 'error' => 'This plan must be approved by System Admin before it can be activated.'];
        }

        if (! $active) {
            if ((int) $current['is_active'] === 1) {
                $activeCount = $this->programModel->where('is_active', 1)->countAllResults();
                if ($activeCount <= 1) {
                    return ['success' => false, 'error' => 'Cannot deactivate the last active plan.'];
                }
            }
        }

        $this->programModel->update($programId, ['is_active' => $active ? 1 : 0]);

        return ['success' => true, 'error' => null];
    }

    /**
     * Approving a Branch-Admin-submitted plan also activates it - the
     * whole point of the approval gate is "not selectable at registration
     * until System Admin signs off", and there's no separate reason to
     * make System Admin take a second Activate step right after.
     *
     * @return array{success: bool, error: ?string, program: ?array}
     */
    public function approve(int $programId, int $reviewerId): array
    {
        $current = $this->programModel->find($programId);
        if (! $current) {
            return ['success' => false, 'error' => 'Plan not found.', 'program' => null];
        }

        if ((string) $current['approval_status'] !== 'pending') {
            return ['success' => false, 'error' => 'This plan is not awaiting approval.', 'program' => null];
        }

        $this->programModel->update($programId, [
            'approval_status' => 'approved',
            'is_active'       => 1,
            'reviewed_by'     => $reviewerId,
            'reviewed_at'     => date('Y-m-d H:i:s'),
            'rejection_reason' => null,
        ]);

        return ['success' => true, 'error' => null, 'program' => $this->programModel->find($programId)];
    }

    /**
     * @return array{success: bool, error: ?string, program: ?array}
     */
    public function reject(int $programId, int $reviewerId, string $reason = ''): array
    {
        $current = $this->programModel->find($programId);
        if (! $current) {
            return ['success' => false, 'error' => 'Plan not found.', 'program' => null];
        }

        if ((string) $current['approval_status'] !== 'pending') {
            return ['success' => false, 'error' => 'This plan is not awaiting approval.', 'program' => null];
        }

        $this->programModel->update($programId, [
            'approval_status'  => 'rejected',
            'is_active'        => 0,
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason !== '' ? $reason : null,
        ]);

        return ['success' => true, 'error' => null, 'program' => $this->programModel->find($programId)];
    }

    /**
     * Deletes a plan's membership_programs row only when no plan holder is
     * actually on it. A plan a real plan holder is on must never disappear
     * from under them - deactivate instead.
     *
     * Does NOT delete the entitled package - since the entitled package is
     * now picked from the shared Services & Packages catalog (see
     * availablePackages()) rather than created bespoke per plan, it isn't
     * this plan's to remove; other plans, or Services & Packages itself,
     * may still reference it. Manage packages there.
     */
    public function delete(int $programId): array
    {
        $found = $this->find($programId);
        if ($found === null) {
            return ['success' => false, 'error' => 'Plan not found.'];
        }

        if ($found['plan_holder_count'] > 0) {
            return ['success' => false, 'error' => 'This plan has plan holders on it and cannot be deleted. Deactivate it instead.'];
        }

        if (! $this->programModel->delete($programId)) {
            return ['success' => false, 'error' => 'Unable to delete plan. Please try again.'];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * @return array<string,string> field => message
     */
    private function validate(array $data, ?int $programId): array
    {
        $errors = [];

        $planName = trim((string) ($data['plan_name'] ?? ''));
        if ($planName === '') {
            $errors['plan_name'] = 'Plan name is required.';
        } elseif (mb_strlen($planName) > 150) {
            $errors['plan_name'] = 'Plan name must be 150 characters or fewer.';
        } else {
            $duplicate = $this->programModel->where('program_name', $planName);
            if ($programId) {
                $duplicate->where('program_id !=', $programId);
            }
            if ($duplicate->countAllResults() > 0) {
                $errors['plan_name'] = 'A plan with this name already exists.';
            }
        }

        $planPrice = (float) ($data['plan_price'] ?? 0);
        if ($planPrice <= 0) {
            $errors['plan_price'] = 'Plan price must be greater than zero.';
        }

        $monthlyFee = (float) ($data['monthly_fee'] ?? 0);
        if ($monthlyFee <= 0) {
            $errors['monthly_fee'] = 'Monthly contribution must be greater than zero.';
        } elseif ($planPrice > 0 && $monthlyFee > $planPrice) {
            $errors['monthly_fee'] = 'Monthly contribution cannot exceed the plan price.';
        }

        $packageId = (int) ($data['package_id'] ?? 0);
        if ($packageId <= 0) {
            $errors['package_id'] = 'Please choose an entitled package.';
        } elseif (! $this->packageModel->find($packageId)) {
            $errors['package_id'] = 'The selected package no longer exists. Please choose another.';
        }

        return $errors;
    }
}
