<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowNullReceivedByInPayments extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('payments')) {
            return;
        }

        $fields = $this->db->getFieldData('payments');
        $fieldNames = array_map(static fn ($field) => $field->name, $fields);

        if (! in_array('received_by', $fieldNames, true)) {
            return;
        }

        $this->forge->modifyColumn('payments', [
            'received_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('payments')) {
            return;
        }

        $this->forge->modifyColumn('payments', [
            'received_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
        ]);
    }
}
