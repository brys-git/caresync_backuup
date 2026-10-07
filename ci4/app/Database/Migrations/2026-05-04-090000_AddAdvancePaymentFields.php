<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdvancePaymentFields extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('payments')) {
            $fields = $this->db->getFieldData('payments');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);

            $newColumns = [];
            if (! in_array('months_covered', $fieldNames, true)) {
                $newColumns['months_covered'] = [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 1,
                    'null' => false,
                ];
            }
            if (! in_array('proof_image', $fieldNames, true)) {
                $newColumns['proof_image'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ];
            }
            if (! in_array('official_receipt_number', $fieldNames, true)) {
                $newColumns['official_receipt_number'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ];
            }
            if (! in_array('verified_at', $fieldNames, true)) {
                $newColumns['verified_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }
            if (! in_array('verified_by', $fieldNames, true)) {
                $newColumns['verified_by'] = [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ];
            }

            if (! empty($newColumns)) {
                $this->forge->addColumn('payments', $newColumns);
            }
        }

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
        if ($this->db->tableExists('payments')) {
            $fields = $this->db->getFieldData('payments');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);
            $dropColumns = [];

            foreach (['months_covered', 'proof_image', 'official_receipt_number', 'verified_at', 'verified_by'] as $column) {
                if (in_array($column, $fieldNames, true)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $this->forge->dropColumn('payments', $dropColumns);
            }
        }

        if ($this->db->tableExists('plans')) {
            $fields = $this->db->getFieldData('plans');
            $fieldNames = array_map(static fn ($field) => $field->name, $fields);

            if (in_array('next_due_date', $fieldNames, true)) {
                $this->forge->dropColumn('plans', 'next_due_date');
            }
        }
    }
}
