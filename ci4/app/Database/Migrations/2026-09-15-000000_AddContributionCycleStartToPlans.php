<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client dashboard rebuild, Phase 0 (months_paid bug fix): months_paid is
 * moving from being written ad hoc at 2-3 different call sites to being
 * derived from SUM(payments.months_covered) - see
 * MembershipService::recalculateMonthsPaid(). That derivation needs a lower
 * bound, or it would silently undo OverduePolicyService::applyForfeitures()
 * (the 60-day-grace forfeiture policy) the next time any payment on a
 * previously-forfeited plan gets verified: summing the plan's *entire*
 * payment history would restore the months that forfeiture deliberately
 * reset to 0.
 *
 * contribution_cycle_started_at marks "count payments from here onward
 * only" - set once at plan creation (to start_date) and reset again every
 * time applyForfeitures() resets months_paid to 0, so recalculation always
 * only sees the current, un-forfeited cycle.
 */
class AddContributionCycleStartToPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if (! $this->db->fieldExists('contribution_cycle_started_at', 'plans')) {
            $this->forge->addColumn('plans', [
                'contribution_cycle_started_at' => [
                    'type'       => 'DATETIME',
                    'null'       => true,
                    'after'      => 'months_paid',
                ],
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if ($this->db->fieldExists('contribution_cycle_started_at', 'plans')) {
            $this->forge->dropColumn('plans', 'contribution_cycle_started_at');
        }
    }
}
