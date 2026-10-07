<?php

namespace App\Services;

use App\Libraries\SpreadsheetReader;

/**
 * "Existing member records": past plan details, payments, remittances,
 * services, documents and notes for members who were with Kaagapay before
 * CareSync. See the migration CreateLegacyMemberRecords for why past
 * payments live in their own table.
 *
 * Rules enforced here (not in the controller, so every entry point - the
 * manual forms and the file import - obeys the same ones):
 *  - Branch scoping: Branch Admin / Staff only reach clients of their branch.
 *  - Staff-encoded rows are saved 'pending_verification'; Branch Admin
 *    (and System Admin) rows are 'verified' immediately.
 *  - Only 'verified' rows count toward the client's totals.
 *  - Nothing here sends a notification or touches commissions/remittance
 *    reports; no live `payments` row is ever written.
 *  - Every add / edit / delete / verify / reject / import is audit-logged.
 */
class LegacyRecordService
{
    public const CHANNELS = [
        'branch'    => 'Cash at branch',
        'collector' => 'Collector',
        'gcash'     => 'GCash',
        'bank'      => 'Bank',
    ];

    public const FREQUENCIES = [
        'monthly'     => 'Monthly',
        'quarterly'   => 'Quarterly',
        'semi_annual' => 'Semi-annual',
        'annual'      => 'Annual',
    ];

    public const PLAN_STATUSES = [
        'active'     => 'Active',
        'lapsed'     => 'Lapsed',
        'forfeited'  => 'Forfeited',
        'fully_paid' => 'Fully paid',
    ];

    public const SERVICE_STATUSES = [
        'completed' => 'Completed',
        'ongoing'   => 'Ongoing',
        'cancelled' => 'Cancelled',
    ];

    public const DOC_TYPES = [
        'ledger_card' => 'Old ledger card',
        'contract'    => 'Contract',
        'receipt'     => 'Receipt',
        'id'          => 'ID',
        'other'       => 'Other',
    ];

    /** type => [table, primary key] for the verification queue. */
    public const TYPES = [
        'plan'        => ['legacy_plan_records', 'legacy_plan_record_id', 'Plan details'],
        'payment'     => ['legacy_payments', 'legacy_payment_id', 'Payment'],
        'remittance'  => ['legacy_remittances', 'legacy_remittance_id', 'Remittance'],
        'service'     => ['legacy_services', 'legacy_service_id', 'Service / claim'],
        'document'    => ['legacy_documents', 'legacy_document_id', 'Document'],
        'note'        => ['legacy_notes', 'legacy_note_id', 'Note'],
        'beneficiary' => ['beneficiaries', 'beneficiary_id', 'Beneficiary'],
    ];

    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5 MB per document
    private const ALLOWED_DOC_EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    private static ?bool $tablesReady = null;

    // =====================================================================
    //  Read helpers used by the live plan code (months paid, balances, history)
    // =====================================================================

    /** False until the migration has run, so the live app keeps working either way. */
    public static function ready(): bool
    {
        if (self::$tablesReady === null) {
            try {
                self::$tablesReady = db_connect()->tableExists('legacy_payments');
            } catch (\Throwable $e) {
                self::$tablesReady = false;
            }
        }

        return self::$tablesReady;
    }

    /** Verified past months in the plan's current cycle (for MembershipService::recalculateMonthsPaid). */
    public static function verifiedMonths(int $planId, int $cycleNumber, string $since): int
    {
        if (! self::ready() || $planId <= 0) {
            return 0;
        }

        return (int) (db_connect()->table('legacy_payments')
            ->selectSum('months_covered')
            ->where('plan_id', $planId)
            ->where('record_status', 'verified')
            ->where('cycle_number', $cycleNumber)
            ->where('payment_date >=', $since)
            ->get()
            ->getRowArray()['months_covered'] ?? 0);
    }

    /** Verified past money (for PaymentService::getTotalPaidForPlan). */
    public static function verifiedAmount(int $planId): float
    {
        if (! self::ready() || $planId <= 0) {
            return 0.0;
        }

        return (float) (db_connect()->table('legacy_payments')
            ->selectSum('amount')
            ->where('plan_id', $planId)
            ->where('record_status', 'verified')
            ->get()
            ->getRowArray()['amount'] ?? 0);
    }

    /**
     * Verified past payments shaped like live `payments` rows, labelled so
     * every screen can say "Recorded by branch".
     *
     * @return list<array<string,mixed>>
     */
    public static function verifiedPaymentsAsRows(int $planId): array
    {
        if (! self::ready() || $planId <= 0) {
            return [];
        }

        $rows = db_connect()->table('legacy_payments')
            ->where('plan_id', $planId)
            ->where('record_status', 'verified')
            ->orderBy('payment_date', 'DESC')
            ->orderBy('legacy_payment_id', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $r): array {
            $start = sprintf('%04d-%02d-01', (int) $r['period_year'], (int) $r['period_month']);

            return [
                'payment_id'              => 'L' . $r['legacy_payment_id'],
                'plan_id'                 => (int) $r['plan_id'],
                'payment_date'            => $r['payment_date'],
                'amount'                  => $r['amount'],
                'months_covered'          => (int) $r['months_covered'],
                'coverage_start'          => $start,
                'payment_method'          => $r['channel'] === 'gcash' ? 'gcash' : ($r['channel'] === 'bank' ? 'bank' : 'cash'),
                'reference_number'        => $r['or_number'],
                'official_receipt_number' => $r['or_number'],
                'remarks'                 => $r['remarks'],
                'status'                  => 'paid',
                'created_at'              => $r['encoded_at'],
                'receiver_first_name'     => 'Recorded by branch',
                'receiver_last_name'      => '',
                'is_legacy'               => true,
                'recorded_label'          => 'Recorded by branch',
                'channel_label'           => self::CHANNELS[$r['channel']] ?? $r['channel'],
            ];
        }, $rows);
    }

    // =====================================================================
    //  Clients
    // =====================================================================

    /** @return array{user_id:int, role_id:int, branch_id:?int}|null */
    private function actorContext(array $actor): array
    {
        return [
            'user_id'   => (int) ($actor['user_id'] ?? 0),
            'role_id'   => (int) ($actor['role_id'] ?? 0),
            'branch_id' => isset($actor['branch_id']) && $actor['branch_id'] !== null ? (int) $actor['branch_id'] : null,
        ];
    }

    private function isAdminRole(array $actor): bool
    {
        return in_array((int) $actor['role_id'], [1, 2], true);
    }

    /**
     * Client accounts (role 4) the actor may work on, matched by name,
     * mobile number, email or account (plan) number.
     *
     * @return list<array<string,mixed>>
     */
    public function searchClients(string $term, array $actor, int $limit = 25): array
    {
        $actor = $this->actorContext($actor);

        $builder = db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.account_status, ph.plan_holder_id, ph.unique_identifier, ph.status AS plan_holder_status, COALESCE(ph.branch_id, u.branch_id) AS client_branch_id, b.branch_name')
            ->join('plan_holders ph', 'ph.user_id = u.user_id', 'left')
            ->join('branches b', 'b.branch_id = COALESCE(ph.branch_id, u.branch_id)', 'left')
            ->where('u.role_id', 4);

        $this->scopeToActorBranch($builder, $actor);

        cs_apply_search($builder, $term, ['u.first_name', 'u.last_name', 'u.contact_number', 'u.email', 'ph.unique_identifier']);

        return $builder->orderBy('u.first_name', 'ASC')->orderBy('u.last_name', 'ASC')->limit($limit)->get()->getResultArray();
    }

    private function scopeToActorBranch($builder, array $actor): void
    {
        if ((int) $actor['role_id'] === 1) {
            return;
        }

        $branch = (int) ($actor['branch_id'] ?? 0);
        // No branch => nothing is visible (never "everything").
        $builder->groupStart()
            ->where('ph.branch_id', $branch)
            ->orGroupStart()->where('ph.plan_holder_id IS NULL', null, false)->where('u.branch_id', $branch)->groupEnd()
            ->groupEnd();
    }

    /** One client the actor may work on (or null - same answer for "missing" and "other branch"). */
    public function findClient(int $userId, array $actor): ?array
    {
        $actor = $this->actorContext($actor);

        $builder = db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.account_status, u.branch_id AS user_branch_id, ph.plan_holder_id, ph.unique_identifier, ph.status AS plan_holder_status, COALESCE(ph.branch_id, u.branch_id) AS client_branch_id, b.branch_name')
            ->join('plan_holders ph', 'ph.user_id = u.user_id', 'left')
            ->join('branches b', 'b.branch_id = COALESCE(ph.branch_id, u.branch_id)', 'left')
            ->where('u.role_id', 4)
            ->where('u.user_id', $userId);

        $this->scopeToActorBranch($builder, $actor);

        $client = $builder->get()->getRowArray();
        if (! $client) {
            return null;
        }

        $client['plan'] = null;
        if (! empty($client['plan_holder_id'])) {
            $client['plan'] = db_connect()->table('plans')
                ->where('plan_holder_id', (int) $client['plan_holder_id'])
                ->orderBy('plan_id', 'DESC')
                ->get()
                ->getRowArray() ?: null;
        }

        return $client;
    }

    /**
     * Make sure the client has a plan_holders row and a plan to hang records
     * on. They are created inactive - nothing about the client's access
     * changes until a verified plan record applies.
     *
     * @return array{plan_holder_id:int, plan_id:int, branch_id:int}
     */
    public function ensureRegistration(array $client, array $actor): array
    {
        $actor = $this->actorContext($actor);
        $db    = db_connect();

        $branchId = (int) ($client['client_branch_id'] ?? 0) ?: (int) ($actor['branch_id'] ?? 0);
        if ($branchId <= 0) {
            throw new \RuntimeException('This client has no branch. Assign a branch before encoding past records.');
        }

        $planHolderId = (int) ($client['plan_holder_id'] ?? 0);
        $registration = new ClientRegistrationService();

        if ($planHolderId <= 0) {
            $db->table('plan_holders')->insert([
                'user_id'           => (int) $client['user_id'],
                'registered_by'     => $actor['user_id'],
                'branch_id'         => $branchId,
                'status'            => 'inactive',
                'unique_identifier' => $registration->generateUniquePlanNumber($branchId),
            ]);
            $planHolderId = (int) $db->insertID();

            if (empty($client['user_branch_id'])) {
                $db->table('users')->where('user_id', (int) $client['user_id'])->update(['branch_id' => $branchId]);
            }
        }

        $plan = $db->table('plans')->select('plan_id')->where('plan_holder_id', $planHolderId)->orderBy('plan_id', 'DESC')->get()->getRowArray();
        if (! $plan) {
            $created = $registration->createMembershipPlan($planHolderId, $branchId, $actor['user_id']);
            if (empty($created['success'])) {
                throw new \RuntimeException('Could not prepare a plan for this client: ' . ($created['error'] ?? 'unknown error'));
            }
            $plan = ['plan_id' => (int) $created['plan_id']];
        }

        return ['plan_holder_id' => $planHolderId, 'plan_id' => (int) $plan['plan_id'], 'branch_id' => $branchId];
    }

    // =====================================================================
    //  Status / audit helpers
    // =====================================================================

    /** Staff records wait for Branch Admin; Branch Admin / System Admin are trusted immediately. */
    public function statusFor(array $actor): string
    {
        return $this->isAdminRole($this->actorContext($actor)) ? 'verified' : 'pending_verification';
    }

    private function auditColumns(array $actor, string $source): array
    {
        $actor  = $this->actorContext($actor);
        $status = $this->statusFor($actor);
        $now    = date('Y-m-d H:i:s');

        return [
            'record_status' => $status,
            'source'        => $source,
            'encoded_by'    => $actor['user_id'],
            'encoded_at'    => $now,
            'verified_by'   => $status === 'verified' ? $actor['user_id'] : null,
            'verified_at'   => $status === 'verified' ? $now : null,
        ];
    }

    private function log(array $actor, string $action, string $module, int $targetId, string $description, ?array $new = null, ?array $old = null): void
    {
        (new ActivityLogService())->log((int) $actor['user_id'], $action, $module, $targetId, $description, $old, $new);
    }

    // =====================================================================
    //  1. Membership and plan
    // =====================================================================

    public function getPlanRecord(int $planHolderId): ?array
    {
        return db_connect()->table('legacy_plan_records')->where('plan_holder_id', $planHolderId)->get()->getRowArray() ?: null;
    }

    /** @return array{ok:bool, errors:list<string>, warnings:list<string>} */
    public function savePlanRecord(array $client, array $input, array $actor, int $userIdForLog): array
    {
        $errors = [];

        $date = trim((string) ($input['original_membership_date'] ?? ''));
        if (! $this->validDate($date) || $date > date('Y-m-d')) {
            $errors[] = 'Original membership date must be a valid date that is not in the future.';
        }

        $monthly = (float) str_replace(',', '', (string) ($input['monthly_contribution'] ?? ''));
        if ($monthly <= 0 || $monthly > 100000) {
            $errors[] = 'Monthly contribution must be greater than zero.';
        }

        $frequency = (string) ($input['payment_frequency'] ?? 'monthly');
        if (! isset(self::FREQUENCIES[$frequency])) {
            $errors[] = 'Choose a valid payment frequency.';
        }

        $status = (string) ($input['plan_status'] ?? '');
        if (! isset(self::PLAN_STATUSES[$status])) {
            $errors[] = 'Choose the current plan status.';
        }

        $total = trim((string) ($input['total_contract_amount'] ?? ''));
        $totalValue = $total === '' ? null : (float) str_replace(',', '', $total);
        if ($totalValue !== null && ($totalValue <= 0 || $totalValue > 10000000)) {
            $errors[] = 'Total contract amount must be greater than zero.';
        }

        $declared = trim((string) ($input['declared_payments_made'] ?? ''));
        if ($declared !== '' && (! ctype_digit($declared) || (int) $declared > 600)) {
            $errors[] = 'Number of payments made must be a whole number.';
        }

        $contract = trim((string) ($input['contract_number'] ?? ''));
        if (mb_strlen($contract) > 60) {
            $errors[] = 'Plan / contract number must be 60 characters or fewer.';
        }

        $planName = trim((string) ($input['plan_name'] ?? ''));
        if (mb_strlen($planName) > 150) {
            $errors[] = 'Plan name must be 150 characters or fewer.';
        }

        $packageId = (int) ($input['package_id'] ?? 0);
        if ($packageId > 0 && ! db_connect()->table('packages')->where('package_id', $packageId)->countAllResults()) {
            $errors[] = 'The selected package does not exist.';
        }

        $branchId = (int) ($input['original_branch_id'] ?? 0);
        if ($branchId > 0 && ! db_connect()->table('branches')->where('branch_id', $branchId)->countAllResults()) {
            $errors[] = 'The selected original branch does not exist.';
        }

        $collectorId = (int) ($input['assigned_collector_id'] ?? 0);
        if ($collectorId > 0) {
            $ok = db_connect()->table('users')->where('user_id', $collectorId)->whereIn('role_id', [3, 5])->countAllResults();
            if (! $ok) {
                $errors[] = 'The selected collector does not exist.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'warnings' => []];
        }

        $reg = $this->ensureRegistration($client, $actor);

        $existing = $this->getPlanRecord($reg['plan_holder_id']);
        $row = [
            'plan_holder_id'           => $reg['plan_holder_id'],
            'plan_id'                  => $reg['plan_id'],
            'package_id'               => $packageId > 0 ? $packageId : null,
            'plan_name'                => $planName !== '' ? $planName : null,
            'contract_number'          => $contract !== '' ? $contract : null,
            'original_membership_date' => $date,
            'monthly_contribution'     => number_format($monthly, 2, '.', ''),
            'payment_frequency'        => $frequency,
            'total_contract_amount'    => $totalValue !== null ? number_format($totalValue, 2, '.', '') : null,
            'declared_payments_made'   => $declared !== '' ? (int) $declared : null,
            'plan_status'              => $status,
            'assigned_collector_id'    => $collectorId > 0 ? $collectorId : null,
            'original_branch_id'       => $branchId > 0 ? $branchId : null,
        ] + $this->auditColumns($actor, 'legacy_manual');

        $db = db_connect();
        if ($existing) {
            $db->table('legacy_plan_records')->where('legacy_plan_record_id', (int) $existing['legacy_plan_record_id'])->update($row + ['rejection_reason' => null]);
            $id = (int) $existing['legacy_plan_record_id'];
            $this->log($actor, 'updated', 'legacy_plan_record', $id, 'Edited past plan details for client #' . $userIdForLog, $row, $existing);
        } else {
            $db->table('legacy_plan_records')->insert($row);
            $id = (int) $db->insertID();
            $this->log($actor, 'created', 'legacy_plan_record', $id, 'Encoded past plan details for client #' . $userIdForLog, $row);
        }

        $warnings = [];
        if ($row['record_status'] === 'verified') {
            $warnings = $this->applyPlanRecord($id);
        }

        return ['ok' => true, 'errors' => [], 'warnings' => $warnings];
    }

    /**
     * Push a VERIFIED plan record onto the real plan / plan holder / user, then
     * recompute the totals. Returns human-readable warnings.
     *
     * @return list<string>
     */
    public function applyPlanRecord(int $recordId): array
    {
        $db  = db_connect();
        $rec = $db->table('legacy_plan_records')->where('legacy_plan_record_id', $recordId)->get()->getRowArray();
        if (! $rec || $rec['record_status'] !== 'verified') {
            return [];
        }

        $holder = $db->table('plan_holders')->where('plan_holder_id', (int) $rec['plan_holder_id'])->get()->getRowArray();
        $planId = (int) $rec['plan_id'];

        $planUpdate = [
            'monthly_fee' => $rec['monthly_contribution'],
            'start_date'  => $rec['original_membership_date'],
        ];
        if (! empty($rec['package_id'])) {
            $planUpdate['package_id'] = (int) $rec['package_id'];
        }
        $db->table('plans')->where('plan_id', $planId)->update($planUpdate);

        // Becoming a real, active member: the same flags a normal approved
        // registration sets, so the client lands on the right dashboard.
        $db->table('plan_holders')->where('plan_holder_id', (int) $rec['plan_holder_id'])->update(['status' => 'active']);
        $db->table('users')->where('user_id', (int) $holder['user_id'])->update(['is_plan_holder' => 1, 'account_status' => 'verified']);

        return $this->recompute($planId);
    }

    // =====================================================================
    //  Recompute plan totals from live + verified past records
    // =====================================================================

    /**
     * Recompute a plan after past records changed. All the arithmetic
     * (oldest unpaid first, grace period, lapse) lives in PlanLedgerService;
     * this only asks it to refresh the plan quietly - quiet means the
     * overdue reminders for the episode already in progress are marked as
     * sent, so encoding history never notifies anyone.
     *
     * The status typed on the old record is only a reference: CareSync works
     * out the real status from the payments, the same way everywhere. When
     * the two differ, say so.
     *
     * @return list<string> warnings
     */
    public function recompute(int $planId): array
    {
        $warnings = [];
        $summary = (new PlanLedgerService())->sync($planId, true);
        if ($summary === null) {
            return $warnings;
        }

        $rec = db_connect()->table('legacy_plan_records')->where('plan_id', $planId)->where('record_status', 'verified')->get()->getRowArray();
        if ($rec) {
            $computed = (string) $summary['lifecycle'];
            $declared = (string) $rec['plan_status'];
            $comparable = ['active' => ['active', 'grace'], 'lapsed' => ['lapsed'], 'forfeited' => ['forfeited'], 'fully_paid' => ['fully_paid']];

            if (! in_array($computed, $comparable[$declared] ?? [], true)) {
                $labels = ['active' => 'Active', 'grace' => 'In grace period', 'lapsed' => 'Lapsed', 'forfeited' => 'Forfeited', 'fully_paid' => 'Fully paid'];
                $warnings[] = 'The old record says "' . (self::PLAN_STATUSES[$declared] ?? $declared) . '", but from the payments recorded the plan is "' . ($labels[$computed] ?? $computed) . '". CareSync uses the payments. Add or verify any missing payments if that looks wrong.';
            }
        }

        return $warnings;
    }

    // =====================================================================
    //  2. Beneficiaries
    // =====================================================================

    /** @return list<array<string,mixed>> */
    public function beneficiaries(int $planHolderId): array
    {
        return db_connect()->table('beneficiaries')->where('plan_holder_id', $planHolderId)->orderBy('is_primary', 'DESC')->orderBy('beneficiary_id', 'ASC')->get()->getResultArray();
    }

    /** @return array{ok:bool, errors:list<string>} */
    public function addBeneficiary(array $client, array $input, array $actor): array
    {
        $namePattern = '/^[\p{L}\s.\'-]+$/u';
        $allowed     = config(\Config\Beneficiary::class)->relationships;
        $errors      = [];

        $first = trim((string) ($input['first_name'] ?? ''));
        $mid   = trim((string) ($input['middle_name'] ?? ''));
        $last  = trim((string) ($input['last_name'] ?? ''));
        $dob   = trim((string) ($input['birthday'] ?? ''));
        $rel   = trim((string) ($input['relationship'] ?? ''));
        $relOther = trim((string) ($input['relationship_other'] ?? ''));
        $phone = trim((string) ($input['contact_number'] ?? ''));

        if ($first === '' || $last === '' || $rel === '') {
            $errors[] = 'A beneficiary needs a first name, last name, and relationship.';
        }
        if ($first !== '' && (mb_strlen($first) > 100 || ! preg_match($namePattern, $first))) {
            $errors[] = 'Beneficiary first name must be 100 characters or fewer and contain only letters.';
        }
        if ($mid !== '' && (mb_strlen($mid) > 100 || ! preg_match($namePattern, $mid))) {
            $errors[] = 'Beneficiary middle name must be 100 characters or fewer and contain only letters.';
        }
        if ($last !== '' && (mb_strlen($last) > 100 || ! preg_match($namePattern, $last))) {
            $errors[] = 'Beneficiary last name must be 100 characters or fewer and contain only letters.';
        }
        if ($rel !== '' && ! array_key_exists($rel, $allowed)) {
            $errors[] = 'Please select a valid beneficiary relationship.';
        }
        if ($rel === 'Other') {
            if ($relOther === '' || mb_strlen($relOther) > 50) {
                $errors[] = 'Please specify the beneficiary relationship (50 characters or fewer).';
            } else {
                $rel = $relOther;
            }
        }
        if ($dob !== '' && (! $this->validDate($dob) || $dob > date('Y-m-d'))) {
            $errors[] = 'Beneficiary birthday must be a valid date that is not in the future.';
        }
        if ($phone !== '' && (mb_strlen($phone) > 30 || ! preg_match('/^[0-9+\-\s()]+$/', $phone))) {
            $errors[] = 'Beneficiary contact number may only contain digits, spaces, + - ( ) and be 30 characters or fewer.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $reg = $this->ensureRegistration($client, $actor);
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $actorCtx = $this->actorContext($actor);
        $verified = $this->statusFor($actor) === 'verified';

        $isPrimary = $db->table('beneficiaries')->where('plan_holder_id', $reg['plan_holder_id'])->countAllResults() === 0 ? 1 : 0;

        $db->table('beneficiaries')->insert([
            'plan_holder_id'      => $reg['plan_holder_id'],
            'first_name'          => $first,
            'middle_name'         => $mid !== '' ? $mid : null,
            'last_name'           => $last,
            'name_extension'      => null,
            'date_of_birth'       => $dob !== '' ? $dob : null,
            'relationship'        => $rel,
            'is_primary'          => $isPrimary,
            'contact_number'      => $phone !== '' ? $phone : null,
            'verification_status' => $verified ? 'verified' : 'pending',
            'source'              => 'legacy_manual',
            'encoded_by'          => $actorCtx['user_id'],
            'encoded_at'          => $now,
        ]);
        $id = (int) $db->insertID();

        $this->log($actor, 'created', 'legacy_beneficiary', $id, 'Encoded beneficiary ' . $first . ' ' . $last . ' for client #' . (int) $client['user_id']);

        return ['ok' => true, 'errors' => []];
    }

    // =====================================================================
    //  3. Payment history
    // =====================================================================

    /** @return list<array<string,mixed>> */
    public function payments(int $planHolderId): array
    {
        return db_connect()->table('legacy_payments lp')
            ->select('lp.*, eb.first_name AS encoder_first, eb.last_name AS encoder_last')
            ->join('users eb', 'eb.user_id = lp.encoded_by', 'left')
            ->where('lp.plan_holder_id', $planHolderId)
            ->orderBy('lp.period_year', 'ASC')->orderBy('lp.period_month', 'ASC')->orderBy('lp.legacy_payment_id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Validate one payment row. Returns normalised values or errors.
     *
     * @return array{errors:list<string>, warnings:list<string>, row:array<string,mixed>}
     */
    public function validatePayment(array $in, int $branchId, int $planHolderId, ?int $ignoreId = null): array
    {
        $errors = [];
        $warnings = [];

        $date = trim((string) ($in['payment_date'] ?? ''));
        if (! $this->validDate($date)) {
            $errors[] = 'Invalid date';
        } elseif ($date > date('Y-m-d')) {
            $errors[] = 'Payment date is in the future';
        } elseif ($date < '1990-01-01') {
            $errors[] = 'Payment date is too old';
        }

        $amountRaw = str_replace(',', '', trim((string) ($in['amount'] ?? '')));
        if (! is_numeric($amountRaw) || (float) $amountRaw <= 0 || (float) $amountRaw > 1000000) {
            $errors[] = 'Invalid amount';
        }
        $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;

        $month = (int) ($in['period_month'] ?? 0);
        $year  = (int) ($in['period_year'] ?? 0);
        if ($month < 1 || $month > 12) {
            $errors[] = 'Invalid period month';
        }
        if ($year < 1990 || $year > (int) date('Y') + 1) {
            $errors[] = 'Invalid period year';
        }

        $monthsCovered = (int) (($in['months_covered'] ?? '') === '' ? 1 : $in['months_covered']);
        if ($monthsCovered < 1 || $monthsCovered > 60) {
            $errors[] = 'Months covered must be between 1 and 60';
        }

        $channel = strtolower(trim((string) ($in['channel'] ?? 'branch')));
        if (! isset(self::CHANNELS[$channel])) {
            $errors[] = 'Invalid payment method';
        }

        $or = trim((string) ($in['or_number'] ?? ''));
        if (mb_strlen($or) > 100) {
            $errors[] = 'OR number is too long';
        }

        $collectedBy = trim((string) ($in['collected_by'] ?? ''));
        if (mb_strlen($collectedBy) > 120) {
            $errors[] = 'Collected/received by is too long';
        }

        $remarks = trim((string) ($in['remarks'] ?? ''));
        if (mb_strlen($remarks) > 255) {
            $errors[] = 'Remarks are too long';
        }

        if ($or !== '' && $errors === []) {
            $dup = db_connect()->table('legacy_payments')
                ->where('branch_id', $branchId)->where('or_number', $or)->where('record_status !=', 'rejected');
            if ($ignoreId) {
                $dup->where('legacy_payment_id !=', $ignoreId);
            }
            $live = db_connect()->table('payments')->where('branch_id', $branchId)->where('official_receipt_number', $or)->countAllResults();
            if ($dup->countAllResults() > 0 || $live > 0) {
                $errors[] = 'Duplicate OR number';
            }
        }

        if ($errors === [] && $planHolderId > 0) {
            $same = db_connect()->table('legacy_payments')
                ->where('plan_holder_id', $planHolderId)->where('period_year', $year)->where('period_month', $month)
                ->where('record_status !=', 'rejected');
            if ($ignoreId) {
                $same->where('legacy_payment_id !=', $ignoreId);
            }
            if ($same->countAllResults() > 0) {
                $warnings[] = 'A payment for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' already exists for this client';
            }
        }

        return [
            'errors'   => $errors,
            'warnings' => $warnings,
            'row'      => [
                'payment_date'   => $date,
                'amount'         => number_format($amount, 2, '.', ''),
                'months_covered' => $monthsCovered,
                'period_month'   => $month,
                'period_year'    => $year,
                'or_number'      => $or !== '' ? $or : null,
                'channel'        => $channel,
                'collected_by'   => $collectedBy !== '' ? $collectedBy : null,
                'remarks'        => $remarks !== '' ? $remarks : null,
            ],
        ];
    }

    /**
     * Add many payment rows for one client in one go. Rows with errors are
     * skipped and reported; valid rows are saved.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{saved:int, errors:array<int,list<string>>, warnings:list<string>, plan_warnings:list<string>}
     */
    public function addPayments(array $client, array $rows, array $actor, string $source = 'legacy_manual', ?string $batch = null): array
    {
        $reg = $this->ensureRegistration($client, $actor);
        $db  = db_connect();
        $plan = $db->table('plans')->select('current_cycle_number')->where('plan_id', $reg['plan_id'])->get()->getRowArray();
        $cycle = (int) ($plan['current_cycle_number'] ?? 1);

        $saved = 0;
        $errors = [];
        $warnings = [];
        $audit = $this->auditColumns($actor, $source);
        $seenOr = [];

        $db->transBegin();
        foreach ($rows as $i => $in) {
            // Entirely empty rows from the "add row" table are ignored.
            if (trim((string) ($in['payment_date'] ?? '')) === '' && trim((string) ($in['amount'] ?? '')) === '') {
                continue;
            }

            $v = $this->validatePayment($in, $reg['branch_id'], $reg['plan_holder_id']);
            $or = $v['row']['or_number'];
            if ($or !== null && isset($seenOr[$or])) {
                $v['errors'][] = 'Duplicate OR number';
            }

            if ($v['errors'] !== []) {
                $errors[$i] = $v['errors'];
                continue;
            }
            if ($or !== null) {
                $seenOr[$or] = true;
            }
            foreach ($v['warnings'] as $w) {
                $warnings[] = 'Row ' . ($i + 1) . ': ' . $w;
            }

            $db->table('legacy_payments')->insert($v['row'] + [
                'plan_holder_id' => $reg['plan_holder_id'],
                'plan_id'        => $reg['plan_id'],
                'branch_id'      => $reg['branch_id'],
                'cycle_number'   => $cycle,
                'import_batch'   => $batch,
            ] + $audit);
            $saved++;
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            throw new \RuntimeException('The payments could not be saved.');
        }
        $db->transCommit();

        $planWarnings = [];
        if ($saved > 0) {
            $this->log($actor, 'created', 'legacy_payment', $reg['plan_holder_id'], ($source === 'legacy_import' ? 'Imported ' : 'Encoded ') . $saved . ' past payment(s) for client #' . (int) $client['user_id'], ['count' => $saved, 'status' => $audit['record_status'], 'batch' => $batch]);
            if ($audit['record_status'] === 'verified') {
                $planWarnings = $this->recompute($reg['plan_id']);
            }
        }

        return ['saved' => $saved, 'errors' => $errors, 'warnings' => $warnings, 'plan_warnings' => $planWarnings];
    }

    /** @return array{ok:bool, errors:list<string>, warnings:list<string>} */
    public function updatePayment(int $id, array $client, array $input, array $actor): array
    {
        $db  = db_connect();
        $row = $db->table('legacy_payments')->where('legacy_payment_id', $id)->get()->getRowArray();
        if (! $row || (int) $row['plan_holder_id'] !== (int) ($client['plan_holder_id'] ?? 0)) {
            return ['ok' => false, 'errors' => ['Record not found.'], 'warnings' => []];
        }

        $v = $this->validatePayment($input, (int) $row['branch_id'], (int) $row['plan_holder_id'], $id);
        if ($v['errors'] !== []) {
            return ['ok' => false, 'errors' => $v['errors'], 'warnings' => []];
        }

        $audit = $this->auditColumns($actor, (string) $row['source']);
        $db->table('legacy_payments')->where('legacy_payment_id', $id)->update($v['row'] + $audit + ['rejection_reason' => null]);
        $this->log($actor, 'updated', 'legacy_payment', $id, 'Edited a past payment for client #' . (int) $client['user_id'], $v['row'], $row);

        $this->recompute((int) $row['plan_id']);

        return ['ok' => true, 'errors' => [], 'warnings' => $v['warnings']];
    }

    /** Months between the original membership date and the last period covered that have no payment. */
    public function missingMonths(int $planHolderId, ?string $membershipDate): array
    {
        $payments = array_filter($this->payments($planHolderId), static fn ($p) => $p['record_status'] !== 'rejected');
        if ($payments === [] || ! $membershipDate) {
            return [];
        }

        $covered = [];
        $last = null;
        foreach ($payments as $p) {
            for ($k = 0; $k < (int) $p['months_covered']; $k++) {
                $ts = mktime(0, 0, 0, (int) $p['period_month'] + $k, 1, (int) $p['period_year']);
                $covered[date('Y-m', $ts)] = true;
                $last = max($last ?? 0, $ts);
            }
        }

        $missing = [];
        $cursor = strtotime(date('Y-m-01', strtotime($membershipDate)));
        while ($cursor <= $last && count($missing) < 240) {
            $key = date('Y-m', $cursor);
            if (! isset($covered[$key])) {
                $missing[] = date('M Y', $cursor);
            }
            $cursor = strtotime('+1 month', $cursor);
        }

        return $missing;
    }

    // =====================================================================
    //  4-6. Remittances, services, notes
    // =====================================================================

    /** @return list<array<string,mixed>> */
    public function rows(string $type, int $planHolderId): array
    {
        [$table, $pk] = self::TYPES[$type];
        $order = match ($type) {
            'remittance' => 'remittance_date',
            'service'    => 'service_date',
            default      => $pk,
        };

        return db_connect()->table($table)->where('plan_holder_id', $planHolderId)->orderBy($order, 'DESC')->get()->getResultArray();
    }

    /** @return array{ok:bool, errors:list<string>} */
    public function addRemittance(array $client, array $in, array $actor): array
    {
        $errors = [];
        $date = trim((string) ($in['remittance_date'] ?? ''));
        if (! $this->validDate($date) || $date > date('Y-m-d')) {
            $errors[] = 'Remittance date must be a valid date that is not in the future.';
        }
        $amount = str_replace(',', '', trim((string) ($in['amount'] ?? '')));
        if (! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 10000000) {
            $errors[] = 'Remitted amount must be greater than zero.';
        }
        $collectorId = (int) ($in['collector_id'] ?? 0);
        $collectorName = trim((string) ($in['collector_name'] ?? ''));
        if ($collectorId > 0) {
            $u = db_connect()->table('users')->select('first_name, last_name')->where('user_id', $collectorId)->whereIn('role_id', [3, 5])->get()->getRowArray();
            if (! $u) {
                $errors[] = 'The selected collector does not exist.';
            } elseif ($collectorName === '') {
                $collectorName = trim($u['first_name'] . ' ' . $u['last_name']);
            }
        }
        if ($collectorId === 0 && $collectorName === '') {
            $errors[] = 'Choose the collector or type the collector\'s name.';
        }
        $ref = trim((string) ($in['reference_number'] ?? ''));
        if (mb_strlen($ref) > 100 || mb_strlen($collectorName) > 120) {
            $errors[] = 'Reference number or collector name is too long.';
        }
        $covers = $in['covers_payments'] ?? [];
        $covers = is_array($covers) ? $covers : [];
        $reg = null;
        if ($errors === []) {
            $reg = $this->ensureRegistration($client, $actor);
            $valid = db_connect()->table('legacy_payments')->select('legacy_payment_id')->where('plan_holder_id', $reg['plan_holder_id'])->whereIn('legacy_payment_id', array_map('intval', $covers) ?: [0])->get()->getResultArray();
            $covers = array_map(static fn ($r) => (int) $r['legacy_payment_id'], $valid);
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $remarks = trim((string) ($in['remarks'] ?? ''));
        $db = db_connect();
        $db->table('legacy_remittances')->insert([
            'plan_holder_id'   => $reg['plan_holder_id'],
            'branch_id'        => $reg['branch_id'],
            'remittance_date'  => $date,
            'collector_id'     => $collectorId > 0 ? $collectorId : null,
            'collector_name'   => $collectorName !== '' ? $collectorName : null,
            'amount'           => number_format((float) $amount, 2, '.', ''),
            'reference_number' => $ref !== '' ? $ref : null,
            'covers_payments'  => $covers ? implode(',', $covers) : null,
            'remarks'          => mb_substr($remarks, 0, 255) ?: null,
        ] + $this->auditColumns($actor, 'legacy_manual'));
        $this->log($actor, 'created', 'legacy_remittance', (int) $db->insertID(), 'Encoded a past remittance for client #' . (int) $client['user_id']);

        return ['ok' => true, 'errors' => []];
    }

    /** @return array{ok:bool, errors:list<string>} */
    public function addService(array $client, array $in, array $actor): array
    {
        $errors = [];
        $name = trim((string) ($in['service_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Service or package name is required (150 characters or fewer).';
        }
        $date = trim((string) ($in['service_date'] ?? ''));
        if (! $this->validDate($date) || $date > date('Y-m-d')) {
            $errors[] = 'Service date must be a valid date that is not in the future.';
        }
        $status = (string) ($in['service_status'] ?? 'completed');
        if (! isset(self::SERVICE_STATUSES[$status])) {
            $errors[] = 'Choose a valid status.';
        }
        $amount = trim((string) ($in['amount'] ?? ''));
        $amountValue = $amount === '' ? null : str_replace(',', '', $amount);
        if ($amountValue !== null && (! is_numeric($amountValue) || (float) $amountValue < 0 || (float) $amountValue > 100000000)) {
            $errors[] = 'Amount must be a valid number.';
        }
        $remarks = trim((string) ($in['remarks'] ?? ''));
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $reg = $this->ensureRegistration($client, $actor);
        $db = db_connect();
        $db->table('legacy_services')->insert([
            'plan_holder_id' => $reg['plan_holder_id'],
            'branch_id'      => $reg['branch_id'],
            'service_name'   => $name,
            'service_date'   => $date,
            'service_status' => $status,
            'amount'         => $amountValue !== null ? number_format((float) $amountValue, 2, '.', '') : null,
            'remarks'        => mb_substr($remarks, 0, 255) ?: null,
        ] + $this->auditColumns($actor, 'legacy_manual'));
        $this->log($actor, 'created', 'legacy_service', (int) $db->insertID(), 'Encoded a past service/claim "' . $name . '" for client #' . (int) $client['user_id']);

        return ['ok' => true, 'errors' => []];
    }

    /** @return array{ok:bool, errors:list<string>} */
    public function addNote(array $client, string $note, array $actor): array
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 5000) {
            return ['ok' => false, 'errors' => ['A note is required (5,000 characters or fewer).']];
        }

        $reg = $this->ensureRegistration($client, $actor);
        $db = db_connect();
        $db->table('legacy_notes')->insert([
            'plan_holder_id' => $reg['plan_holder_id'],
            'branch_id'      => $reg['branch_id'],
            'note'           => $note,
        ] + $this->auditColumns($actor, 'legacy_manual'));
        $this->log($actor, 'created', 'legacy_note', (int) $db->insertID(), 'Added a history note for client #' . (int) $client['user_id']);

        return ['ok' => true, 'errors' => []];
    }

    // =====================================================================
    //  7. Documents
    // =====================================================================

    /**
     * @param \CodeIgniter\HTTP\Files\UploadedFile|null $file
     * @return array{ok:bool, errors:list<string>}
     */
    public function addDocument(array $client, $file, string $docType, array $actor): array
    {
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return ['ok' => false, 'errors' => ['Choose a file to upload.']];
        }
        if (! isset(self::DOC_TYPES[$docType])) {
            return ['ok' => false, 'errors' => ['Choose what kind of document this is.']];
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return ['ok' => false, 'errors' => ['That file is larger than ' . (self::MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.']];
        }

        $ext = strtolower($file->getExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        if (! isset(self::ALLOWED_DOC_EXT[$ext])) {
            return ['ok' => false, 'errors' => ['Only PDF, JPG and PNG files can be uploaded.']];
        }

        // Trust the file's real content, not just its extension.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getTempName()) ?: '';
        if ($mime !== self::ALLOWED_DOC_EXT[$ext]) {
            return ['ok' => false, 'errors' => ['That file does not look like a real ' . strtoupper($ext) . ' file.']];
        }

        $reg = $this->ensureRegistration($client, $actor);

        $dir = WRITEPATH . 'uploads/legacy_documents';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($dir, $stored);

        $db = db_connect();
        $db->table('legacy_documents')->insert([
            'plan_holder_id' => $reg['plan_holder_id'],
            'branch_id'      => $reg['branch_id'],
            'doc_type'       => $docType,
            'original_name'  => mb_substr(preg_replace('/[^\p{L}\p{N}._ ()-]/u', '_', $file->getClientName()), 0, 200),
            'stored_name'    => $stored,
            'mime_type'      => $mime,
            'file_size'      => (int) filesize($dir . '/' . $stored),
        ] + $this->auditColumns($actor, 'legacy_manual'));
        $this->log($actor, 'created', 'legacy_document', (int) $db->insertID(), 'Uploaded ' . self::DOC_TYPES[$docType] . ' for client #' . (int) $client['user_id']);

        return ['ok' => true, 'errors' => []];
    }

    public function documentPath(array $doc): string
    {
        return WRITEPATH . 'uploads/legacy_documents/' . basename((string) $doc['stored_name']);
    }

    // =====================================================================
    //  Delete / verify / reject
    // =====================================================================

    /** Row of $type the actor may touch (branch-scoped through its client), or null. */
    public function findRecord(string $type, int $id, array $actor): ?array
    {
        if (! isset(self::TYPES[$type])) {
            return null;
        }
        [$table, $pk] = self::TYPES[$type];
        $row = db_connect()->table($table)->where($pk, $id)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        $actor = $this->actorContext($actor);
        if ((int) $actor['role_id'] !== 1) {
            $ph = db_connect()->table('plan_holders')->select('branch_id')->where('plan_holder_id', (int) $row['plan_holder_id'])->get()->getRowArray();
            if (! $ph || (int) $ph['branch_id'] !== (int) $actor['branch_id']) {
                return null;
            }
        }

        // Beneficiaries from normal registration are not "past records".
        if ($type === 'beneficiary' && ($row['source'] ?? '') !== 'legacy_manual') {
            return null;
        }

        return $row;
    }

    /**
     * Staff may only delete their own still-pending rows; Branch Admin may
     * delete any row in the branch.
     *
     * @return array{ok:bool, message:string}
     */
    public function delete(string $type, int $id, array $actor): array
    {
        $row = $this->findRecord($type, $id, $actor);
        if (! $row) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }

        $ctx = $this->actorContext($actor);
        if (! $this->isAdminRole($ctx)) {
            $status = $type === 'beneficiary' ? ($row['verification_status'] === 'pending' ? 'pending_verification' : $row['verification_status']) : $row['record_status'];
            if ($status !== 'pending_verification' || (int) $row['encoded_by'] !== $ctx['user_id']) {
                return ['ok' => false, 'message' => 'Staff can only remove their own records that are still waiting for verification.'];
            }
        }

        [$table, $pk] = self::TYPES[$type];

        if ($type === 'document') {
            @unlink($this->documentPath($row));
        }
        if ($type === 'plan') {
            return ['ok' => false, 'message' => 'Plan details cannot be deleted - edit them instead.'];
        }

        db_connect()->table($table)->where($pk, $id)->delete();
        $this->log($actor, 'deleted', 'legacy_' . $type, $id, 'Deleted a past ' . strtolower(self::TYPES[$type][2]) . ' record', null, $row);

        if ($type === 'payment' && ! empty($row['plan_id'])) {
            $this->recompute((int) $row['plan_id']);
        }

        return ['ok' => true, 'message' => 'Record removed.'];
    }

    /** @return array{ok:bool, message:string} */
    public function verify(string $type, int $id, array $actor): array
    {
        $ctx = $this->actorContext($actor);
        if (! $this->isAdminRole($ctx)) {
            return ['ok' => false, 'message' => 'Only a Branch Admin can verify past records.'];
        }

        $row = $this->findRecord($type, $id, $actor);
        if (! $row) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }

        $now = date('Y-m-d H:i:s');
        [$table, $pk] = self::TYPES[$type];
        $db = db_connect();

        if ($type === 'beneficiary') {
            $db->table($table)->where($pk, $id)->update(['verification_status' => 'verified']);
        } else {
            $db->table($table)->where($pk, $id)->update(['record_status' => 'verified', 'verified_by' => $ctx['user_id'], 'verified_at' => $now, 'rejection_reason' => null]);
        }
        $this->log($actor, 'approved', 'legacy_' . $type, $id, 'Verified a past ' . strtolower(self::TYPES[$type][2]) . ' record');

        $message = 'Verified.';
        if ($type === 'payment') {
            $w = $this->recompute((int) $row['plan_id']);
            $message .= $w ? ' ' . implode(' ', $w) : '';
        } elseif ($type === 'plan') {
            $w = $this->applyPlanRecord($id);
            $message .= $w ? ' ' . implode(' ', $w) : '';
        }

        return ['ok' => true, 'message' => $message];
    }

    /** @return array{ok:bool, message:string} */
    public function reject(string $type, int $id, string $reason, array $actor): array
    {
        $ctx = $this->actorContext($actor);
        if (! $this->isAdminRole($ctx)) {
            return ['ok' => false, 'message' => 'Only a Branch Admin can reject past records.'];
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            return ['ok' => false, 'message' => 'Give a short reason for rejecting (255 characters or fewer).'];
        }

        $row = $this->findRecord($type, $id, $actor);
        if (! $row) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }

        [$table, $pk] = self::TYPES[$type];
        $db = db_connect();

        if ($type === 'beneficiary') {
            $db->table($table)->where($pk, $id)->update(['verification_status' => 'rejected']);
        } else {
            $db->table($table)->where($pk, $id)->update(['record_status' => 'rejected', 'verified_by' => $ctx['user_id'], 'verified_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason]);
        }
        $this->log($actor, 'rejected', 'legacy_' . $type, $id, 'Rejected a past ' . strtolower(self::TYPES[$type][2]) . ' record: ' . $reason);

        if ($type === 'payment') {
            $this->recompute((int) $row['plan_id']);
        }

        return ['ok' => true, 'message' => 'Rejected.'];
    }

    /**
     * Everything waiting for verification in a branch, grouped by client.
     *
     * @return list<array{plan_holder_id:int, name:string, plan_number:?string, items:list<array<string,mixed>>}>
     */
    public function pendingForBranch(int $branchId, bool $allBranches = false): array
    {
        $db = db_connect();
        $groups = [];

        foreach (self::TYPES as $type => [$table, $pk, $label]) {
            $statusCol = $type === 'beneficiary' ? 'verification_status' : 'record_status';
            $pending   = $type === 'beneficiary' ? 'pending' : 'pending_verification';

            $builder = $db->table($table . ' t')
                ->select("t.*, '{$type}' AS _type, t.{$pk} AS _id, ph.unique_identifier, u.first_name AS client_first, u.last_name AS client_last, eb.first_name AS encoder_first, eb.last_name AS encoder_last")
                ->join('plan_holders ph', 'ph.plan_holder_id = t.plan_holder_id', 'inner')
                ->join('users u', 'u.user_id = ph.user_id', 'inner')
                ->join('users eb', 'eb.user_id = t.encoded_by', 'left')
                ->where("t.{$statusCol}", $pending);

            if ($type === 'beneficiary') {
                $builder->where('t.source', 'legacy_manual');
            }
            if (! $allBranches) {
                $builder->where('ph.branch_id', $branchId);
            }

            foreach ($builder->get()->getResultArray() as $r) {
                $phId = (int) $r['plan_holder_id'];
                $groups[$phId] ??= [
                    'plan_holder_id' => $phId,
                    'user_id'        => (int) ($db->table('plan_holders')->select('user_id')->where('plan_holder_id', $phId)->get()->getRowArray()['user_id'] ?? 0),
                    'name'           => trim($r['client_first'] . ' ' . $r['client_last']),
                    'plan_number'    => $r['unique_identifier'],
                    'items'          => [],
                ];
                $r['_label'] = $label;
                $groups[$phId]['items'][] = $r;
            }
        }

        usort($groups, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $groups;
    }

    public function pendingCountForBranch(int $branchId): int
    {
        $total = 0;
        foreach (self::TYPES as $type => [$table]) {
            $statusCol = $type === 'beneficiary' ? 'verification_status' : 'record_status';
            $pending   = $type === 'beneficiary' ? 'pending' : 'pending_verification';
            $q = db_connect()->table($table . ' t')
                ->join('plan_holders ph', 'ph.plan_holder_id = t.plan_holder_id', 'inner')
                ->where("t.{$statusCol}", $pending)->where('ph.branch_id', $branchId);
            if ($type === 'beneficiary') {
                $q->where('t.source', 'legacy_manual');
            }
            $total += $q->countAllResults();
        }

        return $total;
    }

    // =====================================================================
    //  Bulk import
    // =====================================================================

    public const IMPORT_HEADERS = ['plan_number', 'payment_date', 'amount', 'period_month', 'period_year', 'months_covered', 'or_number', 'payment_method', 'collected_by', 'remarks'];

    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, self::IMPORT_HEADERS, ',', '"', '\\');
        fputcsv($out, ['PH-1-202601011200-ABC123', '2024-03-15', '240.00', '3', '2024', '1', 'OR-10456', 'branch', 'Juan Collector', 'Paid at the branch'], ',', '"', '\\');
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Parse an uploaded file into a preview. Nothing is saved.
     *
     * @return array{rows:list<array<string,mixed>>, valid:int, invalid:int, total_amount:float, token:string}
     */
    public function previewImport(string $path, string $extension, array $actor): array
    {
        $raw = (new SpreadsheetReader())->read($path, $extension);
        if ($raw === []) {
            throw new \RuntimeException('That file is empty.');
        }

        $header = array_map(static fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', (string) $h))), array_shift($raw));
        $missing = array_diff(['plan_number', 'payment_date', 'amount', 'period_month', 'period_year'], $header);
        if ($missing !== []) {
            throw new \RuntimeException('The file is missing these columns: ' . implode(', ', $missing) . '. Download the template to see the exact headers.');
        }

        $ctx = $this->actorContext($actor);
        $db  = db_connect();
        $seenOr = [];
        $rows = [];
        $totalAmount = 0.0;
        $valid = 0;
        $invalid = 0;
        $clientCache = [];

        foreach ($raw as $index => $cells) {
            $line = $index + 2; // spreadsheet row number (header is row 1)
            $assoc = [];
            foreach ($header as $col => $name) {
                $assoc[$name] = trim((string) ($cells[$col] ?? ''));
            }

            // Excel date serials -> ISO dates.
            if (($iso = SpreadsheetReader::excelSerialToDate($assoc['payment_date'] ?? '')) !== null) {
                $assoc['payment_date'] = $iso;
            }

            $client = null;
            $errors = [];
            $warnings = [];
            $clientLabel = '';
            $planNumber = $assoc['plan_number'] ?? '';

            if ($planNumber === '') {
                $errors[] = 'Client not found (plan number is empty)';
            } else {
                if (! array_key_exists($planNumber, $clientCache)) {
                    $found = $db->table('plan_holders ph')
                        ->select('ph.plan_holder_id, ph.user_id, ph.branch_id, u.first_name, u.last_name')
                        ->join('users u', 'u.user_id = ph.user_id', 'inner')
                        ->where('ph.unique_identifier', $planNumber);
                    if ($ctx['role_id'] !== 1) {
                        $found->where('ph.branch_id', (int) $ctx['branch_id']);
                    }
                    $clientCache[$planNumber] = $found->get()->getRowArray() ?: null;
                }
                $client = $clientCache[$planNumber];
                if (! $client) {
                    $errors[] = 'Client not found';
                } else {
                    $clientLabel = trim($client['first_name'] . ' ' . $client['last_name']);
                }
            }

            if ($errors === [] && $client) {
                $v = $this->validatePayment([
                    'payment_date'   => $assoc['payment_date'] ?? '',
                    'amount'         => $assoc['amount'] ?? '',
                    'period_month'   => $assoc['period_month'] ?? '',
                    'period_year'    => $assoc['period_year'] ?? '',
                    'months_covered' => $assoc['months_covered'] ?? '',
                    'or_number'      => $assoc['or_number'] ?? '',
                    'channel'        => $this->normaliseChannel($assoc['payment_method'] ?? ''),
                    'collected_by'   => $assoc['collected_by'] ?? '',
                    'remarks'        => $assoc['remarks'] ?? '',
                ], (int) $client['branch_id'], (int) $client['plan_holder_id']);

                $errors = $v['errors'];
                $warnings = $v['warnings'];

                $or = $v['row']['or_number'];
                if ($or !== null && $errors === []) {
                    $key = $client['branch_id'] . '|' . $or;
                    if (isset($seenOr[$key])) {
                        $errors[] = 'Duplicate OR number (also on row ' . $seenOr[$key] . ' of this file)';
                    } else {
                        $seenOr[$key] = $line;
                    }
                }
            }

            $rowOk = $errors === [];
            $rowOk ? $valid++ : $invalid++;
            if ($rowOk) {
                $totalAmount += (float) $v['row']['amount'];
            }

            $rows[] = [
                'line'      => $line,
                'client'    => $clientLabel,
                'user_id'   => $rowOk ? (int) $client['user_id'] : 0,
                'input'     => $assoc,
                'row'       => $rowOk ? $v['row'] : null,
                'errors'    => $errors,
                'warnings'  => $warnings,
            ];
        }

        $token = bin2hex(random_bytes(16));
        $dir = WRITEPATH . 'uploads/legacy_import';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $token . '.json', json_encode([
            'actor_id' => $ctx['user_id'],
            'created'  => time(),
            'rows'     => $rows,
        ]));

        return ['rows' => $rows, 'valid' => $valid, 'invalid' => $invalid, 'total_amount' => $totalAmount, 'token' => $token];
    }

    private function normaliseChannel(string $value): string
    {
        $v = strtolower(trim($value));

        return match (true) {
            $v === '' => 'branch',
            in_array($v, ['branch', 'cash', 'cash at branch', 'cash_at_branch', 'office'], true) => 'branch',
            in_array($v, ['collector', 'field', 'agent'], true) => 'collector',
            in_array($v, ['gcash', 'g-cash'], true) => 'gcash',
            in_array($v, ['bank', 'bank transfer', 'deposit'], true) => 'bank',
            default => $v, // fails validation with a clear "Invalid payment method"
        };
    }

    /** The saved preview, only for the person who made it, only for 1 hour. */
    public function loadImport(string $token, array $actor): ?array
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $file = WRITEPATH . 'uploads/legacy_import/' . $token . '.json';
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data) || (int) $data['actor_id'] !== (int) $actor['user_id'] || time() - (int) $data['created'] > 3600) {
            return null;
        }

        return $data;
    }

    /**
     * Save the valid rows of a previewed import. Client branches were
     * resolved (and checked against the actor's branch) at preview time and
     * are checked again by findClient().
     *
     * @return array{saved:int, skipped:int}
     */
    public function commitImport(string $token, array $actor): array
    {
        $data = $this->loadImport($token, $actor);
        if (! $data) {
            throw new \RuntimeException('That import preview has expired. Upload the file again.');
        }

        $byClient = [];
        $skipped = 0;
        foreach ($data['rows'] as $r) {
            if ($r['errors'] === [] && $r['row'] !== null) {
                $byClient[(int) $r['user_id']][] = $r['row'];
            } else {
                $skipped++;
            }
        }

        $saved = 0;
        foreach ($byClient as $userId => $rows) {
            $client = $this->findClient($userId, $actor);
            if (! $client) {
                $skipped += count($rows);
                continue;
            }

            $res = $this->addPayments($client, $rows, $actor, 'legacy_import', substr($token, 0, 12));
            $saved += $res['saved'];
            $skipped += count($rows) - $res['saved'];
        }

        @unlink(WRITEPATH . 'uploads/legacy_import/' . $token . '.json');

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /** CSV of the rows that failed, original columns + a reason column. */
    public function errorRowsCsv(array $data): string
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_merge(self::IMPORT_HEADERS, ['error']), ',', '"', '\\');
        foreach ($data['rows'] as $r) {
            if ($r['errors'] === []) {
                continue;
            }
            $line = [];
            foreach (self::IMPORT_HEADERS as $h) {
                // Neutralise spreadsheet formula injection when the file is re-opened in Excel.
                $cell = (string) ($r['input'][$h] ?? '');
                $line[] = preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell;
            }
            $line[] = implode('; ', $r['errors']);
            fputcsv($out, $line, ',', '"', '\\');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF" . $csv;
    }

    // =====================================================================

    private function validDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }
}
