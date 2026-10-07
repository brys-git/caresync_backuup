<?php

namespace App\Services;

/**
 * CommissionService
 *
 * The single source of collector commission math and eligibility. Before
 * this existed, the admin Commission Report (ReportService::
 * getCommissionReport()) counted every verified payment where received_by
 * matched, which included desk cash/GCash a Branch Admin recorded through
 * Payment Tracking - not just cash a collector actually collected in the
 * field. The rule here, used everywhere commission is shown or computed:
 *
 *   Commission = 10% of VERIFIED (status='paid') CASH payments where
 *   payments.received_by is the collector. A client's own GCash payment,
 *   or desk cash/GCash recorded by someone else, never earns commission.
 *
 * Pending (unverified) collections are surfaced separately as "not yet
 * earned" - never included in the commission total itself.
 */
class CommissionService
{
    /** Reference the one rate constant, don't redefine it. */
    public const RATE = ReportService::COMMISSION_RATE;

    public function forPayment(float $amount): float
    {
        return round($amount * self::RATE, 2);
    }

    /**
     * Base query for one collector's eligible payments - cash, this
     * collector, a given verification status (default 'paid'), optionally
     * date-bounded. The shared eligibility rule every method below builds
     * on, so the collector's own page and the admin report can never
     * silently drift apart on what counts.
     */
    public function eligiblePaymentsQuery(int $collectorUserId, ?string $from, ?string $to, string $status = 'paid')
    {
        $builder = db_connect()->table('payments p')
            ->join('plans pl', 'pl.plan_id = p.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users cu', 'cu.user_id = ph.user_id', 'inner')
            ->where('p.received_by', $collectorUserId)
            ->where('p.payment_method', 'cash')
            ->where('p.status', $status);

        if (! empty($from)) {
            $builder->where('p.payment_date >=', $from);
        }

        if (! empty($to)) {
            $builder->where('p.payment_date <=', $to);
        }

        return $builder;
    }

    /**
     * One collector's verified collections, grouped by plan/client - the
     * "Commission per client" table on their own commission page.
     */
    public function perClient(int $collectorUserId, ?string $from, ?string $to): array
    {
        $rows = $this->eligiblePaymentsQuery($collectorUserId, $from, $to, 'paid')
            ->select('pl.plan_id, ph.plan_holder_id, ph.unique_identifier, ph.address_barangay, cu.first_name, cu.last_name, COUNT(p.payment_id) AS payments_count, COALESCE(SUM(p.months_covered), 0) AS months_total, COALESCE(SUM(p.amount), 0) AS collected, MAX(p.payment_date) AS last_collection_date', false)
            ->groupBy('pl.plan_id, ph.plan_holder_id, ph.unique_identifier, ph.address_barangay, cu.first_name, cu.last_name')
            ->orderBy('last_collection_date', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['plan_id'] = (int) $row['plan_id'];
            $row['plan_holder_id'] = (int) $row['plan_holder_id'];
            $row['plan_number'] = (string) ($row['unique_identifier'] ?: '-');
            $row['barangay'] = (string) ($row['address_barangay'] ?: '-');
            $row['client_name'] = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
            $row['payments_count'] = (int) $row['payments_count'];
            $row['months_total'] = (int) $row['months_total'];
            $row['collected'] = (float) $row['collected'];
            $row['commission'] = $this->forPayment($row['collected']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Every pending (awaiting verification) cash payment this collector
     * has recorded - "not yet earned" until a branch admin verifies it.
     */
    public function pendingPayments(int $collectorUserId, ?string $from, ?string $to): array
    {
        $rows = $this->eligiblePaymentsQuery($collectorUserId, $from, $to, 'pending')
            ->select('p.payment_id, p.payment_date, p.amount, p.official_receipt_number, cu.first_name, cu.last_name', false)
            ->orderBy('p.payment_date', 'DESC')
            ->orderBy('p.payment_id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['amount'] = (float) $row['amount'];
            $row['projected_commission'] = $this->forPayment($row['amount']);
            $row['client_name'] = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{collected: float, commission: float, payments: int, clients: int, pending_amount: float, pending_commission: float}
     */
    public function totals(int $collectorUserId, ?string $from, ?string $to): array
    {
        $paidRow = $this->eligiblePaymentsQuery($collectorUserId, $from, $to, 'paid')
            ->select('COUNT(DISTINCT p.payment_id) AS payments, COUNT(DISTINCT pl.plan_id) AS clients, COALESCE(SUM(p.amount), 0) AS collected', false)
            ->get()
            ->getRowArray();

        $pendingRow = $this->eligiblePaymentsQuery($collectorUserId, $from, $to, 'pending')
            ->select('COALESCE(SUM(p.amount), 0) AS pending_amount', false)
            ->get()
            ->getRowArray();

        $collected = (float) ($paidRow['collected'] ?? 0);
        $pendingAmount = (float) ($pendingRow['pending_amount'] ?? 0);

        return [
            'collected' => $collected,
            'commission' => $this->forPayment($collected),
            'payments' => (int) ($paidRow['payments'] ?? 0),
            'clients' => (int) ($paidRow['clients'] ?? 0),
            'pending_amount' => $pendingAmount,
            'pending_commission' => $this->forPayment($pendingAmount),
        ];
    }

    /**
     * Cross-collector version of the same eligibility rule, grouped by
     * collector instead of scoped to one - what ReportService::
     * getCommissionReport() (the admin/branch Commission Report) uses, so
     * that report and this collector's own page always agree on the same
     * date range. Same return shape ReportService::getCommissionReport()
     * already had (via getCollectionsByCollector() + its own commission
     * calc) - user_id/first_name/last_name/role_name/transaction_count/
     * total_collected, plus commission/net_remitted.
     */
    public function reportRows(?int $branchId, ?string $from, ?string $to): array
    {
        $builder = db_connect()->table('payments p')
            ->select('u.user_id, u.first_name, u.last_name, r.role_name, COUNT(p.payment_id) AS transaction_count, COALESCE(SUM(p.amount), 0) AS total_collected', false)
            ->join('users u', 'u.user_id = p.received_by', 'inner')
            ->join('roles r', 'r.role_id = u.role_id', 'left')
            ->where('p.payment_method', 'cash')
            ->where('p.status', 'paid');

        if (! empty($branchId)) {
            $builder->where('p.branch_id', $branchId);
        }

        if (! empty($from)) {
            $builder->where('p.payment_date >=', $from);
        }

        if (! empty($to)) {
            $builder->where('p.payment_date <=', $to);
        }

        $rows = $builder->groupBy('u.user_id, u.first_name, u.last_name, r.role_name')
            ->orderBy('total_collected', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['user_id'] = (int) $row['user_id'];
            $row['transaction_count'] = (int) $row['transaction_count'];
            $row['total_collected'] = (float) $row['total_collected'];
            $row['commission'] = $this->forPayment($row['total_collected']);
            $row['net_remitted'] = round($row['total_collected'] - $row['commission'], 2);
        }
        unset($row);

        return $rows;
    }
}
