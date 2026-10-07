<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\NotificationService;
use App\Services\PlanBuilderService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Fix Prompts Task 5A. Replaces the "Plan builder" nav item, which used to
 * point at the four-step Packages module (Create Package/Add Item/Set
 * Price Version/Assign to Plan Holder) - a real plan-definition screen
 * (name, price, monthly contribution, term, entitled package + its
 * inclusions) that never existed. See PlanBuilderService for what a "plan"
 * means here.
 *
 * Create is now role:1,2 (System Admin, Branch Admin) at the route level -
 * a Branch Admin submission goes in as approval_status='pending', invisible
 * everywhere until approve()/reject() (role:1 only, same as edit/toggle/
 * delete) resolves it. See PlanBuilderService's docblock.
 */
class PlanBuilder extends BaseController
{
    private PlanBuilderService $service;

    public function __construct()
    {
        $this->service = new PlanBuilderService();
    }

    public function index(): string
    {
        $search = cs_search_term($this->request->getGet('search'));

        return view('plan_builder/index', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title'  => 'Plan Builder',
            'page_sub'    => 'Every sellable plan and what it entitles.',
            'search'      => $search,
            'plans'       => cs_filter_rows($this->service->list(), $search, ['program_name', 'package_name', 'description']),
            'can_manage'  => $this->canManage(),
            'can_create'  => $this->canCreate(),
        ]);
    }

    public function create(): string
    {
        return view('plan_builder/form', [
            'role_layout'   => $this->resolveLayoutView(),
            'page_title'    => 'New Plan',
            'program_id'    => null,
            'plan'          => null,
            'package'       => null,
            'packages'      => $this->service->availablePackages(),
            'errors'        => session()->getFlashdata('errors') ?? [],
            'needs_approval' => (int) session('role_id') !== 1,
        ]);
    }

    public function store(): ResponseInterface
    {
        $isSystemAdmin = (int) session('role_id') === 1;

        $result = $this->service->save($this->collectInput(), null, [
            'auto_approve'           => $isSystemAdmin,
            'submitted_by'           => (int) session('user_id'),
            'submitted_by_branch_id' => session('branch_id') !== null ? (int) session('branch_id') : null,
        ]);

        if (! $result['success']) {
            return redirect()->to('/plan-builder/create')->withInput()->with('errors', $result['errors'])->with('error', 'Please fix the errors below.');
        }

        if (! $isSystemAdmin) {
            $this->notifySystemAdmins(
                trim((string) session('first_name') . ' ' . (string) session('last_name')) . ' submitted a new plan ("' . trim((string) $this->request->getPost('plan_name')) . '") for approval.'
            );

            return redirect()->to('/plan-builder')->with('success', 'Plan submitted for System Admin approval. It will appear at registration once approved.');
        }

        return redirect()->to('/plan-builder/' . $result['program_id'])->with('success', 'Plan created successfully.');
    }

    public function approve(int $programId): ResponseInterface
    {
        $result = $this->service->approve($programId, (int) session('user_id'));

        if (! $result['success']) {
            return redirect()->to('/plan-builder')->with('error', (string) $result['error']);
        }

        $submittedBy = (int) ($result['program']['submitted_by'] ?? 0);
        if ($submittedBy > 0) {
            (new NotificationService())->notify(
                $submittedBy,
                'Your plan "' . (string) ($result['program']['program_name'] ?? '') . '" was approved and is now available at registration.',
                'plan_approved'
            );
        }

        return redirect()->to('/plan-builder')->with('success', 'Plan approved and activated.');
    }

    public function reject(int $programId): ResponseInterface
    {
        $reason = trim((string) $this->request->getPost('reason'));
        $result = $this->service->reject($programId, (int) session('user_id'), $reason);

        if (! $result['success']) {
            return redirect()->to('/plan-builder')->with('error', (string) $result['error']);
        }

        $submittedBy = (int) ($result['program']['submitted_by'] ?? 0);
        if ($submittedBy > 0) {
            $message = 'Your plan "' . (string) ($result['program']['program_name'] ?? '') . '" was rejected.';
            if ($reason !== '') {
                $message .= ' Reason: ' . $reason;
            }
            (new NotificationService())->notify($submittedBy, $message, 'plan_rejected');
        }

        return redirect()->to('/plan-builder')->with('success', 'Plan rejected.');
    }

    /**
     * AJAX-only: the create/edit form's package dropdown fetches this to
     * preview what a package actually includes before committing to it.
     * Read-only - inclusions belong to Services & Packages, not Plan
     * Builder, now that packages are picked from the catalog rather than
     * authored per plan (see PlanBuilderService's docblock).
     */
    public function packageInclusions(): ResponseInterface
    {
        $packageId = (int) $this->request->getGet('package_id');
        $items = $packageId > 0
            ? db_connect()->table('package_items')->select('item_name, description')->where('package_id', $packageId)->orderBy('item_id', 'ASC')->get()->getResultArray()
            : [];

        return $this->response->setJSON([
            'items' => array_map(static fn (array $i): array => [
                'name' => (string) $i['item_name'],
                'description' => (string) ($i['description'] ?? ''),
            ], $items),
        ]);
    }

    public function show(int $programId): string
    {
        $found = $this->service->find($programId);
        if ($found === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('plan_builder/show', [
            'role_layout'       => $this->resolveLayoutView(),
            'page_title'        => (string) $found['program']['program_name'],
            'program'           => $found['program'],
            'package'           => $found['package'],
            'inclusions'        => $found['inclusions'],
            'plan_holder_count' => $found['plan_holder_count'],
            'can_manage'        => $this->canManage(),
        ]);
    }

    public function edit(int $programId): string
    {
        $found = $this->service->find($programId);
        if ($found === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('plan_builder/form', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title'  => 'Edit Plan',
            'program_id'  => $programId,
            'plan'        => $found['program'],
            'package'     => $found['package'],
            'packages'    => $this->service->availablePackages(),
            'errors'      => session()->getFlashdata('errors') ?? [],
        ]);
    }

    public function update(int $programId): ResponseInterface
    {
        $result = $this->service->save($this->collectInput(), $programId);

        if (! $result['success']) {
            return redirect()->to('/plan-builder/' . $programId . '/edit')->withInput()->with('errors', $result['errors'])->with('error', 'Please fix the errors below.');
        }

        return redirect()->to('/plan-builder/' . $programId)->with('success', 'Plan updated successfully.');
    }

    public function toggle(int $programId): ResponseInterface
    {
        $found = $this->service->find($programId);
        if ($found === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $activateNext = (int) ($found['program']['is_active'] ?? 0) !== 1;
        $result = $this->service->setActive($programId, $activateNext);

        if (! $result['success']) {
            return redirect()->back()->with('error', (string) $result['error']);
        }

        return redirect()->back()->with('success', $activateNext ? 'Plan activated.' : 'Plan deactivated.');
    }

    public function destroy(int $programId): ResponseInterface
    {
        $result = $this->service->delete($programId);

        if (! $result['success']) {
            return redirect()->to('/plan-builder/' . $programId)->with('error', (string) $result['error']);
        }

        return redirect()->to('/plan-builder')->with('success', 'Plan deleted.');
    }

    private function canManage(): bool
    {
        return (int) session('role_id') === 1;
    }

    /** System Admin creates directly; Branch Admin can submit too (goes to approval). */
    private function canCreate(): bool
    {
        return in_array((int) session('role_id'), [1, 2], true);
    }

    private function notifySystemAdmins(string $message): void
    {
        $admins = (new UserModel())->select('user_id')->where('role_id', 1)->findAll();
        $notificationService = new NotificationService();

        foreach ($admins as $admin) {
            $notificationService->notify((int) ($admin['user_id'] ?? 0), $message, 'plan_submitted');
        }
    }

    private function collectInput(): array
    {
        return [
            'plan_name'   => trim((string) $this->request->getPost('plan_name')),
            'plan_price'  => (float) $this->request->getPost('plan_price'),
            'monthly_fee' => (float) $this->request->getPost('monthly_fee'),
            'description' => trim((string) $this->request->getPost('description')),
            'is_active'   => (string) $this->request->getPost('is_active') === '1' ? 1 : 0,
            'package_id'  => (int) $this->request->getPost('package_id'),
        ];
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session('role_id');

        if ($role === 2) {
            return 'layouts/branch_admin';
        }

        if ($role === 3) {
            return 'layouts/staff';
        }

        return 'layouts/admin';
    }
}
