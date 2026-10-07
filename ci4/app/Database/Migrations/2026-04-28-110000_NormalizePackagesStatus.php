<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizePackagesStatus extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('packages')) {
            return;
        }

        // Check if packages.status already exists
        $fields = $this->db->getFieldData('packages');
        $hasStatus = false;
        foreach ($fields as $field) {
            if ($field->name === 'status') {
                $hasStatus = true;
                break;
            }
        }

        if ($hasStatus) {
            return;
        }

        $this->forge->addColumn('packages', [
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'approved', 'rejected', 'inactive'],
                'default' => 'approved',
                'null' => false,
                'comment' => 'Package approval status: pending (awaiting approval), approved (visible), rejected, inactive (unavailable)',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('packages')) {
            $this->forge->dropColumn('packages', 'status');
        }
    }
}
