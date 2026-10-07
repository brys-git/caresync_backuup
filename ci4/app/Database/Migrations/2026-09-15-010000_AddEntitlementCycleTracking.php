<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CareSync entitlement cycle model: replaces the buggy "reset months_paid
 * to 0 the instant a claim is approved" behaviour
 * (ClaimService::resetContributionCycleIfEntitlementClaim()), which
 * destroyed a plan holder's progress toward their current ₱14,500
 * contribution target the moment they claimed early, and gave no way to
 * tell "claimed but still paying off this cycle" apart from "just started
 * a fresh cycle."
 *
 * A cycle is identified by plans.current_cycle_number. It only advances
 * when the cycle is fully paid off AND its entitlement has been claimed -
 * whichever happens last is what closes it (see CycleService). Both
 * payments and service_applications are stamped with the cycle they
 * belong to at the time they're recorded, so the whole history stays
 * attributable to a specific cycle even after current_cycle_number moves
 * on. plans.contribution_cycle_started_at (Phase 0) is unchanged - it
 * keeps its existing job of marking "count progress from here forward
 * only" within whichever cycle is current, which is exactly what lets a
 * 60-day forfeiture reset progress without also closing/advancing the
 * cycle.
 */
class AddEntitlementCycleTracking extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('plans') && ! $this->db->fieldExists('current_cycle_number', 'plans')) {
            $this->forge->addColumn('plans', [
                'current_cycle_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'contribution_cycle_started_at',
                ],
            ]);
        }

        if ($this->db->tableExists('payments') && ! $this->db->fieldExists('cycle_number', 'payments')) {
            $this->forge->addColumn('payments', [
                'cycle_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'months_covered',
                ],
            ]);
        }

        if ($this->db->tableExists('service_applications') && ! $this->db->fieldExists('cycle_number', 'service_applications')) {
            $this->forge->addColumn('service_applications', [
                'cycle_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'package_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('service_applications') && $this->db->fieldExists('cycle_number', 'service_applications')) {
            $this->forge->dropColumn('service_applications', 'cycle_number');
        }

        if ($this->db->tableExists('payments') && $this->db->fieldExists('cycle_number', 'payments')) {
            $this->forge->dropColumn('payments', 'cycle_number');
        }

        if ($this->db->tableExists('plans') && $this->db->fieldExists('current_cycle_number', 'plans')) {
            $this->forge->dropColumn('plans', 'current_cycle_number');
        }
    }
}
