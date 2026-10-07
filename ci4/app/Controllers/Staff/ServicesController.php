<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Models\PackageModel;
use App\Models\PackageVersionModel;
use App\Models\ServiceModel;
use App\Models\ServiceListModel;

class ServicesController extends BaseController
{
    protected ServiceModel $serviceModel;
    protected PackageModel $packageModel;
    protected PackageVersionModel $packageVersionModel;
    protected ServiceListModel $serviceListModel;

    public function __construct()
    {
        $this->serviceModel = new ServiceModel();
        $this->packageModel = new PackageModel();
        $this->packageVersionModel = new PackageVersionModel();
        $this->serviceListModel = new ServiceListModel();
    }

    public function index(): string
    {
        $this->ensureStaffAccess();

        $branchId = (int) session()->get('branch_id');
        $activeTab = (string) $this->request->getGet('tab');
        if (! in_array($activeTab, ['services', 'packages'], true)) {
            $activeTab = 'services';
        }

        $branchIssue = null;
        if ($branchId <= 0) {
            $services = [];
            $packages = [];
            $branchIssue = 'No branch is assigned to your staff account. Please contact the branch admin.';
        } else {
            $services = db_connect()->table('services s')
                ->select('s.service_id, s.plan_holder_id, s.service_list_id, s.package_id, s.service_date, s.status, sl.service_name, ph.unique_identifier, u.first_name, u.last_name, p.package_name')
                ->join('plan_holders ph', 'ph.plan_holder_id = s.plan_holder_id', 'inner')
                ->join('users u', 'u.user_id = ph.user_id', 'inner')
                ->join('packages p', 'p.package_id = s.package_id', 'left')
                ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
                ->where('s.branch_id', $branchId)
                ->orderBy('s.service_date', 'DESC')
                ->orderBy('s.service_id', 'DESC')
                ->get()
                ->getResultArray();

            $packages = $this->packageModel
                ->where('is_available', 1)
                ->orderBy('package_name', 'ASC')
                ->findAll();
        }

        $selectedServiceId = (int) $this->request->getGet('service_id');
        $selectedPackageId = (int) $this->request->getGet('package_id');

        $selectedService = null;
        if ($selectedServiceId > 0) {
            foreach ($services as $service) {
                if ((int) ($service['service_id'] ?? 0) === $selectedServiceId) {
                    $selectedService = $service;
                    break;
                }
            }
        }

        $selectedPackage = null;
        $selectedPackageServices = [];
        $selectedPackageVersions = [];
        if ($selectedPackageId > 0) {
            foreach ($packages as $package) {
                if ((int) ($package['package_id'] ?? 0) === $selectedPackageId) {
                    $selectedPackage = $package;
                    break;
                }
            }

            if ($selectedPackage !== null) {
                // package_services is self-contained (its own service_id/
                // service_name/description) - no service_list_id column to
                // join service_list on, despite what this used to assume.
                $selectedPackageServices = db_connect()->table('package_services')
                    ->select('service_id, service_name, description')
                    ->where('package_id', $selectedPackageId)
                    ->orderBy('service_name', 'ASC')
                    ->get()
                    ->getResultArray();

                $selectedPackageVersions = $this->packageVersionModel
                    ->where('package_id', $selectedPackageId)
                    ->orderBy('effective_date', 'DESC')
                    ->orderBy('version_id', 'DESC')
                    ->findAll();
            }
        }

        return view('staff/services/index', [
            'page_title' => 'Services',
            'active_tab' => $activeTab,
            'services' => $services,
            'packages' => $packages,
            'selected_service' => $selectedService,
            'selected_package' => $selectedPackage,
            'selected_package_services' => $selectedPackageServices,
            'selected_package_versions' => $selectedPackageVersions,
            'service_list' => $this->serviceListModel->where('is_available', 1)->orderBy('service_name', 'ASC')->findAll(),
            'branch_issue' => $branchIssue,
            'role_layout' => 'layouts/staff',
        ]);
    }

    public function ongoingServices(): string
    {
        $this->ensureStaffAccess();

        $branchId = (int) session()->get('branch_id');

        $branchIssue = null;
        if ($branchId <= 0) {
            $services = [];
            $branchIssue = 'No branch is assigned to your staff account. Please contact the branch admin.';
        } else {
            $services = db_connect()->table('services s')
                ->select('s.service_id, s.plan_holder_id, s.service_list_id, s.service_date, s.status, sl.service_name, ph.unique_identifier, u.first_name, u.last_name, p.package_name')
                ->join('plan_holders ph', 'ph.plan_holder_id = s.plan_holder_id', 'inner')
                ->join('users u', 'u.user_id = ph.user_id', 'inner')
                ->join('packages p', 'p.package_id = s.package_id', 'left')
                ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
                ->where('s.branch_id', $branchId)
                ->whereIn('s.status', ['pending', 'ongoing', 'completed'])
                ->orderBy('s.service_date', 'DESC')
                ->orderBy('s.service_id', 'DESC')
                ->get()
                ->getResultArray();
        }

        return view('staff/services/ongoing', [
            'page_title' => 'Ongoing Services',
            'services' => $services,
            'branch_issue' => $branchIssue,
            'role_layout' => 'layouts/staff',
        ]);
    }

    private function ensureStaffAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 3 && $roleName !== 'staff') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}
