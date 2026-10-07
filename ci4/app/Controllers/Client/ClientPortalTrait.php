<?php

namespace App\Controllers\Client;

use App\Models\PlanHolderModel;
use App\Models\PlanModel;
use App\Models\UserModel;
use App\Services\MembershipService;

/**
 * ClientPortalTrait
 * 
 * Shared helper methods used across client portal controllers
 * Extracted from the original ClientPortal controller for DRY principle
 */
trait ClientPortalTrait
{
    private ?bool $paymentsHasProofImage = null;

    /**
     * Get current authenticated user
     */
    protected function currentUser(): array
    {
        $userId = (int) session('user_id');
        $roleId = (int) session('role_id');

        if ($userId <= 0 || $roleId !== 4) {
            throw new \RuntimeException('Unauthorized client session.');
        }

        $user = (new UserModel())->find($userId);
        if (! $user) {
            throw new \RuntimeException('Client account not found.');
        }

        return $user;
    }

    /**
     * Get current plan holder for the logged-in user
     */
    protected function currentPlanHolder(): ?array
    {
        $userId = (int) session('user_id');

        return (new PlanHolderModel())
            ->where('user_id', $userId)
            ->orderBy('plan_holder_id', 'DESC')
            ->first();
    }

    /**
     * Get latest plan for a plan holder (active or most recent)
     */
    protected function latestPlan(int $planHolderId): ?array
    {
        return (new MembershipService())->getActivePlan($planHolderId)
            ?? (new MembershipService())->getPlans($planHolderId)[0] ?? null;
    }

    /**
     * Get active plan for a plan holder
     */
    protected function activePlan(int $planHolderId): ?array
    {
        return (new MembershipService())->getActivePlan($planHolderId);
    }

    /**
     * Resolve package and package version for plan initialization
     */
    protected function resolvePackageAndVersion(): array
    {
        $db = db_connect();

        // Services & Packages redesign: prefer the explicit Damayan
        // entitlement flag over the assumed DEFAULT_PACKAGE_ID=1 - package
        // ids are no longer guaranteed to start at 1 once the catalog has
        // been re-seeded.
        $package = $db->table('packages')
            ->select('package_id')
            ->where('is_damayan_entitlement', 1)
            ->orderBy('package_id', 'ASC')
            ->get()
            ->getRowArray();

        if (! $package) {
            $package = $db->table('packages')
                ->select('package_id')
                ->where('package_id', MembershipService::DEFAULT_PACKAGE_ID)
                ->get()
                ->getRowArray();
        }

        if (! $package) {
            $package = $db->table('packages')
                ->select('package_id')
                ->orderBy('package_id', 'ASC')
                ->get()
                ->getRowArray();
        }

        if (! $package) {
            throw new \RuntimeException('No package is configured yet. Please ask admin to create a package first.');
        }

        $packageId = (int) $package['package_id'];
        $version = $db->table('package_versions')
            ->select('version_id')
            ->where('package_id', $packageId)
            ->where('status', 'active')
            ->orderBy('version_id', 'DESC')
            ->get()
            ->getRowArray();

        if (! $version) {
            $version = $db->table('package_versions')
                ->select('version_id')
                ->where('package_id', $packageId)
                ->orderBy('version_id', 'DESC')
                ->get()
                ->getRowArray();
        }

        if (! $version) {
            $db->table('package_versions')->insert([
                'package_id' => $packageId,
                'price' => MembershipService::MONTHLY_FEE,
                'effective_date' => date('Y-m-d'),
                'status' => 'active',
            ]);

            $versionId = (int) $db->insertID();
            if ($versionId <= 0) {
                throw new \RuntimeException('No package version is configured yet.');
            }

            return [
                'package_id' => $packageId,
                'version_id' => $versionId,
            ];
        }

        return [
            'package_id' => $packageId,
            'version_id' => (int) $version['version_id'],
        ];
    }

    /**
     * Resolve access state for current user (unregistered/awaiting_activation/active)
     */
    protected function resolveAccessState(): array
    {
        $user = $this->currentUser();
        $planHolder = $this->currentPlanHolder();
        $isPlanHolder = (int) ($user['is_plan_holder'] ?? 0) === 1;
        $activePlan = $planHolder ? $this->activePlan((int) $planHolder['plan_holder_id']) : null;
        $latestPlan = $planHolder ? $this->latestPlan((int) $planHolder['plan_holder_id']) : null;

        $state = 'unregistered';
        if ($planHolder || $isPlanHolder) {
            $state = $activePlan ? 'active' : 'awaiting_activation';
        }

        $badgeClass = 'danger';
        $badgeLabel = 'Unregistered';
        if ($state === 'awaiting_activation') {
            $badgeClass = 'warning';
            $badgeLabel = 'Awaiting Activation';
        } elseif ($state === 'active') {
            $badgeClass = 'success';
            $badgeLabel = 'Active';
        }

        $latestInitialPayment = null;
        if ($planHolder) {
            $latestInitialPayment = $this->latestInitialPayment((int) $planHolder['plan_holder_id']);
        }

        return [
            'user' => $user,
            'plan_holder' => $planHolder,
            'is_plan_holder' => $isPlanHolder,
            'state' => $state,
            'badge_class' => $badgeClass,
            'badge_label' => $badgeLabel,
            'account_status' => strtolower(trim((string) ($user['account_status'] ?? 'pending'))),
            'initial_payment_status' => strtolower((string) ($latestInitialPayment['status'] ?? 'none')),
            'latest_plan' => $latestPlan,
            'active_plan' => $activePlan,
        ];
    }

    /**
     * The plan holder's own latest Government ID verification status
     * ('needs_review'|'verified'|'failed'|'pending', or null if they've
     * never submitted one) - gates access to Initial Payment. Added
     * separately from resolveAccessState()'s own return array rather than
     * folded into it, so every existing caller of that method keeps
     * getting the exact same shape back; only the controllers that
     * actually need this new gate call this too.
     */
    protected function latestIdVerificationStatus(int $userId): ?string
    {
        $verification = (new \App\Services\GovernmentIdVerificationService())->latestForUser($userId);

        return $verification !== null ? (string) ($verification['verification_status'] ?? null) : null;
    }

    /**
     * Get latest initial payment for a plan holder
     */
    protected function latestInitialPayment(int $planHolderId): ?array
    {
        // Return the earliest payment for the plan holder (the initial payment)
        $row = db_connect()->table('payments pay')
            ->select('pay.payment_id, pay.plan_id, pay.amount, pay.payment_date, pay.payment_method, pay.reference_number, pay.official_receipt_number, pay.status, pay.remarks')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->where('p.plan_holder_id', $planHolderId)
            ->orderBy('pay.payment_id', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Check if payments table supports proof image upload
     */
    protected function supportsProofUpload(): bool
    {
        if ($this->paymentsHasProofImage !== null) {
            return $this->paymentsHasProofImage;
        }

        $this->paymentsHasProofImage = db_connect()->fieldExists('proof_image', 'payments');

        return $this->paymentsHasProofImage;
    }

    /**
     * Enforce single active plan for a plan holder
     */
    protected function enforceSingleActivePlan(int $planHolderId, int $activePlanId): void
    {
        $planModel = new PlanModel();

        $planModel
            ->where('plan_holder_id', $planHolderId)
            ->set(['status' => 'inactive'])
            ->update();

        $planModel->update($activePlanId, ['status' => 'active']);
    }

    /**
     * Get nullable value from POST data
     */
    protected function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : $value;
    }

    /**
     * Get nullable integer from POST data
     */
    protected function nullableIntPost(string $key): ?int
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : (int) $value;
    }

    /**
     * Get nullable decimal from POST data
     */
    protected function nullableDecimalPost(string $key): ?float
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : (float) $value;
    }
}
