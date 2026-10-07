<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\GovernmentIdVerificationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The stop between plan registration and Initial Payment: a plan holder
 * can't reach Initial Payment (ClientPaymentInitialController's own gate)
 * until a Branch Admin/Staff has manually verified their Government ID
 * (IdVerificationQueueController - the review side). This page shows them
 * where things stand and, if the ID was rejected, lets them upload another
 * one - reusing the same GovernmentIdVerificationService::verify() call
 * the registration wizard's own Step 2 uses (the account already exists at
 * this point, so no pending-token dance is needed).
 */
class IdVerificationStatusController extends BaseController
{
    use ClientPortalTrait;

    public function show(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/client/dashboard');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info');
        }

        $user = $access['user'];
        $idVerificationService = new GovernmentIdVerificationService();
        $verification = $idVerificationService->latestForUser((int) $user['user_id']);
        $status = $verification !== null ? (string) ($verification['verification_status'] ?? '') : null;

        if ($status === 'verified') {
            return redirect()->to('/initial-payment');
        }

        return view('client/id_verification_status', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Government ID verification',
            'page_sub' => 'One more step before you can make your initial payment.',
            'status' => $status,
            'verification' => $verification,
            'user' => $user,
            'plan_holder' => $planHolder,
            'id_types' => $idVerificationService->idTypes(),
        ]);
    }
}
