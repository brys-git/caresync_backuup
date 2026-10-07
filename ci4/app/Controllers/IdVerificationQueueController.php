<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\GovernmentIdVerificationService;
use App\Services\NotificationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Manual Government ID review queue for Staff and Branch Admin - built
 * because no Anthropic API key is configured in this environment, so every
 * ID verification comes back 'needs_review' instead of an automatic
 * verified/failed. One controller, two entry points (same shape as
 * CollectionListController): Staff and Branch Admin each see only their
 * own branch's queue, sharing the same list/detail/review-decision/image
 * logic instead of two near-duplicate controllers.
 */
class IdVerificationQueueController extends BaseController
{
    public function branchAdmin(): string
    {
        return $this->renderQueue('layouts/branch_admin', base_url('branch-admin/id-verification'));
    }

    public function staff(): string
    {
        return $this->renderQueue('layouts/staff', base_url('staff/id-verification'));
    }

    private function renderQueue(string $layout, string $actionBase): string
    {
        $branchId = (int) session('branch_id');
        $service = new GovernmentIdVerificationService();
        $search = cs_search_term($this->request->getGet('search'));

        return view('id_verification_queue/index', [
            'search' => $search,
            'search_action' => $actionBase,
            'role_layout' => $layout,
            'page_title' => 'Government ID Review',
            'page_sub' => 'Compare each applicant\'s uploaded ID against what they typed in their registration form.',
            'rows' => $branchId > 0 ? cs_filter_rows($service->listNeedingReview($branchId), $search, ['first_name', 'last_name', 'email', 'claimed_first_name', 'claimed_last_name']) : [],
            'action_base' => $actionBase,
        ]);
    }

    public function show(int $verificationId): ResponseInterface|string
    {
        $branchId = (int) session('branch_id');
        $service = new GovernmentIdVerificationService();
        $row = $branchId > 0 ? $service->getForReview($verificationId, $branchId) : null;

        if (! $row) {
            return redirect()->to($this->queueUrl())->with('error', 'That ID verification is not in your branch, or no longer needs review.');
        }

        return view('id_verification_queue/show', [
            'role_layout' => $this->layoutForRole(),
            'page_title' => 'Review Government ID',
            'page_sub' => trim((string) ($row['first_name'] . ' ' . $row['last_name'])),
            'row' => $row,
            'action_base' => $this->roleActionBase(),
            'queue_url' => $this->queueUrl(),
        ]);
    }

    public function image(int $verificationId): ResponseInterface
    {
        $branchId = (int) session('branch_id');
        $service = new GovernmentIdVerificationService();
        $row = $branchId > 0 ? $service->getForReview($verificationId, $branchId) : null;

        if (! $row || empty($row['file_path'])) {
            return $this->response->setStatusCode(404)->setBody('Not found.');
        }

        $fullPath = WRITEPATH . (string) $row['file_path'];
        if (! is_file($fullPath)) {
            return $this->response->setStatusCode(404)->setBody('Image file is missing.');
        }

        $mime = (string) ($row['mime_type'] ?? '');
        if ($mime === '') {
            $detected = @mime_content_type($fullPath);
            $mime = is_string($detected) ? $detected : 'application/octet-stream';
        }

        return $this->response->setContentType($mime)->setBody((string) file_get_contents($fullPath));
    }

    public function markVerified(int $verificationId): ResponseInterface
    {
        return $this->decide($verificationId, 'verified');
    }

    public function markFailed(int $verificationId): ResponseInterface
    {
        return $this->decide($verificationId, 'failed');
    }

    private function decide(int $verificationId, string $status): ResponseInterface
    {
        $branchId = (int) session('branch_id');
        $service = new GovernmentIdVerificationService();
        $row = $branchId > 0 ? $service->getForReview($verificationId, $branchId) : null;

        if (! $row) {
            return redirect()->to($this->queueUrl())->with('error', 'That ID verification is not in your branch, or no longer needs review.');
        }

        $reviewerId = (int) session('user_id');
        $service->markReviewed($verificationId, $status, $reviewerId);

        (new ActivityLogService())->log(
            $reviewerId,
            $status === 'verified' ? 'verified' : 'rejected',
            'government_id_verification',
            $verificationId,
            $status === 'verified' ? 'Manually verified applicant Government ID' : 'Manually marked applicant Government ID as not matching',
            ['verification_status' => 'needs_review'],
            ['verification_status' => $status]
        );

        // 'general' - notifications.type is a fixed MySQL ENUM;
        // NotificationService::notify() silently downgrades anything
        // outside its own whitelist to this value anyway.
        $clientMessage = $status === 'verified'
            ? 'Your Government ID has been verified! You can now proceed to your Initial Payment.'
            : 'Your Government ID could not be confirmed against your application details. Please log in and upload another valid ID to continue.';
        (new NotificationService())->notify((int) $row['user_id'], $clientMessage, 'general');

        $message = $status === 'verified'
            ? 'Marked as verified.'
            : 'Marked as not matching - the applicant has been notified.';

        return redirect()->to($this->queueUrl())->with('success', $message);
    }

    private function layoutForRole(): string
    {
        return (int) session('role_id') === 2 ? 'layouts/branch_admin' : 'layouts/staff';
    }

    private function roleActionBase(): string
    {
        return (int) session('role_id') === 2
            ? base_url('branch-admin/id-verification')
            : base_url('staff/id-verification');
    }

    private function queueUrl(): string
    {
        return (int) session('role_id') === 2
            ? base_url('branch-admin/id-verification')
            : base_url('staff/id-verification');
    }
}
