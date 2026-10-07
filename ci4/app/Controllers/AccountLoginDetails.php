<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\LoginDetailsService;

/**
 * The one-time "login details" screen and the "Resend login details" action
 * on a client's profile. Staff-side roles only (route filter role:1,2,3);
 * Branch Admin and Staff can only touch clients of their own branch.
 */
class AccountLoginDetails extends BaseController
{
    private const AREA_PREFIX = [1 => 'admin', 2 => 'branch-admin', 3 => 'staff'];

    private const LAYOUT = [1 => 'layouts/admin', 2 => 'layouts/branch_admin', 3 => 'layouts/staff'];

    /**
     * Rendered from the one-request flash the creator/resend action left
     * behind, so a reload (or the back button) cannot show the password again.
     */
    public function show()
    {
        $details = session()->getFlashdata('login_details');
        $role    = (int) session('role_id');

        if (! is_array($details)) {
            return redirect()->to(site_url(self::AREA_PREFIX[$role] . '/create-account'))
                ->with('error', 'Those login details are no longer available. Use "Resend login details" on the client profile to issue new ones.');
        }

        return view('accounts/login_details', [
            'role_layout' => self::LAYOUT[$role],
            'page_title'  => 'Login details',
            'page_sub'    => 'Give these to the client now.',
            'crumbs'      => [['label' => 'Accounts'], ['label' => 'Login details']],
            'details'     => $details,
        ]);
    }

    /** POST: issue a NEW temporary password for a plan holder's account. */
    public function resend(int $planHolderId)
    {
        $role   = (int) session('role_id');
        $prefix = self::AREA_PREFIX[$role] ?? null;

        if ($prefix === null) {
            return redirect()->to('/unauthorized');
        }

        $profileUrl = $role === 3
            ? site_url('staff/client/view/' . $planHolderId)
            : site_url($prefix . '/client-management/view/' . $planHolderId);

        $holder = db_connect()->table('plan_holders')
            ->select('plan_holder_id, user_id, branch_id')
            ->where('plan_holder_id', $planHolderId)
            ->get()
            ->getRowArray();

        // Same answer for "does not exist" and "other branch's client", so
        // Branch Admin / Staff cannot probe records outside their branch.
        if (! $holder || ($role !== 1 && (int) $holder['branch_id'] !== (int) session('branch_id'))) {
            return redirect()->to($profileUrl)->with('error', 'Client not found.');
        }

        $account = db_connect()->table('users')
            ->select('user_id, role_id')
            ->where('user_id', (int) $holder['user_id'])
            ->get()
            ->getRowArray();

        if (! $account || (int) $account['role_id'] !== 4) {
            return redirect()->to($profileUrl)->with('error', 'This client has no sign-in account to send details for.');
        }

        $service = new LoginDetailsService();
        $details = $service->reissue((int) $account['user_id']);

        if ($details === null) {
            return redirect()->to($profileUrl)->with('error', 'Unable to issue new login details.');
        }

        $details['emailed']    = $service->email($details);
        $details['back_url']   = $profileUrl;
        $details['back_label'] = 'Back to client profile';

        (new ActivityLogService())->log(
            (int) session('user_id'),
            'updated',
            'user',
            (int) $account['user_id'],
            'Resent login details (new temporary password issued)',
            null,
            ['plan_holder_id' => $planHolderId, 'emailed' => $details['emailed']]
        );

        return redirect()->to(site_url('account-login-details'))
            ->with('success', 'A new temporary password was issued for ' . $details['name'] . '.')
            ->with('login_details', $details);
    }
}
