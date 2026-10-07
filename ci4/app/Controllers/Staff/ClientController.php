<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Services\ClientService;
use App\Services\GovernmentIdVerificationService;
use App\Services\MembershipService;
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
        $branchIssue = null;
        $search = cs_search_term($this->request->getGet('search'));

        if ($branchId <= 0) {
            $clients = [];
            $branchIssue = 'No branch is assigned to your staff account. Please contact the branch admin.';
        } else {
            $clients = $this->clientService->getClientsByBranch($branchId, $search);
        }

        return view('staff/clients/index', [
            'page_title' => 'Client Management',
            'clients' => $clients,
            'search' => $search,
            'program' => MembershipService::getProgramInfo(),
            'branch_issue' => $branchIssue,
            'role_layout' => 'layouts/staff',
        ]);
    }

    public function edit(int $id): string
    {
        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->enforceBranchOwnership($client);

        return view('staff/clients/edit', [
            'page_title' => 'Edit plan holder',
            'page_sub' => 'Plan Holder #' . (string) ($client['plan_holder_id'] ?? $id),
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('staff/client-management')],
                ['label' => 'Edit plan holder'],
            ],
            'client' => $client,
            'beneficiaries' => $client['beneficiaries'] ?? [],
            'role_layout' => 'layouts/staff',
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

        return redirect()->to('/staff/client/view/' . $id)->with('success', 'Client information updated successfully.');
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

        return view('staff/clients/register', [
            'page_title' => 'Register plan holder',
            'page_sub' => 'Add a new plan holder to your branch.',
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('staff/client-management')],
                ['label' => 'Register plan holder'],
            ],
            'branch_name' => (string) ($branch['branch_name'] ?? ''),
            'program' => MembershipService::getProgramInfo(),
            'plans' => (new \App\Services\PlanBuilderService())->listApprovedActive(),
            'role_layout' => 'layouts/staff',
            'id_types' => (new GovernmentIdVerificationService())->idTypes(),
            'existing_users' => $existingUsers,
        ]);
    }

    /**
     * Thin now (Prompt 7, Step D): required/format validation only, then
     * PlanHolderRegistrationService::register() owns PSGC verification,
     * Government ID enforcement, beneficiaries, and plan_holder/plan/
     * account creation - the same routine Branch Admin, System Admin, and
     * client self-service now share.
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

            return redirect()->to('/staff/client/view/' . $planHolderId)->with('success', 'Plan holder registered successfully! They can sign in using the generated username - a password reset sends them their first login link.');
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Registration failed. Please try again.');
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
