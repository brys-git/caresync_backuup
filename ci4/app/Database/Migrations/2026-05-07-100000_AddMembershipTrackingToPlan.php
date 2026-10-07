<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMembershipTrackingToPlan extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('plans')) {
            $fields = $this->db->getFieldData('plans');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);

            $newColumns = [];

            // Check and add payment_coverage_until
            if (! in_array('payment_coverage_until', $fieldNames, true)) {
                $newColumns['payment_coverage_until'] = [
                    'type' => 'DATE',
                    'null' => true,
                ];
            }

            // Check and add overdue_months
            if (! in_array('overdue_months', $fieldNames, true)) {
                $newColumns['overdue_months'] = [
                    'type' => 'INT',
                    'default' => 0,
                ];
            }

            // Check and add membership_state
            if (! in_array('membership_state', $fieldNames, true)) {
                $newColumns['membership_state'] = [
                    'type' => 'ENUM',
                    'constraint' => ['active', 'delinquent', 'suspended', 'completed'],
                    'default' => 'active',
                ];
            }

            if (! empty($newColumns)) {
                $this->forge->addColumn('plans', $newColumns);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans')) {
            $fields = $this->db->getFieldData('plans');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);
            $dropColumns = [];

            foreach (['payment_coverage_until', 'overdue_months', 'membership_state'] as $column) {
                if (in_array($column, $fieldNames, true)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $this->forge->dropColumn('plans', $dropColumns);
            }
        }
    }
}
