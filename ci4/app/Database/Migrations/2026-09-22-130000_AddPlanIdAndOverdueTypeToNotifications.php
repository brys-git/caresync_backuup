<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an overdue notification point back at the plan it's about, so the
 * notifications list can recompute the due date / days-overdue / days-left
 * live from the plan's current row instead of printing a message that was
 * frozen at the moment the daily sweep ran - editing plans.next_due_date
 * afterward (e.g. for manual testing) had no effect on a notification
 * already in the table, which is the gap this closes. Only the 'overdue'
 * type is re-derived this way; forfeiture notifications describe a
 * completed past event and stay as fixed text - see
 * OverduePolicyService::describeOverdue() / NotificationService::
 * getNotifications().
 */
class AddPlanIdAndOverdueTypeToNotifications extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('notifications')) {
            return;
        }

        if (! $this->db->fieldExists('plan_id', 'notifications')) {
            $this->forge->addColumn('notifications', [
                'plan_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'user_id',
                ],
            ]);
        }

        $this->forge->modifyColumn('notifications', [
            'type' => [
                'name'       => 'type',
                'type'       => 'ENUM',
                'constraint' => ['payment_approved', 'payment_rejected', 'service_approved', 'service_rejected', 'registration_pending', 'service_completed', 'general', 'overdue'],
                'default'    => 'general',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('notifications')) {
            return;
        }

        if ($this->db->fieldExists('plan_id', 'notifications')) {
            $this->forge->dropColumn('notifications', 'plan_id');
        }

        $this->forge->modifyColumn('notifications', [
            'type' => [
                'name'       => 'type',
                'type'       => 'ENUM',
                'constraint' => ['payment_approved', 'payment_rejected', 'service_approved', 'service_rejected', 'registration_pending', 'service_completed', 'general'],
                'default'    => 'general',
                'null'       => false,
            ],
        ]);
    }
}
