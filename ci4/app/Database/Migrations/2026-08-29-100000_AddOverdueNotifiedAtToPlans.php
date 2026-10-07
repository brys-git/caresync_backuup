<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Panel brief, section 9: "Notify plan holder when overdue." Tracks
 * whether the plan holder has already been sent an overdue notice for the
 * current unpaid cycle, so the daily sweep (OverduePolicyService::
 * notifyOverdueAccounts()) sends it once per cycle instead of every day
 * the account stays overdue. Cleared back to NULL whenever a payment is
 * recorded (PaymentService::recomputePlan()), so a fresh cycle starts the
 * next time they fall behind.
 */
class AddOverdueNotifiedAtToPlans extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        if (! $this->db->fieldExists('overdue_notified_at', 'plans')) {
            $this->forge->addColumn('plans', [
                'overdue_notified_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans') && $this->db->fieldExists('overdue_notified_at', 'plans')) {
            $this->forge->dropColumn('plans', 'overdue_notified_at');
        }
    }
}
