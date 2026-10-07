<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsPrimaryToBeneficiaries extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('beneficiaries')) {
            $fields = $this->db->getFieldNames('beneficiaries');

            if (! in_array('is_primary', $fields, true)) {
                $this->forge->addColumn('beneficiaries', [
                    'is_primary' => [
                        'type' => 'TINYINT',
                        'constraint' => 1,
                        'default' => 0,
                        'null' => false,
                        'after' => 'relationship',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('beneficiaries')) {
            $fields = $this->db->getFieldNames('beneficiaries');

            if (in_array('is_primary', $fields, true)) {
                $this->forge->dropColumn('beneficiaries', ['is_primary']);
            }
        }
    }
}