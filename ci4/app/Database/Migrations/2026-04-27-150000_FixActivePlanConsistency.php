<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixActivePlanConsistency extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plan_holders') || ! $this->db->tableExists('plans')) {
            return;
        }

        $this->db->transStart();

        $holders = $this->db->table('plan_holders')
            ->select('plan_holder_id, status')
            ->where('status', 'active')
            ->get()
            ->getResultArray();

        foreach ($holders as $holder) {
            $planHolderId = (int) ($holder['plan_holder_id'] ?? 0);
            if ($planHolderId <= 0) {
                continue;
            }

            $activePlans = $this->db->table('plans')
                ->select('plan_id')
                ->where('plan_holder_id', $planHolderId)
                ->where('status', 'active')
                ->orderBy('plan_id', 'DESC')
                ->get()
                ->getResultArray();

            if (empty($activePlans)) {
                $latestPlan = $this->db->table('plans')
                    ->select('plan_id')
                    ->where('plan_holder_id', $planHolderId)
                    ->orderBy('plan_id', 'DESC')
                    ->get()
                    ->getRowArray();

                if ($latestPlan) {
                    $targetPlanId = (int) ($latestPlan['plan_id'] ?? 0);
                    if ($targetPlanId > 0) {
                        $this->db->table('plans')
                            ->where('plan_holder_id', $planHolderId)
                            ->set(['status' => 'inactive'])
                            ->update();

                        $this->db->table('plans')
                            ->where('plan_id', $targetPlanId)
                            ->set(['status' => 'active'])
                            ->update();
                    }
                }

                continue;
            }

            if (count($activePlans) > 1) {
                $keepPlanId = (int) ($activePlans[0]['plan_id'] ?? 0);
                if ($keepPlanId > 0) {
                    $this->db->table('plans')
                        ->where('plan_holder_id', $planHolderId)
                        ->set(['status' => 'inactive'])
                        ->update();

                    $this->db->table('plans')
                        ->where('plan_id', $keepPlanId)
                        ->set(['status' => 'active'])
                        ->update();
                }
            }
        }

        $this->db->transComplete();
    }

    public function down()
    {
        // No rollback: this migration repairs and normalizes existing data.
    }
}
