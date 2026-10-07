<?php

namespace App\Services;

use App\Models\ServiceBalanceModel;
use App\Models\ServiceBalancePaymentModel;

class ServiceBalanceService
{
    public const MINIMUM_PAID_MONTHS = 2;
    public const ASSISTANCE_MULTIPLIER = 10.0;
    public const MINIMUM_ASSISTANCE_AMOUNT = 5000.0;
    public const DEFAULT_INSTALLMENT_FLOOR = 1000.0;

    private ServiceBalanceModel $balanceModel;
    private ServiceBalancePaymentModel $paymentModel;
    private MembershipService $membershipService;

    public function __construct()
    {
        $this->balanceModel = new ServiceBalanceModel();
        $this->paymentModel = new ServiceBalancePaymentModel();
        $this->membershipService = new MembershipService();
    }

    public function validateEligibility(int $planHolderId): array
    {
        if ($planHolderId <= 0) {
            return ['valid' => false, 'error' => 'Plan holder profile is missing.'];
        }

        $plan = $this->membershipService->getActivePlan($planHolderId);
        if (! $plan) {
            return ['valid' => false, 'error' => 'Membership must be active before a service can be availed.'];
        }

        $monthsPaid = (int) ($plan['months_paid'] ?? 0);
        $overdueMonths = (int) ($plan['overdue_months'] ?? 0);

        // Active and in-grace plans may avail; lapsed / forfeited follow the one
        // status rules (PlanLedgerService, Config\CareSync) and say why.
        $access = $this->membershipService->serviceAccess($planHolderId);
        if (! $access['allowed']) {
            return ['valid' => false, 'error' => (string) ($access['reason'] ?: 'Membership must be active before a service can be availed.')];
        }

        if ($monthsPaid < self::MINIMUM_PAID_MONTHS) {
            return ['valid' => false, 'error' => 'Minimum of 2 paid monthly contributions is required before availing services.'];
        }

        return [
            'valid' => true,
            'plan' => $plan,
            'months_paid' => $monthsPaid,
            'monthly_fee' => (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE),
            'overdue_months' => $overdueMonths,
        ];
    }

    public function calculateCoverage(float $packageCost, int $monthsPaid, float $monthlyFee): array
    {
        $packageCost = max(0.0, $packageCost);
        $monthlyFee = max(0.0, $monthlyFee);
        $monthsPaid = max(0, $monthsPaid);

        $totalContributions = $monthsPaid * $monthlyFee;
        $assistanceAmount = min($packageCost, max(self::MINIMUM_ASSISTANCE_AMOUNT, $totalContributions * self::ASSISTANCE_MULTIPLIER));
        $remainingBalance = max(0.0, $packageCost - $assistanceAmount);
        $installmentAmount = $remainingBalance > 0
            ? max(self::DEFAULT_INSTALLMENT_FLOOR, (float) round($remainingBalance / 10, -2))
            : 0.0;

        return [
            'total_contributions' => round($totalContributions, 2),
            'assistance_amount' => round($assistanceAmount, 2),
            'remaining_balance' => round($remainingBalance, 2),
            'installment_amount' => round($installmentAmount, 2),
            'due_date' => $remainingBalance > 0 ? date('Y-m-d', strtotime('+30 days')) : null,
            'next_due_date' => $remainingBalance > 0 ? date('Y-m-d', strtotime('+30 days')) : null,
        ];
    }

    public function createBalanceRecord(array $application, ?array $serviceRecord = null): ?int
    {
        $planHolderId = (int) ($application['plan_holder_id'] ?? 0);
        $eligibility = $this->validateEligibility($planHolderId);
        if (! ($eligibility['valid'] ?? false)) {
            return null;
        }

        $packageCost = (float) ($application['package_cost'] ?? $application['price'] ?? 0);
        $coverage = $this->calculateCoverage(
            $packageCost,
            (int) ($eligibility['months_paid'] ?? 0),
            (float) ($eligibility['monthly_fee'] ?? MembershipService::MONTHLY_FEE)
        );

        $beneficiary = $this->resolvePrimaryBeneficiary($planHolderId);

        // Prefer beneficiary details supplied in the application (PHASE 6) if present
        if (! empty($application['beneficiary_name'])) {
            $beneficiary['beneficiary_user_id'] = $beneficiary['beneficiary_user_id'] ?? null;
            $beneficiary['beneficiary_name'] = trim((string) $application['beneficiary_name']);
            // application may not provide relationship field; keep existing if available
            $beneficiary['beneficiary_relationship'] = $beneficiary['beneficiary_relationship'] ?? (string) ($application['relationship_to_deceased'] ?? '');
        }
        $serviceType = (string) ($application['service_type'] ?? ((int) ($application['service_list_id'] ?? 0) > 0 ? 'service' : 'package'));
        $serviceName = (string) ($application['service_name'] ?? $application['package_name'] ?? 'Selected service');
        $packageName = (string) ($application['package_name'] ?? null);

        $data = [
            'application_id' => (int) ($application['application_id'] ?? 0),
            'service_id' => (int) ($serviceRecord['service_id'] ?? 0) ?: null,
            'plan_holder_id' => $planHolderId,
            'branch_id' => (int) ($application['branch_id'] ?? 0),
            'service_type' => $serviceType,
            'service_name' => $serviceName,
            'package_name' => $packageName !== '' ? $packageName : null,
            'package_cost' => round($packageCost, 2),
            'monthly_fee' => round((float) ($eligibility['monthly_fee'] ?? MembershipService::MONTHLY_FEE), 2),
            'months_paid' => (int) ($eligibility['months_paid'] ?? 0),
            'total_contributions' => $coverage['total_contributions'],
            'assistance_amount' => $coverage['assistance_amount'],
            'remaining_balance' => $coverage['remaining_balance'],
            'installment_amount' => $coverage['installment_amount'],
            'due_date' => $coverage['due_date'],
            'next_due_date' => $coverage['next_due_date'],
            'beneficiary_user_id' => $beneficiary['beneficiary_user_id'] ?? null,
            'beneficiary_name' => $beneficiary['beneficiary_name'] ?? null,
            'beneficiary_relationship' => $beneficiary['beneficiary_relationship'] ?? null,
            'status' => $coverage['remaining_balance'] > 0 ? 'pending_acknowledgment' : 'completed',
        ];

        $balanceId = (int) $this->balanceModel->insert($data);

        if ($balanceId <= 0) {
            return null;
        }

        return $balanceId;
    }

    public function acknowledgeBalance(int $balanceId, int $userId, ?string $notes = null): bool
    {
        if ($balanceId <= 0 || $userId <= 0) {
            return false;
        }

        $balance = $this->balanceModel->find($balanceId);
        if (! $balance) {
            return false;
        }

        $status = ((float) ($balance['remaining_balance'] ?? 0) <= 0) ? 'completed' : 'active';

        return (bool) $this->balanceModel->update($balanceId, [
            'beneficiary_acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledged_by' => $userId,
            'acknowledgement_notes' => $notes,
            'status' => $status,
        ]);
    }

    public function recordPayment(int $balanceId, array $paymentData): bool
    {
        if ($balanceId <= 0) {
            return false;
        }

        $balance = $this->balanceModel->find($balanceId);
        if (! $balance) {
            return false;
        }

        $amount = max(0.0, (float) ($paymentData['amount'] ?? 0));
        if ($amount <= 0) {
            return false;
        }

        $paymentId = (int) $this->paymentModel->insert([
            'service_balance_id' => $balanceId,
            'paid_by_user_id' => (int) ($paymentData['paid_by_user_id'] ?? 0) ?: null,
            'amount' => round($amount, 2),
            'reference_number' => trim((string) ($paymentData['reference_number'] ?? '')) ?: null,
            'payment_method' => trim((string) ($paymentData['payment_method'] ?? '')) ?: null,
            'due_date' => $paymentData['due_date'] ?? ($balance['next_due_date'] ?? null),
            'paid_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string) ($paymentData['notes'] ?? '')) ?: null,
            'status' => 'paid',
        ]);

        if ($paymentId <= 0) {
            return false;
        }

        $paidRow = $this->paymentModel->selectSum('amount', 'paid_amount')
            ->where('service_balance_id', $balanceId)
            ->where('status', 'paid')
            ->first();
        $paidTotal = (float) ($paidRow['paid_amount'] ?? 0);

        $remainingBalance = max(0.0, (float) ($balance['package_cost'] ?? 0) - (float) ($balance['assistance_amount'] ?? 0) - $paidTotal);

        return (bool) $this->balanceModel->update($balanceId, [
            'remaining_balance' => round($remainingBalance, 2),
            'next_due_date' => $remainingBalance > 0 ? date('Y-m-d', strtotime('+30 days')) : null,
            'status' => $remainingBalance <= 0 ? 'completed' : 'active',
        ]);
    }

    private function resolvePrimaryBeneficiary(int $planHolderId): array
    {
        $db = db_connect();

        if (! $db->tableExists('beneficiaries')) {
            return [];
        }

        $fields = ['beneficiary_id', 'first_name', 'middle_name', 'last_name', 'relationship'];
        if ($db->fieldExists('is_primary', 'beneficiaries')) {
            $fields[] = 'is_primary';
        }

        $builder = $db->table('beneficiaries')
            ->select(implode(', ', $fields));

        if ($db->fieldExists('is_primary', 'beneficiaries')) {
            $builder->where('plan_holder_id', $planHolderId)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('beneficiary_id', 'ASC');
        } else {
            $builder->where('plan_holder_id', $planHolderId)
                ->orderBy('beneficiary_id', 'ASC');
        }

        $beneficiary = $builder->get()->getRowArray();
        if (! $beneficiary) {
            return [];
        }

        $nameParts = array_filter([
            trim((string) ($beneficiary['first_name'] ?? '')),
            trim((string) ($beneficiary['middle_name'] ?? '')),
            trim((string) ($beneficiary['last_name'] ?? '')),
        ]);

        return [
            'beneficiary_user_id' => null,
            'beneficiary_name' => trim(implode(' ', $nameParts)),
            'beneficiary_relationship' => (string) ($beneficiary['relationship'] ?? ''),
        ];
    }
}