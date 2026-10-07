<?php

namespace App\Services;

/**
 * Everything the shared "Client details" page shows, loaded with a fixed
 * handful of queries (no per-row lookups) so System Admin, Branch Admin and
 * Staff see exactly the same page.
 *
 * Access is decided here, once: System Admin sees every branch, Branch Admin
 * and Staff only their own. find() answers null for "does not exist" and
 * "another branch's client" alike, so a changed URL cannot probe other
 * branches; every controller turns null into Not Found.
 *
 * All plan numbers come from ClientPlanSummary (-> PlanLedgerService).
 */
class ClientDetailService
{
    public const PER_PAGE = 15;

    public const METHODS = ['branch' => 'Branch', 'collector' => 'Collector', 'gcash' => 'GCash', 'bank' => 'Bank'];

    /** Who may open a client page. Collectors and clients have their own screens. */
    public static function roleMayView(int $roleId): bool
    {
        return in_array($roleId, [1, 2, 3], true);
    }

    /**
     * The client's core record, or null when it does not exist or the viewer
     * may not see it.
     */
    public function find(int $planHolderId, int $roleId, int $branchId): ?array
    {
        if ($planHolderId <= 0 || ! self::roleMayView($roleId)) {
            return null;
        }

        $client = db_connect()->table('plan_holders ph')
            ->select('ph.*, u.username, u.email, u.first_name, u.middle_name, u.last_name, u.name_extension, u.contact_number, u.status AS user_status, u.account_status, u.last_login, u.must_change_password, u.temp_password_expires_at, u.password_changed_at, u.created_at AS account_created_at, b.branch_name', false)
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->where('ph.plan_holder_id', $planHolderId)
            ->get()
            ->getRowArray();

        if (! $client) {
            return null;
        }

        if ($roleId !== 1 && ((int) $client['branch_id'] <= 0 || (int) $client['branch_id'] !== $branchId)) {
            return null;
        }

        return $client;
    }

    /**
     * @param array<string,mixed> $client  from find()
     * @param array<string,mixed> $filters payments tab: date_from, date_to, status, method, or_no, page
     *
     * @return array<string,mixed>
     */
    public function page(array $client, array $filters = []): array
    {
        $db   = db_connect();
        $phId = (int) $client['plan_holder_id'];
        $uid  = (int) $client['user_id'];

        $summary = (new ClientPlanSummary())->forPlanHolder($phId);
        $planId  = (int) ($summary['plan_id'] ?? 0);
        $plan    = $planId > 0 ? $this->plan($planId) : null;

        $payments      = $planId > 0 ? $this->paymentRows($planId) : [];
        $beneficiaries = $this->beneficiaries($phId);
        $services      = $this->services($phId);
        $documents     = $this->documents($client, $planId, array_column($services, 'application_id'));
        $paged         = $this->filterPayments($payments, $filters);

        return [
            'client'        => $client,
            'plan'          => $plan,
            'summary'       => $summary,
            'member_since'  => $this->memberSince($client, $plan),
            'collectors'    => $this->collectors((int) $client['branch_id'], (string) ($client['barangay_code'] ?? '')),
            'inclusions'    => $plan ? $this->inclusions((int) ($plan['package_id'] ?? 0)) : [],
            'payments'      => $paged,
            'services'      => $services,
            'beneficiaries' => $this->markClaims($beneficiaries, $services),
            'documents'     => $documents,
            'activity'      => $this->activity($client, $planId, array_column($payments, 'payment_id'), array_column($services, 'application_id')),
            'notes'         => $this->notes($client),
            'pending_payments' => count(array_filter($payments, static fn (array $p): bool => $p['status'] === 'pending')),
        ];
    }

    // ------------------------------------------------------------------
    //  Plan
    // ------------------------------------------------------------------

    private function plan(int $planId): ?array
    {
        $row = db_connect()->table('plans p')
            ->select('p.*, pk.package_name, lp.payment_frequency, lp.plan_name AS past_plan_name, lp.contract_number', false)
            ->join('packages pk', 'pk.package_id = p.package_id', 'left')
            ->join('legacy_plan_records lp', "lp.plan_id = p.plan_id AND lp.record_status = 'verified'", 'left', false)
            ->where('p.plan_id', $planId)
            ->get()
            ->getRowArray();

        if (! $row) {
            return null;
        }

        $row['plan_name'] = (string) ($row['package_name'] ?: ($row['past_plan_name'] ?: MembershipService::PROGRAM_NAME));
        $row['payment_frequency'] = (string) ($row['payment_frequency'] ?: 'monthly');

        return $row;
    }

    /** @return string[] */
    private function inclusions(int $packageId): array
    {
        if ($packageId <= 0) {
            return [];
        }

        $db = db_connect();
        $items = array_column($db->table('package_items')->select('item_name')->where('package_id', $packageId)->get()->getResultArray(), 'item_name');
        $services = $db->tableExists('package_services')
            ? array_column($db->table('package_services')->select('service_name')->where('package_id', $packageId)->get()->getResultArray(), 'service_name')
            : [];

        return array_values(array_unique(array_filter(array_merge($items, $services))));
    }

    /** @return string[] names; "(all barangays)" collectors are marked */
    private function collectors(int $branchId, string $barangayCode): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $builder = db_connect()->table('collector_assignments ca')
            ->select('u.first_name, u.last_name, ca.barangay_code')
            ->join('users u', 'u.user_id = ca.user_id', 'inner')
            ->where('ca.branch_id', $branchId)
            ->groupStart()->where('ca.barangay_code IS NULL', null, false);
        if ($barangayCode !== '') {
            $builder->orWhere('ca.barangay_code', $barangayCode);
        }
        $rows = $builder->groupEnd()->get()->getResultArray();

        $names = [];
        foreach ($rows as $r) {
            $name = trim($r['first_name'] . ' ' . $r['last_name']);
            $names[$name] = $r['barangay_code'] === null ? $name . ' (whole branch)' : $name;
        }

        return array_values($names);
    }

    private function memberSince(array $client, ?array $plan): ?string
    {
        foreach ([$plan['start_date'] ?? null, $client['application_date'] ?? null, $client['created_at'] ?? null] as $candidate) {
            if (! empty($candidate) && strtotime((string) $candidate)) {
                return date('Y-m-d', strtotime((string) $candidate));
            }
        }

        return null;
    }

    /** "Member for 2 years 3 months" */
    public static function memberFor(?string $since, ?string $today = null): string
    {
        if (empty($since)) {
            return '';
        }

        $diff   = (new \DateTimeImmutable($since))->diff(new \DateTimeImmutable($today ?? date('Y-m-d')));
        $years  = $diff->invert ? 0 : (int) $diff->y;
        $months = $diff->invert ? 0 : (int) $diff->m;

        if ($years === 0 && $months === 0) {
            return 'Member for less than a month';
        }

        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' year' . ($years === 1 ? '' : 's');
        }
        if ($months > 0) {
            $parts[] = $months . ' month' . ($months === 1 ? '' : 's');
        }

        return 'Member for ' . implode(' ', $parts);
    }

    // ------------------------------------------------------------------
    //  Payments
    // ------------------------------------------------------------------

    /**
     * Every payment row for the plan - system payments and past records - in
     * one list, newest first, each with a normalised status/method/source.
     * Only 'verified' rows count toward the plan numbers (the ledger decides
     * that; this list is what the table shows).
     *
     * @return list<array<string,mixed>>
     */
    private function paymentRows(int $planId): array
    {
        $db = db_connect();

        $live = $db->table('payments pay')
            ->select('pay.payment_id, pay.payment_date, pay.amount, pay.payment_method, pay.payment_type, pay.status, pay.reference_number, pay.official_receipt_number, pay.coverage_start, pay.coverage_end, pay.months_covered, pay.proof_image, pay.remarks, rb.first_name AS rb_first, rb.last_name AS rb_last, rb.is_collector AS rb_collector', false)
            ->join('users rb', 'rb.user_id = pay.received_by', 'left')
            ->where('pay.plan_id', $planId)
            ->where('pay.deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        $rows = [];
        foreach ($live as $p) {
            $method = (string) $p['payment_method'] === 'gcash'
                ? 'gcash'
                : (! empty($p['rb_collector']) ? 'collector' : 'branch');
            $status = match ((string) $p['status']) {
                'paid'  => 'verified',
                'pending' => 'pending',
                default => 'rejected',
            };

            $period = '—';
            if (! empty($p['coverage_start']) && ! empty($p['coverage_end'])) {
                $period = cs_date($p['coverage_start']) . ' – ' . cs_date($p['coverage_end']);
            } elseif ((int) $p['months_covered'] > 0) {
                $period = (int) $p['months_covered'] . ' month' . ((int) $p['months_covered'] === 1 ? '' : 's');
            }

            $rows[] = [
                'payment_id'  => (int) $p['payment_id'],
                'date'        => (string) $p['payment_date'],
                'period'      => $period,
                'amount'      => (float) $p['amount'],
                'or_number'   => (string) ($p['official_receipt_number'] ?: $p['reference_number'] ?: ''),
                'method'      => $method,
                'received_by' => trim($p['rb_first'] . ' ' . $p['rb_last']),
                'status'      => $status,
                'source'      => 'system',
                'note'        => (string) $p['payment_type'] === 'reinstatement_fee' ? 'Reinstatement payment' : '',
                'has_proof'   => ! empty($p['proof_image']),
            ];
        }

        if (LegacyRecordService::ready()) {
            $past = $db->table('legacy_payments lp')
                ->select('lp.legacy_payment_id, lp.payment_date, lp.amount, lp.channel, lp.or_number, lp.period_month, lp.period_year, lp.months_covered, lp.collected_by, lp.record_status, eb.first_name AS eb_first, eb.last_name AS eb_last', false)
                ->join('users eb', 'eb.user_id = lp.encoded_by', 'left')
                ->where('lp.plan_id', $planId)
                ->get()
                ->getResultArray();

            foreach ($past as $p) {
                $period = '—';
                if ((int) $p['period_month'] > 0 && (int) $p['period_year'] > 0) {
                    $period = date('M Y', mktime(0, 0, 0, (int) $p['period_month'], 1, (int) $p['period_year']));
                } elseif ((int) $p['months_covered'] > 0) {
                    $period = (int) $p['months_covered'] . ' month' . ((int) $p['months_covered'] === 1 ? '' : 's');
                }

                $rows[] = [
                    'payment_id'  => 0,
                    'date'        => (string) $p['payment_date'],
                    'period'      => $period,
                    'amount'      => (float) $p['amount'],
                    'or_number'   => (string) $p['or_number'],
                    'method'      => isset(self::METHODS[(string) $p['channel']]) ? (string) $p['channel'] : 'branch',
                    'received_by' => (string) ($p['collected_by'] ?: trim($p['eb_first'] . ' ' . $p['eb_last'])),
                    'status'      => match ((string) $p['record_status']) {
                        'verified' => 'verified',
                        'rejected' => 'rejected',
                        default    => 'pending',
                    },
                    'source'      => 'past',
                    'note'        => '',
                    'has_proof'   => false,
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$b['date'], $b['payment_id']] <=> [$a['date'], $a['payment_id']]);

        return $rows;
    }

    /**
     * Applies the Payment history filters and builds the page, with totals
     * over the whole filtered set (not just the visible page).
     *
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed>       $filters
     *
     * @return array<string,mixed>
     */
    public function filterPayments(array $rows, array $filters): array
    {
        $from   = (string) ($filters['date_from'] ?? '');
        $to     = (string) ($filters['date_to'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $method = (string) ($filters['method'] ?? '');
        $orNo   = mb_strtolower(trim((string) ($filters['or_no'] ?? '')));

        $filtered = array_values(array_filter($rows, static function (array $r) use ($from, $to, $status, $method, $orNo): bool {
            return ($from === '' || strcmp($r['date'], $from) >= 0)
                && ($to === '' || strcmp($r['date'], $to) <= 0)
                && ($status === '' || $r['status'] === $status)
                && ($method === '' || $r['method'] === $method)
                && ($orNo === '' || str_contains(mb_strtolower($r['or_number']), $orNo));
        }));

        $totals = ['verified' => 0.0, 'pending' => 0.0, 'rejected' => 0.0];
        foreach ($filtered as $r) {
            $totals[$r['status']] += $r['amount'];
        }

        $count   = count($filtered);
        $pages   = max(1, (int) ceil($count / self::PER_PAGE));
        $current = min($pages, max(1, (int) ($filters['page'] ?? 1)));

        return [
            'all_count' => count($rows),
            'rows'      => array_slice($filtered, ($current - 1) * self::PER_PAGE, self::PER_PAGE),
            'filtered'  => $filtered,
            'count'     => $count,
            'page'      => $current,
            'pages'     => $pages,
            'totals'    => array_map(static fn (float $v): float => round($v, 2), $totals),
            'filters'   => ['date_from' => $from, 'date_to' => $to, 'status' => $status, 'method' => $method, 'or_no' => (string) ($filters['or_no'] ?? '')],
            'is_filtered' => $from !== '' || $to !== '' || $status !== '' || $method !== '' || $orNo !== '',
        ];
    }

    /** Rows for the printed/PDF statement: the same filters, no paging. */
    public function statementRows(array $client, array $filters): array
    {
        $summary = (new ClientPlanSummary())->forPlanHolder((int) $client['plan_holder_id']);
        $planId  = (int) ($summary['plan_id'] ?? 0);
        $paged   = $this->filterPayments($planId > 0 ? $this->paymentRows($planId) : [], $filters);

        return ['summary' => $summary, 'payments' => $paged];
    }

    // ------------------------------------------------------------------
    //  Services and claims, beneficiaries
    // ------------------------------------------------------------------

    /** @return list<array<string,mixed>> newest first */
    private function services(int $planHolderId): array
    {
        $db = db_connect();

        $apps = $db->table('service_applications sa')
            ->select("sa.application_id, sa.created_at, sa.status AS app_status, sa.package_id, sa.beneficiary_name, sa.deceased_name, sa.application_amount, COALESCE(sl.service_name, pk.package_name, '-') AS item_name, sb.service_id AS linked_service_id, s.status AS service_status, s.service_date, s.total_cost, s.deleted_at AS service_deleted, CONCAT(COALESCE(st.first_name, ''), ' ', COALESCE(st.last_name, '')) AS staff_name", false)
            ->join('service_list sl', 'sl.service_list_id = sa.service_list_id', 'left')
            ->join('packages pk', 'pk.package_id = sa.package_id', 'left')
            ->join('service_balances sb', 'sb.application_id = sa.application_id', 'left')
            ->join('services s', 's.service_id = sb.service_id', 'left')
            ->join('users st', 'st.user_id = s.assigned_staff', 'left')
            ->where('sa.plan_holder_id', $planHolderId)
            ->get()
            ->getResultArray();

        $rows = [];
        $linked = [];
        foreach ($apps as $a) {
            if (! empty($a['linked_service_id'])) {
                $linked[(int) $a['linked_service_id']] = true;
            }

            $status = $a['service_status'] !== null && $a['service_deleted'] === null ? (string) $a['service_status'] : (string) $a['app_status'];
            $rows[] = [
                'application_id' => (int) $a['application_id'],
                'requested_on'   => (string) $a['created_at'],
                'item'           => (string) $a['item_name'],
                'kind'           => (int) $a['package_id'] > 0 ? 'Package' : 'Service',
                'for'            => (string) ($a['beneficiary_name'] ?: $a['deceased_name']),
                'status'         => $status,
                'staff'          => trim((string) $a['staff_name']),
                'completed_on'   => $status === 'completed' ? (string) $a['service_date'] : '',
                'amount'         => (float) ($a['total_cost'] ?? 0) > 0 ? (float) $a['total_cost'] : (float) $a['application_amount'],
                'names'          => array_filter([(string) $a['beneficiary_name'], (string) $a['deceased_name']]),
                'rejected'       => (string) $a['app_status'] === 'rejected',
                'source'         => 'system',
            ];
        }

        // Services booked directly (no application behind them).
        $direct = $db->table('services s')
            ->select("s.service_id, s.service_date, s.status, s.total_cost, COALESCE(sl.service_name, '-') AS item_name, CONCAT(COALESCE(st.first_name, ''), ' ', COALESCE(st.last_name, '')) AS staff_name", false)
            ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
            ->join('users st', 'st.user_id = s.assigned_staff', 'left')
            ->where('s.plan_holder_id', $planHolderId)
            ->where('s.deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($direct as $s) {
            if (isset($linked[(int) $s['service_id']])) {
                continue;
            }
            $rows[] = [
                'application_id' => 0,
                'requested_on'   => (string) $s['service_date'],
                'item'           => (string) $s['item_name'],
                'kind'           => 'Service',
                'for'            => '',
                'status'         => (string) $s['status'],
                'staff'          => trim((string) $s['staff_name']),
                'completed_on'   => (string) $s['status'] === 'completed' ? (string) $s['service_date'] : '',
                'amount'         => (float) $s['total_cost'],
                'names'          => [],
                'rejected'       => false,
                'source'         => 'system',
            ];
        }

        if (LegacyRecordService::ready()) {
            $past = $db->table('legacy_services')
                ->where('plan_holder_id', $planHolderId)
                ->where('record_status', 'verified')
                ->get()
                ->getResultArray();

            foreach ($past as $s) {
                $rows[] = [
                    'application_id' => 0,
                    'requested_on'   => (string) $s['service_date'],
                    'item'           => (string) $s['service_name'],
                    'kind'           => 'Service',
                    'for'            => '',
                    'status'         => (string) $s['service_status'],
                    'staff'          => '',
                    'completed_on'   => (string) $s['service_status'] === 'completed' ? (string) $s['service_date'] : '',
                    'amount'         => (float) $s['amount'],
                    'names'          => [],
                    'rejected'       => false,
                    'source'         => 'past',
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['requested_on'], (string) $a['requested_on']));

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function beneficiaries(int $planHolderId): array
    {
        return db_connect()->table('beneficiaries')
            ->where('plan_holder_id', $planHolderId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('beneficiary_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Adds age and "claim already made" to each beneficiary. A claim counts
     * when a request that was not rejected names the beneficiary (as the
     * person it was for or as the deceased).
     *
     * @param list<array<string,mixed>> $beneficiaries
     * @param list<array<string,mixed>> $services
     *
     * @return list<array<string,mixed>>
     */
    private function markClaims(array $beneficiaries, array $services): array
    {
        $norm = static fn (string $s): string => trim(preg_replace('/\s+/', ' ', mb_strtolower($s)));

        $claimed = [];
        foreach ($services as $s) {
            if ($s['rejected'] || $s['status'] === 'cancelled') {
                continue;
            }
            foreach ($s['names'] as $n) {
                $claimed[$norm((string) $n)] = true;
            }
        }

        foreach ($beneficiaries as &$b) {
            $first = trim((string) $b['first_name']);
            $last  = trim((string) $b['last_name']);
            $full  = $norm(trim($first . ' ' . $b['middle_name'] . ' ' . $last . ' ' . $b['name_extension']));
            $short = $norm($first . ' ' . $last);

            $b['full_name'] = trim(preg_replace('/\s+/', ' ', $first . ' ' . $b['middle_name'] . ' ' . $last . ' ' . $b['name_extension']));
            $b['age']       = cs_age_from_dob((string) ($b['date_of_birth'] ?? ''));
            $b['claimed']   = isset($claimed[$full]) || isset($claimed[$short]);
        }
        unset($b);

        return $beneficiaries;
    }

    // ------------------------------------------------------------------
    //  Documents, activity, notes
    // ------------------------------------------------------------------

    /**
     * @param list<int|string> $applicationIds
     *
     * @return list<array<string,mixed>> kind: id | legacy | proof | claim (see ClientDetailController::file)
     */
    private function documents(array $client, int $planId, array $applicationIds): array
    {
        $db = db_connect();
        $docs = [];

        foreach ($db->table('government_id_verifications')->where('user_id', (int) $client['user_id'])->orderBy('verification_id', 'DESC')->get()->getResultArray() as $r) {
            if (empty($r['file_path'])) {
                continue;
            }
            $docs[] = ['kind' => 'id', 'id' => (int) $r['verification_id'], 'label' => 'Government ID' . ($r['id_type'] ? ' (' . $r['id_type'] . ')' : ''), 'name' => (string) $r['original_name'], 'date' => (string) $r['created_at'], 'status' => (string) $r['verification_status']];
        }

        if (LegacyRecordService::ready()) {
            $labels = ['ledger_card' => 'Scanned ledger', 'contract' => 'Contract', 'receipt' => 'Receipt', 'id' => 'ID', 'other' => 'Other document'];
            foreach ($db->table('legacy_documents')->where('plan_holder_id', (int) $client['plan_holder_id'])->where('record_status !=', 'rejected')->orderBy('legacy_document_id', 'DESC')->get()->getResultArray() as $r) {
                $docs[] = ['kind' => 'legacy', 'id' => (int) $r['legacy_document_id'], 'label' => $labels[$r['doc_type']] ?? 'Document', 'name' => (string) $r['original_name'], 'date' => (string) $r['encoded_at'], 'status' => $r['record_status'] === 'verified' ? 'verified' : 'pending'];
            }
        }

        if ($planId > 0) {
            $proofs = $db->table('payments')->select('payment_id, amount, payment_date, proof_image')->where('plan_id', $planId)->where('proof_image IS NOT NULL', null, false)->where('proof_image !=', '')->orderBy('payment_date', 'DESC')->get()->getResultArray();
            foreach ($proofs as $r) {
                $docs[] = ['kind' => 'proof', 'id' => (int) $r['payment_id'], 'label' => 'Payment proof - ' . cs_peso($r['amount']), 'name' => (string) $r['proof_image'], 'date' => (string) $r['payment_date'], 'status' => ''];
            }
        }

        $appIds = array_values(array_filter(array_map('intval', $applicationIds)));
        if ($appIds !== []) {
            foreach ($db->table('service_application_documents')->whereIn('application_id', $appIds)->orderBy('document_id', 'DESC')->get()->getResultArray() as $r) {
                $docs[] = ['kind' => 'claim', 'id' => (int) $r['document_id'], 'label' => 'Service request document' . ($r['document_type'] ? ' (' . str_replace('_', ' ', $r['document_type']) . ')' : ''), 'name' => (string) $r['original_name'], 'date' => (string) $r['created_at'], 'status' => ''];
            }
        }

        return $docs;
    }

    /**
     * Timeline from the audit log: everything logged against this client,
     * their account, their plan, its payments and their service requests.
     *
     * @param list<int|string> $paymentIds
     * @param list<int|string> $applicationIds
     *
     * @return list<array<string,mixed>>
     */
    private function activity(array $client, int $planId, array $paymentIds, array $applicationIds): array
    {
        $db   = db_connect();
        $phId = (int) $client['plan_holder_id'];
        $uid  = (int) $client['user_id'];

        $builder = $db->table('activity_logs al')
            ->select('al.log_id, al.action, al.module, al.description, al.created_at, CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\')) AS actor', false)
            ->join('users u', 'u.user_id = al.user_id', 'left')
            ->groupStart()
                ->groupStart()->where('al.module', 'plan_holder')->where('al.target_id', $phId)->groupEnd()
                ->orGroupStart()->where('al.module', 'user')->where('al.target_id', $uid)->groupEnd()
                ->orGroupStart()->like('al.module', 'legacy', 'after')->where('al.description REGEXP ' . $db->escape('#' . $uid . '([^0-9]|$)'), null, false)->groupEnd();

        if ($planId > 0) {
            $builder->orGroupStart()->where('al.module', 'plan')->where('al.target_id', $planId)->groupEnd();
        }
        $pids = array_values(array_filter(array_map('intval', $paymentIds)));
        if ($pids !== []) {
            $builder->orGroupStart()->where('al.module', 'payment')->whereIn('al.target_id', $pids)->groupEnd();
        }
        $aids = array_values(array_filter(array_map('intval', $applicationIds)));
        if ($aids !== []) {
            $builder->orGroupStart()->whereIn('al.module', ['service_application', 'service', 'claim'])->whereIn('al.target_id', $aids)->groupEnd();
        }

        $rows = $builder->groupEnd()->orderBy('al.created_at', 'DESC')->orderBy('al.log_id', 'DESC')->limit(80)->get()->getResultArray();

        $events = [];
        $hasCreated = false;
        foreach ($rows as $r) {
            if ($r['module'] === 'user' && $r['action'] === 'created') {
                $hasCreated = true;
            }
            $events[] = ['at' => (string) $r['created_at'], 'text' => (string) $r['description'] !== '' ? (string) $r['description'] : ucfirst((string) $r['action']) . ' ' . str_replace('_', ' ', (string) $r['module']), 'actor' => trim((string) $r['actor']), 'action' => (string) $r['action']];
        }

        if (! $hasCreated && ! empty($client['account_created_at'])) {
            $events[] = ['at' => (string) $client['account_created_at'], 'text' => 'Account created', 'actor' => '', 'action' => 'created'];
        }

        usort($events, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return $events;
    }

    /**
     * Internal notes (staff only - never shown to the client): the plan
     * holder's remarks line plus any past-record history notes.
     *
     * @return array{remarks:string, history:list<array<string,mixed>>}
     */
    private function notes(array $client): array
    {
        $history = [];
        if (LegacyRecordService::ready()) {
            $history = db_connect()->table('legacy_notes n')
                ->select("n.note, n.encoded_at, n.record_status, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS author", false)
                ->join('users u', 'u.user_id = n.encoded_by', 'left')
                ->where('n.plan_holder_id', (int) $client['plan_holder_id'])
                ->where('n.record_status !=', 'rejected')
                ->orderBy('n.legacy_note_id', 'DESC')
                ->get()
                ->getResultArray();
        }

        return ['remarks' => (string) ($client['remarks'] ?? ''), 'history' => $history];
    }
}
