<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\CycleService;
use App\Services\PaymentService;
use App\Services\PlanLedgerService;

/**
 * Reinstating a Lapsed plan: record the cash that covers every overdue
 * contribution plus the reinstatement fee (Config\CareSync). Admin, Branch
 * Admin and Staff can record it for clients of their own branch.
 *
 * Cash posts as paid at once, exactly like any other counter payment
 * (PaymentTracking::recordCash). Once the money is in, PlanLedgerService
 * sees nothing overdue and the fee covered, returns the plan to Active on
 * its own and writes the reinstatement to the audit log. GCash is not
 * offered here because the fee has no GCash approval path.
 */
class PlanReinstatement extends BaseController
{
    private const PREFIX = [1 => 'admin', 2 => 'branch-admin', 3 => 'staff'];

    public function record(int $planHolderId)
    {
        $roleId = (int) session('role_id');
        $prefix = self::PREFIX[$roleId] ?? null;
        if ($prefix === null) {
            return redirect()->to('/unauthorized');
        }

        $profile = $roleId === 3
            ? site_url('staff/client/view/' . $planHolderId)
            : site_url($prefix . '/client-management/view/' . $planHolderId);

        $db = db_connect();
        $holder = $db->table('plan_holders')->where('plan_holder_id', $planHolderId)->get()->getRowArray();

        // Same answer for "missing" and "other branch's client".
        if (! $holder || ($roleId !== 1 && (int) $holder['branch_id'] !== (int) session('branch_id'))) {
            return redirect()->to($profile)->with('error', 'Client not found.');
        }

        $plan = $db->table('plans')->where('plan_holder_id', $planHolderId)->orderBy('plan_id', 'DESC')->get()->getRowArray();
        if (! $plan) {
            return redirect()->to($profile)->with('error', 'This client has no plan.');
        }

        $ledger = new PlanLedgerService();
        $s = $ledger->summarize((int) $plan['plan_id']);

        // Re-checked on the server: the panel only shows for a Lapsed plan, but a
        // stale page or a hand-made request must not record money against any other plan.
        if (! $s || $s['lifecycle'] !== 'lapsed') {
            return redirect()->to($profile)->with('error', 'This plan is not on hold, so there is nothing to reinstate.');
        }

        $r = $s['reinstatement'];
        if ($r['total'] <= 0) {
            return redirect()->to($profile)->with('error', 'Nothing is owed on this plan.');
        }

        $actorId = (int) session('user_id');
        $paymentService = new PaymentService();
        $today = date('Y-m-d');
        $branchId = (int) $holder['branch_id'];
        $cycle = (int) ($plan['current_cycle_number'] ?? 1);
        $ids = [];

        $db->transBegin();

        try {
            if ($r['overdue_amount'] > 0) {
                $db->table('payments')->insert([
                    'plan_id' => (int) $plan['plan_id'], 'amount' => number_format($r['overdue_amount'], 2, '.', ''),
                    'months_covered' => max(1, $s['overdue_count']), 'payment_date' => $today, 'payment_method' => 'cash',
                    'received_by' => $actorId, 'branch_id' => $branchId, 'status' => 'paid', 'payment_type' => 'monthly_contribution',
                    'cycle_number' => $cycle, 'verified_by' => $actorId, 'verified_at' => date('Y-m-d H:i:s'),
                    'remarks' => 'Reinstatement: overdue contributions, recorded at branch counter',
                ]);
                $ids[] = (int) $db->insertID();
            }

            if ($r['fee_remaining'] > 0) {
                $db->table('payments')->insert([
                    'plan_id' => (int) $plan['plan_id'], 'amount' => number_format($r['fee_remaining'], 2, '.', ''),
                    'months_covered' => 0, 'payment_date' => $today, 'payment_method' => 'cash',
                    'received_by' => $actorId, 'branch_id' => $branchId, 'status' => 'paid', 'payment_type' => 'reinstatement_fee',
                    'cycle_number' => $cycle, 'verified_by' => $actorId, 'verified_at' => date('Y-m-d H:i:s'),
                    'remarks' => 'Reinstatement fee, recorded at branch counter',
                ]);
                $ids[] = (int) $db->insertID();
            }

            foreach ($ids as $id) {
                $db->table('payments')->where('payment_id', $id)->update([
                    'official_receipt_number' => $paymentService->generateReceiptNumber($id, $today),
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to record the payment.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($profile)->with('error', $e->getMessage());
        }

        (new ActivityLogService())->log(
            $actorId,
            'created',
            'payment',
            $ids[0],
            'Recorded reinstatement payment of ' . cs_peso($r['total']) . ' (overdue ' . cs_peso($r['overdue_amount']) . ($r['fee_remaining'] > 0 ? ' + fee ' . cs_peso($r['fee_remaining']) : '') . ') for plan #' . (int) $plan['plan_id'],
            null,
            ['plan_id' => (int) $plan['plan_id'], 'payment_ids' => $ids]
        );

        // Recompute: this is what flips the plan back to Active (and logs the reinstatement).
        $after = $ledger->sync((int) $plan['plan_id'], false, $actorId);
        (new CycleService())->closeCycleIfSettled((int) $plan['plan_id']);

        $message = $after && $after['lifecycle'] !== 'lapsed'
            ? 'Payment recorded. The plan has been reinstated and is Active again.'
            : 'Payment recorded, but the plan is still on hold: ' . cs_peso($after['reinstatement']['total'] ?? 0) . ' more is needed to reinstate it.';

        return redirect()->to($profile)->with('success', $message);
    }
}
