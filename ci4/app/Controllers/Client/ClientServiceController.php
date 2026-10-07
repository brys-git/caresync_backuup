<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\CycleService;
use App\Services\MembershipService;
use App\Services\NotificationService;

/**
 * ClientServiceController
 *
 * Handles service and package browsing, details, and applications
 * Part of the refactored ClientPortal controller
 *
 * Services & Packages redesign: the Regular Wood Casket package
 * (packages.is_damayan_entitlement = 1) is the standard Damayan Plan
 * Holder entitlement - it shows CLAIM (free, once the ₱14,500 contribution
 * target is fully paid) instead of AVAILABLE. Everything else in this
 * controller (browsing, details, apply forms, submission, document
 * uploads) is the same architecture used before the redesign, just
 * extended with the entitlement/benefit-preview data the new views need.
 */
class ClientServiceController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Flat ₱1,500 optional add-on price used across every package's apply
     * flow, per the brief's own worked example (₱20,000 + ₱1,500 =
     * ₱21,500).
     */
    private const BURIAL_ATTIRE_PRICE = 1500.0;

    private const MIN_MONTHS_PAID = 2;

    /**
     * Display services and packages catalog
     */
    public function services(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $activeTab = (string) $this->request->getGet('tab');
        if (! in_array($activeTab, ['services', 'packages'], true)) {
            $activeTab = 'packages';
        }

        $services = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price, image_path')
            ->where('is_available', 1)
            ->orderBy('service_name', 'ASC')
            ->get()
            ->getResultArray();

        $routesByService = $this->routesGroupedByService(array_column($services, 'service_list_id'));
        foreach ($services as &$svc) {
            $svc['routes'] = $routesByService[(int) $svc['service_list_id']] ?? [];
            $svc['image_url'] = $this->existingImageUrl($svc['image_path'] ?? null);
        }
        unset($svc);

        $packages = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price, is_damayan_entitlement, image_path')
            ->where('is_available', 1)
            ->orderBy('base_price', 'ASC')
            ->get()
            ->getResultArray();

        $inclusionNames = [];
        if ($packages !== []) {
            $itemRows = db_connect()->table('package_items')
                ->select('package_id, item_name')
                ->whereIn('package_id', array_column($packages, 'package_id'))
                ->orderBy('item_id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($itemRows as $row) {
                $inclusionNames[(int) $row['package_id']][] = (string) $row['item_name'];
            }
        }

        [$canApply, $membership, $planId] = $this->resolveEligibility($access);
        $planHolderId = (int) ($access['plan_holder']['plan_holder_id'] ?? 0);

        $entitlement = null;
        $gridPackages = [];
        foreach ($packages as $pkg) {
            $pkg['image_url'] = $this->existingImageUrl($pkg['image_path'] ?? null);
            $pkg['inclusion_names'] = $inclusionNames[(int) $pkg['package_id']] ?? [];
            if ((int) ($pkg['is_damayan_entitlement'] ?? 0) === 1) {
                $entitlement ??= $pkg;
                continue;
            }
            $gridPackages[] = $pkg;
        }

        $openClaim = $entitlement && $planHolderId > 0 ? $this->openClaim($planHolderId, $planId) : null;
        $claimStatus = $entitlement ? $this->entitlementClaimStatus($canApply, $membership, $planId) : ['eligible' => false, 'reason' => null];

        return view('client/services', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Request a service',
            'show_page_head' => false,
            'access' => $access,
            'active_tab' => $activeTab,
            'services' => $services,
            'packages' => $gridPackages,
            'entitlement' => $entitlement,
            'entitlement_claim' => $openClaim,
            'claim_eligible' => (bool) $claimStatus['eligible'],
            'claim_reason' => $claimStatus['reason'],
            'eligibility' => $this->catalogEligibility($canApply, $membership),
            'can_apply' => $canApply,
            'membership' => $membership ?? [],
        ]);
    }

    /**
     * Plain-language eligibility for the Request a service header pill, and
     * the reason every Avail/Apply/Claim button is disabled when it's false.
     *
     * @return array{eligible: bool, label: string, detail: string, reason: ?string, months_paid: int, months_required: int}
     */
    private function catalogEligibility(bool $canApply, ?array $membership): array
    {
        $monthsPaid = (int) ($membership['months_paid'] ?? 0);
        $state = strtolower((string) ($membership['membership_state'] ?? 'inactive'));
        $required = self::MIN_MONTHS_PAID;

        if ($canApply) {
            return [
                'eligible' => true,
                'label' => 'You can apply',
                'detail' => ucfirst($state) . ' · ' . $monthsPaid . ' ' . ($monthsPaid === 1 ? 'month' : 'months') . ' paid (' . $required . ' required)',
                'reason' => null,
                'months_paid' => $monthsPaid,
                'months_required' => $required,
            ];
        }

        if ($membership === null || $membership === []) {
            $reason = 'You need an active plan before you can request services.';
        } elseif (! empty($membership['service_block_reason'])) {
            // The plan's own status says why (on hold / forfeited) - shown instead of hiding the button.
            $reason = (string) $membership['service_block_reason'];
        } elseif ($state !== 'active') {
            $reason = 'Membership ' . $state . '. Contact your branch.';
        } elseif ($monthsPaid < $required) {
            $more = $required - $monthsPaid;
            $reason = 'Not yet eligible · ' . $more . ' more ' . ($more === 1 ? 'month' : 'months') . ' needed';
        } else {
            $reason = 'Your membership needs attention. Contact your branch.';
        }

        return [
            'eligible' => false,
            'label' => $state !== 'active' && $membership !== null && $membership !== [] ? 'Membership ' . $state : 'Not yet eligible',
            'detail' => $reason,
            'reason' => $reason,
            'months_paid' => $monthsPaid,
            'months_required' => $required,
        ];
    }

    /**
     * URL for a catalog image only when the file is actually on disk, so a
     * missing photo renders a placeholder instead of a broken <img> and its
     * alt text.
     */
    private function existingImageUrl(?string $path): ?string
    {
        $path = ltrim(trim((string) $path), '/');
        if ($path === '' || ! is_file(FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            return null;
        }

        return base_url($path);
    }

    /**
     * Display service details (e.g. Balik Probinsya, with its per-route
     * pricing).
     */
    public function serviceDetails(int $serviceListId): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price, image_path')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->to('/client/service?tab=services')->with('error', 'Service not found.');
        }

        $routes = db_connect()->table('service_routes')
            ->where('service_list_id', $serviceListId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('price', 'DESC')
            ->get()
            ->getResultArray();

        [$canApply, $membership] = $this->resolveEligibility($access);

        // Casket benefit note: a qualified Plan Holder's Damayan casket
        // entitlement is covered separately (the Regular Wood Casket
        // claim) - it never reduces this transport service's own route
        // price, it's simply disclosed here so the plan holder knows they
        // won't be asked to pay for a casket again on top of this.
        foreach ($routes as &$route) {
            $route['casket_benefit_covered'] = $canApply;
        }
        unset($route);

        return view('client/service_details', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => (string) ($service['service_name'] ?? 'Service'),
            'page_sub' => 'Service details and pricing.',
            'access' => $access,
            'service' => $service,
            'routes' => $routes,
            'can_apply' => $canApply,
        ]);
    }

    /**
     * Display package details with inclusions and, where relevant, the
     * Damayan entitlement/benefit information.
     */
    public function packageDetails(int $packageId): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $package = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price, is_customizable, is_damayan_entitlement, image_path')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->to('/client/service?tab=packages')->with('error', 'Package not found.');
        }

        $inclusions = db_connect()->table('package_items')
            ->select('item_id, item_name, description')
            ->where('package_id', $packageId)
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        [$canApply, $membership, $planId] = $this->resolveEligibility($access);
        $isEntitlement = (int) ($package['is_damayan_entitlement'] ?? 0) === 1;

        $entitlement = null;
        $benefitCredit = 0.0;
        if ($isEntitlement) {
            $claimStatus = $this->entitlementClaimStatus($canApply, $membership, $planId);
            $entitlement = [
                'eligible' => $claimStatus['eligible'],
                'locked_reason' => $claimStatus['reason'],
            ];
        } elseif ($canApply) {
            // Panel damayan credit: exactly TOTAL_CONTRIBUTION, capped so it
            // can never exceed the package price itself.
            $benefitCredit = min((float) $package['base_price'], MembershipService::TOTAL_CONTRIBUTION);
        }

        return view('client/package_details', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => (string) ($package['package_name'] ?? 'Package'),
            'page_sub' => 'Package details, inclusions, and pricing.',
            'access' => $access,
            'package' => $package,
            'inclusions' => $inclusions,
            'is_entitlement' => $isEntitlement,
            'entitlement' => $entitlement,
            'benefit_credit' => $benefitCredit,
            'can_apply' => $canApply,
            'attire_price' => self::BURIAL_ATTIRE_PRICE,
        ]);
    }

    /**
     * Display service application form
     */
    public function applyServiceForm(int $serviceListId): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price, image_path')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->to('/client/service?tab=services')->with('error', 'Service not found.');
        }

        $routes = db_connect()->table('service_routes')
            ->where('service_list_id', $serviceListId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        [$canApply, $membership] = $this->resolveEligibility($access);

        return view('client/service_apply', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Apply for Service',
            'page_sub' => 'Confirm your service request.',
            'page_actions' => '<a class="btn btn-ghost" href="' . esc(site_url('/client/service/' . (int) ($service['service_list_id'] ?? 0)), 'attr') . '">Back</a>',
            'access' => $access,
            'service' => $service,
            'routes' => $routes,
            'selected_route_id' => (int) $this->request->getGet('route'),
            'can_apply' => $canApply,
        ]);
    }

    /**
     * Display package application form
     */
    public function applyPackageForm(int $packageId): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $package = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price, is_customizable, is_damayan_entitlement, image_path')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->to('/client/service?tab=packages')->with('error', 'Package not found.');
        }

        [$canApply, $membership, $planId] = $this->resolveEligibility($access);
        $isEntitlement = (int) ($package['is_damayan_entitlement'] ?? 0) === 1;

        $eligibility = ['eligible' => $canApply, 'reason' => null];
        if ($isEntitlement) {
            $eligibility = $this->entitlementClaimStatus($canApply, $membership, $planId);
        } elseif (! $canApply) {
            $eligibility = ['eligible' => false, 'reason' => 'You need an active plan with at least 2 paid months to avail packages.'];
        }

        $planHolder = $access['plan_holder'] ?? [];
        $planHolderId = (int) ($planHolder['plan_holder_id'] ?? 0);
        $activePlan = $planHolderId > 0 ? $this->activePlan($planHolderId) : null;
        $user = $access['user'] ?? [];
        $firstBeneficiary = $planHolderId > 0
            ? db_connect()->table('beneficiaries')
                ->select('first_name, middle_name, last_name, name_extension')
                ->where('plan_holder_id', $planHolderId)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('beneficiary_id', 'ASC')
                ->get(1)
                ->getRowArray()
            : null;
        $branch = ! empty($planHolder['branch_id'])
            ? db_connect()->table('branches')
                ->select('branch_name, contact_number')
                ->where('branch_id', (int) $planHolder['branch_id'])
                ->get()
                ->getRowArray()
            : null;

        $benefitCredit = (! $isEntitlement && $canApply)
            ? min((float) $package['base_price'], MembershipService::TOTAL_CONTRIBUTION)
            : 0.0;

        return view('client/package_apply', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => $isEntitlement ? 'Claim Regular Casket' : 'Avail Package',
            'show_page_head' => false,
            'access' => $access,
            'package' => $package,
            'is_entitlement' => $isEntitlement,
            'benefit_credit' => $benefitCredit,
            'can_apply' => $canApply,
            'attire_price' => self::BURIAL_ATTIRE_PRICE,
            'eligibility' => $eligibility,
            'existing_claim' => $isEntitlement && $planHolderId > 0 ? $this->openClaim($planHolderId, $planId) : null,
            'plan_name' => (new MembershipService())->resolvePackageName((int) ($activePlan['package_id'] ?? 0)),
            'member_name' => trim(implode(' ', array_filter([$user['first_name'] ?? '', $user['middle_name'] ?? '', $user['last_name'] ?? '']))),
            'member_address' => trim(implode(', ', array_filter([$planHolder['address_no'] ?? '', $planHolder['address_street'] ?? '', $planHolder['address_barangay'] ?? '', $planHolder['address_city'] ?? '']))),
            'beneficiary_prefill' => $firstBeneficiary
                ? trim(implode(' ', array_filter([$firstBeneficiary['first_name'], $firstBeneficiary['middle_name'], $firstBeneficiary['last_name'], $firstBeneficiary['name_extension']])))
                : '',
            'branch_name' => (string) ($branch['branch_name'] ?? ''),
            'branch_phone' => (string) ($branch['contact_number'] ?? ''),
            'form_errors' => session()->getFlashdata('form_errors') ?? [],
        ]);
    }

    /**
     * The member's claim that's still open: one awaiting review, or one already
     * approved for the current cycle. Shown instead of the form so a family
     * can't submit the same casket twice.
     */
    private function openClaim(int $planHolderId, int $planId): ?array
    {
        $cycleNumber = $planId > 0 ? (new CycleService())->currentCycle($planId)['cycle_number'] : 0;

        return db_connect()->table('service_applications sa')
            ->select('sa.application_id, sa.status, sa.created_at, sa.awaiting_death_certificate, p.package_name')
            ->join('packages p', 'p.package_id = sa.package_id', 'inner')
            ->where('sa.plan_holder_id', $planHolderId)
            ->where('p.is_damayan_entitlement', 1)
            ->groupStart()
                ->where('sa.status', 'pending')
                ->orGroupStart()
                    ->where('sa.status', 'approved')
                    ->where('sa.cycle_number', $cycleNumber)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('sa.application_id', 'DESC')
            ->get(1)
            ->getRowArray() ?: null;
    }

    /**
     * Confirmation screen after a claim or package request is submitted.
     */
    public function packageSubmitted(int $applicationId): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $planHolderId = (int) ($access['plan_holder']['plan_holder_id'] ?? 0);
        $application = $planHolderId > 0
            ? db_connect()->table('service_applications sa')
                ->select('sa.application_id, sa.status, sa.created_at, sa.application_amount, sa.awaiting_death_certificate, p.package_name, p.is_damayan_entitlement')
                ->join('packages p', 'p.package_id = sa.package_id', 'left')
                ->where('sa.application_id', $applicationId)
                ->where('sa.plan_holder_id', $planHolderId)
                ->get()
                ->getRowArray()
            : null;

        if (! $application) {
            return redirect()->to('/client/service?tab=packages')->with('error', 'Claim not found.');
        }

        $isEntitlement = (int) ($application['is_damayan_entitlement'] ?? 0) === 1;

        return view('client/package_submitted', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => $isEntitlement ? 'Claim Regular Casket' : 'Avail Package',
            'show_page_head' => false,
            'access' => $access,
            'application' => $application,
            'is_entitlement' => $isEntitlement,
        ]);
    }

    /**
     * Submit service application
     */
    public function submitServiceApplication(int $serviceListId)
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        if (($access['state'] ?? 'new') === 'new') {
            return redirect()->to('/plan-info')->with('error', 'You must register as a Plan Holder to apply.');
        }

        $state = (string) ($access['state'] ?? 'new');
        if (! in_array($state, ['approved', 'active'], true)) {
            return redirect()->back()->with('error', 'Access denied. Approval required before requesting services.');
        }

        $planHolderId = (int) ($access['plan_holder']['plan_holder_id'] ?? 0);
        $activePlan = $planHolderId > 0 ? $this->activePlan($planHolderId) : null;
        $monthsPaid = (int) ($activePlan['months_paid'] ?? 0);
        if ($monthsPaid < 2) {
            return redirect()->back()->with('error', 'You must complete at least 2 months of payments before requesting services.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found. Please complete registration first.');
        }

        // Check service eligibility based on membership state
        $membershipService = new MembershipService();
        $serviceAccess = $membershipService->serviceAccess((int) $planHolder['plan_holder_id']);
        if (! $serviceAccess['allowed']) {
            return redirect()->back()->with('error', $serviceAccess['reason'] ?: 'Your plan does not allow service requests at this time.');
        }

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name, base_price')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->back()->with('error', 'Selected service is unavailable.');
        }

        // Resolve the selected route (if any) - the route price is what's
        // actually owed, the casket benefit note never reduces it.
        $routeId = (int) $this->request->getPost('route_id');
        $applicationAmount = (float) $service['base_price'];
        if ($routeId > 0) {
            $route = db_connect()->table('service_routes')
                ->where('route_id', $routeId)
                ->where('service_list_id', $serviceListId)
                ->get()
                ->getRowArray();

            if (! $route) {
                return redirect()->back()->withInput()->with('error', 'Selected route is invalid.');
            }

            $applicationAmount = (float) $route['price'];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $insert = [
                'plan_holder_id' => (int) $planHolder['plan_holder_id'],
                'service_list_id' => (int) $serviceListId,
                'status' => 'pending',
                'deceased_name' => trim((string) $this->request->getPost('deceased_name')) ?: null,
                'deceased_date_of_death' => $this->nullablePost('deceased_date_of_death'),
                'deceased_address' => trim((string) $this->request->getPost('deceased_address')) ?: null,
                'relationship_to_deceased' => trim((string) $this->request->getPost('relationship_to_deceased')) ?: null,
                'beneficiary_name' => trim((string) $this->request->getPost('beneficiary_name')) ?: null,
                'beneficiary_contact' => trim((string) $this->request->getPost('beneficiary_contact')) ?: null,
                'application_notes' => trim((string) $this->request->getPost('application_notes')) ?: null,
                'selected_route_id' => $routeId > 0 ? $routeId : null,
                'application_amount' => $applicationAmount,
            ];

            $db->table('service_applications')->insert($insert);
            $applicationId = (int) $db->insertID();

            // Handle uploaded documents
            $files = $this->request->getFiles('documents');
            if (is_array($files) && count($files) > 0) {
                $targetDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'service_applications' . DIRECTORY_SEPARATOR . $applicationId;
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $docModel = new \App\Models\ServiceApplicationDocumentModel();
                foreach ($files as $file) {
                    if (! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $newName = $file->getRandomName();
                    $moved = $file->move($targetDir, $newName);
                    if ($moved) {
                        $docModel->insert([
                            'application_id' => $applicationId,
                            'filename' => $newName,
                            'original_name' => $file->getClientName(),
                            'mime_type' => $file->getClientMimeType(),
                            'path' => str_replace('\\', '/', str_replace(WRITEPATH, '', $targetDir . DIRECTORY_SEPARATOR . $newName)),
                            'uploaded_by' => (int) $user['user_id'],
                        ]);
                    }
                }
            }

            (new NotificationService())->notify(
                (int) $user['user_id'],
                'Your application for ' . (string) $service['service_name'] . ' has been submitted.',
                'registration_pending'
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to submit application.');
            }

            $db->transCommit();

            return redirect()->to('/client/service?tab=services')->with('success', 'Service application submitted successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Submit package application (also the CLAIM path, when $packageId is
     * the Damayan entitlement package).
     */
    public function submitPackageApplication(int $packageId)
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        if (($access['state'] ?? 'new') === 'new') {
            return redirect()->to('/plan-info')->with('error', 'You must register as a Plan Holder to apply.');
        }

        $state = (string) ($access['state'] ?? 'new');
        if (! in_array($state, ['approved', 'active'], true)) {
            return redirect()->back()->with('error', 'Access denied. Approval required before requesting services.');
        }

        $planHolderId = (int) ($access['plan_holder']['plan_holder_id'] ?? 0);
        $activePlan = $planHolderId > 0 ? $this->activePlan($planHolderId) : null;
        $monthsPaid = (int) ($activePlan['months_paid'] ?? 0);
        if ($monthsPaid < 2) {
            return redirect()->back()->with('error', 'You must complete at least 2 months of payments before requesting services.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found. Please complete registration first.');
        }

        // Check service eligibility based on membership state
        $membershipService = new MembershipService();
        $serviceAccess = $membershipService->serviceAccess((int) $planHolder['plan_holder_id']);
        if (! $serviceAccess['allowed']) {
            return redirect()->back()->with('error', $serviceAccess['reason'] ?: 'Your plan does not allow service requests at this time.');
        }

        $package = db_connect()->table('packages')
            ->select('package_id, package_name, base_price, is_damayan_entitlement')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->back()->with('error', 'Selected package is unavailable.');
        }

        $isEntitlement = (int) ($package['is_damayan_entitlement'] ?? 0) === 1;

        // Re-verify server-side, never trust the CLAIM button's visibility
        // client-side alone - reads from the exact same shared method the
        // frontend disabled state uses (entitlementClaimStatus()), so the
        // two can never disagree.
        [$canApply, $membership, $eligPlanId] = $this->resolveEligibility($access);
        if ($isEntitlement) {
            $claimStatus = $this->entitlementClaimStatus($canApply, $membership, $eligPlanId);
            if (! $claimStatus['eligible']) {
                return redirect()->back()->with('error', (string) $claimStatus['reason']);
            }
        }

        if ($isEntitlement && $this->openClaim((int) $planHolder['plan_holder_id'], $eligPlanId) !== null) {
            return redirect()->to('/client/apply-package/' . $packageId)->with('error', 'You already have a claim in progress for this entitlement.');
        }

        $input = [
            'deceased_name' => trim((string) $this->request->getPost('deceased_name')),
            'deceased_date_of_death' => trim((string) $this->request->getPost('deceased_date_of_death')),
            'deceased_address' => trim((string) $this->request->getPost('deceased_address')),
            'relationship_to_deceased' => trim((string) $this->request->getPost('relationship_to_deceased')),
            'beneficiary_name' => trim((string) $this->request->getPost('beneficiary_name')),
            'beneficiary_contact' => trim((string) $this->request->getPost('beneficiary_contact')),
        ];

        $errors = [];
        if ($input['deceased_name'] === '') {
            $errors['deceased_name'] = 'Enter the full name of the deceased.';
        } elseif (mb_strlen($input['deceased_name']) > 150) {
            $errors['deceased_name'] = 'Keep the name under 150 characters.';
        }
        if ($input['deceased_date_of_death'] !== '') {
            $dateOfDeath = strtotime($input['deceased_date_of_death']);
            if ($dateOfDeath === false) {
                $errors['deceased_date_of_death'] = 'Enter a valid date.';
            } elseif (date('Y-m-d', $dateOfDeath) > date('Y-m-d')) {
                $errors['deceased_date_of_death'] = 'The date of death can’t be in the future.';
            }
        }
        if (mb_strlen($input['deceased_address']) > 255) {
            $errors['deceased_address'] = 'Keep the address under 255 characters.';
        }
        if ($input['beneficiary_name'] === '') {
            $errors['beneficiary_name'] = 'Enter the name of the contact person.';
        } elseif (mb_strlen($input['beneficiary_name']) > 150) {
            $errors['beneficiary_name'] = 'Keep the name under 150 characters.';
        }
        if (! preg_match('/^09\d{9}$/', (string) preg_replace('/[\s-]/', '', $input['beneficiary_contact']))) {
            $errors['beneficiary_contact'] = 'Enter an 11-digit mobile number that starts with 09.';
        } else {
            $input['beneficiary_contact'] = preg_replace('/[\s-]/', '', $input['beneficiary_contact']);
        }

        $uploads = $this->request->getFiles('documents') ?? [];
        $deathCertificate = $this->acceptedUpload($uploads['death_certificate'] ?? null);
        $beneficiaryId = $this->acceptedUpload($uploads['beneficiary_id'] ?? null);
        $otherDocuments = [];
        foreach (is_array($uploads['other'] ?? null) ? $uploads['other'] : [] as $file) {
            if (! $this->hasUpload($file)) {
                continue;
            }
            $accepted = $this->acceptedUpload($file);
            if ($accepted === null) {
                $errors['documents_other'] = 'Other documents must be images or PDFs up to 5 MB each.';
                break;
            }
            $otherDocuments[] = $accepted;
        }

        if ($beneficiaryId === null) {
            $errors['documents_beneficiary_id'] = $this->hasUpload($uploads['beneficiary_id'] ?? null)
                ? 'The ID must be an image or PDF up to 5 MB.'
                : 'Upload a valid ID of the beneficiary.';
        }
        if ($this->hasUpload($uploads['death_certificate'] ?? null) && $deathCertificate === null) {
            $errors['documents_death_certificate'] = 'The death certificate must be an image or PDF up to 5 MB.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('form_errors', $errors);
        }

        $burialAttireSelected = $this->request->getPost('burial_attire') ? true : false;
        $burialAttirePrice = $burialAttireSelected ? self::BURIAL_ATTIRE_PRICE : null;

        $basePrice = (float) $package['base_price'];
        $damayanBenefitApplied = null;
        if ($isEntitlement) {
            // Fully covered - the whole casket price is the benefit itself.
            $damayanBenefitApplied = $basePrice;
            $applicationAmount = $burialAttireSelected ? self::BURIAL_ATTIRE_PRICE : 0.0;
        } else {
            $damayanBenefitApplied = 0.0;
            if (! empty($membership) && (! empty($membership['can_access_services']))) {
                $damayanBenefitApplied = min($basePrice, MembershipService::TOTAL_CONTRIBUTION);
            }
            $applicationAmount = $basePrice - $damayanBenefitApplied + ($burialAttireSelected ? self::BURIAL_ATTIRE_PRICE : 0.0);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $insert = [
                'plan_holder_id' => (int) $planHolder['plan_holder_id'],
                'package_id' => (int) $packageId,
                'status' => 'pending',
                'deceased_name' => $input['deceased_name'],
                'deceased_date_of_death' => $input['deceased_date_of_death'] !== '' ? $input['deceased_date_of_death'] : null,
                'deceased_address' => $input['deceased_address'] ?: null,
                'relationship_to_deceased' => $input['relationship_to_deceased'] ?: null,
                'beneficiary_name' => $input['beneficiary_name'],
                'beneficiary_contact' => $input['beneficiary_contact'],
                'application_notes' => trim((string) $this->request->getPost('application_notes')) ?: null,
                'burial_attire_selected' => $burialAttireSelected ? 1 : 0,
                'burial_attire_price' => $burialAttirePrice,
                'damayan_benefit_applied' => $damayanBenefitApplied,
                'application_amount' => $applicationAmount,
                'awaiting_death_certificate' => $deathCertificate === null ? 1 : 0,
            ];

            $db->table('service_applications')->insert($insert);
            $applicationId = (int) $db->insertID();

            $targetDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'service_applications' . DIRECTORY_SEPARATOR . $applicationId;
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $documentsToStore = [];
            if ($deathCertificate !== null) {
                $documentsToStore[] = ['death_certificate', $deathCertificate];
            }
            $documentsToStore[] = ['beneficiary_id', $beneficiaryId];
            foreach ($otherDocuments as $file) {
                $documentsToStore[] = ['other', $file];
            }

            $docModel = new \App\Models\ServiceApplicationDocumentModel();
            foreach ($documentsToStore as [$documentType, $file]) {
                $newName = $file->getRandomName();
                if ($file->move($targetDir, $newName)) {
                    $docModel->insert([
                        'application_id' => $applicationId,
                        'document_type' => $documentType,
                        'filename' => $newName,
                        'original_name' => $file->getClientName(),
                        'mime_type' => $file->getClientMimeType(),
                        'path' => str_replace('\\', '/', str_replace(WRITEPATH, '', $targetDir . DIRECTORY_SEPARATOR . $newName)),
                        'uploaded_by' => (int) $user['user_id'],
                    ]);
                }
            }

            (new NotificationService())->notify(
                (int) $user['user_id'],
                ($isEntitlement ? 'Your claim for ' : 'Your application for ') . (string) $package['package_name'] . ' has been submitted.',
                'registration_pending'
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to submit application.');
            }

            $db->transCommit();

            return redirect()->to('/client/apply-package/submitted/' . $applicationId);
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function hasUpload(mixed $file): bool
    {
        return $file instanceof \CodeIgniter\HTTP\Files\UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    private function acceptedUpload(mixed $file): ?\CodeIgniter\HTTP\Files\UploadedFile
    {
        if (! $file instanceof \CodeIgniter\HTTP\Files\UploadedFile || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        if ($file->getSize() > 5 * 1024 * 1024 || ! in_array($file->getMimeType(), $allowed, true)) {
            return null;
        }

        return $file;
    }

    /**
     * Shared can_apply + membership summary resolution (same rule used
     * throughout this controller: active/good-standing plan with at least
     * 2 months paid). Also returns the plan_id, since entitlementClaimStatus()
     * below needs it and every caller already has $access in scope rather
     * than re-deriving it themselves.
     *
     * @return array{0: bool, 1: array|null, 2: int}
     */
    private function resolveEligibility(array $access): array
    {
        $planHolderId = (int) ($access['plan_holder']['plan_holder_id'] ?? 0);
        $activePlan = $planHolderId > 0 ? $this->activePlan($planHolderId) : null;
        $monthsPaid = (int) ($activePlan['months_paid'] ?? 0);
        $planId = (int) ($activePlan['plan_id'] ?? 0);

        $membership = $planHolderId > 0 ? (new MembershipService())->getMembershipSummary($planHolderId) : null;

        if (is_array($membership) && $membership !== []) {
            $monthsPaid = (int) ($membership['months_paid'] ?? $monthsPaid);
            $canApply = (! empty($membership['can_access_services'])) && $monthsPaid >= 2;
        } else {
            $canApply = (($access['state'] ?? 'unregistered') === 'active') && $monthsPaid >= 2;
        }

        return [$canApply, $membership, $planId];
    }

    /**
     * The ONE shared CLAIM-eligibility check. services(), packageDetails()
     * and applyPackageForm() (frontend display / form gate) and
     * submitPackageApplication() (authoritative backend rejection) all call
     * this, so they can never disagree.
     *
     * Replaces the old ($canApply && hasFullyPaidContribution()) gate,
     * which wrongly required the ENTIRE ₱14,500 cycle paid off just to
     * claim the entitled package. Correct rule: 2+ verified months in the
     * current cycle - same threshold as availing anything else - and not
     * already claimed in this cycle. "Claimed but still paying it off" is
     * CycleService's 'claimed_owing' state; this method doesn't need to
     * name it, it just asks currentCycle() whether this cycle's
     * entitlement has been claimed yet.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    private function entitlementClaimStatus(bool $canApply, ?array $membership, int $planId): array
    {
        if (! $canApply) {
            // canApply's own formula is (can_access_services && monthsPaid
            // >= 2), so if it's false but access is fine, months < 2 must
            // be why - gives the specific message the spec asks for
            // instead of the generic membership-guard fallback.
            $monthsPaid = (int) ($membership['months_paid'] ?? 0);
            $canAccessServices = is_array($membership) && ! empty($membership['can_access_services']);

            if ($canAccessServices && $monthsPaid < 2) {
                return [
                    'eligible' => false,
                    'reason' => "You need at least 2 paid months to claim your entitled package or avail services. You have paid {$monthsPaid} month(s).",
                ];
            }

            if (is_array($membership) && ! empty($membership['service_block_reason'])) {
                return ['eligible' => false, 'reason' => (string) $membership['service_block_reason']];
            }

            return ['eligible' => false, 'reason' => 'Become an active, eligible Plan Holder to claim this entitlement.'];
        }

        if ($planId <= 0) {
            return ['eligible' => false, 'reason' => 'Become an active, eligible Plan Holder to claim this entitlement.'];
        }

        $cycle = (new CycleService())->currentCycle($planId);
        if ($cycle['entitlement_claimed']) {
            $remaining = number_format((float) $cycle['remaining'], 2);

            return [
                'eligible' => false,
                'reason' => "You've already claimed your entitled package for this cycle. You have ₱{$remaining} remaining on your current ₱14,500 contribution. Once it's fully paid, a new cycle begins and you can claim again after 2 months.",
            ];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * @param array<int, int> $serviceListIds
     * @return array<int, array>
     */
    private function routesGroupedByService(array $serviceListIds): array
    {
        $serviceListIds = array_values(array_unique(array_map('intval', $serviceListIds)));
        if ($serviceListIds === []) {
            return [];
        }

        $rows = db_connect()->table('service_routes')
            ->whereIn('service_list_id', $serviceListIds)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('price', 'DESC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['service_list_id']][] = $row;
        }

        return $grouped;
    }
}
