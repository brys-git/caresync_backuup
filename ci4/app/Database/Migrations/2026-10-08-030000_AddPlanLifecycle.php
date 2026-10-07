<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One overdue rule (see Config\CareSync and PlanLedgerService).
 *
 *  plans.lifecycle_status  active | grace | lapsed | forfeited | fully_paid
 *                          (derived by PlanLedgerService; stored so reports
 *                          and SQL filters can use it)
 *  plans.lapsed_at         the day the plan went on hold; stays set until the
 *                          plan is reinstated, so a Lapsed plan cannot slip
 *                          back to "in grace" by paying only part of what is owed
 *  plans.reinstated_at     when it last came back from Lapsed
 *  payments.payment_type   + 'reinstatement_fee' (never counted as a contribution)
 *  plan_notices            which reminder was sent for which overdue episode,
 *                          so each reminder goes out once
 */
class AddPlanLifecycle extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('lifecycle_status', 'plans')) {
            $this->forge->addColumn('plans', [
                'lifecycle_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'lapsed_at'        => ['type' => 'DATE', 'null' => true],
                'reinstated_at'    => ['type' => 'DATE', 'null' => true],
            ]);
        }

        $this->db->query(
            "ALTER TABLE payments MODIFY payment_type ENUM('initial_registration','monthly_contribution','service_payment','addon_payment','reinstatement_fee') NOT NULL DEFAULT 'monthly_contribution'"
        );

        $this->forge->addField([
            'notice_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'plan_id'      => ['type' => 'INT', 'constraint' => 11],
            'episode_date' => ['type' => 'DATE'],
            'stage'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'delivered'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sent_at'      => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('notice_id', true);
        $this->forge->addUniqueKey(['plan_id', 'episode_date', 'stage']);
        $this->forge->createTable('plan_notices', true);
    }

    public function down()
    {
        $this->forge->dropTable('plan_notices', true);

        foreach (['lifecycle_status', 'lapsed_at', 'reinstated_at'] as $column) {
            if ($this->db->fieldExists($column, 'plans')) {
                $this->forge->dropColumn('plans', $column);
            }
        }

        $this->db->query(
            "ALTER TABLE payments MODIFY payment_type ENUM('initial_registration','monthly_contribution','service_payment','addon_payment') NOT NULL DEFAULT 'monthly_contribution'"
        );
    }
}
