<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlanHolderRegistrationFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('plan_holders')) {
            return;
        }

        $fields = $this->db->getFieldData('plan_holders');
        $fieldNames = array_map(static fn ($field) => $field->name, $fields);

        $newColumns = [];
        if (! in_array('id_control_no', $fieldNames, true)) {
            $newColumns['id_control_no'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ];
        }
        if (! in_array('coordinator', $fieldNames, true)) {
            $newColumns['coordinator'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        if (! in_array('application_date', $fieldNames, true)) {
            $newColumns['application_date'] = [
                'type' => 'DATE',
                'null' => true,
            ];
        }
        if (! in_array('emergency_contact_name', $fieldNames, true)) {
            $newColumns['emergency_contact_name'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        if (! in_array('emergency_contact_number', $fieldNames, true)) {
            $newColumns['emergency_contact_number'] = [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
            ];
        }
        if (! in_array('emergency_contact_address', $fieldNames, true)) {
            $newColumns['emergency_contact_address'] = [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ];
        }

        if (! empty($newColumns)) {
            $this->forge->addColumn('plan_holders', $newColumns);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('plan_holders')) {
            return;
        }

        $fields = $this->db->getFieldData('plan_holders');
        $fieldNames = array_map(static fn ($field) => $field->name, $fields);
        $dropColumns = [];

        foreach (['id_control_no', 'coordinator', 'application_date', 'emergency_contact_name', 'emergency_contact_number', 'emergency_contact_address'] as $column) {
            if (in_array($column, $fieldNames, true)) {
                $dropColumns[] = $column;
            }
        }

        if (! empty($dropColumns)) {
            $this->forge->dropColumn('plan_holders', $dropColumns);
        }
    }
}
