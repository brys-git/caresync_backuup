<?php

namespace App\Services;

use App\Models\PlanModel;

/**
 * MembershipService
 * 
 * Handles all membership-related business logic.
 * Centralizes the logic for determining membership status and plan activation.
 */
class MembershipService
{
    public const PROGRAM_NAME = 'Damayan Burial Program';
    public const MONTHLY_FEE = 240.0;
    public const TOTAL_CONTRIBUTION = 14500.0;
    public const DEFAULT_PACKAGE_ID = 1;

    /**
     * Fix Prompts Task 5A: the active Damayan program row, for display in
     * the new Plan Builder catalogue - plan_price/term_months/package_id
     * included. Deliberately does not touch getProgramInfo() or any of
     * its existing callers, which keep running on the constants above
     * (registration/payments read from those unchanged in 5A - see
     * PlanBuilderService's own docblock).
     */
    public static function activeProgram(): ?array
    {
        $db = db_connect();
        if (! $db->tableExists('membership_programs')) {
            return null;
        }

        return $db->table('membership_programs')
            ->where('is_active', 1)
            ->orderBy('program_id', 'ASC')
            ->get()
            ->getRowArray() ?: null;
    }

    /**
     * Panel brief, section 10: "ensure package/plan type is clearly
     * distinguished throughout the interface." getProgramInfo() below
     * always names the one generic legacy program regardless of which
     * package a plan holder is actually on - since Packages::
     * assignToPlan() (section 1) lets a plan holder be moved onto a real,
     * different package, client-facing screens need to show that package's
     * real name, not always "Damayan Burial Program."
     */
    public function resolvePackageName(int $packageId): string
    {
        if ($packageId > 0) {
            $package = db_connect()->table('packages')
                ->select('package_name')
                ->where('package_id', $packageId)
                ->get()
                ->getRowArray();

            if ($package && ! empty($package['package_name'])) {
                return (string) $package['package_name'];
            }
        }

        return self::PROGRAM_NAME;
    }

    public static function getProgramInfo(): array
    {
        $db = db_connect();
        if ($db->tableExists('membership_programs')) {
            $program = $db->table('membership_programs')
                ->select('program_id, program_name, monthly_fee')
                ->where('is_active', 1)
                ->orderBy('program_id', 'ASC')
                ->get()
                ->getRowArray();

            if ($program) {
                return [
                    'id' => (int) ($program['program_id'] ?? 0),
                    'name' => (string) ($program['program_name'] ?? self::PROGRAM_NAME),
                    'monthly_fee' => (float) ($program['monthly_fee'] ?? self::MONTHLY_FEE),
                    'package_id' => self::DEFAULT_PACKAGE_ID,
                ];
            }
        }

        return [
            'name' => self::PROGRAM_NAME,
            'monthly_fee' => self::MONTHLY_FEE,
            'package_id' => self::DEFAULT_PACKAGE_ID,
        ];
    }

    public static function ensureDefaultPackageVersion(): void
    {
        $db = db_connect();

        if (! $db->tableExists('packages') || ! $db->tableExists('package_versions')) {
            return;
        }

        $package = $db->table('packages')
            ->select('package_id')
            ->where('package_id', self::DEFAULT_PACKAGE_ID)
            ->get()
            ->getRowArray();

        if (! $package) {
            return;
        }

        $version = $db->table('package_versions')
            ->select('version_id')
            ->where('package_id', self::DEFAULT_PACKAGE_ID)
            ->where('status', 'active')
            ->orderBy('version_id', 'DESC')
            ->get()
            ->getRowArray();

        if ($version) {
            return;
        }

        $db->table('package_versions')->insert([
            'package_id' => self::DEFAULT_PACKAGE_ID,
            'price' => self::MONTHLY_FEE,
            'effective_date' => date('Y-m-d'),
            'status' => 'active',
        ]);
    }

    public static function ensureMembershipProgram(): void
    {
        $db = db_connect();

        if (! $db->tableExists('membership_programs')) {
            return;
        }

        $exists = $db->table('membership_programs')
            ->select('program_id')
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($exists) {
            return;
        }

        $db->table('membership_programs')->insert([
            'program_name' => self::PROGRAM_NAME,
            'monthly_fee' => self::MONTHLY_FEE,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private PlanModel $planModel;

    public function __construct()
    {
        $this->planModel = new PlanModel();
    }

    /**
     * Get the active plan for a plan holder
     * 
     * A user is an active member when:
     * - is_plan_holder = 1
     * - plans.status = 'active'
     * 
     * @param int $planHolderId
     * @return array|null
     */
    public function getActivePlan(int $planHolderId): ?array
    {
        if ($planHolderId <= 0) {
            return null;
        }

        return $this->planModel
            ->where('plan_holder_id', $planHolderId)
            ->where('status', 'active')
            ->orderBy('plan_id', 'DESC')
            ->first();
    }

    /**
     * Get all plans for a plan holder (prioritizing active)
     * 
     * @param int $planHolderId
     * @return array
     */
    public function getPlans(int $planHolderId): array
    {
        if ($planHolderId <= 0) {
            return [];
        }

        return $this->planModel
            ->where('plan_holder_id', $planHolderId)
            ->orderBy("CASE WHEN status = 'active' THEN 1 ELSE 2 END", 'ASC', false)
            ->orderBy('plan_id', 'DESC')
            ->findAll();
    }

    /**
     * Check if a user is an active member
     * 
     * @param int $userId
     * @param int $planHolderId
     * @return bool
     */
    public function isActiveMember(int $userId, int $planHolderId): bool
    {
        if ($userId <= 0 || $planHolderId <= 0) {
            return false;
        }

        $user = db_connect()->table('users')
            ->select('is_plan_holder, status')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if (! $user || (int) ($user['is_plan_holder'] ?? 0) !== 1 || (string) ($user['status'] ?? '') !== 'active') {
            return false;
        }

        $activePlan = $this->getActivePlan($planHolderId);

        return $activePlan !== null;
    }

    /**
     * Ensure only one active plan per plan holder
     * 
     * @param int $planHolderId
     * @return bool
     */
    public function enforceOneActivePlan(int $planHolderId): bool
    {
        if ($planHolderId <= 0) {
            return false;
        }

        $activePlans = $this->planModel
            ->where('plan_holder_id', $planHolderId)
            ->where('status', 'active')
            ->orderBy('plan_id', 'DESC')
            ->findAll();

        if (count($activePlans) <= 1) {
            return true; // Already compliant
        }

        // Keep only the most recent active plan
        $keepPlanId = (int) ($activePlans[0]['plan_id'] ?? 0);
        if ($keepPlanId <= 0) {
            return false;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // Deactivate all active plans
            $this->planModel
                ->where('plan_holder_id', $planHolderId)
                ->where('status', 'active')
                ->set(['status' => 'inactive'])
                ->update();

            // Reactivate only the latest one
            $this->planModel->update((int) $keepPlanId, ['status' => 'active']);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to enforce single active plan');
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }

    /**
     * Get membership details for a plan holder
     * 
     * @param int $planHolderId
     * @return array|null
     */
    public function getMembershipDetails(int $planHolderId): ?array
    {
        if ($planHolderId <= 0) {
            return null;
        }

        $activePlan = $this->getActivePlan($planHolderId);

        if (!$activePlan) {
            return null;
        }

        return [
            'plan_id' => (int) ($activePlan['plan_id'] ?? 0),
            'status' => (string) ($activePlan['status'] ?? 'inactive'),
            'monthly_fee' => (float) ($activePlan['monthly_fee'] ?? 0),
            'months_paid' => (int) ($activePlan['months_paid'] ?? 0),
            'remaining_balance' => (float) ($activePlan['remaining_balance'] ?? 0),
            'start_date' => (string) ($activePlan['start_date'] ?? ''),
            'package_id' => (int) ($activePlan['package_id'] ?? 0),
            'program_name' => self::PROGRAM_NAME,
        ];
    }

    /**
     * Get plans for a branch, prioritizing active records.
     *
     * @param int $branchId
     * @return array
     */
    public function getBranchPlans(int $branchId): array
    {
        $builder = $this->planModel
            ->select('plans.plan_id, plans.monthly_fee, plans.remaining_balance, plans.months_paid, plans.status AS plan_status, plans.start_date, ph.branch_id, ph.unique_identifier, u.first_name, u.last_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = plans.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->orderBy("CASE WHEN plans.status = 'active' THEN 1 ELSE 2 END", 'ASC', false)
            ->orderBy('plans.plan_id', 'DESC');

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        return $builder->findAll();
    }

    /**
     * Apply membership coverage from a payment
     * 
     * Called when a payment is approved (initial or advance).
     * Extends the payment_coverage_until date and updates next_due_date.
     * 
     * CORRECTED LOGIC (Phase requirement):
     * next_due_date = payment_coverage_until + 1 day (not +1 month)
     * This ensures the next due date is immediately after coverage ends.
     * 
     * @param int $planId
     * @param int $monthsCovered
     * @return bool
     */
    public function applyMembershipCoverage(int $planId, int $monthsCovered): bool
    {
        if ($planId <= 0 || $monthsCovered <= 0) {
            return false;
        }

        $plan = $this->planModel->find($planId);
        if (!$plan) {
            return false;
        }

        $baseDate = (string) ($plan['payment_coverage_until'] ?? $plan['start_date'] ?? date('Y-m-d'));
        if ($baseDate === '') {
            $baseDate = date('Y-m-d');
        }

        $newCoverageDate = date('Y-m-d', strtotime('+' . max(1, $monthsCovered) . ' months', strtotime($baseDate)));
        // CORRECTED: next_due_date = payment_coverage_until + 1 day
        $nextDueDate = date('Y-m-d', strtotime('+1 day', strtotime($newCoverageDate)));

        try {
            $db = db_connect();

            // Prepare update payload but only include fields that exist in the plans table
            $updateData = [
                'overdue_months' => 0,
                'membership_state' => 'active',
            ];

            if ($db->fieldExists('payment_coverage_until', 'plans')) {
                $updateData['payment_coverage_until'] = $newCoverageDate;
            }

            if ($db->fieldExists('next_due_date', 'plans')) {
                $updateData['next_due_date'] = $nextDueDate;
            }

            $this->planModel->update($planId, $updateData);

            // Phase 0 fix: months_paid is derived from verified payment
            // history, not incremented here - the caller must already have
            // written this payment's row with a final ('paid') status
            // before calling applyMembershipCoverage(), which every
            // existing caller does.
            $this->recalculateMonthsPaid($planId);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The single entry point every payment path calls after money changes
     * (live payments, GCash approval, verified past records). Kept under its
     * old name so those call sites did not have to change, but the rule now
     * lives in ONE place: PlanLedgerService decides how many contributions
     * are fully paid (oldest unpaid first, live + verified past records),
     * what is overdue and what the plan's status is, and writes the derived
     * plan columns. Nothing is ever erased.
     */
    public function recalculateMonthsPaid(int $planId): int
    {
        if ($planId <= 0) {
            return 0;
        }

        $summary = (new PlanLedgerService())->sync($planId);

        return (int) ($summary['months_paid'] ?? 0);
    }



    /**
     * Services & Packages redesign: whether a plan has fully re-paid the
     * standard ₱14,500 contribution target for its current cycle - the
     * gate for claiming the Regular Wood Casket (Damayan) entitlement
     * again. `total_plan_amount` isn't an actual plans column today, so
     * this always falls back to TOTAL_CONTRIBUTION, same as
     * getMembershipSummary() already does elsewhere.
     *
     * @param array $plan A plan row (as returned by PlanModel/getActivePlan)
     */
    public function hasFullyPaidContribution(array $plan): bool
    {
        $monthsPaid = (int) ($plan['months_paid'] ?? 0);
        $monthlyFee = (float) ($plan['monthly_fee'] ?? self::MONTHLY_FEE);
        $target = (float) ($plan['total_plan_amount'] ?? self::TOTAL_CONTRIBUTION);

        if ($monthlyFee <= 0) {
            $monthlyFee = self::MONTHLY_FEE;
        }

        return ($monthsPaid * $monthlyFee) >= $target;
    }

    /**
     * Whether the plan may request services / claim its package.
     * Decided by the plan's status (PlanLedgerService, Config\CareSync):
     * Active and In grace period: yes. Lapsed: only if lapsedCanClaimServices.
     * Forfeited: never. Use serviceAccess() to also get the reason to show.
     */
    public function canAccessServices(int $planHolderId): bool
    {
        return $this->serviceAccess($planHolderId)['allowed'];
    }

    /** @return array{allowed: bool, reason: ?string} */
    public function serviceAccess(int $planHolderId): array
    {
        if ($planHolderId <= 0) {
            return ['allowed' => false, 'reason' => 'No plan holder profile found.'];
        }

        $summary = (new PlanLedgerService())->summarizeForPlanHolder($planHolderId);
        if ($summary === null || $summary['lifecycle'] === PlanLedgerService::NOT_STARTED) {
            return ['allowed' => false, 'reason' => 'Your plan is not active yet.'];
        }

        return $summary['service_access'];
    }

    /**
     * Refreshes every plan's status from PlanLedgerService (the one rule).
     * Kept for older callers; the daily command calls
     * PlanLedgerService::runDailyCheck() directly, which also sends reminders.
     *
     * @return array status counts
     */
    public function updateMembershipStates(): array
    {
        $counts = ['active' => 0, 'delinquent' => 0, 'suspended' => 0, 'updated' => 0];
        $ledger = new PlanLedgerService();

        foreach (db_connect()->table('plans')->select('plan_id')->get()->getResultArray() as $row) {
            $s = $ledger->sync((int) $row['plan_id']);
            if (! $s || $s['lifecycle'] === PlanLedgerService::NOT_STARTED) {
                continue;
            }
            $counts['updated']++;
            $key = match ($s['lifecycle']) {
                'grace' => 'delinquent',
                'lapsed', 'forfeited' => 'suspended',
                default => 'active',
            };
            $counts[$key]++;
        }

        return $counts;
    }



    /**
     * Get enhanced membership summary for display
     * 
     * @param int $planHolderId
     * @return array|null
     */
    public function getMembershipSummary(int $planHolderId): ?array
    {
        if ($planHolderId <= 0) {
            return null;
        }

        $activePlan = $this->getActivePlan($planHolderId);
        if (! $activePlan) {
            // A forfeited plan is no longer 'active', but the client must still be told why.
            $latest = $this->planModel->where('plan_holder_id', $planHolderId)->orderBy('plan_id', 'DESC')->first();
            if ($latest && ($latest['lifecycle_status'] ?? '') === 'forfeited') {
                $activePlan = $latest;
            }
        }
        if (! $activePlan) {
            return null;
        }

        // Live numbers from the one rule (PlanLedgerService), so this summary
        // can never disagree with the client details page or the dashboard.
        $ledger = (new PlanLedgerService())->summarize((int) $activePlan['plan_id']);
        $access = $ledger['service_access'] ?? ['allowed' => false, 'reason' => null];
        $activePlan['months_paid']    = $ledger['months_paid'] ?? $activePlan['months_paid'];
        $activePlan['overdue_months'] = $ledger['overdue_count'] ?? $activePlan['overdue_months'];
        $activePlan['next_due_date']  = $ledger['oldest_unpaid_due'] ?? $activePlan['next_due_date'];

        return [
            'ledger' => $ledger,
            'lifecycle' => (string) ($ledger['lifecycle'] ?? 'active'),
            'service_block_reason' => $access['reason'],
            'plan_id' => (int) ($activePlan['plan_id'] ?? 0),
            'status' => (string) ($activePlan['status'] ?? 'inactive'),
            'membership_state' => (string) ($activePlan['membership_state'] ?? 'active'),
            'monthly_fee' => (float) ($activePlan['monthly_fee'] ?? 0),
            'total_plan_amount' => (float) ($activePlan['total_plan_amount'] ?? self::TOTAL_CONTRIBUTION),
            'months_paid' => (int) ($activePlan['months_paid'] ?? 0),
            'start_date' => (string) ($activePlan['start_date'] ?? ''),
            'next_due_date' => (string) ($activePlan['next_due_date'] ?? ''),
            'payment_coverage_until' => (string) ($activePlan['payment_coverage_until'] ?? ''),
            'overdue_months' => (int) ($activePlan['overdue_months'] ?? 0),
            'package_id' => (int) ($activePlan['package_id'] ?? 0),
            'program_name' => $this->resolvePackageName((int) ($activePlan['package_id'] ?? 0)),
            'can_access_services' => (bool) $access['allowed'],
            'has_fully_paid_contribution' => $this->hasFullyPaidContribution($activePlan),
        ];
    }
}
