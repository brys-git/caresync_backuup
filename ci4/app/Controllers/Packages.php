<?php

namespace App\Controllers;

use App\Models\PackageItemModel;
use App\Models\PackageModel;
use App\Models\PackageVersionModel;
use App\Models\PlanModel;

class Packages extends BaseController
{
    public function index()
    {
        $this->ensureAccess();

        $db = db_connect();
        $roleId = (int) session('role_id');
        $branchId = (int) session('branch_id');

        // Admin has its own separate package/item/version CRUD tool
        // (admin/service-offer) - but not an equivalent for assigning a
        // package to a plan holder's plan, which only exists here, so
        // Admin isn't redirected away entirely (as this used to do)
        // before this session's Create Plan work made that the one
        // capability Admin actually needs from this page.

        $packageModel = new PackageModel();
        $packages = $packageModel->orderBy('package_name', 'ASC')->findAll();

        $packageItems = $db->table('package_items pi')
            ->select('pi.item_id, pi.package_id, pi.item_name, pi.description, p.package_name')
            ->join('packages p', 'p.package_id = pi.package_id', 'inner')
            ->orderBy('p.package_name', 'ASC')
            ->orderBy('pi.item_name', 'ASC')
            ->get()
            ->getResultArray();

        $packageVersions = $db->table('package_versions pv')
            ->select('pv.version_id, pv.package_id, pv.price, pv.effective_date, pv.status, p.package_name')
            ->join('packages p', 'p.package_id = pv.package_id', 'inner')
            ->orderBy('p.package_name', 'ASC')
            ->orderBy('pv.effective_date', 'DESC')
            ->get()
            ->getResultArray();

        // Panel brief, section 1: "offered several plan options based on
        // their current plan" - each plan holder's current package/price
        // is included so staff can see what they'd be upgrading from.
        $planHolderBuilder = $db->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.branch_id, ph.unique_identifier, u.first_name, u.last_name, p.package_name AS current_package_name, pl.monthly_fee AS current_monthly_fee')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('plans pl', 'pl.plan_holder_id = ph.plan_holder_id AND pl.status = "active"', 'left')
            ->join('packages p', 'p.package_id = pl.package_id', 'left')
            ->where('ph.status', 'active')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC');

        // Staff was previously unscoped here (only Branch Admin's branch
        // filter was applied) - Staff should likewise only see and assign
        // plans for their own branch's plan holders.
        if (in_array($roleId, [2, 3], true) && $branchId > 0) {
            $planHolderBuilder->where('ph.branch_id', $branchId);
        }

        $planHolders = $planHolderBuilder->get()->getResultArray();

        return view('packages/index', [
            'packages' => $packages,
            'package_items' => $packageItems,
            'package_versions' => $packageVersions,
            'plan_holders' => $planHolders,
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Services & packages',
            'page_sub' => 'Create packages, price versions, and assign a plan to a plan holder.',
        ]);
    }

    public function storePackage()
    {
        $this->ensureAccess();

        $rules = [
            'package_name' => 'required|max_length[100]',
            'base_price' => 'required|decimal',
            'is_customizable' => 'required|in_list[0,1]',
            'initial_effective_date' => 'required|valid_date',
            'initial_version_status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $packageModel = new PackageModel();
            $versionModel = new PackageVersionModel();

            $packageId = (int) $packageModel->insert([
                'package_name' => trim((string) $this->request->getPost('package_name')),
                'description' => trim((string) $this->request->getPost('description')),
                'base_price' => (string) $this->request->getPost('base_price'),
                'is_customizable' => (int) $this->request->getPost('is_customizable'),
            ], true);

            if ($packageId <= 0) {
                throw new \RuntimeException('Failed to create package.');
            }

            $savedVersion = $versionModel->insert([
                'package_id' => $packageId,
                'price' => (string) $this->request->getPost('base_price'),
                'effective_date' => (string) $this->request->getPost('initial_effective_date'),
                'status' => (string) $this->request->getPost('initial_version_status'),
            ]);

            if (! $savedVersion) {
                throw new \RuntimeException('Failed to create initial price version.');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed.');
            }

            $db->transCommit();

            return redirect()->to('/packages')->with('success', 'Package created with initial price version.');
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeItem()
    {
        $this->ensureAccess();

        $rules = [
            'package_id' => 'required|is_natural_no_zero',
            'item_name' => 'required|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $itemModel = new PackageItemModel();
        $saved = $itemModel->insert([
            'package_id' => (int) $this->request->getPost('package_id'),
            'item_name' => trim((string) $this->request->getPost('item_name')),
            'description' => trim((string) $this->request->getPost('description')),
        ]);

        if (! $saved) {
            return redirect()->back()->withInput()->with('error', 'Failed to add package item.');
        }

        return redirect()->to('/packages')->with('success', 'Package item added.');
    }

    public function storeVersion()
    {
        $this->ensureAccess();

        $rules = [
            'package_id' => 'required|is_natural_no_zero',
            'price' => 'required|decimal',
            'effective_date' => 'required|valid_date',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $versionModel = new PackageVersionModel();
        $saved = $versionModel->insert([
            'package_id' => (int) $this->request->getPost('package_id'),
            'price' => (string) $this->request->getPost('price'),
            'effective_date' => (string) $this->request->getPost('effective_date'),
            'status' => (string) $this->request->getPost('status'),
        ]);

        if (! $saved) {
            return redirect()->back()->withInput()->with('error', 'Failed to create package version.');
        }

        return redirect()->to('/packages')->with('success', 'Price version created.');
    }

    /**
     * Panel brief, section 1: "Add a feature to create a new Plan,
     * supporting different packages/benefit tiers" and "allow a plan
     * holder to be offered several plan options based on their current
     * plan (upgrade/cross-sell paths)." This is that feature: pick a plan
     * holder, pick a package + priced version, and it becomes their plan.
     *
     * If they already have an active plan, this is an upgrade/change, not
     * a duplicate enrollment - the previous plan is marked 'completed'
     * (superseded) so exactly one plan stays 'active', matching what
     * MembershipService::getActivePlan() and everything built on it
     * (payments, dashboards, the overdue policy) already assume.
     */
    public function assignToPlan()
    {
        $this->ensureAccess();

        $rules = [
            'plan_holder_id' => 'required|is_natural_no_zero',
            'package_id' => 'required|is_natural_no_zero',
            'version_id' => 'required|is_natural_no_zero',
            'start_date' => 'required|valid_date',
            'status' => 'required|in_list[active,inactive,completed]',
            'passbook_fee' => 'permit_empty|decimal',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $packageId = (int) $this->request->getPost('package_id');
        $versionId = (int) $this->request->getPost('version_id');
        $planHolderId = (int) $this->request->getPost('plan_holder_id');
        $newStatus = (string) $this->request->getPost('status');

        $versionModel = new PackageVersionModel();
        $version = $versionModel->find($versionId);

        if (! $version || (int) $version['package_id'] !== $packageId) {
            return redirect()->back()->withInput()->with('error', 'Selected version does not belong to selected package.');
        }

        $lockedPrice = (string) $version['price'];
        $planModel = new PlanModel();
        $db = db_connect();
        $db->transBegin();

        try {
            if ($newStatus === 'active') {
                $planModel->where('plan_holder_id', $planHolderId)
                    ->where('status', 'active')
                    ->set(['status' => 'completed'])
                    ->update();
            }

            $saved = $planModel->insert([
                'plan_holder_id' => $planHolderId,
                'package_id' => $packageId,
                'version_id' => $versionId,
                'monthly_fee' => $lockedPrice,
                'passbook_fee' => $this->nullablePost('passbook_fee') ?? '50.00',
                'start_date' => (string) $this->request->getPost('start_date'),
                'status' => $newStatus,
                'months_paid' => 0,
                'remaining_balance' => $lockedPrice,
            ]);

            if (! $saved || $db->transStatus() === false) {
                throw new \RuntimeException('Failed to assign package version to plan holder.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/packages')->with('success', 'Package version assigned to plan with locked price.');
    }

    private function ensureAccess(): void
    {
        $roleId = (int) session()->get('role_id');

        // Admin has its own separate package tool (admin/service-offer) -
        // index() redirects role 1 there already; this guard is the
        // write-side backstop so Admin can't POST to these actions
        // directly, and blocks everyone else (Plan Holder, Collector).
        if (! in_array($roleId, [1, 2, 3], true)) {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return 'layouts/admin';
        }

        if ($role === 2) {
            return 'layouts/branch_admin';
        }

        if ($role === 3) {
            return 'layouts/staff';
        }

        return 'layouts/plan_holder';
    }

    // nullablePost() is inherited from BaseController - this class used to
    // redeclare it as private, which is invalid PHP (you can't narrow a
    // parent's visibility) and made the whole class fail to load the
    // moment anything tried to route to it.
}
