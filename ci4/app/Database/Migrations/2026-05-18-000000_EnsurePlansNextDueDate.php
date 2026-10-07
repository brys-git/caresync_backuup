<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsurePlansNextDueDate extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('plans')) {
            $fields = $this->db->getFieldData('plans');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);

            if (! in_array('next_due_date', $fieldNames, true)) {
                $this->forge->addColumn('plans', [
                    'next_due_date' => [
                        'type' => 'DATE',
                        'null' => true,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('plans')) {
            $fields = $this->db->getFieldData('plans');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);

            if (in_array('next_due_date', $fieldNames, true)) {
                $this->forge->dropColumn('plans', 'next_due_date');
            }
        }
    }
}
