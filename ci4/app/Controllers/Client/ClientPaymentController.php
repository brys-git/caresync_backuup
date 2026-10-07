<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PaymentModel;
use App\Config\ValidationRules;
use App\Services\CollectorGcashService;
use App\Services\MembershipService;
use App\Services\PaymentService;

/**
 * ClientPaymentController
 *
 * Handles client payment submissions and tracking
 * Part of the refactored ClientPortal controller
 *
 * Uses centralized validation rules to reduce code duplication
 */
class ClientPaymentController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Task 1 (Payments page merge): PAYMENT overview + full history in one
     * place - plan name, monthly contribution, remaining balance, months
     * paid (+ an awaiting-verification note so a client understands why
     * the number hasn't moved yet and isn't tempted to pay twice), next
     * due date, a Make Payment button, and the complete payment history
     * table (newest first). Previously split across this page and
     * paymentHistory()/payment_history.php, reachable only from a
     * dashboard quick action and not from the sidebar at all.
     */
    public function payment(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $planHolder = $access['plan_holder'];

        if (($access['state'] ?? 'unregistered') === 'unregistered' || ! $planHolder) {
            return view('client/payment', [
                'role_layout' => 'layouts/plan_holder',
                'page_title' => 'Payments',
            'show_page_head' => false,
                'page_sub' => 'Track contribution records.',
                'access' => $access,
                'plan' => null,
            ]);
        }

        if (($access['state'] ?? 'unregistered') === 'awaiting_activation') {
            return redirect()->to('/initial-payment')->with('info', 'Complete your initial payment before viewing payment history.');
        }

        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        $schedule = null;
        $due = null;
        $history = [];
        if ($plan) {
            $schedule = (new PaymentService())->paymentSchedule($plan);
            $history = $this->historyRows($this->paymentRows((int) $plan['plan_id']));
            $history = array_merge($history, $this->pastHistoryRows((int) $plan['plan_id']));
            usort($history, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
            $due = $this->dueSummary($plan);
        }

        return view('client/payment', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Payments',
            'show_page_head' => false,
            'page_sub' => 'Track contribution records.',
            'access' => $access,
            'plan' => $plan,
            'plan_name' => $plan ? (new \App\Services\MembershipService())->resolvePackageName((int) ($plan['package_id'] ?? 0)) : '',
            'schedule' => $schedule,
            'due' => $due,
            'standing' => $due['standing'] ?? null,
            'history' => $history,
        ]);
    }

    /**
     * @return array{amount:float, date:?string, days:?int, overdue:bool, overdue_since:?string, covered_until:?string}
     */
    private function dueSummary(array $plan): array
    {
        $today = date('Y-m-d');
        // Live numbers from the one rule (PlanLedgerService), not the stored columns.
        $s = (new \App\Services\PlanLedgerService())->summarize((int) $plan['plan_id'], $today);
        $nextDue = $s['oldest_unpaid_due'] ?? null;
        if ($s && $s['lifecycle'] === 'fully_paid') {
            $nextDue = null;
        }
        $coverageUntil = $s && $s['months_paid'] > 0 ? $s['paid_through'] : null;
        $overdue = $s ? $s['overdue_count'] > 0 : false;

        return [
            'amount' => (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE),
            'pay_amount' => $overdue ? (float) $s['overdue_amount'] : (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE),
            'standing' => $s,
            'date' => $nextDue,
            'days' => $nextDue !== null ? (int) round((strtotime($nextDue) - strtotime($today)) / 86400) : null,
            'overdue' => $overdue,
            'overdue_since' => $nextDue ?? $coverageUntil,
            'covered_until' => $coverageUntil,
        ];
    }

    /**
     * @param list<array<string,mixed>> $payments
     * @return list<array<string,mixed>>
     */
    private function historyRows(array $payments): array
    {
        return array_map(function (array $payment): array {
            $status = (string) ($payment['status'] ?? '');
            $method = (string) ($payment['payment_method'] ?? '');
            $receipt = trim((string) ($payment['official_receipt_number'] ?: $payment['reference_number'] ?: ''));

            return [
                'id' => (int) $payment['payment_id'],
                'date' => $payment['payment_date'] ?? null,
                'covers' => $this->coversLabel($payment['coverage_start'] ?? null, (int) ($payment['months_covered'] ?? 1)),
                'amount' => (float) ($payment['amount'] ?? 0),
                'method' => match ($method) {
                    'gcash' => 'GCash',
                    'cash' => 'Cash · branch',
                    default => $method !== '' ? ucfirst($method) : '—',
                },
                'status' => match ($status) {
                    'paid' => 'approved',
                    'cancelled' => 'rejected',
                    default => 'pending',
                },
                'receipt' => $receipt !== '' ? $receipt : null,
                'receipt_url' => $status === 'paid' ? base_url('client/payment/download-receipt/' . (int) $payment['payment_id']) : null,
            ];
        }, $payments);
    }

    /**
     * Verified existing-member records, in the same shape as historyRows(),
     * flagged so the table can label them "Recorded by branch".
     *
     * @return list<array<string,mixed>>
     */
    private function pastHistoryRows(int $planId): array
    {
        return array_map(function (array $p): array {
            $method = (string) ($p['channel_label'] ?? 'Cash');

            return [
                'id'          => (string) $p['payment_id'],
                'date'        => $p['payment_date'],
                'covers'      => $this->coversLabel($p['coverage_start'] ?? null, (int) $p['months_covered']),
                'amount'      => (float) $p['amount'],
                'method'      => $method,
                'status'      => 'approved',
                'receipt'     => $p['official_receipt_number'] ?: null,
                'receipt_url' => null,
                'recorded_by_branch' => true,
            ];
        }, \App\Services\LegacyRecordService::verifiedPaymentsAsRows($planId));
    }

    private function coversLabel(?string $coverageStart, int $months): string
    {
        $months = max(1, $months);
        $ts = $coverageStart ? strtotime($coverageStart) : false;
        if ($ts === false) {
            return $months . ' month' . ($months === 1 ? '' : 's');
        }

        $first = strtotime(date('Y-m-01', $ts));
        if ($months === 1) {
            return date('M Y', $first);
        }

        $last = strtotime('+' . ($months - 1) . ' months', $first);

        return date('Y', $first) === date('Y', $last)
            ? date('M', $first) . ' – ' . date('M Y', $last)
            : date('M Y', $first) . ' – ' . date('M Y', $last);
    }

    /**
     * Task 1: payment history is no longer a separate page - the history
     * table now lives on client/payment itself. Route kept (not removed)
     * so the old sidebar/bookmark/dashboard-quick-action URL still lands
     * somewhere sensible instead of 404ing.
     */
    private function paymentRows(int $planId): array
    {
        return (new PaymentModel())
            ->where('plan_id', $planId)
            ->orderBy('payment_date', 'DESC')
            ->orderBy('payment_id', 'DESC')
            ->findAll();
    }

    /**
     * Client dashboard rebuild, Phase 3: Make Payment page. Read-only plan
     * name/monthly contribution, a months selector capped by how much of
     * the plan's term is left to pay for, a fixed GCash payment method, and
     * a reference number field - the actual amount is never entered by the
     * client, only computed (client-side for display, server-side for
     * real) from months selected x monthly fee.
     */
    public function makePayment(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        if (($access['state'] ?? 'unregistered') !== 'active' || ! $access['plan_holder']) {
            return redirect()->to('/client/payment')->with('error', 'Access denied. Payment submission requires active membership.');
        }

        $planHolder = $access['plan_holder'];
        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        if (! $plan) {
            return redirect()->to('/client/payment')->with('error', 'No active plan found for payment submission.');
        }

        $schedule = (new PaymentService())->paymentSchedule($plan);

        // The GCash account of the collector assigned to this client's
        // barangay (falling back to a branch-wide collector) - see
        // CollectorGcashService::resolveForClient().
        $gcashAccount = (new CollectorGcashService())->resolveForClient(
            (int) ($planHolder['branch_id'] ?? 0),
            $planHolder['barangay_code'] ?? null
        );

        $chips = [];
        for ($index = 1; $index <= $schedule['remaining_months']; $index++) {
            $monthStart = strtotime(PaymentService::monthStart($schedule['anchor'], $schedule['offset'] + $index - 1));
            $chips[] = [
                'index' => $index,
                'label' => date('M Y', $monthStart),
                'month' => date('Y-m', $monthStart),
                'year' => (int) date('Y', $monthStart),
                'short' => date('M', $monthStart),
            ];
        }

        return view('client/payment_make', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Make payment',
            'show_page_head' => false,
            'page_sub' => 'Submit a GCash payment toward your plan.',
            'access' => $access,
            'plan' => $plan,
            'plan_name' => (new MembershipService())->resolvePackageName((int) ($plan['package_id'] ?? 0)),
            'schedule' => $schedule,
            'chips' => $chips,
            'gcash' => $this->gcashDisplay($gcashAccount),
        ]);
    }

    /**
     * @return array{formatted:string, digits:string, collector_name:string, account_name:?string, qr_url:?string}|null
     */
    private function gcashDisplay(?array $account): ?array
    {
        if ($account === null) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', (string) $account['gcash_number']);

        return [
            'formatted' => trim((string) preg_replace('/^(\d{4})(\d{3})(\d{0,4})$/', '$1 $2 $3', $digits)),
            'digits' => $digits,
            'collector_name' => (string) $account['collector_name'],
            'account_name' => $account['account_name'] ?? null,
            'qr_url' => ! empty($account['qr_path']) ? base_url('gcash-qr/' . (int) $account['user_id']) : null,
        ];
    }

    /**
     * Client dashboard rebuild, Phase 3: server-side re-validation for the
     * Make Payment form. Never trusts the client for months covered or
     * amount - the months cap and the total are both recomputed here from
     * the plan's own current state, exactly like makePayment() computed
     * them for display. payment_method and status are never read from
     * POST: GCash is the only method this page offers, and every
     * client-submitted payment starts 'pending' pending branch admin
     * verification, same as the old submitGcashPayment() flow.
     */
    public function submitPayment()
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        if (($access['state'] ?? 'unregistered') !== 'active' || ! $access['plan_holder']) {
            return redirect()->to('/client/payment')->with('error', 'Access denied. Payment submission requires active membership.');
        }

        $planHolder = $access['plan_holder'];
        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        if (! $plan) {
            return redirect()->to('/client/payment')->with('error', 'No active plan found for payment submission.');
        }

        $schedule = (new PaymentService())->paymentSchedule($plan);
        $remainingMonths = $schedule['remaining_months'];

        $rules = [
            'months_count' => 'required|is_natural_no_zero',
            'covers_from' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            'covers_to' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            // GCash reference numbers are 13 numeric digits.
            'reference_number' => 'required|regex_match[/^\d{13}$/]',
        ];
        $messages = [
            'months_count' => ['is_natural_no_zero' => 'Select how many months you want to pay.'],
            'covers_from' => ['regex_match' => 'Your month selection is incomplete. Please choose the months again.'],
            'covers_to' => ['regex_match' => 'Your month selection is incomplete. Please choose the months again.'],
            'reference_number' => ['regex_match' => 'Enter a valid 13-digit GCash reference number.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->to('/client/payment/make')->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $monthsCovered = (int) $this->request->getPost('months_count');

        if ($remainingMonths <= 0) {
            return redirect()->to('/client/payment')->with('error', 'Your plan has no remaining months left to pay.');
        }

        if ($monthsCovered > $remainingMonths) {
            return redirect()->to('/client/payment/make')->withInput()->with('error', "You can pay for at most {$remainingMonths} more month(s) on this plan.");
        }

        $coverageStart = PaymentService::monthStart($schedule['anchor'], $schedule['offset']);
        $coverageEnd = PaymentService::monthEnd($coverageStart, $monthsCovered - 1);
        if (substr($coverageStart, 0, 7) !== (string) $this->request->getPost('covers_from')
            || substr($coverageEnd, 0, 7) !== (string) $this->request->getPost('covers_to')) {
            return redirect()->to('/client/payment/make')->withInput()->with('error', 'Your payment schedule changed while you were filling this in. Please review the months and try again.');
        }

        $reference = trim((string) $this->request->getPost('reference_number'));
        $duplicate = (new PaymentModel())
            ->where('reference_number', $reference)
            ->first();

        if ($duplicate) {
            return redirect()->to('/client/payment/make')->withInput()->with('error', 'Duplicate reference number detected. Please verify your reference number.');
        }

        $amount = round(min($monthsCovered * $schedule['monthly_fee'], $schedule['payable_amount']), 2);

        $payload = [
            'plan_id' => (int) $plan['plan_id'],
            'amount' => $amount,
            'months_covered' => $monthsCovered,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'gcash',
            'reference_number' => $reference,
            'received_by' => null,
            'branch_id' => (int) ($planHolder['branch_id'] ?? 0),
            'status' => 'pending',
            'coverage_start' => $coverageStart,
            'coverage_end' => $coverageEnd,
            'remarks' => 'Submitted by client, awaiting branch verification',
        ];

        $paymentModel = new PaymentModel();
        $paymentId = (int) $paymentModel->insert($payload, true);

        if ($paymentId > 0) {
            $paymentModel->update($paymentId, [
                'official_receipt_number' => (new PaymentService())->generateReceiptNumber($paymentId, $payload['payment_date']),
            ]);
        }

        return redirect()->to('/client/payment')->with('success', 'GCash payment submitted. Waiting for branch admin verification.');
    }

    /**
     * Submit GCash payment for membership fee
     */
    public function submitGcashPayment()
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        if (($access['state'] ?? 'unregistered') !== 'active') {
            return redirect()->back()->with('error', 'Access denied. Payment submission requires active membership.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->back()->with('error', 'No plan holder profile found.');
        }

        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        if (! $plan) {
            return redirect()->back()->with('error', 'No active plan found for payment submission.');
        }

        $rules = ValidationRules::getPaymentRules();
        $messages = ValidationRules::getValidationMessages();

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $monthsCovered = max(1, (int) $this->request->getPost('months_covered'));
        $amount = (float) $this->request->getPost('amount');
        $monthlyFee = (float) ($plan['monthly_fee'] ?? 0);
        $expectedAmount = round($monthlyFee * $monthsCovered, 2);
        if ($expectedAmount <= 0 || abs($expectedAmount - $amount) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Amount must match your monthly fee multiplied by months covered.');
        }

        if ($amount > (float) ($plan['remaining_balance'] ?? 0)) {
            return redirect()->back()->withInput()->with('error', 'Payment exceeds your remaining balance.');
        }

        $reference = trim((string) $this->request->getPost('reference_number'));
        $duplicate = (new PaymentModel())
            ->where('reference_number', $reference)
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Duplicate reference number detected. Please verify your reference number.');
        }

        $coverage = (new PaymentService())->projectCoverage($plan, $monthsCovered);

        $payload = [
            'plan_id' => (int) $plan['plan_id'],
            'amount' => $amount,
            'months_covered' => $monthsCovered,
            'payment_date' => (string) $this->request->getPost('payment_date'),
            'payment_method' => 'gcash',
            'reference_number' => $reference,
            'received_by' => null,
            'branch_id' => (int) ($planHolder['branch_id'] ?? 0),
            'status' => 'pending',
            'coverage_start' => $coverage['start'],
            'coverage_end' => $coverage['end'],
            'remarks' => 'Submitted by client, awaiting branch verification',
        ];

        $proof = $this->request->getFile('proof_image');
        if ($this->supportsProofUpload() && $monthsCovered > 1 && (! $proof || ! $proof->isValid())) {
            return redirect()->back()->withInput()->with('error', 'Proof image is required for advance payments.');
        }
        if ($this->supportsProofUpload() && $proof && $proof->isValid() && ! $proof->hasMoved()) {
            $uploadDir = WRITEPATH . 'uploads/payment-proofs';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $proofName = $proof->getRandomName();
            $proof->move($uploadDir, $proofName);
            $payload['proof_image'] = $proofName;
        }

        $paymentModel = new PaymentModel();
        $paymentId = (int) $paymentModel->insert($payload, true);

        if ($paymentId > 0) {
            // Panel brief, section 3: auto-generate the receipt number, no manual entry.
            $paymentModel->update($paymentId, [
                'official_receipt_number' => (new PaymentService())->generateReceiptNumber($paymentId, $payload['payment_date']),
            ]);
        }

        return redirect()->to('/client/payment')->with('success', 'GCash payment submitted. Waiting for branch admin verification.');
    }

    /**
     * Download the receipt for one specific payment (Phase 1: was a
     * dashboard quick action that only ever grabbed the plan's latest
     * paid payment - now a per-row link in Payment History, so it needs
     * to fetch the exact payment the client clicked, not just the most
     * recent one).
     *
     * Never trusts the payment ID alone: re-verifies server-side that the
     * payment belongs to a plan owned by the requesting client's own
     * plan_holder_id (not just "some plan"), and that its status is
     * 'paid' - the UI only ever links to paid rows, but a client could
     * still hand-edit the URL to a pending/cancelled payment_id otherwise.
     */
    public function downloadReceipt(int $paymentId)
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $planHolder = $access['plan_holder'] ?? null;
        if (! $planHolder) {
            return redirect()->back()->with('error', 'No plan holder profile found.');
        }

        $payment = db_connect()->table('payments pay')
            ->select('pay.*')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->where('pay.payment_id', $paymentId)
            ->where('p.plan_holder_id', (int) $planHolder['plan_holder_id'])
            ->where('pay.status', 'paid')
            ->get()
            ->getRowArray();

        if (! $payment) {
            return redirect()->back()->with('error', 'Receipt not found, or this payment has not been verified yet.');
        }

        $content = "Receipt #" . $payment['payment_id'] . "\n";
        $content .= "Date: " . ($payment['payment_date'] ?? '') . "\n";
        $content .= "Amount: PHP " . number_format((float) ($payment['amount'] ?? 0), 2) . "\n";
        $content .= "Method: " . strtoupper((string) ($payment['payment_method'] ?? '')) . "\n";
        $content .= "Reference/OR: " . ($payment['reference_number'] ?: ($payment['official_receipt_number'] ?? '-')) . "\n";
        $content .= "Status: " . ($payment['status'] ?? '') . "\n";

        $filename = 'receipt-' . $payment['payment_id'] . '.txt';

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }
}
