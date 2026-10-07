<?php

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\PlanModel;
use App\Models\ServiceApplicationModel;
use App\Models\ServiceModel;

/**
 * Panel brief, section 5: "Claim Availed Plan" - a plan holder applies to
 * avail their package's (or an individual service's) benefit, and the claim
 * is processed by staff or an Encoder, never self-approved by the plan
 * holder. The submission side of this already existed as "service
 * applications" (Client\ClientServiceController::submitServiceApplication /
 * submitPackageApplication, always inserted with status='pending' - no
 * self-approval path exists). What was missing was staff-level approval:
 * only Branch Admin could approve/reject (BranchAdmin\
 * ServiceApplicationController), matching section 6's "Encoder/Staff...
 * processes claims" only partially.
 *
 * This service holds the approve/reject logic shared by both roles, pulled
 * out of BranchAdmin\ServiceApplicationController so Staff's copy isn't a
 * second, drifting implementation of the same transaction.
 */
class ClaimService
{
    private ServiceApplicationModel $serviceApplicationModel;
    private ServiceModel $serviceModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        $this->serviceApplicationModel = new ServiceApplicationModel();
        $this->serviceModel = new ServiceModel();
        $this->notificationModel = new NotificationModel();
    }

    /**
     * Claims (service_applications) for a branch, with the plan holder's
     * name and a single "what's being claimed" label that falls back to the
     * individual service name when the claim isn't for a whole package.
     */
    /**
     * @param string $search optional search-box text: plan holder name, plan
     *                       number, or the package / service being claimed
     */
    public function getBranchClaims(int $branchId, string $search = ''): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $builder = db_connect()->table('service_applications sa')
            ->select('sa.application_id, sa.plan_holder_id, sa.package_id, sa.service_list_id, sa.status, sa.created_at, ph.unique_identifier, u.first_name, u.last_name, p.package_name, sl.service_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = sa.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('packages p', 'p.package_id = sa.package_id', 'left')
            ->join('service_list sl', 'sl.service_list_id = sa.service_list_id', 'left')
            ->where('ph.branch_id', $branchId);

        cs_apply_search($builder, $search, ['u.first_name', 'u.last_name', 'ph.unique_identifier', 'p.package_name', 'sl.service_name']);

        $rows = $builder
            ->orderBy('sa.created_at', 'DESC')
            ->orderBy('sa.application_id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['claim_label'] = (string) ($row['package_name'] ?? $row['service_name'] ?? '-');
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function approve(int $applicationId, int $branchId, int $processedByUserId): array
    {
        $request = $this->findClaimForBranch($applicationId, $branchId);
        if (! $request) {
            return ['ok' => false, 'message' => 'Claim not found.'];
        }

        if (($request['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending claims can be approved.'];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->serviceApplicationModel->update($applicationId, ['status' => 'approved']);

            $this->stampClaimCycle($applicationId, (int) $request['package_id'], (int) $request['plan_holder_id']);

            $serviceRecordId = (int) $this->serviceModel->insert([
                'plan_holder_id' => (int) $request['plan_holder_id'],
                'branch_id' => $branchId,
                'service_list_id' => (int) ($request['service_list_id'] ?? 0) > 0 ? (int) $request['service_list_id'] : null,
                'package_id' => (int) ($request['package_id'] ?? 0) > 0 ? (int) $request['package_id'] : null,
                'total_cost' => (string) ((int) ($request['service_list_id'] ?? 0) > 0 ? ($request['service_price'] ?? 0) : ($request['package_price'] ?? 0)),
                'service_date' => date('Y-m-d'),
                'service_time' => null,
                'burial_location' => null,
                'assigned_staff' => null,
                'notes' => 'Created from approved claim.',
                'status' => 'pending',
            ]);

            $balanceService = new ServiceBalanceService();
            $balanceId = $balanceService->createBalanceRecord([
                'application_id' => (int) $request['application_id'],
                'plan_holder_id' => (int) $request['plan_holder_id'],
                'branch_id' => $branchId,
                'service_list_id' => (int) ($request['service_list_id'] ?? 0),
                'package_id' => (int) ($request['package_id'] ?? 0),
                'service_type' => (int) ($request['service_list_id'] ?? 0) > 0 ? 'service' : 'package',
                'service_name' => (string) ($request['service_name'] ?? $request['package_name'] ?? 'Selected service'),
                'package_name' => (string) ($request['package_name'] ?? null),
                'package_cost' => (float) ((int) ($request['service_list_id'] ?? 0) > 0 ? ($request['service_price'] ?? 0) : ($request['package_price'] ?? 0)),
            ], [
                'service_id' => $serviceRecordId,
            ]);

            if ($balanceId) {
                $this->notificationModel->insert([
                    'user_id' => (int) $request['user_id'],
                    'message' => 'Your approved claim now has a separate funeral balance for beneficiary continuation.',
                ]);
            }

            $this->notificationModel->insert([
                'user_id' => (int) $request['user_id'],
                'message' => 'Your claim has been approved.',
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to approve claim.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Claim approved and moved to services.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function reject(int $applicationId, int $branchId, int $processedByUserId, string $reason = ''): array
    {
        $request = $this->findClaimForBranch($applicationId, $branchId);
        if (! $request) {
            return ['ok' => false, 'message' => 'Claim not found.'];
        }

        if (($request['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending claims can be rejected.'];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->serviceApplicationModel->update($applicationId, ['status' => 'rejected']);

            $message = 'Your claim has been rejected.' . ($reason !== '' ? (' Reason: ' . $reason) : '');
            $this->notificationModel->insert([
                'user_id' => (int) $request['user_id'],
                'message' => $message,
            ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to reject claim.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Claim rejected.'];
    }

    /**
     * Full detail for a single claim (for a review/detail page), scoped to
     * the reviewer's branch.
     */
    public function getClaimDetail(int $applicationId, int $branchId): ?array
    {
        if ($applicationId <= 0 || $branchId <= 0) {
            return null;
        }

        $request = db_connect()->table('service_applications sa')
            ->select('sa.*, ph.branch_id, ph.user_id, u.first_name, u.last_name, p.base_price AS package_price, p.package_name, sl.base_price AS service_price, sl.service_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = sa.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('packages p', 'p.package_id = sa.package_id', 'left')
            ->join('service_list sl', 'sl.service_list_id = sa.service_list_id', 'left')
            ->where('sa.application_id', $applicationId)
            ->get()
            ->getRowArray();

        if (! $request || (int) ($request['branch_id'] ?? 0) !== $branchId) {
            return null;
        }

        return $request;
    }

    public function getClaimDocuments(int $applicationId): array
    {
        if ($applicationId <= 0) {
            return [];
        }

        return db_connect()->table('service_application_documents')
            ->where('application_id', $applicationId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * A single claim document, scoped to the reviewer's branch - or null if
     * it doesn't exist or belongs to a different branch.
     */
    public function getClaimDocumentForBranch(int $documentId, int $branchId): ?array
    {
        if ($documentId <= 0 || $branchId <= 0) {
            return null;
        }

        $document = db_connect()->table('service_application_documents')
            ->where('document_id', $documentId)
            ->get()
            ->getRowArray();

        if (! $document) {
            return null;
        }

        $application = db_connect()->table('service_applications')
            ->select('plan_holder_id')
            ->where('application_id', (int) $document['application_id'])
            ->get()
            ->getRowArray();

        if (! $application) {
            return null;
        }

        $planHolder = db_connect()->table('plan_holders')
            ->select('branch_id')
            ->where('plan_holder_id', (int) $application['plan_holder_id'])
            ->get()
            ->getRowArray();

        if (! $planHolder || (int) ($planHolder['branch_id'] ?? 0) !== $branchId) {
            return null;
        }

        return $document;
    }

    /**
     * Entitlement cycle model (replaces the old
     * resetContributionCycleIfEntitlementClaim(), which zeroed
     * months_paid the instant ANY entitlement claim was approved - even
     * one approved before the plan holder had finished paying off the
     * current ₱14,500 cycle, silently destroying that progress). Every
     * approved claim - entitlement or not - is stamped with the cycle it
     * was approved in, for a consistent audit trail alongside
     * payments.cycle_number. Only an entitlement-package (Regular Wood
     * Casket) claim can actually progress a cycle, so
     * CycleService::closeCycleIfSettled() is only checked for that case -
     * it's a no-op unless this claim is also the thing that completes an
     * already-fully-paid cycle, and never touches months_paid directly
     * itself.
     */
    private function stampClaimCycle(int $applicationId, int $packageId, int $planHolderId): void
    {
        if ($planHolderId <= 0) {
            return;
        }

        $planModel = new PlanModel();
        $activePlan = $planModel
            ->where('plan_holder_id', $planHolderId)
            ->where('status', 'active')
            ->orderBy('plan_id', 'DESC')
            ->first();

        if (! $activePlan) {
            return;
        }

        $planId = (int) $activePlan['plan_id'];
        $cycleNumber = (int) ($activePlan['current_cycle_number'] ?? 1);

        if ($applicationId > 0) {
            db_connect()->table('service_applications')
                ->where('application_id', $applicationId)
                ->update(['cycle_number' => $cycleNumber]);
        }

        if ($packageId <= 0) {
            return;
        }

        $package = db_connect()->table('packages')
            ->select('is_damayan_entitlement')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package || (int) ($package['is_damayan_entitlement'] ?? 0) !== 1) {
            return;
        }

        $planModel->update($planId, [
            'last_damayan_claim_at' => date('Y-m-d H:i:s'),
        ]);

        (new CycleService())->closeCycleIfSettled($planId);
    }

    private function findClaimForBranch(int $applicationId, int $branchId): ?array
    {
        if ($applicationId <= 0 || $branchId <= 0) {
            return null;
        }

        $request = db_connect()->table('service_applications sa')
            ->select('sa.application_id, sa.plan_holder_id, sa.package_id, sa.service_list_id, sa.status, ph.branch_id, ph.user_id, p.base_price AS package_price, p.package_name, sl.base_price AS service_price, sl.service_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = sa.plan_holder_id', 'inner')
            ->join('packages p', 'p.package_id = sa.package_id', 'left')
            ->join('service_list sl', 'sl.service_list_id = sa.service_list_id', 'left')
            ->where('sa.application_id', $applicationId)
            ->get()
            ->getRowArray();

        if (! $request || (int) ($request['branch_id'] ?? 0) !== $branchId) {
            return null;
        }

        return $request;
    }
}
