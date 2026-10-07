<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Services\ClaimService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Panel brief, section 5/6: claims ("service applications" - a plan holder
 * applying to avail their package's or a service's benefit) must be
 * processed by staff or an Encoder, not just Branch Admin. Mirrors
 * BranchAdmin\ServiceApplicationController, sharing its approve/reject/
 * lookup logic via ClaimService so the two don't drift into separate
 * implementations of the same transaction.
 */
class ServiceApplicationController extends BaseController
{
    private const BASE_PATH = '/staff/services/requests';

    private ClaimService $claimService;

    public function __construct()
    {
        $this->claimService = new ClaimService();
    }

    public function index(): string
    {
        $this->ensureStaffAccess();

        $branchId = (int) session('branch_id');
        $branchIssue = $branchId <= 0
            ? 'No branch is assigned to your staff account. Please contact the branch admin.'
            : null;

        return view('staff/services/requests', [
            'page_title' => 'Claims',
            'page_sub' => "Plan holders claiming their package's or a service's benefit. Review the details and supporting documents, then approve or reject.",
            'search' => cs_search_term($this->request->getGet('search')),
            'requests' => $branchId > 0 ? $this->claimService->getBranchClaims($branchId, cs_search_term($this->request->getGet('search'))) : [],
            'branch_issue' => $branchIssue,
            'role_layout' => 'layouts/staff',
        ]);
    }

    public function show(int $id): string
    {
        $this->ensureStaffAccess();

        $branchId = (int) session('branch_id');
        $request = $this->claimService->getClaimDetail($id, $branchId);

        if (! $request) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/service_package/request_show', [
            'role_layout' => 'layouts/staff',
            'page_title' => 'Claim Details',
            'page_sub' => 'Review the submitted claim and supporting documents before approving or rejecting.',
            'claim_base_path' => self::BASE_PATH,
            'request' => $request,
            'documents' => $this->claimService->getClaimDocuments($id),
        ]);
    }

    public function approve(int $id)
    {
        $this->ensureStaffAccess();

        $result = $this->claimService->approve($id, (int) session('branch_id'), (int) session('user_id'));

        if (! $result['ok']) {
            if ($result['message'] === 'Claim not found.') {
                throw PageNotFoundException::forPageNotFound();
            }

            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->to(self::BASE_PATH)->with('success', $result['message']);
    }

    public function reject(int $id)
    {
        $this->ensureStaffAccess();

        $reason = trim((string) $this->request->getPost('rejection_reason'));
        $result = $this->claimService->reject($id, (int) session('branch_id'), (int) session('user_id'), $reason);

        if (! $result['ok']) {
            if ($result['message'] === 'Claim not found.') {
                throw PageNotFoundException::forPageNotFound();
            }

            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->to(self::BASE_PATH)->with('success', $result['message']);
    }

    public function downloadDocument(int $id)
    {
        $this->ensureStaffAccess();

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
