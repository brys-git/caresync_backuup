<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeServiceListStatus extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('service_list')) {
            return;
        }

            // service_list.status already exists, so we just document its purpose:
            // Status values: active (visible), pending (awaiting approval), rejected, inactive (unavailable)
            // No column addition needed - existing status column serves this purpose
    }

    public function down()
    {
        if ($this->db->tableExists('service_list')) {
            $this->forge->dropColumn('service_list', 'status');
        }
    }
}
