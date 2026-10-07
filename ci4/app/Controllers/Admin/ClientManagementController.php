<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\ClientService;
use App\Services\GovernmentIdVerificationService;
use App\Services\MembershipService;
use App\Services\PlanHolderRegistrationService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ClientManagementController extends BaseController
{
    private ClientService $clientService;

    public function __construct()
    {
        $this->clientService = new ClientService();
    }

    public function index(): string
    {
        $this->ensureAdminAccess();

        $db = db_connect();
        $branchId = (int) $this->request->getGet('branch_id');
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $search = cs_search_term($this->request->getGet('search'));

        $branches = $db->table('branches')
            ->select('branch_id, branch_name')
            ->orderBy('branch_name', 'ASC')
            ->get()
            ->getResultArray();

        $latestPlanSubquery = '(SELECT pl1.* FROM plans pl1 JOIN (SELECT plan_holder_id, MAX(plan_id) AS max_plan_id FROM plans GROUP BY plan_holder_id) pl2 ON pl1.plan_id = pl2.max_plan_id)';

        $builder = $db->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.unique_identifier, ph.status AS plan_holder_status, u.first_name, u.last_name, u.email, u.contact_number, b.branch_name, p.status AS plan_status, p.monthly_fee, pkg.package_name', false)
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->join($latestPlanSubquery . ' p', 'p.plan_holder_id = ph.plan_holder_id', 'left', false)
            ->join('packages pkg', 'pkg.package_id = p.package_id', 'left')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC');

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $builder->where('ph.status', $status);
        }

        cs_apply_search($builder, $search, [
            'u.first_name', 'u.last_name', 'u.email', 'u.contact_number', 'ph.unique_identifier',
        ]);

        $clients = $builder->get()->getResultArray();

        return view('admin/client_management/index', [
            'role_layout' => 'layouts/admin',
            'clients' => $clients,
            'branches' => $branches,
            'filters' => [
                'branch_id' => $branchId,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Prompt 7, Step C: System Admin registration - the only mode that
     * picks a branch explicitly rather than using session('branch_id'),
     * since an Admin account isn't scoped to one branch.
     */
    public function create(): string
    {
        $this->ensureAdminAccess();

        $db = db_connect();

        $branches = $db->table('branches')
            ->select('branch_id, branch_name')
            ->where('status', 'active')
            ->orderBy('branch_name', 'ASC')
            ->get()
            ->getResultArray();

        $existingUsers = $db->table('users u')
            ->select('u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.contact_number')
            ->join('plan_holders ph', 'ph.user_id = u.user_id', 'left')
            ->where('u.role_id', 4)
            ->where('ph.plan_holder_id IS NULL', null, false)
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('admin/client_management/register', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Register plan holder',
            'page_sub' => 'Add a new plan holder to any branch.',
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('admin/client-management')],
                ['label' => 'Register plan holder'],
            ],
            'branches' => $branches,
            'existing_users' => $existingUsers,
            'program' => MembershipService::getProgramInfo(),
            'plans' => (new \App\Services\PlanBuilderService())->listApprovedActive(),
            'id_types' => (new GovernmentIdVerificationService())->idTypes(),
        ]);
    }

    public function store()
    {
        $this->ensureAdminAccess();

        $branchId = (int) $this->request->getPost('branch_id');
        $branchExists = $branchId > 0 && db_connect()->table('branches')->where('branch_id', $branchId)->where('status', 'active')->countAllResults() > 0;
        if (! $branchExists) {
            return redirect()->back()->withInput()->with('error', 'Please select a valid, active branch.');
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

            return redirect()->to('/admin/client-management/view/' . $planHolderId)->with('success', 'Plan holder registered successfully!');
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Registration failed. Please try again.');
        }
    }

    public function edit(int $id): string
    {
        $this->ensureAdminAccess();

        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('admin/client_management/edit', [
            'client' => $client,
            'beneficiaries' => $client['beneficiaries'] ?? [],
            'role_layout' => 'layouts/admin',
            'page_title' => 'Edit plan holder',
            'page_sub' => 'Plan Holder #' . (string) ($client['plan_holder_id'] ?? $id),
            'crumbs' => [
                ['label' => 'Plan holders', 'url' => base_url('admin/client-management')],
                ['label' => 'Edit plan holder'],
            ],
        ]);
    }

    public function update(int $id)
    {
        $this->ensureAdminAccess();

        $client = $this->clientService->getClientDetails($id);

        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

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
                // Age is never trusted from the client - always derived
                // server-side from date_of_birth (Task 3, cs_age_from_dob()).
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

        return redirect()->to('/admin/client-management/view/' . $id)->with('success', 'Client information updated successfully.');
    }

    private function ensureAdminAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 1 && $roleName !== 'admin') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}
