<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restores `plans.remaining_balance` as a real column.
 *
 * Some earlier, unrecorded manual change renamed it to
 * `legacy_remaining_balance` — but that rename was never propagated to the
 * application. Fifteen-plus places across PaymentService, ReportService,
 * MembershipService, ClientService, and several controllers still
 * unconditionally SELECT/WHERE/UPDATE `plans.remaining_balance` with no
 * existence guard (unlike `total_plan_amount`, which the code already checks
 * for defensively before using). Against the current schema every one of
 * those call sites throws "Unknown column 'remaining_balance'" — in
 * particular PaymentService::recomputePlan(), which runs after every
 * payment is recorded.
 *
 * Given how many independent, otherwise-correct files agree on this column
 * name, the schema is what drifted — not the application. Restoring the
 * column (backfilled from legacy_remaining_balance's existing values) is far
 * safer than rewriting business logic across a dozen files to chase a
 * rename with no recorded rationale. legacy_remaining_balance is left in
 * place, untouched, rather than dropped.
 */
class RestorePlanRemainingBalance extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plans')) {
            return;
        }

        $fields = array_map(static fn ($field) => $field->name, $this->db->getFieldData('plans'));

        if (! in_array('remaining_balance', $fields, true)) {
            $this->forge->addColumn('plans', [
                'remaining_balance' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                    'default'    => 0,
                    'after'      => 'legacy_remaining_balance',
                ],
            ]);
        }

        if (in_array('legacy_remaining_balance', $fields, true)) {
            $this->db->query(
                'UPDATE plans SET remaining_balance = legacy_remaining_balance WHERE legacy_remaining_balance IS NOT NULL'
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans') && $this->db->fieldExists('remaining_balance', 'plans')) {
            $this->forge->dropColumn('plans', 'remaining_balance');
        }
    }
}
