<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Services\ActivityLogService;
use App\Services\ClientService;
use App\Services\GovernmentIdVerificationService;
use App\Services\MembershipService;
use App\Services\NotificationService;
use App\Services\PlanHolderRegistrationService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ClientController extends BaseController
{
    private ClientService $clientService;

    public function __construct()
    {
        $this->clientService = new ClientService();
    }

    public function index(): string
    {
        $branchId = (int) session('branch_id');
        $search = cs_search_term($this->request->getGet('search'));
        $clients = $branchId > 0
            ? $this->clientService->getClientsByBranch($branchId, $search)
            : [];

        return view('branch_admin/client_management/index', [
            'holders' => $clients,
            'search' => $search,
            'program' => MembershipService::getProgramInfo(),
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Client Management',
            'page_sub' => 'Review registration payment status and open plan holder details for activation approval.',
        ]);
    }

    public function edit(int $id): string
    {
        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->enforceBranchOwnership($client);

        return view('branch_admin/clients/edit', [
            'client' => $client,
            'beneficiaries' => $client['beneficiaries'] ?? [],
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Edit plan holder',
            'page_sub' => 'Plan Holder #' . (string) ($client['plan_holder_id'] ?? $id),
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('branch-admin/client-management')],
                ['label' => 'Edit plan holder'],
            ],
        ]);
    }

    public function update(int $id)
    {
        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->enforceBranchOwnership($client);

        $userId = (int) $client['user_id'];

        $rules = [
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'email' => "required|valid_email|max_length[100]|is_unique[users.email,user_id,{$userId}]",
            'contact_number' => 'permit_empty|max_length[30]',
            'date_of_birth' => 'permit_empty|valid_date',
            'spouse_birthdate' => 'permit_empty|valid_date',
            'application_date' => 'permit_empty|valid_date',
            'height' => 'permit_empty|decimal',
            'weight' => 'permit_empty|decimal',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));
        if ($dateOfBirth !== '' && $dateOfBirth > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('error', 'Date of birth cannot be in the future.');
        }

        try {
            $this->clientService->updateClient($id, [
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'middle_name' => trim((string) $this->request->getPost('middle_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
                'email' => trim((string) $this->request->getPost('email')),
                'contact_number' => trim((string) $this->request->getPost('contact_number')),
                'id_control_no' => trim((string) $this->request->getPost('id_control_no')),
                'coordinator' => trim((string) $this->request->getPost('coordinator')),
                'application_date' => trim((string) $this->request->getPost('application_date')),
                'address_no' => trim((string) $this->request->getPost('address_no')),
                'address_street' => trim((string) $this->request->getPost('address_street')),
                'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
                'address_city' => trim((string) $this->request->getPost('address_city')),
                'barangay_code' => trim((string) $this->request->getPost('barangay_code')),
                'city_municipality_code' => trim((string) $this->request->getPost('city_municipality_code')),
                'date_of_birth' => $dateOfBirth,
                'place_of_birth' => trim((string) $this->request->getPost('place_of_birth')),
                'age' => cs_age_from_dob($dateOfBirth),
                'gender' => trim((string) $this->request->getPost('gender')),
                'civil_status' => trim((string) $this->request->getPost('civil_status')),
                'citizenship' => trim((string) $this->request->getPost('citizenship')),
                'height' => trim((string) $this->request->getPost('height')),
                'weight' => trim((string) $this->request->getPost('weight')),
                'spouse_name' => trim((string) $this->request->getPost('spouse_name')),
                'spouse_birthdate' => trim((string) $this->request->getPost('spouse_birthdate')),
                'spouse_occupation' => trim((string) $this->request->getPost('spouse_occupation')),
                'senior_citizen_id' => trim((string) $this->request->getPost('senior_citizen_id')),
                'organization_affiliation' => trim((string) $this->request->getPost('organization_affiliation')),
                'status' => trim((string) $this->request->getPost('status')),
                'emergency_contact_name' => trim((string) $this->request->getPost('emergency_contact_name')),
                'emergency_contact_relationship' => trim((string) $this->request->getPost('emergency_contact_relationship')),
                'emergency_contact_number' => trim((string) $this->request->getPost('emergency_contact_number')),
                'emergency_contact_address' => trim((string) $this->request->getPost('emergency_contact_address')),
                'beneficiaries' => $this->request->getPost('beneficiaries') ?? [],
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/branch-admin/client/view/' . $id)->with('success', 'Client information updated successfully.');
    }

    public function create(): string
    {
        $branchId = (int) session('branch_id');

        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $branch = db_connect()->table('branches')->select('branch_name')->where('branch_id', $branchId)->get()->getRowArray();

        $existingUsers = db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.contact_number')
            ->join('plan_holders ph', 'ph.user_id = u.user_id', 'left')
            ->where('u.role_id', 4)
            ->where('ph.plan_holder_id IS NULL', null, false)
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('branch_admin/clients/register', [
            'existing_users' => $existingUsers,
            'branch_name' => (string) ($branch['branch_name'] ?? ''),
            'program' => MembershipService::getProgramInfo(),
            'plans' => (new \App\Services\PlanBuilderService())->listApprovedActive(),
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Register plan holder',
            'page_sub' => 'Add a new plan holder to your branch.',
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('branch-admin/client-management')],
                ['label' => 'Register plan holder'],
            ],
            'id_types' => (new GovernmentIdVerificationService())->idTypes(),
        ]);
    }

    /**
     * Thin now (Prompt 7, Step D): required/format validation only, then
     * PlanHolderRegistrationService::register() owns PSGC verification,
     * Government ID enforcement (including matching the identity of
     * whichever account - new or linked-existing - actually gets used),
     * beneficiaries, and plan_holder/plan/account creation. The
     * new-vs-existing distinction no longer needs branching here: the
     * "existing account" mode's own JS (caresync-wizard.js's Account
     * section) already autofills first_name/middle_name/last_name/
     * contact_number from the matched account before submit, so the posted
     * identity is correct either way, and ClientService::registerPlanHolder()
     * (called inside the service) already auto-detects an existing account
     * by email.
     */
    public function store()
    {
        $branchId = (int) session('branch_id');

        if ($branchId <= 0) {
            return redirect()->back()->with('error', 'Branch information is missing.');
        }

        $rules = [
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'permit_empty|max_length[30]',
            'date_of_birth' => 'permit_empty|valid_date',
            'gender' => 'permit_empty|in_list[Male,Female,Other]',
            'civil_status' => 'permit_empty|in_list[Single,Married,Divorced,Widowed]',
            'citizenship' => 'permit_empty|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $planHolderId = (new PlanHolderRegistrationService())->register(
                $this->request->getPost() ?? [],
                $branchId,
                'staff',
                null,
                (int) session('user_id')
            );

            $client = $this->clientService->getClientDetails($planHolderId);
            $targetUserId = (int) ($client['user_id'] ?? 0);
            if ($targetUserId > 0) {
                (new NotificationService())->notify(
                    $targetUserId,
                    'Your account was linked as a plan holder and your Damayan Burial Program plan was registered by branch admin.',
                    'registration_pending'
                );

                (new ActivityLogService())->log(
                    (int) session('user_id'),
                    'created',
                    'plan_holder',
                    $planHolderId,
                    'Registered plan holder from branch admin client form',
                    null,
                    ['branch_id' => $branchId]
                );
            }

            return redirect()->to('/branch-admin/client/view/' . $planHolderId)->with('success', 'Plan holder registered successfully!');
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Registration failed. Please try again.');
        }
    }

    public function approve(int $id)
    {
        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->enforceBranchOwnership($client);

        // Check if client has pending initial payment
        // getClientDetails() returns the plan nested under 'plan', not a
        // top-level 'plan_id' - this always evaluated to 0, meaning
        // approve() could never actually find the pending payment it's
        // supposed to approve.
        $pendingPayment = db_connect()->table('payments')
            ->select('payment_id, amount, payment_method, reference_number, months_covered')
            ->where('plan_id', (int) ($client['plan']['plan_id'] ?? 0))
            ->where('status', 'pending')
            ->orderBy('payment_date', 'DESC')
            ->get()
            ->getRowArray();

        if (! $pendingPayment) {
            return redirect()->back()->with('error', 'No pending payment found for approval.');
        }

        try {
            // Update payment status to paid
            db_connect()->table('payments')
                ->where('payment_id', (int) $pendingPayment['payment_id'])
                ->update(['status' => 'paid']);

            // Trigger approval service
            (new \App\Services\ApprovalService())->approveInitialPayment((int) $pendingPayment['payment_id']);

            // Log activity
            (new ActivityLogService())->log(
                (int) session('user_id'),
                'approved',
                'payment',
                (int) $pendingPayment['payment_id'],
                'Approved initial payment for plan holder',
                null,
                ['plan_holder_id' => $id, 'amount' => $pendingPayment['amount']]
            );

            // Notify client
            (new NotificationService())->notify(
                (int) $client['user_id'],
                'Your initial payment has been approved! Your Damayan Burial Program membership is now active.',
                'payment_approved'
            );

            return redirect()->back()->with('success', 'Payment approved and membership activated successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to approve payment: ' . $e->getMessage());
        }
    }

    private function enforceBranchOwnership(array $client): void
    {
        $sessionBranchId = (int) session('branch_id');
        $clientBranchId = (int) ($client['branch_id'] ?? 0);

        if ($sessionBranchId <= 0 || $clientBranchId !== $sessionBranchId) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
