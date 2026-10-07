<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Services\ClaimService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ServiceApplicationController extends BaseController
{
    private ClaimService $claimService;

    public function __construct()
    {
        $this->claimService = new ClaimService();
    }

    public function index(): string
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');
        $search = cs_search_term($this->request->getGet('search'));

        return view('branch_admin/service_package/index', [
            'page_title' => 'Service / Package Management',
            'active_tab' => 'requests',
            'search' => $search,
            'requests' => $this->claimService->getBranchClaims($branchId, $search),
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function approve(int $id)
    {
        $this->ensureBranchAdminAccess();

        $result = $this->claimService->approve($id, (int) session('branch_id'), (int) session('user_id'));

        if (! $result['ok']) {
            if ($result['message'] === 'Claim not found.') {
                throw PageNotFoundException::forPageNotFound();
            }

            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->to('/branch-admin/service-package/requests')->with('success', $result['message']);
    }

    public function reject(int $id)
    {
        $this->ensureBranchAdminAccess();

        $reason = trim((string) $this->request->getPost('rejection_reason'));
        $result = $this->claimService->reject($id, (int) session('branch_id'), (int) session('user_id'), $reason);

        if (! $result['ok']) {
            if ($result['message'] === 'Claim not found.') {
                throw PageNotFoundException::forPageNotFound();
            }

            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->to('/branch-admin/service-package/requests')->with('success', $result['message']);
    }

    public function show(int $id): string
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');
        $request = $this->claimService->getClaimDetail($id, $branchId);

        if (! $request) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/service_package/request_show', [
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Claim Details',
            'page_sub' => 'Review the submitted claim and supporting documents before approving or rejecting.',
            'claim_base_path' => '/branch-admin/service-package/requests',
            'request' => $request,
            'documents' => $this->claimService->getClaimDocuments($id),
        ]);
    }

    public function downloadDocument(int $id)
    {
        $this->ensureBranchAdminAccess();

        $document = $this->claimService->getClaimDocumentForBranch($id, (int) session('branch_id'));

        if (! $document) {
            return redirect()->back()->with('error', 'Document not found or you are not authorized to view it.');
        }

        $fullPath = WRITEPATH . ($document['path'] ?? '');
        if (! is_file($fullPath)) {
            return redirect()->back()->with('error', 'Document file is missing.');
        }

        return $this->response->download($fullPath, null)->setFileName($document['original_name']);
    }

    private function ensureBranchAdminAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 2 && $roleName !== 'branch admin') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}
