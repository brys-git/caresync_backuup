<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client details page: the emergency contact now shows how the person is
 * related to the plan holder. Nullable, so existing records stay as they are.
 */
class AddEmergencyContactRelationship extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('emergency_contact_relationship', 'plan_holders')) {
            $this->forge->addColumn('plan_holders', [
                'emergency_contact_relationship' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                    'after'      => 'emergency_contact_name',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('emergency_contact_relationship', 'plan_holders')) {
            $this->forge->dropColumn('plan_holders', 'emergency_contact_relationship');
        }
    }
}
