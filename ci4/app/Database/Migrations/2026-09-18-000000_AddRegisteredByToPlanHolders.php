<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Prompt 7, Step D: which Staff/Branch Admin/System Admin account
 * registered a plan holder on someone else's behalf - null for
 * self-service (client) registrations.
 */
class AddRegisteredByToPlanHolders extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('registered_by', 'plan_holders')) {
            $this->forge->addColumn('plan_holders', [
                'registered_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'user_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('registered_by', 'plan_holders')) {
            $this->forge->dropColumn('plan_holders', 'registered_by');
        }
    }
}
