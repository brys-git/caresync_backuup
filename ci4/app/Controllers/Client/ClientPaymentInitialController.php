<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\CollectorGcashService;
use App\Services\NotificationService;
use App\Services\ApprovalService;
use App\Config\ValidationRules;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\MembershipService;

/**
 * ClientPaymentInitialController
 * 
 * Handles initial payment submission and verification
 * Part of the refactored ClientPortal controller
 * 
 * Uses centralized validation rules to reduce code duplication
 */
class ClientPaymentInitialController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Display initial payment form
     */
    public function initialPayment(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/dashboard')->with('info', 'Your membership is already active.');
        }

        if (($access['state'] ?? 'unregistered') === 'unregistered') {
            return redirect()->to('/plan-info')->with('info', 'Initial payment is required during registration.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found.');
        }

        // Initial Payment is only reachable once a Branch Admin/Staff has
        // manually verified the Government ID submitted at registration -
        // see Client\IdVerificationStatusController and
        // IdVerificationQueueController (the review side). 'verified' is
        // the only status that clears this gate; 'needs_review' means it's
        // still waiting on staff, 'failed'/null means the applicant needs
        // to upload a better/valid ID first.
        if ($this->latestIdVerificationStatus((int) $access['user']['user_id']) !== 'verified') {
            return redirect()->to('/id-verification-status');
        }

        $planHolderId = (int) $planHolder['plan_holder_id'];
        $plan = $this->latestPlan($planHolderId);
        if (! $plan) {
            // This used to redirect back to /plan-info - but plan-info
            // redirects here whenever access.state is 'awaiting_activation'
            // (a registered plan holder with no *active* plan), which is
            // also true whenever there's no plan record at all. A plan
            // holder with a plan_holders row but zero plans rows (possible
            // from older data, or a registration path that never called
            // ClientRegistrationService::createMembershipPlan()) bounced
            // between these two routes forever. There's nothing the plan
            // holder can self-service here - they need staff to assign a
            // plan first (Packages::assignToPlan(), section 1) - so land
            // somewhere that doesn't loop and says so.
            return redirect()->to('/client/dashboard')->with('error', 'Your plan holder registration has no plan assigned yet. Please contact your branch for assistance.');
        }

        $program = MembershipService::getProgramInfo();
        $monthlyFee = (float) ($program['monthly_fee'] ?? ($plan['monthly_fee'] ?? 0));
        $latestInitialPayment = $this->latestInitialPayment($planHolderId);

        // Shown only while the client has GCash selected - same
        // barangay-matched collector account as the advance payment page.
        $gcashAccount = (new CollectorGcashService())->resolveForClient(
            (int) ($planHolder['branch_id'] ?? 0),
            $planHolder['barangay_code'] ?? null
        );

        return view('client/initial_payment', [
            'gcash_account' => $gcashAccount,
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Initial payment',
            'page_sub' => 'Submit your initial contribution (minimum 2 months) for verification.',
            'access' => $access,
            'plan_holder' => $planHolder,
            'plan' => $plan,
            'monthly_fee' => $monthlyFee,
            'program' => $program,
            'latest_initial_payment' => $latestInitialPayment,
        ]);
    }

    /**
     * Submit initial payment (GCash or Cash)
     */
    public function submitInitialPayment()
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/dashboard')->with('info', 'Your membership is already active.');
        }

        if (($access['state'] ?? 'unregistered') === 'unregistered') {
            return redirect()->to('/plan-info')->with('error', 'Access denied.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found.');
        }

        // Same Government ID gate as initialPayment() (GET) above - never
        // trust the client only having reached this page once; re-check on
        // the actual submission too.
        if ($this->latestIdVerificationStatus((int) $user['user_id']) !== 'verified') {
            return redirect()->to('/id-verification-status')->with('error', 'Your Government ID must be verified before you can submit a payment.');
        }

        $paymentMethod = $this->request->getPost('payment_method');
        if (! in_array($paymentMethod, ['gcash', 'cash'], true)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid payment method.');
        }

        $rules = [
            'payment_method' => 'required|in_list[gcash,cash]',
            // Panel brief, section 3: advance payment must support 1-59 months.
            // The initial payment specifically requires a 2-month minimum
            // (user request, 2026-09-09) so a client is immediately eligible
            // for services/claims (months_paid >= 2) right after their first
            // payment is approved, instead of needing a second payment cycle.
            'months_covered' => 'required|is_natural_no_zero|greater_than_equal_to[2]|less_than_equal_to[59]',
            'reference_number' => 'required|string|min_length[5]|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        try {
            $db = db_connect();

            $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);

            if (! $plan) {
                return redirect()->back()->with('error', 'Plan information not found.');
            }

            $program = MembershipService::getProgramInfo();
            $monthlyFee = (float) ($program['monthly_fee'] ?? ($plan['monthly_fee'] ?? 0));
            $monthsCovered = max(1, (int) $this->request->getPost('months_covered'));
            $amount = $monthlyFee * $monthsCovered;

            $db->transStart();

            $paymentData = [
                'plan_id' => (int) $plan['plan_id'],
                'amount' => number_format($amount, 2, '.', ''),
                'months_covered' => $monthsCovered,
                'payment_date' => date('Y-m-d'),
                'payment_method' => $paymentMethod,
                'reference_number' => null,
                'received_by' => null,
                'branch_id' => (int) ($planHolder['branch_id'] ?? 0),
                'remarks' => null,
                'status' => 'pending',
            ];

            $isAutoApproved = false;
            $paymentId = null;

            if ($paymentMethod === 'gcash') {
                $referenceNumber = (string) $this->request->getPost('reference_number');

                // Check for duplicate GCash references
                $existingPayment = $db->table('payments')
                    ->where('reference_number', $referenceNumber)
                    ->where('payment_method', 'gcash')
                    ->where('plan_id', (int) $plan['plan_id'])
                    ->get()
                    ->getRowArray();

                if ($existingPayment) {
                    $db->transRollback();

                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'This GCash reference has already been used.');
                }

                $paymentData['reference_number'] = $referenceNumber;
                
                // Insert new GCash payment with status='pending'
                $db->table('payments')->insert($paymentData);
                $paymentId = (int) $db->insertID();
            } elseif ($paymentMethod === 'cash') {
                $receiptNumber = (string) $this->request->getPost('reference_number');
                $planId = (int) $plan['plan_id'];

                // Debug logging
                log_message('debug', '[CashPayment] Client entered receipt: "' . $receiptNumber . '" for plan_id: ' . $planId);
                log_message('debug', '[CashPayment] Plan details: ' . json_encode($plan));

                // Fetch the latest branch-recorded cash payment for this plan and compare the OR/reference directly.
                $cashRecord = $db->table('payments')
                    ->select('payment_id, plan_id, amount, payment_date, payment_method, reference_number, official_receipt_number, status, remarks')
                    ->where('plan_id', $planId)
                    ->where('payment_method', 'cash')
                    ->orderBy('payment_id', 'DESC')
                    ->limit(1)
                    ->get()
                    ->getRowArray();

                log_message('debug', '[CashPayment] Latest cash payment row for plan ' . $planId . ': ' . json_encode($cashRecord));

                $branchReceipt = trim((string) ($cashRecord['official_receipt_number'] ?? ''));
                $storedReference = trim((string) ($cashRecord['reference_number'] ?? ''));
                $storedStatus = strtolower(trim((string) ($cashRecord['status'] ?? '')));

                if ($storedStatus !== 'paid' || $receiptNumber === '' || (
                    ($branchReceipt === '' || $branchReceipt !== $receiptNumber) &&
                    ($storedReference === '' || $storedReference !== $receiptNumber)
                )) {
                    log_message('debug', '[CashPayment] Latest row did not match. Branch OR="' . $branchReceipt . '", stored reference="' . $storedReference . '", client input="' . $receiptNumber . '", status="' . $storedStatus . '"');

                    $db->transRollback();

                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Receipt number not found or payment already processed. Please verify with your branch admin.');
                }

                // Update the existing payment record to make sure it stays marked as paid.
                $db->table('payments')
                    ->where('payment_id', (int) $cashRecord['payment_id'])
                    ->set(['status' => 'paid'])
                    ->update();

                $paymentId = (int) $cashRecord['payment_id'];
                $amount = (float) $cashRecord['amount']; // Use the amount that branch admin recorded
                $isAutoApproved = true;
            }

            if (!$paymentId) {
                throw new \RuntimeException('Failed to process payment');
            }

            $db->transComplete();

            // For cash OR match, fully auto-approve registration with no manual branch action.
            if ($isAutoApproved) {
                $approved = (new ApprovalService())->approveInitialPayment((int) $paymentId);

                if (! $approved) {
                    // Fallback activation path if service-level approval returns false.
                    $db->table('plans')
                        ->where('plan_id', (int) $plan['plan_id'])
                        ->set(['status' => 'active'])
                        ->update();

                    $db->table('plan_holders')
                        ->where('plan_holder_id', (int) $planHolder['plan_holder_id'])
                        ->set(['status' => 'active'])
                        ->update();

                    $db->table('users')
                        ->where('user_id', (int) $user['user_id'])
                        ->set([
                            'is_plan_holder' => 1,
                            'account_status' => 'verified',
                        ])
                        ->update();

                    log_message('warning', '[CashPayment] ApprovalService fallback activation applied for payment_id=' . (int) $paymentId);
                }
            }

            (new NotificationService())->notify(
                (int) $user['user_id'],
                'Initial payment of PHP ' . number_format($amount, 2) . ' has been ' . ($isAutoApproved ? 'verified and approved!' : 'submitted for verification.'),
                'payment_' . ($isAutoApproved ? 'approved' : 'submitted')
            );

            // For approved cash, go to dashboard; for GCash, wait for verification
            $message = $isAutoApproved 
                ? 'Payment verified! Your membership is now active.' 
                : 'Payment submitted. Waiting for verification.';

            return redirect()->to('/dashboard')
                ->with('success', $message);
        } catch (\Exception $e) {
            log_message('error', '[ClientPaymentInitial] submitInitialPayment error: ' . $e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'An error occurred during payment submission. Please try again.');
        }
    }

    /**
     * Verify initial payment and trigger approval
     */
    public function verifyInitialPayment(int $paymentId)
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        $payment = db_connect()->table('payments')
            ->select('payment_id, plan_id, status, payment_method')
            ->where('payment_id', $paymentId)
            ->get()
            ->getRowArray();

        if (! $payment) {
            return redirect()->to('/client/dashboard')->with('error', 'Payment not found.');
        }

        $currentPlan = $this->latestPlan((int) ($access['plan_holder']['plan_holder_id'] ?? 0));
        if ((int) ($currentPlan['plan_id'] ?? 0) !== (int) ($payment['plan_id'] ?? 0)) {
            return redirect()->to('/client/dashboard')->with('error', 'Unauthorized access.');
        }

        return view('client/payment_verify', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'payment' => $payment,
        ]);
    }
}
